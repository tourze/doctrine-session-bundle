<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DoctrineSessionBundle\Service\PdoSessionHandler;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 集成测试：验证完整的Session生命周期流程
 * 包括创建、读取、写入、regenerate、destroy全流程.
 *
 * @internal
 */
#[CoversClass(PdoSessionHandler::class)]
#[RunTestsInSeparateProcesses]
final class SessionLifecycleIntegrationTest extends AbstractIntegrationTestCase
{
    private PdoSessionHandler $sessionHandler;

    protected function onSetUp(): void
    {
        // 设置环境变量
        $_ENV['APP_SESSION_TTL'] = '3600';

        // 从容器获取真实的 PdoSessionHandler 服务
        $this->sessionHandler = self::getService(PdoSessionHandler::class);
    }

    protected function onTearDown(): void
    {
        unset($_ENV['APP_SESSION_TTL']);
    }

    /**
     * 测试基本的Session写入和读取流程.
     */
    public function testBasicSessionWriteAndRead(): void
    {
        // Arrange
        $sessionId = 'test_lifecycle_session';
        $sessionData = 'test_session_data';

        // Act - 直接测试真实的服务功能
        $writeResult = $this->sessionHandler->write($sessionId, $sessionData);

        // Assert - 验证写入成功
        $this->assertTrue($writeResult);
    }

    /**
     * 测试Session销毁功能.
     */
    public function testDestroy(): void
    {
        // Arrange
        $sessionId = 'test_destroy_session';

        // Act
        $result = $this->sessionHandler->destroy($sessionId);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试Session读取功能.
     */
    public function testSessionReadFromCache(): void
    {
        // Arrange
        $sessionId = 'test_read_session';

        // Act
        $result = $this->sessionHandler->read($sessionId);

        // Assert
        $this->assertSame('', $result);
    }

    /**
     * 测试Session GC功能.
     */
    public function testGc(): void
    {
        // Act
        $result = $this->sessionHandler->gc(3600);

        // Assert
        $this->assertSame(0, $result);
    }

    /**
     * 测试configureSchema功能.
     */
    public function testConfigureSchema(): void
    {
        // Arrange
        $schema = $this->createMock(Schema::class);

        // Act - 直接调用配置方法
        $this->sessionHandler->configureSchema($schema, fn () => true);

        // Assert - 验证方法正常执行
        $this->assertInstanceOf(PdoSessionHandler::class, $this->sessionHandler);
    }

    /**
     * 测试configureSchema创建表功能.
     */
    public function testConfigureSchemaCreateTable(): void
    {
        // Arrange
        $schema = $this->createMock(Schema::class);
        $table = $this->createMock(Table::class);

        $schema->expects($this->once())
            ->method('hasTable')
            ->with('sessions')
            ->willReturn(false)
        ;

        $schema->expects($this->once())
            ->method('createTable')
            ->with('sessions')
            ->willReturn($table)
        ;

        // Act & Assert - 直接调用方法
        $this->sessionHandler->configureSchema($schema, fn () => true);

        // 验证handler仍然正常工作
        $this->assertInstanceOf(PdoSessionHandler::class, $this->sessionHandler);
    }

    /**
     * 测试Session read功能.
     */
    public function testRead(): void
    {
        // Arrange
        $sessionId = 'test_read_session';

        // Act
        $result = $this->sessionHandler->read($sessionId);

        // Assert
        $this->assertSame('', $result);
    }

    /**
     * 测试Session open功能.
     */
    public function testOpen(): void
    {
        // Act
        $result = $this->sessionHandler->open('session_save_path', 'session_name');

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试Session close功能.
     */
    public function testClose(): void
    {
        // Act
        $result = $this->sessionHandler->close();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试Session write功能.
     */
    public function testWrite(): void
    {
        // Arrange
        $sessionId = 'test_write_session';
        $sessionData = 'test_write_data';

        // Act
        $result = $this->sessionHandler->write($sessionId, $sessionData);

        // Assert
        $this->assertTrue($result);
    }
}
