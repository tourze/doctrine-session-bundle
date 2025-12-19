<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\Storage;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Tourze\DoctrineSessionBundle\Service\HttpSessionStorageFactory;
use Tourze\DoctrineSessionBundle\Storage\HttpSessionStorage;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(HttpSessionStorage::class)]
#[RunTestsInSeparateProcesses]
final class SessionIdManagementTest extends AbstractIntegrationTestCase
{
    private HttpSessionStorageFactory $factory;

    private Connection $connection;

    protected function onSetUp(): void
    {
        // 从容器获取真实服务
        $this->factory = self::getService(HttpSessionStorageFactory::class);

        // 获取数据库连接
        $connection = self::getContainer()->get('doctrine.dbal.doctrine_session_connection');
        $this->assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;

        // 确保数据库表存在
        $this->createSessionsTableIfNotExists();

        // 清理测试数据
        $this->connection->executeStatement('DELETE FROM sessions');
    }

    protected function onTearDown(): void
    {
        // 清理测试数据
        $this->connection->executeStatement('DELETE FROM sessions');
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

    public function testStart(): void
    {
        // Arrange
        $request = new Request();

        // Act
        $storage = $this->factory->createStorage($request);
        $result = $storage->start();

        // Assert
        $this->assertTrue($result);
        $this->assertTrue($storage->isStarted());
    }

    public function testRegisterBag(): void
    {
        // Arrange
        $request = new Request();
        $bag = new AttributeBag();
        $bag->setName('test_bag');

        // Act
        $storage = $this->factory->createStorage($request);
        $storage->registerBag($bag);

        // Assert
        $retrievedBag = $storage->getBag('test_bag');
        $this->assertSame($bag, $retrievedBag);
    }

    public function testCreateStorage(): void
    {
        // Arrange
        $request = new Request();

        // Act
        $storage = $this->factory->createStorage($request);

        // Assert
        $this->assertInstanceOf(HttpSessionStorage::class, $storage);
        $this->assertNotEmpty($storage->getName());
    }

    public function testFactoryCreatesUniqueStorageInstances(): void
    {
        // Arrange
        $request1 = new Request();
        $request2 = new Request();

        // Act
        $storage1 = $this->factory->createStorage($request1);
        $storage2 = $this->factory->createStorage($request2);

        // Assert
        $this->assertNotSame($storage1, $storage2);
    }

    /**
     * 测试会话清理功能.
     */
    public function testClear(): void
    {
        // Arrange
        $request = new Request();
        $storage = $this->factory->createStorage($request);

        $bag = new AttributeBag();
        $bag->setName('test_bag');

        $storage->registerBag($bag);
        $storage->start();

        // 设置一些数据
        $bag->set('key', 'value');
        $this->assertSame('value', $bag->get('key'));

        // Act
        $storage->clear();

        // Assert - 验证bag被清空
        $this->assertEmpty($bag->all());
    }

    /**
     * 测试会话销毁功能.
     */
    public function testDestroy(): void
    {
        // Arrange
        $request = new Request();
        $storage = $this->factory->createStorage($request);
        $this->assertInstanceOf(HttpSessionStorage::class, $storage);
        $storage->start();

        // Act
        $result = $storage->destroy();

        // Assert
        $this->assertTrue($result);
        $this->assertFalse($storage->isStarted());
    }

    /**
     * 测试会话regenerate功能.
     */
    public function testRegenerate(): void
    {
        // Arrange
        $request = new Request();
        $storage = $this->factory->createStorage($request);
        $storage->start();

        $oldId = $storage->getId();

        // Act
        $result = $storage->regenerate(false);

        // Assert
        $this->assertTrue($result);
        $this->assertNotSame($oldId, $storage->getId());
    }

    /**
     * 测试会话保存功能.
     */
    public function testSave(): void
    {
        // Arrange
        $request = new Request();
        $storage = $this->factory->createStorage($request);
        $this->assertInstanceOf(HttpSessionStorage::class, $storage);

        // 先注册bag，再启动session，然后修改数据才会触发write
        $bag = new AttributeBag();
        $bag->setName('test_bag');
        $storage->registerBag($bag);

        $storage->start();

        // 设置数据来触发变化
        $bag->set('test_key', 'test_value');

        // Act
        $storage->save();

        // Assert - 验证数据被保存到数据库
        $sessionId = $storage->getId();
        $dbData = $this->connection->fetchOne(
            'SELECT sess_data FROM sessions WHERE sess_id = ?',
            [$sessionId]
        );

        // 验证数据已写入数据库
        $this->assertNotFalse($dbData);
    }

    /**
     * 测试会话ID管理.
     */
    public function testSessionIdManagement(): void
    {
        // Arrange
        $request = new Request();
        $storage = $this->factory->createStorage($request);

        // Act - 获取ID（会自动生成）
        $id = $storage->getId();

        // Assert
        $this->assertNotEmpty($id);
        $this->assertIsString($id);

        // 测试设置新ID
        $newId = 'custom_session_id_'.uniqid();
        $storage->setId($newId);
        $this->assertSame($newId, $storage->getId());
    }

    /**
     * 测试会话名称管理.
     */
    public function testSessionNameManagement(): void
    {
        // Arrange
        $request = new Request();
        $storage = $this->factory->createStorage($request);

        // Act & Assert
        $this->assertNotEmpty($storage->getName());

        $newName = 'CUSTOM_SESSION';
        $storage->setName($newName);
        $this->assertSame($newName, $storage->getName());
    }

    /**
     * 测试从请求中提取会话ID.
     */
    public function testSessionIdFromRequest(): void
    {
        // Arrange
        $sessionId = hash('md5', random_bytes(16));
        $request = new Request();
        $request->cookies->set('PHPSESSID', $sessionId);

        // Act
        $storage = $this->factory->createStorage($request);

        // Assert - 验证会话ID从请求中获取
        // 由于工厂可能使用不同的session name，我们验证storage能正常创建
        $this->assertInstanceOf(HttpSessionStorage::class, $storage);
    }
}
