<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\DoctrineSessionBundle\DependencyInjection\DoctrineSessionExtension;
use Tourze\DoctrineSessionBundle\Service\HttpSessionStorageFactory;
use Tourze\DoctrineSessionBundle\Service\PdoSessionHandler;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(DoctrineSessionExtension::class)]
final class DoctrineORMSessionExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    public function testPrepend(): void
    {
        $container = new ContainerBuilder();

        // 先配置 doctrine 的基础配置（模拟现有项目的配置）
        $container->prependExtensionConfig('doctrine', [
            'dbal' => [
                'default_connection' => 'default',
                'connections' => [
                    'default' => [
                        'driver' => 'pdo_sqlite',
                        'path' => ':memory:',
                    ],
                ],
            ],
        ]);

        $extension = new DoctrineSessionExtension();
        $extension->prepend($container);

        $this->assertFrameworkSessionConfig($container);
        $this->assertDoctrineSessionConnection($container);

        // 添加显式断言以满足 PHPStan 要求
        $this->assertTrue(true, 'Test completed with assertions in private methods');
    }

    /**
     * 验证 framework 会话配置.
     */
    private function assertFrameworkSessionConfig(ContainerBuilder $container): void
    {
        $frameworkConfigs = $container->getExtensionConfig('framework');
        $this->assertNotEmpty($frameworkConfigs);

        $firstConfig = $frameworkConfigs[0];
        $this->assertIsArray($firstConfig);

        $sessionConfig = $firstConfig['session'] ?? null;
        $this->assertNotNull($sessionConfig);
        $this->assertIsArray($sessionConfig);

        $this->assertTrue($sessionConfig['enabled']);
        $this->assertSame(PdoSessionHandler::class, $sessionConfig['handler_id']);
        $this->assertSame('auto', $sessionConfig['cookie_secure']);
        $this->assertSame('lax', $sessionConfig['cookie_samesite']);
        $this->assertSame(0, $sessionConfig['cookie_lifetime']);
        $this->assertSame(HttpSessionStorageFactory::class, $sessionConfig['storage_factory_id']);
        $this->assertSame(86400, $sessionConfig['gc_maxlifetime']);
    }

    /**
     * 验证 doctrine 会话连接配置.
     */
    private function assertDoctrineSessionConnection(ContainerBuilder $container): void
    {
        $doctrineConfigs = $container->getExtensionConfig('doctrine');
        $this->assertNotEmpty($doctrineConfigs);

        $doctrineSessionConfig = $this->findDoctrineSessionConnection($doctrineConfigs);
        $this->assertNotNull($doctrineSessionConfig);
        $this->assertIsArray($doctrineSessionConfig);
        $this->assertSame('pdo_sqlite', $doctrineSessionConfig['driver']);
    }

    /**
     * 在 doctrine 配置中查找 doctrine_session 连接.
     *
     * @param array<array<string, mixed>> $doctrineConfigs
     *
     * @return array<string, mixed>|null
     */
    private function findDoctrineSessionConnection(array $doctrineConfigs): ?array
    {
        foreach ($doctrineConfigs as $config) {
            $this->assertIsArray($config);

            $dbalConfig = $config['dbal'] ?? null;
            if (!is_array($dbalConfig)) {
                continue;
            }

            $connections = $dbalConfig['connections'] ?? null;
            if (!is_array($connections)) {
                continue;
            }

            $sessionConnection = $connections['doctrine_session'] ?? null;
            if (!is_array($sessionConnection)) {
                continue;
            }

            // 验证并转换为正确的类型
            $validConnection = $this->extractValidConnection($sessionConnection);
            if (null !== $validConnection) {
                return $validConnection;
            }
        }

        return null;
    }

    /**
     * 提取有效的连接配置.
     *
     * @param array<mixed, mixed> $connection
     *
     * @return array<string, mixed>|null
     */
    private function extractValidConnection(array $connection): ?array
    {
        /** @var array<string, mixed> $validConnection */
        $validConnection = [];

        foreach ($connection as $key => $value) {
            if (!is_string($key)) {
                return null;
            }
            $validConnection[$key] = $value;
        }

        return $validConnection;
    }
}
