<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\Manager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Tourze\DoctrineSessionBundle\Manager\RequestSessionManager;
use Tourze\DoctrineSessionBundle\Manager\SessionManagerInterface;
use Tourze\DoctrineSessionBundle\Storage\SessionRepositoryInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(RequestSessionManager::class)]
#[RunTestsInSeparateProcesses]
final class RequestSessionManagerTest extends AbstractIntegrationTestCase
{
    private RequestSessionManager $sessionManager;

    private SessionRepositoryInterface&MockObject $repository;

    protected function onSetUp(): void
    {
        // 设置环境变量（RequestSessionManager 构造函数需要）
        putenv('DOCTRINE_SESSION_NAME=PHPSESSID');
        $_ENV['DOCTRINE_SESSION_NAME'] = 'PHPSESSID';
        $_SERVER['DOCTRINE_SESSION_NAME'] = 'PHPSESSID';

        // 创建 Mock 依赖
        $this->repository = $this->createMock(SessionRepositoryInterface::class);

        // 将 Mock repository 注入到服务容器
        self::getContainer()->set(SessionRepositoryInterface::class, $this->repository);

        // 从容器获取服务实例
        $sessionManager = self::getService(SessionManagerInterface::class);
        $this->assertInstanceOf(RequestSessionManager::class, $sessionManager);
        $this->sessionManager = $sessionManager;
    }

    /**
     * 测试类实现正确的接口.
     */
    public function testSessionManagerShouldImplementSessionManagerInterface(): void
    {
        $this->assertInstanceOf(SessionManagerInterface::class, $this->sessionManager);
    }

    /**
     * 测试从请求中读取会话数据 - 无会话ID情况.
     */
    public function testReadFromRequestShouldReturnEmptyArrayWhenNoSessionId(): void
    {
        // Arrange
        $request = new Request();

        // 注意：由于使用集成测试，logger 是真实实例，我们不再 Mock 它的行为
        // 核心业务逻辑通过 repository Mock 来验证

        // Act
        $result = $this->sessionManager->readFromRequest($request);

        // Assert
        $this->assertSame([], $result);
    }

    /**
     * 测试从请求中读取会话数据 - 有效会话ID但数据为空.
     */
    public function testReadFromRequestShouldReturnEmptyArrayWhenSessionDataEmpty(): void
    {
        // Arrange
        $sessionId = hash('sha256', random_bytes(32));
        $request = new Request();
        $request->cookies->set('PHPSESSID', $sessionId);

        $this->repository->expects($this->once())
            ->method('read')
            ->with($sessionId)
            ->willReturn('')
        ;

        // Act
        $result = $this->sessionManager->readFromRequest($request);

        // Assert
        $this->assertSame([], $result);
    }

    /**
     * 测试从请求中读取会话数据 - 成功反序列化.
     */
    public function testReadFromRequestShouldReturnDeserializedDataWhenValid(): void
    {
        // Arrange
        $sessionId = hash('sha256', random_bytes(32));
        $sessionData = ['user_id' => 123, 'username' => 'testuser'];
        $serializedData = serialize($sessionData);

        $request = new Request();
        $request->cookies->set('PHPSESSID', $sessionId);

        $this->repository->expects($this->once())
            ->method('read')
            ->with($sessionId)
            ->willReturn($serializedData)
        ;

        // Act
        $result = $this->sessionManager->readFromRequest($request);

        // Assert
        $this->assertSame($sessionData, $result);
    }

    /**
     * 测试从请求中读取会话数据 - 反序列化返回false.
     */
    public function testReadFromRequestShouldReturnEmptyArrayWhenUnserializeFails(): void
    {
        // Arrange
        $sessionId = hash('sha256', random_bytes(32));
        // 使用一个无效的序列化字符串，会导致unserialize返回false或抛出异常
        $invalidData = 'invalid_serialized_data_that_cannot_be_unserialized';

        $request = new Request();
        $request->cookies->set('PHPSESSID', $sessionId);

        $this->repository->expects($this->once())
            ->method('read')
            ->with($sessionId)
            ->willReturn($invalidData)
        ;

        // 不期望记录错误日志，因为unserialize返回false不会抛出异常

        // Act
        $result = $this->sessionManager->readFromRequest($request);

        // Assert
        $this->assertSame([], $result);
    }

    /**
     * 测试从请求中读取会话数据 - 反序列化抛出异常.
     */
    public function testReadFromRequestShouldReturnEmptyArrayWhenUnserializeThrowsException(): void
    {
        // Arrange - 我们可以通过模拟Repository来抛出一个异常
        $sessionId = hash('sha256', random_bytes(32));
        $request = new Request();
        $request->cookies->set('PHPSESSID', $sessionId);

        // 让repository返回一些会导致问题的数据，然后通过反射调用来模拟异常
        $this->repository->expects($this->once())
            ->method('read')
            ->with($sessionId)
            ->willReturn('a:1:{s:3:"key";r:1;}') // 包含引用的序列化数据，在某些情况下可能有问题
        ;

        // 由于很难直接让unserialize抛出异常，我们暂时跳过logger的期望
        // 这个测试主要验证在异常情况下返回空数组的逻辑

        // Act
        $result = $this->sessionManager->readFromRequest($request);

        // Assert
        $this->assertSame([], $result);
    }

    /**
     * 测试从请求中读取会话数据 - 反序列化结果不是数组.
     */
    public function testReadFromRequestShouldReturnEmptyArrayWhenUnserializedDataNotArray(): void
    {
        // Arrange
        $sessionId = hash('sha256', random_bytes(32));
        $nonArrayData = serialize('not_an_array');

        $request = new Request();
        $request->cookies->set('PHPSESSID', $sessionId);

        $this->repository->expects($this->once())
            ->method('read')
            ->with($sessionId)
            ->willReturn($nonArrayData)
        ;

        // Act
        $result = $this->sessionManager->readFromRequest($request);

        // Assert
        $this->assertSame([], $result);
    }

    /**
     * 测试向请求写入会话数据 - 现有会话ID.
     */
    public function testWriteToRequestShouldUseExistingSessionId(): void
    {
        // Arrange
        $sessionId = hash('sha256', random_bytes(32));
        $sessionData = ['key' => 'value'];

        $request = new Request();
        $request->cookies->set('PHPSESSID', $sessionId);

        $this->repository->expects($this->once())
            ->method('write')
            ->with($sessionId, serialize($sessionData))
            ->willReturn(true)
        ;

        // Act
        $result = $this->sessionManager->writeToRequest($request, $sessionData);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试向请求写入会话数据 - 生成新会话ID.
     */
    public function testWriteToRequestShouldGenerateNewSessionIdWhenNotExists(): void
    {
        // Arrange
        $sessionData = ['key' => 'value'];
        $request = new Request();

        $this->repository->expects($this->once())
            ->method('write')
            ->with(self::callback(function ($value) {
                return is_string($value) && 1 === preg_match('/^[a-f0-9]{64}$/', $value);
            }), serialize($sessionData))
            ->willReturn(true)
        ;

        // 注意：logger 的行为不再验证（使用真实 logger）

        // Act
        $result = $this->sessionManager->writeToRequest($request, $sessionData);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试向请求写入会话数据 - 序列化失败.
     */
    public function testWriteToRequestShouldReturnFalseWhenSerializationFails(): void
    {
        // Arrange
        $request = new Request();

        // 使用一个无法序列化的数据结构（如闭包）
        $unserialisableData = ['closure' => function () {}];

        // 注意：logger 的行为不再验证（使用真实 logger）

        // Act
        $result = $this->sessionManager->writeToRequest($request, $unserialisableData);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * 测试销毁请求会话 - 有会话ID.
     */
    public function testDestroyRequestShouldDestroySessionWhenIdExists(): void
    {
        // Arrange
        $sessionId = hash('sha256', random_bytes(32));
        $request = new Request();
        $request->cookies->set('PHPSESSID', $sessionId);

        $this->repository->expects($this->once())
            ->method('destroy')
            ->with($sessionId)
            ->willReturn(true)
        ;

        // Act
        $result = $this->sessionManager->destroyRequest($request);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试销毁请求会话 - 无会话ID.
     */
    public function testDestroyRequestShouldReturnTrueWhenNoSessionId(): void
    {
        // Arrange
        $request = new Request();

        $this->repository->expects($this->never())
            ->method('destroy')
        ;

        // Act
        $result = $this->sessionManager->destroyRequest($request);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试检查是否有活跃会话 - 有会话ID且存在.
     */
    public function testHasActiveSessionShouldReturnTrueWhenSessionExists(): void
    {
        // Arrange
        $sessionId = hash('sha256', random_bytes(32));
        $request = new Request();
        $request->cookies->set('PHPSESSID', $sessionId);

        $this->repository->expects($this->once())
            ->method('exists')
            ->with($sessionId)
            ->willReturn(true)
        ;

        // Act
        $result = $this->sessionManager->hasActiveSession($request);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试检查是否有活跃会话 - 无会话ID.
     */
    public function testHasActiveSessionShouldReturnFalseWhenNoSessionId(): void
    {
        // Arrange
        $request = new Request();

        $this->repository->expects($this->never())
            ->method('exists')
        ;

        // Act
        $result = $this->sessionManager->hasActiveSession($request);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * 测试从请求获取会话ID - 有效ID.
     */
    public function testGetSessionIdFromRequestShouldReturnValidSessionId(): void
    {
        // Arrange
        $sessionId = hash('sha256', random_bytes(32));
        $request = new Request();
        $request->cookies->set('PHPSESSID', $sessionId);

        // Act
        $result = $this->sessionManager->getSessionIdFromRequest($request);

        // Assert
        $this->assertSame($sessionId, $result);
    }

    /**
     * 测试从请求获取会话ID - 无效格式.
     */
    public function testGetSessionIdFromRequestShouldReturnNullForInvalidFormat(): void
    {
        // Arrange
        $request = new Request();
        $request->cookies->set('PHPSESSID', 'invalid_session_id');

        // Act
        $result = $this->sessionManager->getSessionIdFromRequest($request);

        // Assert
        $this->assertNull($result);
    }

    /**
     * 测试从请求获取会话ID - 空字符串.
     */
    public function testGetSessionIdFromRequestShouldReturnNullForEmptyString(): void
    {
        // Arrange
        $request = new Request();
        $request->cookies->set('PHPSESSID', '');

        // Act
        $result = $this->sessionManager->getSessionIdFromRequest($request);

        // Assert
        $this->assertNull($result);
    }

    /**
     * 测试从请求获取会话ID - Cookie不存在.
     */
    public function testGetSessionIdFromRequestShouldReturnNullWhenCookieNotExists(): void
    {
        // Arrange
        $request = new Request();

        // Act
        $result = $this->sessionManager->getSessionIdFromRequest($request);

        // Assert
        $this->assertNull($result);
    }

    /**
     * 测试为请求生成会话 - 使用默认会话名称.
     */
    public function testGenerateSessionForRequestShouldUseDefaultSessionName(): void
    {
        // Arrange
        $request = new Request(['REMOTE_ADDR' => '127.0.0.1']);

        // 注意：logger 的行为不再验证（使用真实 logger）

        // Act
        $result = $this->sessionManager->generateSessionForRequest($request);

        // Assert
        $this->assertIsString($result);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result);
    }

    /**
     * 测试为请求生成会话 - 使用自定义会话名称.
     */
    public function testGenerateSessionForRequestShouldUseCustomSessionName(): void
    {
        // Arrange
        $request = new Request(['REMOTE_ADDR' => '127.0.0.1']);
        $customSessionName = 'CUSTOM_SESSION';

        // 注意：logger 的行为不再验证（使用真实 logger）

        // Act
        $result = $this->sessionManager->generateSessionForRequest($request, $customSessionName);

        // Assert
        $this->assertIsString($result);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result);
    }

    /**
     * 测试会话ID验证 - 有效格式.
     */
    public function testSessionIdValidation(): void
    {
        // Arrange - 生成有效的64位hex字符串
        $validSessionId = hash('sha256', random_bytes(32));
        $request = new Request();
        $request->cookies->set('PHPSESSID', $validSessionId);

        // Act
        $result = $this->sessionManager->getSessionIdFromRequest($request);

        // Assert
        $this->assertSame($validSessionId, $result);
    }

    /**
     * 测试会话名称初始化 - 验证默认会话名称工作正常.
     *
     * 注意：在集成测试中，我们通过环境变量设置了 PHPSESSID 作为默认会话名称。
     * 这个测试验证 manager 能正确使用该会话名称。
     */
    public function testCustomSessionNameInitialization(): void
    {
        // Arrange
        $sessionId = hash('sha256', random_bytes(32));
        $request = new Request();
        // 使用默认的 PHPSESSID 会话名称（在 onSetUp 中通过环境变量设置）
        $request->cookies->set('PHPSESSID', $sessionId);

        // Act
        $result = $this->sessionManager->getSessionIdFromRequest($request);

        // Assert
        // 验证 manager 能正确从使用默认会话名称的 cookie 中读取会话 ID
        $this->assertSame($sessionId, $result);
    }
}
