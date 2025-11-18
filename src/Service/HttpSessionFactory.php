<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Service\ResetInterface;
use Tourze\DoctrineSessionBundle\Storage\HttpSessionStorage;

#[WithMonologChannel(channel: 'doctrine_session')]
#[Autoconfigure(public: true)]
class HttpSessionFactory implements SessionFactoryInterface, ResetInterface
{
    /**
     * 基于请求对象的弱引用缓存，避免内存泄漏.
     *
     * @var \WeakMap<Request, SessionInterface>
     */
    private \WeakMap $requestSessionCache;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PdoSessionHandler $sessionHandler,
        private readonly string $sessionName,
        private readonly ?RequestStack $requestStack = null,
    ) {
        $this->requestSessionCache = new \WeakMap();
    }

    private function getSessionName(): string
    {
        return $this->sessionName;
    }

    public function createSession(): SessionInterface
    {
        // 检查是否有主请求，如果有，使用缓存机制
        $mainRequest = $this->requestStack?->getMainRequest();

        if (null !== $mainRequest && isset($this->requestSessionCache[$mainRequest])) {
            return $this->requestSessionCache[$mainRequest];
        }

        // 为主请求创建会话
        if (null !== $mainRequest) {
            $session = $this->createSessionForRequest($mainRequest);
            $this->requestSessionCache[$mainRequest] = $session;

            return $session;
        }

        // 在没有Request上下文时创建默认session
        return $this->createSessionForId(null);
    }

    /**
     * 为指定的请求创建会话.
     */
    public function createSessionForRequest(Request $request): SessionInterface
    {
        $sessionId = $request->cookies->get($this->getSessionName());

        // 确保sessionId是字符串类型或null
        if (!is_string($sessionId)) {
            $sessionId = null;
        }

        return $this->createSessionForId($sessionId, $request);
    }

    /**
     * 为指定的sessionId创建会话.
     */
    public function createSessionForId(?string $sessionId, ?Request $request = null): SessionInterface
    {
        // 生成新的sessionId如果没有提供
        if (null === $sessionId || '' === $sessionId) {
            $sessionId = $this->generateSessionId();
        }

        $storage = new HttpSessionStorage($this->logger, $this->sessionHandler, $this->getSessionName(), $sessionId, $request);

        return new Session($storage);
    }

    private function generateSessionId(): string
    {
        return hash('md5', random_bytes(16));
    }

    public function reset(): void
    {
        // 清除 WeakMap 缓存
        $this->requestSessionCache = new \WeakMap();
    }
}
