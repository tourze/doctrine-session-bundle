<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Storage;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * PDO 会话数据仓库实现.
 *
 * 负责会话数据的数据库 CRUD 操作，包括缓存优化
 */
#[WithMonologChannel(channel: 'doctrine_session')]
class PdoSessionRepository implements SessionRepositoryInterface
{
    private const CACHE_PREFIX = 'doctrine_session_';

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
        private readonly string $tableName = 'sessions',
        private readonly int $ttl = 86400,
    ) {
    }

    public function read(string $sessionId): string
    {
        if ('' === $sessionId) {
            return '';
        }

        $cached = $this->readFromCache($sessionId);
        if (null !== $cached) {
            return $cached;
        }

        return $this->readFromDatabase($sessionId);
    }

    /**
     * 从缓存读取会话数据.
     */
    private function readFromCache(string $sessionId): ?string
    {
        try {
            $cached = $this->cache->get(self::CACHE_PREFIX.$sessionId);
            if (null === $cached) {
                return null;
            }

            if (!is_string($cached)) {
                $this->logger->warning('Invalid cached session data type', [
                    'sessionId' => $sessionId,
                    'type' => get_debug_type($cached),
                ]);
                $this->cache->delete(self::CACHE_PREFIX.$sessionId);

                return null;
            }

            $this->logger->debug('Session data loaded from cache', ['sessionId' => $sessionId]);

            return $cached;
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to read from cache', ['sessionId' => $sessionId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * 从数据库读取会话数据.
     */
    private function readFromDatabase(string $sessionId): string
    {
        try {
            $sql = "SELECT sess_data FROM {$this->tableName} WHERE sess_id = ? AND sess_time >= ?";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue(1, $sessionId);
            $stmt->bindValue(2, time() - $this->ttl);

            $result = $stmt->executeQuery();
            $data = $result->fetchOne();

            if (false === $data) {
                $this->logger->debug('Session not found in database', ['sessionId' => $sessionId]);

                return '';
            }

            if (!is_string($data)) {
                $this->logger->warning('Invalid session data type from database', [
                    'sessionId' => $sessionId,
                    'type' => get_debug_type($data),
                ]);

                return '';
            }

            $sessionData = $this->decodeSessionData($data);
            $this->cacheSessionData($sessionId, $sessionData);

            $this->logger->debug('Session data loaded from database', ['sessionId' => $sessionId]);

            return $sessionData;
        } catch (Exception $e) {
            $this->logger->error('Failed to read session from database', [
                'sessionId' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * 解码 Base64 编码的会话数据.
     */
    private function decodeSessionData(string $data): string
    {
        $sessionData = base64_decode($data, true);

        return false === $sessionData ? '' : $sessionData;
    }

    /**
     * 缓存会话数据.
     */
    private function cacheSessionData(string $sessionId, string $data): void
    {
        try {
            $this->cache->set(self::CACHE_PREFIX.$sessionId, $data, $this->ttl);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to cache session data', ['sessionId' => $sessionId, 'error' => $e->getMessage()]);
        }
    }

    public function write(string $sessionId, string $data): bool
    {
        if ('' === $sessionId) {
            return true; // 空会话ID直接返回true，不执行数据库操作
        }

        try {
            $encodedData = base64_encode($data);
            $time = time();

            // 根据数据库平台使用不同的 UPSERT 语句
            $sql = $this->buildUpsertSql();

            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue(1, $sessionId);
            $stmt->bindValue(2, $encodedData);
            $stmt->bindValue(3, $time);
            $stmt->bindValue(4, $this->ttl);

            $result = $stmt->executeStatement() >= 0;

            if ($result) {
                // 更新缓存
                try {
                    $this->cache->set(self::CACHE_PREFIX.$sessionId, $data, $this->ttl);
                } catch (\Throwable $e) {
                    $this->logger->warning('Failed to update cache', ['sessionId' => $sessionId, 'error' => $e->getMessage()]);
                }

                $this->logger->debug('Session data written successfully', ['sessionId' => $sessionId]);
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to write session to database', [
                'sessionId' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function destroy(string $sessionId): bool
    {
        try {
            // 删除缓存
            $this->cache->delete(self::CACHE_PREFIX.$sessionId);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to delete from cache', ['sessionId' => $sessionId, 'error' => $e->getMessage()]);
        }

        try {
            $sql = "DELETE FROM {$this->tableName} WHERE sess_id = ?";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue(1, $sessionId);

            $deleted = $stmt->executeStatement() > 0;

            $this->logger->debug('Session destroyed', ['sessionId' => $sessionId, 'deleted' => $deleted]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to destroy session', [
                'sessionId' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function exists(string $sessionId): bool
    {
        try {
            $sql = "SELECT 1 FROM {$this->tableName} WHERE sess_id = ? AND sess_time >= ?";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue(1, $sessionId);
            $stmt->bindValue(2, time() - $this->ttl);

            $result = $stmt->executeQuery();

            return false !== $result->fetchOne();
        } catch (Exception $e) {
            $this->logger->error('Failed to check session existence', [
                'sessionId' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getLastModified(string $sessionId): ?int
    {
        try {
            $sql = "SELECT sess_time FROM {$this->tableName} WHERE sess_id = ?";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue(1, $sessionId);

            $result = $stmt->executeQuery();
            $time = $result->fetchOne();

            if (false === $time) {
                return null;
            }

            // 安全地转换为 int
            if (is_int($time)) {
                return $time;
            }

            if (is_numeric($time)) {
                return (int) $time;
            }

            $this->logger->warning('Invalid session time type from database', [
                'sessionId' => $sessionId,
                'type' => get_debug_type($time),
            ]);

            return null;
        } catch (Exception $e) {
            $this->logger->error('Failed to get session last modified time', [
                'sessionId' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function gc(int $maxlifetime): int
    {
        try {
            $sql = "DELETE FROM {$this->tableName} WHERE sess_time < ?";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue(1, time() - $maxlifetime);

            $deleted = $stmt->executeStatement();
            $deletedCount = is_int($deleted) ? $deleted : (int) $deleted;

            $this->logger->info('Session garbage collection completed', [
                'deleted' => $deletedCount,
                'maxlifetime' => $maxlifetime,
            ]);

            return $deletedCount;
        } catch (Exception $e) {
            $this->logger->error('Session garbage collection failed', [
                'error' => $e->getMessage(),
                'maxlifetime' => $maxlifetime,
            ]);

            return 0;
        }
    }

    /**
     * 获取数据库连接对象
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * 根据数据库平台构建 UPSERT SQL 语句.
     */
    private function buildUpsertSql(): string
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            // SQLite 使用 INSERT OR REPLACE
            return "INSERT OR REPLACE INTO {$this->tableName} (sess_id, sess_data, sess_time, sess_lifetime) 
                    VALUES (?, ?, ?, ?)";
        }

        if ($platform instanceof PostgreSQLPlatform) {
            // PostgreSQL 使用 ON CONFLICT
            return "INSERT INTO {$this->tableName} (sess_id, sess_data, sess_time, sess_lifetime) 
                    VALUES (?, ?, ?, ?) 
                    ON CONFLICT (sess_id) DO UPDATE SET 
                    sess_data = EXCLUDED.sess_data, 
                    sess_time = EXCLUDED.sess_time, 
                    sess_lifetime = EXCLUDED.sess_lifetime";
        }

        if ($platform instanceof SQLServerPlatform) {
            // SQL Server 使用 MERGE
            return "MERGE {$this->tableName} AS target
                    USING (VALUES (?, ?, ?, ?)) AS source (sess_id, sess_data, sess_time, sess_lifetime)
                    ON target.sess_id = source.sess_id
                    WHEN MATCHED THEN
                        UPDATE SET sess_data = source.sess_data, sess_time = source.sess_time, sess_lifetime = source.sess_lifetime
                    WHEN NOT MATCHED THEN
                        INSERT (sess_id, sess_data, sess_time, sess_lifetime) VALUES (source.sess_id, source.sess_data, source.sess_time, source.sess_lifetime);";
        }

        if ($platform instanceof OraclePlatform) {
            // Oracle 使用 MERGE
            return "MERGE INTO {$this->tableName} target
                    USING (SELECT ? sess_id, ? sess_data, ? sess_time, ? sess_lifetime FROM DUAL) source
                    ON (target.sess_id = source.sess_id)
                    WHEN MATCHED THEN
                        UPDATE SET sess_data = source.sess_data, sess_time = source.sess_time, sess_lifetime = source.sess_lifetime
                    WHEN NOT MATCHED THEN
                        INSERT (sess_id, sess_data, sess_time, sess_lifetime) VALUES (source.sess_id, source.sess_data, source.sess_time, source.sess_lifetime)";
        }

        // 默认使用 MySQL 语法 (支持 MySQL 和 MariaDB)
        return "INSERT INTO {$this->tableName} (sess_id, sess_data, sess_time, sess_lifetime) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                sess_data = VALUES(sess_data), 
                sess_time = VALUES(sess_time), 
                sess_lifetime = VALUES(sess_lifetime)";
    }
}
