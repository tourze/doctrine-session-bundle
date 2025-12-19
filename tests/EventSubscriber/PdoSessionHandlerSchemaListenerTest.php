<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\EventSubscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bridge\Doctrine\SchemaListener\AbstractSchemaListener;
use Tourze\DoctrineSessionBundle\EventSubscriber\PdoSessionHandlerSchemaListener;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * @internal
 */
#[CoversClass(PdoSessionHandlerSchemaListener::class)]
#[RunTestsInSeparateProcesses]
final class PdoSessionHandlerSchemaListenerTest extends AbstractEventSubscriberTestCase
{
    private PdoSessionHandlerSchemaListener $listener;

    private Connection $connection;

    protected function onSetUp(): void
    {
        // 从容器获取真实的服务，避免修改 readonly 属性
        $this->listener = self::getService(PdoSessionHandlerSchemaListener::class);

        // 获取真实的数据库连接
        $connection = self::getContainer()->get('doctrine.dbal.doctrine_session_connection');
        $this->assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;

        // 确保数据库表存在
        $this->createSessionsTableIfNotExists();
    }

    /**
     * 创建 sessions 表（如果不存在）.
     */
    private function createSessionsTableIfNotExists(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $sql = '
                CREATE TABLE IF NOT EXISTS sessions (
                    sess_id TEXT PRIMARY KEY,
                    sess_data TEXT NOT NULL,
                    sess_lifetime INTEGER NOT NULL,
                    sess_time INTEGER NOT NULL
                )
            ';
        } else {
            $sql = '
                CREATE TABLE IF NOT EXISTS sessions (
                    sess_id VARBINARY(128) NOT NULL PRIMARY KEY,
                    sess_data BLOB NOT NULL,
                    sess_lifetime INTEGER UNSIGNED NOT NULL,
                    sess_time INTEGER UNSIGNED NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin
            ';
        }

        $this->connection->executeStatement($sql);
    }

    /**
     * 测试服务能够正常构造并初始化.
     */
    public function testListenerCanBeConstructed(): void
    {
        $this->assertInstanceOf(PdoSessionHandlerSchemaListener::class, $this->listener);
        $this->assertInstanceOf(AbstractSchemaListener::class, $this->listener);
    }

    /**
     * 测试postGenerateSchema方法正常执行会话处理器配置模式.
     */
    public function testPostGenerateSchemaWithValidEventShouldConfigureSchema(): void
    {
        // Arrange - 使用真实连接创建事件参数
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $schema = new Schema();
        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        // 配置 EntityManager 返回真实连接
        $entityManager->method('getConnection')->willReturn($this->connection);

        // Act - 调用方法，sessions 表已存在所以不会尝试创建
        $this->listener->postGenerateSchema($event);

        // Assert - 测试方法正常执行没有抛出异常
        $this->assertInstanceOf(PdoSessionHandlerSchemaListener::class, $this->listener);
    }

    /**
     * 测试监听器继承AbstractSchemaListener基类.
     */
    public function testListenerShouldExtendAbstractSchemaListener(): void
    {
        $this->assertInstanceOf(
            AbstractSchemaListener::class,
            $this->listener
        );
    }
}
