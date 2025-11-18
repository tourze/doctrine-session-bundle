<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DoctrineSessionBundle\Exception\UnsupportedDatabaseDriverException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(UnsupportedDatabaseDriverException::class)]
final class UnsupportedDatabaseDriverExceptionTest extends AbstractExceptionTestCase
{
    /**
     * 测试异常基本实例化和继承关系.
     */
    public function testExceptionWithDefaultConstructorShouldExtendRuntimeException(): void
    {
        $exception = new UnsupportedDatabaseDriverException();

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(UnsupportedDatabaseDriverException::class, $exception);
    }

    /**
     * 测试异常消息设置功能.
     */
    public function testExceptionWithMessageShouldReturnCorrectMessage(): void
    {
        $message = '不支持的数据库驱动异常';
        $exception = new UnsupportedDatabaseDriverException($message);

        $this->assertSame($message, $exception->getMessage());
    }

    /**
     * 测试异常代码设置功能.
     */
    public function testExceptionWithCodeShouldReturnCorrectCode(): void
    {
        $code = 5001;
        $exception = new UnsupportedDatabaseDriverException('测试消息', $code);

        $this->assertSame($code, $exception->getCode());
    }

    /**
     * 测试异常链设置功能.
     */
    public function testExceptionWithPreviousExceptionShouldMaintainChain(): void
    {
        $previousException = new \LogicException('逻辑错误');
        $exception = new UnsupportedDatabaseDriverException('不支持的驱动', 0, $previousException);

        $this->assertSame($previousException, $exception->getPrevious());
    }

    /**
     * 测试异常完整参数构造.
     */
    public function testExceptionWithAllParametersShouldSetAllProperties(): void
    {
        $message = '完整不支持数据库驱动异常测试';
        $code = 6006;
        $previousException = new \Exception('基础异常');

        $exception = new UnsupportedDatabaseDriverException($message, $code, $previousException);

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
        $message = '可抛出的不支持数据库驱动异常';

        $this->expectException(UnsupportedDatabaseDriverException::class);
        $this->expectExceptionMessage($message);

        throw new UnsupportedDatabaseDriverException($message);
    }

    /**
     * 测试不支持的数据库平台场景.
     */
    public function testUnsupportedDatabasePlatformScenarioShouldProvideDriverDetails(): void
    {
        $driverName = 'MongoDB';
        $message = sprintf('不支持的数据库平台: %s', $driverName);

        $exception = new UnsupportedDatabaseDriverException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertStringContainsString($driverName, $exception->getMessage());
        $this->assertStringContainsString('不支持', $exception->getMessage());
    }

    /**
     * 测试PDO驱动不支持场景.
     */
    public function testUnsupportedPDODriverScenarioShouldIndicatePDOIssue(): void
    {
        $pdoDriver = 'pdo_firebird';
        $message = sprintf('PDO驱动 "%s" 暂不支持会话存储', $pdoDriver);
        $code = 1000;

        $exception = new UnsupportedDatabaseDriverException($message, $code);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertStringContainsString($pdoDriver, $exception->getMessage());
        $this->assertStringContainsString('暂不支持', $exception->getMessage());
    }

    /**
     * 测试数据库版本不支持场景.
     */
    public function testUnsupportedDatabaseVersionScenarioShouldIndicateVersion(): void
    {
        $databaseName = 'MySQL';
        $version = '5.0';
        $minVersion = '5.7';
        $message = sprintf('%s %s 版本过低，最低支持版本: %s', $databaseName, $version, $minVersion);

        $exception = new UnsupportedDatabaseDriverException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertStringContainsString($databaseName, $exception->getMessage());
        $this->assertStringContainsString($version, $exception->getMessage());
        $this->assertStringContainsString($minVersion, $exception->getMessage());
    }

    /**
     * 测试驱动缺失场景.
     */
    public function testMissingDriverExtensionScenarioShouldIndicateMissing(): void
    {
        $extension = 'pdo_pgsql';
        $message = sprintf('缺少必要的PHP扩展: %s', $extension);

        $exception = new UnsupportedDatabaseDriverException($message, 404);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(404, $exception->getCode());
        $this->assertStringContainsString($extension, $exception->getMessage());
        $this->assertStringContainsString('缺少', $exception->getMessage());
    }

    /**
     * 测试创建会话表时的不支持场景.
     */
    public function testCreateSessionTableUnsupportedScenarioShouldProvideContext(): void
    {
        $driverClass = 'Doctrine\DBAL\Platforms\DB2Platform';
        $operation = '创建会话表';
        $message = sprintf('%s 当前不支持 PDO 驱动 "%s"。', $operation, $driverClass);

        $exception = new UnsupportedDatabaseDriverException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertStringContainsString($driverClass, $exception->getMessage());
        $this->assertStringContainsString($operation, $exception->getMessage());
    }

    /**
     * 测试数据库连接串不支持场景.
     */
    public function testUnsupportedConnectionStringScenarioShouldIndicateConnectionIssue(): void
    {
        $connectionString = 'odbc:Driver={SQL Server};Server=localhost';
        $message = sprintf('不支持的数据库连接串格式: %s', $connectionString);

        $exception = new UnsupportedDatabaseDriverException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertStringContainsString('不支持', $exception->getMessage());
        $this->assertStringContainsString('连接串格式', $exception->getMessage());
    }

    /**
     * 测试建议的解决方案场景.
     */
    public function testExceptionWithSolutionSuggestionShouldProvideGuidance(): void
    {
        $unsupportedDriver = 'CouchDB';
        $supportedDrivers = ['MySQL', 'PostgreSQL', 'SQLite', 'SQL Server', 'Oracle'];
        $supportedList = implode(', ', $supportedDrivers);
        $message = sprintf(
            '不支持的数据库驱动: %s。支持的驱动包括: %s',
            $unsupportedDriver,
            $supportedList
        );

        $exception = new UnsupportedDatabaseDriverException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertStringContainsString($unsupportedDriver, $exception->getMessage());
        foreach ($supportedDrivers as $driver) {
            $this->assertStringContainsString($driver, $exception->getMessage());
        }
    }

    /**
     * 测试空消息异常.
     */
    public function testExceptionWithEmptyMessageShouldAcceptEmpty(): void
    {
        $exception = new UnsupportedDatabaseDriverException('');

        $this->assertSame('', $exception->getMessage());
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
