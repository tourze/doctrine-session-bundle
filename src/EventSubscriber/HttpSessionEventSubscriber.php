<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\EventSubscriber;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\EventListener\SessionListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Tourze\DoctrineSessionBundle\Service\HttpSessionFactory;

#[AsDecorator(decorates: 'session_listener')]
#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'doctrine_session')]
readonly class HttpSessionEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[AutowireDecorated] private SessionListener $inner,
        #[Autowire(service: 'session.factory')] private HttpSessionFactory $sessionFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->hasSession()) {
            $request->setSessionFactory($this->createSessionFactory($request));
        }
    }

    /**
     * @return \Closure(): SessionInterface
     */
    private function createSessionFactory(Request $request): \Closure
    {
        return function () use ($request) {
            if (!$request->hasSession(true)) {
                $sess = $this->sessionFactory->createSessionForRequest($request);
                $request->setSession($sess);

                return $sess;
            }

            return $request->getSession();
        };
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getRequest()->hasSession()) {
            return;
        }

        $response = $event->getResponse();
        $autoCacheControl = $this->handleCacheControlHeader($response);

        if (!$event->getRequest()->hasSession(true)) {
            return;
        }

        $session = $event->getRequest()->getSession();

        if ($session->isStarted()) {
            $this->saveSessionAndSetCookie($event, $session);
        }

        if ($session instanceof Session ? 0 === $session->getUsageIndex() : !$session->isStarted()) {
            return;
        }

        $this->handleCacheControl($response, $autoCacheControl);
        $this->handleStatelessRequest($event);
    }

    private function handleCacheControlHeader(Response $response): bool
    {
        $autoCacheControl = !$response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER);
        $response->headers->remove(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER);

        return $autoCacheControl;
    }

    private function saveSessionAndSetCookie(ResponseEvent $event, SessionInterface $session): void
    {
        $session->save();

        $sessionName = $session->getName();
        $sessionId = $session->getId();
        $sessionOptions = $this->getSessionOptions();

        $useCookies = $sessionOptions['use_cookies'] ?? true;
        if (is_bool($useCookies) && $useCookies) {
            $this->setSessionCookie($event, $session, $sessionOptions);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getSessionOptions(): array
    {
        return [
            'cookie_path' => '/',
            'cookie_domain' => null,
            'cookie_secure' => (bool) ($_ENV['APP_SESSION_COOKIE_SECURE'] ?? false),
            'cookie_httponly' => true,
            'cookie_samesite' => $_ENV['APP_SESSION_COOKIE_SAMESITE'] ?? Cookie::SAMESITE_LAX,
            'use_cookies' => true,
        ];
    }

    /**
     * @param array<string, mixed> $sessionOptions
     */
    private function setSessionCookie(ResponseEvent $event, SessionInterface $session, array $sessionOptions): void
    {
        $request = $event->getRequest();
        $sessionName = $session->getName();
        $sessionId = $session->getId();

        if (!$this->shouldSetCookie($request, $session, $sessionName, $sessionId)) {
            return;
        }

        $cookie = $this->createSessionCookie($sessionName, $sessionId, $sessionOptions);
        $event->getResponse()->headers->setCookie($cookie);
    }

    /**
     * 判断是否应该设置会话 Cookie.
     */
    private function shouldSetCookie(Request $request, SessionInterface $session, string $sessionName, string $sessionId): bool
    {
        $requestSessionCookieId = $request->cookies->get($sessionName);
        $isSessionEmpty = $session instanceof Session ? $session->isEmpty() : 0 === count($session->all());

        return $sessionId !== $requestSessionCookieId && !$isSessionEmpty;
    }

    /**
     * 创建会话 Cookie.
     *
     * @param array<string, mixed> $sessionOptions
     */
    private function createSessionCookie(string $sessionName, string $sessionId, array $sessionOptions): Cookie
    {
        $expire = $this->calculateCookieExpireTime($sessionOptions);
        $cookieParams = $this->extractCookieParams($sessionOptions);

        return Cookie::create(
            $sessionName,
            $sessionId,
            $expire,
            $cookieParams['path'],
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httponly'],
            false,
            $cookieParams['samesite']
        );
    }

    /**
     * 计算 Cookie 过期时间.
     *
     * @param array<string, mixed> $sessionOptions
     */
    private function calculateCookieExpireTime(array $sessionOptions): int
    {
        $lifetime = $sessionOptions['cookie_lifetime'] ?? null;
        if (is_int($lifetime) && $lifetime > 0) {
            return time() + $lifetime;
        }

        return 0;
    }

    /**
     * 提取并验证 Cookie 参数.
     *
     * @param array<string, mixed> $sessionOptions
     *
     * @return array{path: string|null, domain: string|null, secure: bool|null, httponly: bool, samesite: ''|'lax'|'none'|'strict'|null}
     */
    private function extractCookieParams(array $sessionOptions): array
    {
        $cookiePath = $sessionOptions['cookie_path'] ?? null;
        $cookiePath = is_string($cookiePath) ? $cookiePath : null;

        $cookieDomain = $sessionOptions['cookie_domain'] ?? null;
        $cookieDomain = is_string($cookieDomain) ? $cookieDomain : null;

        $cookieSecure = $sessionOptions['cookie_secure'] ?? null;
        $cookieSecure = is_bool($cookieSecure) ? $cookieSecure : null;

        $cookieHttpOnly = $sessionOptions['cookie_httponly'] ?? true;
        $cookieHttpOnly = is_bool($cookieHttpOnly) ? $cookieHttpOnly : true;

        $cookieSameSite = $this->validateSameSiteValue($sessionOptions['cookie_samesite'] ?? null);

        return [
            'path' => $cookiePath,
            'domain' => $cookieDomain,
            'secure' => $cookieSecure,
            'httponly' => $cookieHttpOnly,
            'samesite' => $cookieSameSite,
        ];
    }

    /**
     * 验证 SameSite 值.
     *
     * @return ''|'lax'|'none'|'strict'|null
     */
    private function validateSameSiteValue(mixed $value): ?string
    {
        if (!is_string($value) || !in_array($value, ['', 'lax', 'none', 'strict'], true)) {
            return null;
        }

        return $value;
    }

    private function handleCacheControl(Response $response, bool $autoCacheControl): void
    {
        if (!$autoCacheControl) {
            return;
        }

        $maxAge = $response->headers->hasCacheControlDirective('public') ? 0 : (int) $response->getMaxAge();
        $response
            ->setExpires(new \DateTimeImmutable('+'.$maxAge.' seconds'))
            ->setPrivate()
            ->setMaxAge($maxAge)
            ->headers->addCacheControlDirective('must-revalidate')
        ;
    }

    private function handleStatelessRequest(ResponseEvent $event): void
    {
        $isStateless = $event->getRequest()->attributes->get('_stateless', false);
        if (is_bool($isStateless) && $isStateless) {
            $this->logger->warning('会话在被声明为无状态的请求中被使用。');
        }
    }

    public function onSessionUsage(): void
    {
        $this->inner->onSessionUsage();
    }

    public function reset(): void
    {
        // 会话数据已通过自定义存储清理，无需额外操作
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 128],
            // low priority to come after regular response listeners
            // KernelEvents::RESPONSE => ['onKernelResponse', -1000],
            KernelEvents::RESPONSE => ['onKernelResponse', -255],
        ];
    }
}
