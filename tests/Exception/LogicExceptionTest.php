<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DoctrineSessionBundle\Exception\LogicException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(LogicException::class)]
final class LogicExceptionTest extends AbstractExceptionTestCase
{
    /**
     * 测试异常基本实例化和继承关系.
     */
    public function testExceptionWithDefaultConstructorShouldExtendLogicException(): void
    {
        $exception = new LogicException();

        $this->assertInstanceOf(\LogicException::class, $exception);
        $this->assertInstanceOf(LogicException::class, $exception);
    }

    /**
     * 测试异常消息设置功能.
     */
    public function testExceptionWithMessageShouldReturnCorrectMessage(): void
    {
        $message = '这是一个逻辑异常消息';
        $exception = new LogicException($message);

        $this->assertSame($message, $exception->getMessage());
    }

    /**
     * 测试异常代码设置功能.
     */
    public function testExceptionWithCodeShouldReturnCorrectCode(): void
    {
        $code = 1001;
        $exception = new LogicException('测试消息', $code);

        $this->assertSame($code, $exception->getCode());
    }

    /**
     * 测试异常链设置功能.
     */
    public function testExceptionWithPreviousExceptionShouldMaintainChain(): void
    {
        $previousException = new \RuntimeException('前一个异常');
        $exception = new LogicException('当前异常', 0, $previousException);

        $this->assertSame($previousException, $exception->getPrevious());
    }

    /**
     * 测试异常完整参数构造.
     */
    public function testExceptionWithAllParametersShouldSetAllProperties(): void
    {
        $message = '完整参数测试异常';
        $code = 2002;
        $previousException = new \InvalidArgumentException('参数错误');

        $exception = new LogicException($message, $code, $previousException);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previousException, $exception->getPrevious());
        $this->assertInstanceOf(\LogicException::class, $exception);
    }

    /**
     * 测试异常可被抛出和捕获.
     */
    public function testExceptionCanBeThrownAndCaughtShouldWork(): void
    {
        $message = '可抛出的异常';

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        throw new LogicException($message);
    }

    /**
     * 测试空消息异常.
     */
    public function testExceptionWithEmptyMessageShouldAcceptEmpty(): void
    {
        $exception = new LogicException('');

        $this->assertSame('', $exception->getMessage());
        $this->assertInstanceOf(\LogicException::class, $exception);
    }

    /**
     * 测试零代码异常.
     */
    public function testExceptionWithZeroCodeShouldAcceptZero(): void
    {
        $exception = new LogicException('测试', 0);

        $this->assertSame(0, $exception->getCode());
    }

    /**
     * 测试负数代码异常.
     */
    public function testExceptionWithNegativeCodeShouldAcceptNegative(): void
    {
        $code = -100;
        $exception = new LogicException('测试', $code);

        $this->assertSame($code, $exception->getCode());
    }
}
