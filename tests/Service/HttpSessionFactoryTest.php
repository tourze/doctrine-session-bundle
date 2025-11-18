<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Service\ResetInterface;
use Tourze\DoctrineSessionBundle\Service\HttpSessionFactory;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(HttpSessionFactory::class)]
#[RunTestsInSeparateProcesses]
final class HttpSessionFactoryTest extends AbstractIntegrationTestCase
{
    private HttpSessionFactory $sessionFactory;

    protected function onSetUp(): void
    {
        $this->sessionFactory = self::getService(HttpSessionFactory::class);
    }

    /**
     * 测试服务能够正常从容器获取并实现预期接口.
     */
    public function testServiceImplementsExpectedInterfaces(): void
    {
        $this->assertInstanceOf(HttpSessionFactory::class, $this->sessionFactory);
        $this->assertInstanceOf(SessionFactoryInterface::class, $this->sessionFactory);
        $this->assertInstanceOf(ResetInterface::class, $this->sessionFactory);
    }

    /**
     * 测试没有主请求时创建会话.
     */
    public function testCreateSessionWithoutMainRequestShouldUseInnerFactory(): void
    {
        // Act - 在没有请求的情况下创建会话
        $session = $this->sessionFactory->createSession();

        // Assert - 应该返回有效的会话对象
        $this->assertInstanceOf(SessionInterface::class, $session);
    }

    /**
     * 测试有主请求时首次创建会话.
     */
    public function testCreateSessionWithMainRequestFirstTimeShouldCreateAndCacheSession(): void
    {
        // Arrange - 模拟一个HTTP请求环境
        $requestStack = self::getService(RequestStack::class);
        $request = new Request();
        $requestStack->push($request);

        // Act
        $session = $this->sessionFactory->createSession();

        // Assert
        $this->assertInstanceOf(SessionInterface::class, $session);
    }

    /**
     * 测试有主请求时重复调用应返回缓存的会话.
     */
    public function testCreateSessionWithMainRequestSecondTimeShouldReturnCachedSession(): void
    {
        // Arrange - 设置请求环境
        $requestStack = self::getService(RequestStack::class);
        $request = new Request();
        $requestStack->push($request);

        // Act - 两次调用工厂方法
        $session1 = $this->sessionFactory->createSession();
        $session2 = $this->sessionFactory->createSession();

        // Assert - 对于同一个请求对象，应该返回缓存的会话实例
        $this->assertInstanceOf(SessionInterface::class, $session1);
        $this->assertInstanceOf(SessionInterface::class, $session2);
        $this->assertSame($session1, $session2);
    }

    /**
     * 测试不同请求应创建不同会话.
     */
    public function testCreateSessionWithDifferentRequestsShouldCreateDifferentSessions(): void
    {
        // Arrange - 创建不同的请求
        $requestStack = self::getService(RequestStack::class);
        $request1 = new Request();
        $request2 = new Request();

        // Act - 先使用第一个请求
        $requestStack->push($request1);
        $session1 = $this->sessionFactory->createSession();
        $requestStack->pop();

        // 然后使用第二个请求
        $requestStack->push($request2);
        $session2 = $this->sessionFactory->createSession();
        $requestStack->pop();

        // Assert - 不同的请求对象应该创建不同的会话
        $this->assertInstanceOf(SessionInterface::class, $session1);
        $this->assertInstanceOf(SessionInterface::class, $session2);
        $this->assertNotSame($session1, $session2);
    }

    /**
     * 测试reset方法清空WeakMap缓存.
     */
    public function testResetShouldClearWeakMapCache(): void
    {
        // Arrange - 设置请求环境并创建缓存的会话
        $requestStack = self::getService(RequestStack::class);
        $request = new Request();
        $requestStack->push($request);

        // 创建一个会话以填充缓存
        $firstSession = $this->sessionFactory->createSession();

        // Act - 重置缓存
        $this->sessionFactory->reset();

        // 重置后再次创建会话
        $secondSession = $this->sessionFactory->createSession();

        // Assert - 重置后应该创建新的会话实例
        $this->assertInstanceOf(SessionInterface::class, $firstSession);
        $this->assertInstanceOf(SessionInterface::class, $secondSession);
        $this->assertNotSame($firstSession, $secondSession);
    }

    /**
     * 测试WeakMap在请求对象被销毁时自动清理.
     */
    public function testWeakMapAutoCleanupWhenRequestDestroyed(): void
    {
        // Arrange
        $requestStack = self::getService(RequestStack::class);
        $request = new Request();
        $requestStack->push($request);

        // Act - 创建会话
        $createdSession = $this->sessionFactory->createSession();

        // Assert
        $this->assertInstanceOf(SessionInterface::class, $createdSession);

        // 销毁请求对象引用（模拟垃圾回收）
        $requestStack->pop();
        unset($request);
    }

    /**
     * 测试实现的接口.
     */
    public function testFactoryShouldImplementRequiredInterfaces(): void
    {
        $this->assertInstanceOf(SessionFactoryInterface::class, $this->sessionFactory);
        $this->assertInstanceOf(ResetInterface::class, $this->sessionFactory);
    }

    /**
     * 测试工厂创建的会话类型正确.
     */
    public function testFactoryCreatedSessionShouldBeSessionInterface(): void
    {
        // Act
        $createdSession = $this->sessionFactory->createSession();

        // Assert
        $this->assertInstanceOf(SessionInterface::class, $createdSession);
    }

    /**
     * 测试空请求栈场景.
     */
    public function testCreateSessionWithEmptyRequestStackShouldUseInnerFactory(): void
    {
        // Act - 在空请求栈的情况下创建会话
        $result = $this->sessionFactory->createSession();

        // Assert - 应该使用内部工厂创建会话
        $this->assertInstanceOf(SessionInterface::class, $result);
    }

    /**
     * 测试多次重置不会造成问题.
     */
    public function testMultipleResetCallsShouldNotCauseIssues(): void
    {
        // Arrange - 创建一些缓存
        $requestStack = self::getService(RequestStack::class);
        $request = new Request();
        $requestStack->push($request);

        $this->sessionFactory->createSession();

        // Act - 多次重置应该不会抛出异常
        $this->sessionFactory->reset();
        $this->sessionFactory->reset();
        $this->sessionFactory->reset();

        // Assert - 验证重置后能正常创建新会话
        $newSession = $this->sessionFactory->createSession();
        $this->assertInstanceOf(SessionInterface::class, $newSession);
    }

    /**
     * 测试为指定请求创建会话.
     */
    public function testCreateSessionForRequestShouldReturnValidSession(): void
    {
        // Arrange
        $request = new Request();

        // Act
        $session = $this->sessionFactory->createSessionForRequest($request);

        // Assert
        $this->assertInstanceOf(SessionInterface::class, $session);
    }

    /**
     * 测试为指定请求创建会话时使用现有Session ID.
     */
    public function testCreateSessionForRequestShouldUseExistingSessionId(): void
    {
        // Arrange
        $request = new Request();
        $sessionId = 'test_session_id_12345';
        $request->cookies->set('PHPSESSID', $sessionId);

        // Act
        $session = $this->sessionFactory->createSessionForRequest($request);

        // Assert
        $this->assertInstanceOf(SessionInterface::class, $session);
        $this->assertSame($sessionId, $session->getId());
    }

    /**
     * 测试为指定Session ID创建会话.
     */
    public function testCreateSessionForIdShouldReturnValidSession(): void
    {
        // Arrange
        $sessionId = 'test_session_id_67890';

        // Act
        $session = $this->sessionFactory->createSessionForId($sessionId);

        // Assert
        $this->assertInstanceOf(SessionInterface::class, $session);
        $this->assertSame($sessionId, $session->getId());
    }

    /**
     * 测试为空Session ID创建会话时应生成新ID.
     */
    public function testCreateSessionForIdWithNullShouldGenerateNewId(): void
    {
        // Arrange
        $sessionId = null;

        // Act
        $session = $this->sessionFactory->createSessionForId($sessionId);

        // Assert
        $this->assertInstanceOf(SessionInterface::class, $session);
        $this->assertNotEmpty($session->getId());
        // 验证生成的ID是MD5哈希（32位十六进制字符串）
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $session->getId());
    }

    /**
     * 测试为空字符串Session ID创建会话时应生成新ID.
     */
    public function testCreateSessionForIdWithEmptyStringShouldGenerateNewId(): void
    {
        // Arrange
        $sessionId = '';

        // Act
        $session = $this->sessionFactory->createSessionForId($sessionId);

        // Assert
        $this->assertInstanceOf(SessionInterface::class, $session);
        $this->assertNotEmpty($session->getId());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $session->getId());
    }

    /**
     * 测试为Session ID和请求创建会话.
     */
    public function testCreateSessionForIdWithRequestShouldUseRequest(): void
    {
        // Arrange
        $sessionId = 'test_session_id_with_request';
        $request = new Request();

        // Act
        $session = $this->sessionFactory->createSessionForId($sessionId, $request);

        // Assert
        $this->assertInstanceOf(SessionInterface::class, $session);
        $this->assertSame($sessionId, $session->getId());
    }
}
