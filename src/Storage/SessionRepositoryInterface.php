<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Storage;

/**
 * 会话数据仓库接口.
 *
 * 提供基于 sessionId 的数据持久化操作，职责单一：CRUD
 */
interface SessionRepositoryInterface
{
    /**
     * 读取会话数据.
     */
    public function read(string $sessionId): string;

    /**
     * 写入会话数据.
     */
    public function write(string $sessionId, string $data): bool;

    /**
     * 销毁会话.
     */
    public function destroy(string $sessionId): bool;

    /**
     * 检查会话是否存在.
     */
    public function exists(string $sessionId): bool;

    /**
     * 获取会话最后修改时间.
     */
    public function getLastModified(string $sessionId): ?int;

    /**
     * 清理过期会话.
     */
    public function gc(int $maxlifetime): int;
}
