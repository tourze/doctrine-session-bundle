<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DoctrineSessionBundle\Exception\StorageException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(StorageException::class)]
final class StorageExceptionTest extends AbstractExceptionTestCase
{
    /**
     * 测试异常基本实例化和继承关系.
     */
    public function testExceptionWithDefaultConstructorShouldExtendLogicException(): void
    {
        $exception = new StorageException();

        $this->assertInstanceOf(\LogicException::class, $exception);
        $this->assertInstanceOf(StorageException::class, $exception);
    }

    /**
     * 测试异常消息设置功能.
     */
    public function testExceptionWithMessageShouldReturnCorrectMessage(): void
    {
        $message = '存储异常消息';
        $exception = new StorageException($message);

        $this->assertSame($message, $exception->getMessage());
    }

    /**
     * 测试异常代码设置功能.
     */
    public function testExceptionWithCodeShouldReturnCorrectCode(): void
    {
        $code = 3001;
        $exception = new StorageException('测试消息', $code);

        $this->assertSame($code, $exception->getCode());
    }

    /**
     * 测试异常链设置功能.
     */
    public function testExceptionWithPreviousExceptionShouldMaintainChain(): void
    {
        $previousException = new \RuntimeException('运行时错误');
        $exception = new StorageException('存储异常', 0, $previousException);

        $this->assertSame($previousException, $exception->getPrevious());
    }

    /**
     * 测试异常完整参数构造.
     */
    public function testExceptionWithAllParametersShouldSetAllProperties(): void
    {
        $message = '完整存储异常测试';
        $code = 4004;
        $previousException = new \InvalidArgumentException('参数无效');

        $exception = new StorageException($message, $code, $previousException);

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
        $message = '可抛出的存储异常';

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage($message);

        throw new StorageException($message);
    }

    /**
     * 测试数据库连接失败场景.
     */
    public function testDatabaseConnectionFailureScenarioShouldProvideConnectionDetails(): void
    {
        $host = 'localhost';
        $database = 'sessions_db';
        $message = sprintf('无法连接到数据库 %s@%s', $database, $host);

        $exception = new StorageException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertStringContainsString($host, $exception->getMessage());
        $this->assertStringContainsString($database, $exception->getMessage());
    }

    /**
     * 测试表不存在场景.
     */
    public function testTableNotFoundScenarioShouldIndicateTableIssue(): void
    {
        $tableName = 'sessions';
        $message = sprintf('会话表 "%s" 不存在', $tableName);
        $code = 1146; // MySQL table doesn't exist error code

        $exception = new StorageException($message, $code);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertStringContainsString($tableName, $exception->getMessage());
    }

    /**
     * 测试磁盘空间不足场景.
     */
    public function testDiskSpaceFullScenarioShouldIndicateSpaceIssue(): void
    {
        $availableSpace = '0 MB';
        $message = sprintf('磁盘空间不足，可用空间: %s', $availableSpace);

        $exception = new StorageException($message, 28); // No space left on device

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(28, $exception->getCode());
        $this->assertStringContainsString('磁盘空间不足', $exception->getMessage());
    }

    /**
     * 测试权限不足场景.
     */
    public function testPermissionDeniedScenarioShouldIndicatePermissionIssue(): void
    {
        $filePath = '/var/lib/php/sessions';
        $message = sprintf('权限不足，无法访问: %s', $filePath);

        $exception = new StorageException($message, 13); // Permission denied

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(13, $exception->getCode());
        $this->assertStringContainsString('权限不足', $exception->getMessage());
    }

    /**
     * 测试存储配置错误场景.
     */
    public function testStorageConfigurationErrorShouldProvideConfigDetails(): void
    {
        $configKey = 'session.storage.driver';
        $configValue = 'invalid_driver';
        $message = sprintf('存储配置错误: %s = %s', $configKey, $configValue);

        $exception = new StorageException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertStringContainsString($configKey, $exception->getMessage());
        $this->assertStringContainsString($configValue, $exception->getMessage());
    }

    /**
     * 测试空消息异常.
     */
    public function testExceptionWithEmptyMessageShouldAcceptEmpty(): void
    {
        $exception = new StorageException('');

        $this->assertSame('', $exception->getMessage());
        $this->assertInstanceOf(\LogicException::class, $exception);
    }

    /**
     * 测试存储容量限制场景.
     */
    public function testStorageCapacityLimitScenarioShouldIndicateLimit(): void
    {
        $maxSessions = 10000;
        $currentSessions = 10001;
        $message = sprintf('存储容量已达上限，最大会话数: %d，当前会话数: %d', $maxSessions, $currentSessions);

        $exception = new StorageException($message, 122); // Disk quota exceeded

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(122, $exception->getCode());
        $this->assertStringContainsString('存储容量已达上限', $exception->getMessage());
    }
}
