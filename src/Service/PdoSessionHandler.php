<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Service;

use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\DoctrineSessionBundle\Storage\PdoSessionRepository;
use Tourze\DoctrineSessionBundle\Storage\SessionRepositoryInterface;

/**
 * PDO 会话处理器.
 *
 * 基于分层架构的会话处理器，实现 PHP 的 SessionHandlerInterface
 * 底层使用 SessionRepositoryInterface 进行数据操作
 */
#[WithMonologChannel(channel: 'doctrine_session')]
class PdoSessionHandler implements \SessionHandlerInterface
{
    private bool $gcCalled = false;

    public function __construct(
        private readonly SessionRepositoryInterface $repository,
        private readonly LoggerInterface $logger,
        private readonly int $ttl = 86400,
    ) {
    }

    public function open(string $path, string $name): bool
    {
        // SessionRepository 不需要 open 操作
        $this->logger->debug('Session handler opened', ['path' => $path, 'name' => $name]);

        return true;
    }

    public function close(): bool
    {
        // 在关闭时执行垃圾回收
        if ($this->gcCalled) {
            $this->repository->gc($this->ttl);
            $this->gcCalled = false;
        }

        return true;
    }

    public function read(#[\SensitiveParameter] string $sessionId): string
    {
        return $this->repository->read($sessionId);
    }

    public function write(#[\SensitiveParameter] string $sessionId, string $data): bool
    {
        return $this->repository->write($sessionId, $data);
    }

    public function destroy(#[\SensitiveParameter] string $sessionId): bool
    {
        return $this->repository->destroy($sessionId);
    }

    public function gc(int $max_lifetime): int|false
    {
        // 我们将gc()延迟到close()，以便在事务和阻塞的读写进程之外执行
        $this->gcCalled = true;

        return 0;
    }

    /**
     * 配置数据库Schema，用于自动创建sessions表.
     */
    public function configureSchema(Schema $schema, callable $isSameDatabase): void
    {
        // 获取数据库连接对象进行检查
        $connection = $this->repository instanceof PdoSessionRepository
            ? $this->repository->getConnection()
            : null;

        if (null === $connection || !$isSameDatabase(fn (string $sql) => $connection->executeStatement($sql))) {
            return;
        }

        if ($schema->hasTable('sessions')) {
            return;
        }

        $table = $schema->createTable('sessions');
        $table->addColumn('sess_id', 'string', ['length' => 128]);
        $table->addColumn('sess_data', 'blob');
        $table->addColumn('sess_time', 'integer', ['unsigned' => true]);
        $table->addColumn('sess_lifetime', 'integer', ['unsigned' => true]);
        // 设置主键
        $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(
            UnqualifiedName::unquoted('sessions_pkey'),
            [UnqualifiedName::unquoted('sess_id')],
            false
        ));
    }

    /**
     * 获取底层数据仓库实例（用于高级操作）.
     */
    public function getRepository(): SessionRepositoryInterface
    {
        return $this->repository;
    }
}
