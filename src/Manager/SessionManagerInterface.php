<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Manager;

use Symfony\Component\HttpFoundation\Request;

/**
 * Request-based Session 管理接口.
 *
 * 提供基于 Request 的高级会话操作，无需关心 sessionId 的提取和管理
 */
interface SessionManagerInterface
{
    /**
     * 从请求中读取会话数据.
     *
     * @return array<string, mixed>
     */
    public function readFromRequest(Request $request): array;

    /**
     * 写入会话数据到请求对应的会话.
     *
     * @param array<string, mixed> $data
     */
    public function writeToRequest(Request $request, array $data): bool;

    /**
     * 销毁请求对应的会话.
     */
    public function destroyRequest(Request $request): bool;

    /**
     * 检查请求是否有有效会话.
     */
    public function hasActiveSession(Request $request): bool;

    /**
     * 获取请求的会话ID（如果存在）.
     */
    public function getSessionIdFromRequest(Request $request): ?string;

    /**
     * 为请求生成新的会话ID.
     */
    public function generateSessionForRequest(Request $request, ?string $sessionName = null): string;
}
