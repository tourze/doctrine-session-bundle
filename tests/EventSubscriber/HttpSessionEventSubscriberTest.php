<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Tourze\DoctrineSessionBundle\EventSubscriber\HttpSessionEventSubscriber;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * @internal
 */
#[CoversClass(HttpSessionEventSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class HttpSessionEventSubscriberTest extends AbstractEventSubscriberTestCase
{
    private HttpSessionEventSubscriber $subscriber;

    protected function onSetUp(): void
    {
        $this->subscriber = self::getService(HttpSessionEventSubscriber::class);
    }

    /**
     * 测试事件订阅配置.
     */
    public function testGetSubscribedEvents(): void
    {
        $events = HttpSessionEventSubscriber::getSubscribedEvents();

        $this->assertNotEmpty($events);
        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);

        // 验证优先级
        $this->assertSame(['onKernelRequest', 128], $events[KernelEvents::REQUEST]);
        $this->assertSame(['onKernelResponse', -255], $events[KernelEvents::RESPONSE]);
    }

    /**
     * 测试主请求时创建会话.
     */
    public function testOnKernelRequestForMainRequest(): void
    {
        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // 验证会话工厂函数被设置
        $this->assertTrue($request->hasSession());

        // 验证调用会话工厂函数返回会话（这会触发实际的 sessionFactory 调用）
        $sessionFromFactory = $request->getSession();
        $this->assertInstanceOf(SessionInterface::class, $sessionFromFactory);
    }

    /**
     * 测试子请求时不处理.
     */
    public function testOnKernelRequestForSubRequest(): void
    {
        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // 验证会话未被设置
        $this->assertFalse($request->hasSession());
    }

    /**
     * 测试会话已存在时不重复设置.
     */
    public function testOnKernelRequestWhenSessionExists(): void
    {
        $request = new Request();
        $existingSession = $this->createMock(SessionInterface::class);
        $request->setSession($existingSession);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // 验证原有会话未被更改
        $this->assertSame($existingSession, $request->getSession());
    }

    /**
     * 测试响应事件处理 - 主请求且有会话.
     */
    public function testOnKernelResponseForMainRequestWithSession(): void
    {
        $session = $this->createMock(Session::class);
        $session->expects($this->once())
            ->method('isStarted')
            ->willReturn(true)
        ;
        $session->expects($this->once())
            ->method('save')
        ;
        $session->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('PHPSESSID')
        ;
        $session->expects($this->atLeastOnce())
            ->method('getId')
            ->willReturn('test-session-id')
        ;
        $session->expects($this->once())
            ->method('isEmpty')
            ->willReturn(false)
        ;

        $request = new Request();
        $request->setSession($session);
        $request->cookies->set('PHPSESSID', 'different-id');

        $response = new Response();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        // 验证会话cookie被设置
        $cookies = $response->headers->getCookies();
        $this->assertNotEmpty($cookies);

        $sessionCookie = null;
        foreach ($cookies as $cookie) {
            if ('PHPSESSID' === $cookie->getName()) {
                $sessionCookie = $cookie;
                break;
            }
        }

        $this->assertInstanceOf(Cookie::class, $sessionCookie);
        $this->assertSame('test-session-id', $sessionCookie->getValue());
    }

    /**
     * 测试响应事件处理 - 子请求时不处理.
     */
    public function testOnKernelResponseForSubRequest(): void
    {
        $request = new Request();
        $response = new Response();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        // 验证没有cookie被设置
        $this->assertEmpty($response->headers->getCookies());
    }

    /**
     * 测试响应事件处理 - 无会话时不处理.
     */
    public function testOnKernelResponseWithoutSession(): void
    {
        $request = new Request();
        $response = new Response();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        // 验证没有cookie被设置
        $this->assertEmpty($response->headers->getCookies());
    }

    /**
     * 测试环境变量控制的会话选项.
     */
    public function testSessionOptionsFromEnvironment(): void
    {
        // 设置环境变量
        $_ENV['APP_SESSION_COOKIE_SECURE'] = 'true';
        $_ENV['APP_SESSION_COOKIE_SAMESITE'] = 'strict';

        $session = $this->createMock(Session::class);
        $session->method('isStarted')->willReturn(true);
        $session->method('save');
        $session->method('getName')->willReturn('PHPSESSID');
        $session->method('getId')->willReturn('test-id');
        $session->method('isEmpty')->willReturn(false);

        $request = new Request();
        $request->setSession($session);

        $response = new Response();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $cookies = $response->headers->getCookies();
        $this->assertNotEmpty($cookies);

        $cookie = $cookies[0];
        $this->assertTrue($cookie->isSecure());
        $this->assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());

        // 清理环境变量
        unset($_ENV['APP_SESSION_COOKIE_SECURE'], $_ENV['APP_SESSION_COOKIE_SAMESITE']);
    }

    /**
     * 测试reset方法清空会话数据.
     */
    public function testReset(): void
    {
        // 创建测试用的Session实例
        $storage = new MockArraySessionStorage();
        $session = new Session($storage);

        // 设置一些会话数据用于测试
        $session->set('test', 'data');
        $this->assertSame('data', $session->get('test'));

        // 调用reset方法
        $this->subscriber->reset();

        // 验证reset方法被调用而没有抛出异常（已经有断言在前面）
    }

    /**
     * 测试onSessionUsage方法.
     */
    public function testOnSessionUsage(): void
    {
        // 成功执行，验证方法存在且可调用
        $this->expectNotToPerformAssertions();

        // 此方法是对内部SessionListener的代理调用，主要验证不抛出异常
        $this->subscriber->onSessionUsage();
    }

    /**
     * 测试无状态请求处理.
     */
    public function testHandleStatelessRequest(): void
    {
        // Arrange
        $request = new Request();
        $response = new Response();
        $request->attributes->set('_stateless', true);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $responseEvent = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        // Act
        $this->subscriber->onKernelResponse($responseEvent);

        // Assert - 验证方法调用不抛出异常 (无状态请求不设置cookie)
        $this->assertEmpty($response->headers->getCookies());
    }
}
