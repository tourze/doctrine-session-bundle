<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DoctrineSessionBundle\Exception\SessionException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(SessionException::class)]
final class SessionExceptionTest extends AbstractExceptionTestCase
{
    /**
     * 测试异常基本实例化和继承关系.
     */
    public function testExceptionWithDefaultConstructorShouldExtendRuntimeException(): void
    {
        $exception = new SessionException();

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(SessionException::class, $exception);
    }

    /**
     * 测试异常消息设置功能.
     */
    public function testExceptionWithMessageShouldReturnCorrectMessage(): void
    {
        $message = '会话异常消息';
        $exception = new SessionException($message);

        $this->assertSame($message, $exception->getMessage());
    }

    /**
     * 测试异常代码设置功能.
     */
    public function testExceptionWithCodeShouldReturnCorrectCode(): void
    {
        $code = 2001;
        $exception = new SessionException('测试消息', $code);

        $this->assertSame($code, $exception->getCode());
    }

    /**
     * 测试异常链设置功能.
     */
    public function testExceptionWithPreviousExceptionShouldMaintainChain(): void
    {
        $previousException = new \LogicException('逻辑错误异常');
        $exception = new SessionException('会话异常', 0, $previousException);

        $this->assertSame($previousException, $exception->getPrevious());
    }

    /**
     * 测试异常完整参数构造.
     */
    public function testExceptionWithAllParametersShouldSetAllProperties(): void
    {
        $message = '完整会话异常测试';
        $code = 3003;
        $previousException = new \Exception('基础异常');

        $exception = new SessionException($message, $code, $previousException);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previousException, $exception->getPrevious());
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    /**
     * 测试异常可被抛出和捕获.
     */
    public function testExceptionCanBeThrownAndCaughtShouldWork(): void
    {
        $message = '可抛出的会话异常';

        $this->expectException(SessionException::class);
        $this->expectExceptionMessage($message);

        throw new SessionException($message);
    }

    /**
     * 测试会话相关场景异常.
     */
    public function testSessionRelatedScenarioShouldThrowCorrectException(): void
    {
        $sessionId = 'invalid_session_id_123';
        $message = sprintf('无效的会话ID: %s', $sessionId);

        $exception = new SessionException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertStringContainsString($sessionId, $exception->getMessage());
    }

    /**
     * 测试会话超时场景异常.
     */
    public function testSessionTimeoutScenarioShouldIncludeTimeoutInfo(): void
    {
        $timeout = 1800; // 30分钟
        $message = sprintf('会话超时，超时时长: %d 秒', $timeout);

        $exception = new SessionException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertStringContainsString('1800', $exception->getMessage());
    }

    /**
     * 测试空消息异常.
     */
    public function testExceptionWithEmptyMessageShouldAcceptEmpty(): void
    {
        $exception = new SessionException('');

        $this->assertSame('', $exception->getMessage());
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    /**
     * 测试会话存储异常场景.
     */
    public function testSessionStorageErrorScenarioShouldProvideDetails(): void
    {
        $storageError = 'Database connection failed';
        $message = sprintf('会话存储错误: %s', $storageError);
        $code = 5001;

        $exception = new SessionException($message, $code);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertStringContainsString($storageError, $exception->getMessage());
    }

    /**
     * 测试会话安全异常场景.
     */
    public function testSessionSecurityViolationShouldIndicateSecurity(): void
    {
        $clientIp = '192.168.1.100';
        $message = sprintf('会话安全违规，来源IP: %s', $clientIp);

        $exception = new SessionException($message, 4001);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(4001, $exception->getCode());
        $this->assertStringContainsString('安全违规', $exception->getMessage());
    }
}
