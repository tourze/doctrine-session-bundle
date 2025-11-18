<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Manager;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Tourze\DoctrineSessionBundle\Storage\SessionRepositoryInterface;

/**
 * Request-based Session Manager 实现.
 *
 * 基于 Request 对象进行会话管理，自动处理 sessionId 的提取和生成
 */
#[WithMonologChannel(channel: 'doctrine_session')]
class RequestSessionManager implements SessionManagerInterface
{
    public function __construct(
        private readonly SessionRepositoryInterface $repository,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'DOCTRINE_SESSION_NAME')] private readonly string $sessionName = 'PHPSESSID',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function readFromRequest(Request $request): array
    {
        $sessionId = $this->getSessionIdFromRequest($request);
        if (null === $sessionId) {
            $this->logger->debug('No session ID found in request');

            return [];
        }

        $data = $this->repository->read($sessionId);
        if ('' === $data) {
            return [];
        }

        try {
            $sessionData = @unserialize($data);

            // 确保返回 array<string, mixed> 类型
            if (!is_array($sessionData)) {
                return [];
            }

            // 验证数组键都是字符串，并重建数组确保类型正确
            /** @var array<string, mixed> $validData */
            $validData = [];
            foreach ($sessionData as $key => $value) {
                if (!is_string($key)) {
                    $this->logger->warning('Session data contains non-string key', [
                        'sessionId' => $sessionId,
                        'keyType' => get_debug_type($key),
                    ]);

                    return [];
                }
                $validData[$key] = $value;
            }

            return $validData;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to unserialize session data', [
                'sessionId' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function writeToRequest(Request $request, array $data): bool
    {
        $sessionId = $this->getSessionIdFromRequest($request);
        if (null === $sessionId) {
            // 生成新的会话ID
            $sessionId = $this->generateSessionForRequest($request);
        }

        try {
            $serializedData = serialize($data);

            return $this->repository->write($sessionId, $serializedData);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to serialize and write session data', [
                'sessionId' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function destroyRequest(Request $request): bool
    {
        $sessionId = $this->getSessionIdFromRequest($request);
        if (null === $sessionId) {
            return true; // 没有会话，认为已经销毁
        }

        return $this->repository->destroy($sessionId);
    }

    public function hasActiveSession(Request $request): bool
    {
        $sessionId = $this->getSessionIdFromRequest($request);
        if (null === $sessionId) {
            return false;
        }

        return $this->repository->exists($sessionId);
    }

    public function getSessionIdFromRequest(Request $request): ?string
    {
        $sessionName = $this->getSessionName();
        $sessionId = $request->cookies->get($sessionName);

        if (!is_string($sessionId) || '' === $sessionId || !$this->isValidSessionId($sessionId)) {
            return null;
        }

        return $sessionId;
    }

    public function generateSessionForRequest(Request $request, ?string $sessionName = null): string
    {
        $sessionId = $this->generateSessionId();
        $actualSessionName = $sessionName ?? $this->getSessionName();

        // 记录日志
        $this->logger->debug('Generated new session ID', [
            'sessionId' => $sessionId,
            'sessionName' => $actualSessionName,
            'remoteAddr' => $request->getClientIp(),
        ]);

        return $sessionId;
    }

    private function getSessionName(): string
    {
        return $this->sessionName;
    }

    private function generateSessionId(): string
    {
        // 生成安全的会话ID
        return hash('sha256', random_bytes(32));
    }

    private function isValidSessionId(string $sessionId): bool
    {
        // 验证会话ID格式（根据生成方法调整）
        return 1 === preg_match('/^[a-f0-9]{64}$/', $sessionId);
    }
}
