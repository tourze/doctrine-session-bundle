<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\Service;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DoctrineSessionBundle\Service\PdoSessionHandler;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(PdoSessionHandler::class)]
#[RunTestsInSeparateProcesses]
final class PdoSessionHandlerBoundaryTest extends AbstractIntegrationTestCase
{
    private PdoSessionHandler $handler;

    protected function onSetUp(): void
    {
        // 设置环境变量
        $_ENV['APP_SESSION_TTL'] = '3600';

        $this->handler = self::getService(PdoSessionHandler::class);
    }

    protected function onTearDown(): void
    {
        unset($_ENV['APP_SESSION_TTL']);
        parent::onTearDown();
    }

    public function testReadEmptySessionId(): void
    {
        $result = $this->handler->read('');

        $this->assertSame('', $result);
    }

    public function testReadNonExistentSession(): void
    {
        $sessionId = 'non_existent_session_'.uniqid();

        $data = $this->handler->read($sessionId);

        $this->assertSame('', $data);
    }

    public function testOpen(): void
    {
        // Act
        $result = $this->handler->open('/tmp', 'PHPSESSID');

        // Assert
        $this->assertTrue($result);
    }

    public function testClose(): void
    {
        $result = $this->handler->close();
        $this->assertTrue($result);
    }

    public function testWriteEmptySessionId(): void
    {
        $result = $this->handler->write('', 'data');
        $this->assertTrue($result);
    }

    public function testDestroyEmptySessionId(): void
    {
        $result = $this->handler->destroy('');
        $this->assertTrue($result);
    }

    public function testGc(): void
    {
        $result = $this->handler->gc(3600);
        $this->assertSame(0, $result);
    }

    public function testConfigureSchema(): void
    {
        // Arrange
        $schema = $this->createMock(Schema::class);
        $table = $this->createMock(Table::class);
        $platform = $this->createMock(MySQLPlatform::class);

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

        // Act & Assert - no exception should be thrown
        $this->handler->configureSchema($schema, fn () => true);
    }

    public function testConfigureSchemaCreateTable(): void
    {
        // 测试 configureSchema 方法创建表的场景
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

        // Act & Assert - no exception should be thrown
        $this->handler->configureSchema($schema, fn () => true);
    }
}
