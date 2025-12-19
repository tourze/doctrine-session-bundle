<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\Storage;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\SimpleCache\CacheInterface;
use Tourze\DoctrineSessionBundle\Storage\PdoSessionRepository;
use Tourze\DoctrineSessionBundle\Storage\SessionRepositoryInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(PdoSessionRepository::class)]
#[RunTestsInSeparateProcesses]
final class PdoSessionRepositoryTest extends AbstractIntegrationTestCase
{
    private Connection $connection;

    private PdoSessionRepository $repository;

    private CacheInterface $cache;

    protected function onSetUp(): void
    {
        // 从容器获取数据库连接
        $connection = self::getContainer()->get('doctrine.dbal.doctrine_session_connection');
        $this->assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;

        // 从容器获取 PSR-16 缓存服务
        $cache = self::getContainer()->get(CacheInterface::class);
        $this->assertInstanceOf(CacheInterface::class, $cache);
        $this->cache = $cache;

        // 从容器获取 Repository 服务
        $repository = self::getService(SessionRepositoryInterface::class);
        $this->assertInstanceOf(PdoSessionRepository::class, $repository);
        $this->repository = $repository;

        // 确保数据库表存在
        $this->createSessionsTableIfNotExists();

        // 清理测试数据和缓存
        $this->connection->executeStatement('DELETE FROM sessions');
        $this->cache->clear();
    }

    protected function onTearDown(): void
    {
        // 清理测试数据和缓存
        $this->connection->executeStatement('DELETE FROM sessions');
        $this->cache->clear();
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
     * 插入测试会话数据到数据库.
     */
    private function insertTestSession(string $sessionId, string $data, int $time, int $lifetime = 3600): void
    {
        $encodedData = base64_encode($data);
        $this->connection->executeStatement(
            'INSERT INTO sessions (sess_id, sess_data, sess_lifetime, sess_time) VALUES (?, ?, ?, ?)',
            [$sessionId, $encodedData, $lifetime, $time]
        );
    }

    /**
     * 测试类实现正确的接口.
     */
    public function testRepositoryShouldImplementSessionRepositoryInterface(): void
    {
        $this->assertInstanceOf(SessionRepositoryInterface::class, $this->repository);
    }

    /**
     * 测试读取会话数据 - 空会话ID.
     */
    public function testReadShouldReturnEmptyStringForEmptySessionId(): void
    {
        // Act
        $result = $this->repository->read('');

        // Assert
        $this->assertSame('', $result);
    }

    /**
     * 测试读取会话数据 - 从数据库读取成功.
     */
    public function testReadShouldReturnDataFromDatabase(): void
    {
        // Arrange
        $sessionId = 'test_session_id_'.uniqid();
        $sessionData = 'test_session_data';
        $this->insertTestSession($sessionId, $sessionData, time());

        // Act
        $result = $this->repository->read($sessionId);

        // Assert
        $this->assertSame($sessionData, $result);
    }

    /**
     * 测试读取会话数据 - 从缓存读取成功.
     */
    public function testReadShouldReturnDataFromCacheWhenAvailable(): void
    {
        // Arrange
        $sessionId = 'test_session_id_'.uniqid();
        $sessionData = 'cached_session_data';

        // 先写入数据库
        $this->insertTestSession($sessionId, $sessionData, time());

        // 第一次读取会从数据库读取并缓存
        $firstRead = $this->repository->read($sessionId);
        $this->assertSame($sessionData, $firstRead);

        // 删除数据库中的记录
        $this->connection->executeStatement('DELETE FROM sessions WHERE sess_id = ?', [$sessionId]);

        // 第二次读取应该从缓存获取
        $secondRead = $this->repository->read($sessionId);
        $this->assertSame($sessionData, $secondRead);
    }

    /**
     * 测试读取会话数据 - 数据库中不存在.
     */
    public function testReadShouldReturnEmptyStringWhenNotFoundInDatabase(): void
    {
        // Arrange
        $sessionId = 'non_existent_session_'.uniqid();

        // Act
        $result = $this->repository->read($sessionId);

        // Assert
        $this->assertSame('', $result);
    }

    /**
     * 测试读取会话数据 - 会话已过期.
     */
    public function testReadShouldReturnEmptyStringForExpiredSession(): void
    {
        // Arrange
        $sessionId = 'expired_session_'.uniqid();
        $sessionData = 'expired_data';
        // 默认 TTL 是 86400 秒（24小时），必须超过这个时间才算过期
        $expiredTime = time() - 86401; // 超过24小时前

        $this->insertTestSession($sessionId, $sessionData, $expiredTime);

        // Act
        $result = $this->repository->read($sessionId);

        // Assert
        $this->assertSame('', $result);
    }

    /**
     * 测试写入会话数据 - 空会话ID.
     */
    public function testWriteShouldReturnTrueForEmptySessionId(): void
    {
        // Act
        $result = $this->repository->write('', 'test_data');

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试写入会话数据 - 成功写入新会话.
     */
    public function testWriteShouldWriteNewSessionToDatabase(): void
    {
        // Arrange
        $sessionId = 'new_session_'.uniqid();
        $sessionData = 'new_session_data';

        // Act
        $result = $this->repository->write($sessionId, $sessionData);

        // Assert
        $this->assertTrue($result);

        // 验证数据已写入数据库
        $dbData = $this->connection->fetchOne(
            'SELECT sess_data FROM sessions WHERE sess_id = ?',
            [$sessionId]
        );
        $this->assertSame($sessionData, base64_decode($dbData, true));

        // 验证数据已缓存
        $cachedData = $this->cache->get('doctrine_session_'.$sessionId);
        $this->assertSame($sessionData, $cachedData);
    }

    /**
     * 测试写入会话数据 - 更新现有会话.
     */
    public function testWriteShouldUpdateExistingSession(): void
    {
        // Arrange
        $sessionId = 'existing_session_'.uniqid();
        $oldData = 'old_data';
        $newData = 'new_data';

        $this->insertTestSession($sessionId, $oldData, time());

        // Act
        $result = $this->repository->write($sessionId, $newData);

        // Assert
        $this->assertTrue($result);

        // 验证数据已更新
        $dbData = $this->connection->fetchOne(
            'SELECT sess_data FROM sessions WHERE sess_id = ?',
            [$sessionId]
        );
        $this->assertSame($newData, base64_decode($dbData, true));
    }

    /**
     * 测试销毁会话.
     */
    public function testDestroyShouldDeleteSessionFromCacheAndDatabase(): void
    {
        // Arrange
        $sessionId = 'destroy_session_'.uniqid();
        $sessionData = 'test_data';

        $this->insertTestSession($sessionId, $sessionData, time());
        $this->cache->set('doctrine_session_'.$sessionId, $sessionData);

        // Act
        $result = $this->repository->destroy($sessionId);

        // Assert
        $this->assertTrue($result);

        // 验证数据库中已删除
        $dbData = $this->connection->fetchOne(
            'SELECT sess_data FROM sessions WHERE sess_id = ?',
            [$sessionId]
        );
        $this->assertFalse($dbData);

        // 验证缓存中已删除
        $cachedData = $this->cache->get('doctrine_session_'.$sessionId);
        $this->assertNull($cachedData);
    }

    /**
     * 测试销毁会话 - 会话不存在.
     */
    public function testDestroyShouldReturnTrueForNonExistentSession(): void
    {
        // Arrange
        $sessionId = 'non_existent_'.uniqid();

        // Act
        $result = $this->repository->destroy($sessionId);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试检查会话是否存在 - 存在且未过期.
     */
    public function testExistsShouldReturnTrueForExistingSession(): void
    {
        // Arrange
        $sessionId = 'existing_session_'.uniqid();
        $this->insertTestSession($sessionId, 'test_data', time());

        // Act
        $result = $this->repository->exists($sessionId);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试检查会话是否存在 - 不存在.
     */
    public function testExistsShouldReturnFalseForNonExistentSession(): void
    {
        // Arrange
        $sessionId = 'non_existent_'.uniqid();

        // Act
        $result = $this->repository->exists($sessionId);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * 测试检查会话是否存在 - 已过期.
     */
    public function testExistsShouldReturnFalseForExpiredSession(): void
    {
        // Arrange
        $sessionId = 'expired_session_'.uniqid();
        // 默认 TTL 是 86400 秒（24小时），必须超过这个时间才算过期
        $expiredTime = time() - 86401; // 超过24小时前

        $this->insertTestSession($sessionId, 'test_data', $expiredTime);

        // Act
        $result = $this->repository->exists($sessionId);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * 测试获取会话最后修改时间.
     */
    public function testGetLastModifiedShouldReturnTimestamp(): void
    {
        // Arrange
        $sessionId = 'test_session_'.uniqid();
        $timestamp = time();

        $this->insertTestSession($sessionId, 'test_data', $timestamp);

        // Act
        $result = $this->repository->getLastModified($sessionId);

        // Assert
        $this->assertSame($timestamp, $result);
    }

    /**
     * 测试获取会话最后修改时间 - 会话不存在.
     */
    public function testGetLastModifiedShouldReturnNullWhenSessionNotFound(): void
    {
        // Arrange
        $sessionId = 'non_existent_'.uniqid();

        // Act
        $result = $this->repository->getLastModified($sessionId);

        // Assert
        $this->assertNull($result);
    }

    /**
     * 测试垃圾回收 - 删除过期会话.
     */
    public function testGcShouldDeleteExpiredSessions(): void
    {
        // Arrange
        $maxLifetime = 3600;

        // 插入过期会话
        $expiredSession1 = 'expired_1_'.uniqid();
        $expiredSession2 = 'expired_2_'.uniqid();
        $this->insertTestSession($expiredSession1, 'data1', time() - $maxLifetime - 100);
        $this->insertTestSession($expiredSession2, 'data2', time() - $maxLifetime - 200);

        // 插入未过期会话
        $validSession = 'valid_'.uniqid();
        $this->insertTestSession($validSession, 'data3', time());

        // Act
        $deletedCount = $this->repository->gc($maxLifetime);

        // Assert
        $this->assertSame(2, $deletedCount);

        // 验证过期会话已删除
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM sessions');
        $this->assertEquals(1, $count);

        // 验证未过期会话仍存在
        $validData = $this->connection->fetchOne(
            'SELECT sess_data FROM sessions WHERE sess_id = ?',
            [$validSession]
        );
        $this->assertNotFalse($validData);
    }

    /**
     * 测试垃圾回收 - 没有过期会话.
     */
    public function testGcShouldReturnZeroWhenNoExpiredSessions(): void
    {
        // Arrange
        $maxLifetime = 3600;

        // 插入未过期会话
        $validSession = 'valid_'.uniqid();
        $this->insertTestSession($validSession, 'data', time());

        // Act
        $deletedCount = $this->repository->gc($maxLifetime);

        // Assert
        $this->assertSame(0, $deletedCount);

        // 验证会话仍存在
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM sessions');
        $this->assertEquals(1, $count);
    }

    /**
     * 测试获取数据库连接.
     */
    public function testGetConnectionShouldReturnConnection(): void
    {
        // Act
        $result = $this->repository->getConnection();

        // Assert
        $this->assertInstanceOf(Connection::class, $result);
        $this->assertSame($this->connection, $result);
    }

    /**
     * 测试缓存前缀行为 - 通过验证缓存键来测试.
     *
     * 这个测试验证 repository 使用正确的缓存前缀 'doctrine_session_'，
     * 通过观察缓存中键的格式来验证，而不是使用反射访问私有常量。
     */
    public function testRepositoryCachePrefixBehaviorShouldBeCorrect(): void
    {
        // Arrange
        $sessionId = 'test_prefix_'.uniqid();
        $sessionData = 'test_data';

        // Act - 写入会话数据，这会同时更新缓存
        $this->repository->write($sessionId, $sessionData);

        // Assert - 验证缓存键使用了正确的前缀
        $cachedData = $this->cache->get('doctrine_session_'.$sessionId);
        $this->assertSame($sessionData, $cachedData);

        // 验证不带前缀的键不存在
        $this->assertNull($this->cache->get($sessionId));
    }

    /**
     * 测试查找存在的会话ID应该返回会话数据.
     *
     * 此测试用例验证当会话存在时,repository 能正确返回会话数据。
     * 对应于 AbstractRepositoryTestCase 中 testFindWithExistingIdShouldReturnEntity 的语义,
     * 但适配了 PdoSessionRepository 的 read 方法而非标准的 find 方法。
     */
    public function testFindWithExistingIdShouldReturnEntity(): void
    {
        // Arrange
        $sessionId = 'existing_session_id_'.uniqid();
        $sessionData = 'existing_session_data';

        $this->insertTestSession($sessionId, $sessionData, time());

        // Act
        $result = $this->repository->read($sessionId);

        // Assert - 验证返回正确的会话数据
        $this->assertSame($sessionData, $result);
    }

    /**
     * 测试写入操作同时更新缓存和数据库.
     */
    public function testWriteShouldUpdateBothCacheAndDatabase(): void
    {
        // Arrange
        $sessionId = 'test_session_'.uniqid();
        $sessionData = 'test_data';

        // Act
        $result = $this->repository->write($sessionId, $sessionData);

        // Assert
        $this->assertTrue($result);

        // 验证缓存
        $cachedData = $this->cache->get('doctrine_session_'.$sessionId);
        $this->assertSame($sessionData, $cachedData);

        // 验证数据库
        $dbData = $this->connection->fetchOne(
            'SELECT sess_data FROM sessions WHERE sess_id = ?',
            [$sessionId]
        );
        $this->assertSame($sessionData, base64_decode($dbData, true));
    }

    /**
     * 测试读取操作会缓存数据库结果.
     */
    public function testReadShouldCacheDatabaseResult(): void
    {
        // Arrange
        $sessionId = 'test_session_'.uniqid();
        $sessionData = 'test_data';

        $this->insertTestSession($sessionId, $sessionData, time());

        // 确保缓存中没有数据
        $this->cache->delete('doctrine_session_'.$sessionId);

        // Act - 第一次读取应该从数据库读取并缓存
        $result = $this->repository->read($sessionId);

        // Assert
        $this->assertSame($sessionData, $result);

        // 验证已缓存
        $cachedData = $this->cache->get('doctrine_session_'.$sessionId);
        $this->assertSame($sessionData, $cachedData);
    }
}
