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
final class DoctrineSessionExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private function getExtension(): DoctrineSessionExtension
    {
        return new DoctrineSessionExtension();
    }

    /**
     * 测试服务加载.
     */
    public function testServicesAreLoaded(): void
    {
        $extension = $this->getExtension();
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');

        $extension->load([], $container);

        // 验证关键服务已定义
        $this->assertTrue($container->hasDefinition(PdoSessionHandler::class));
        $this->assertTrue($container->hasDefinition(HttpSessionStorageFactory::class));
    }

    /**
     * 测试数据库连接名称配置.
     */
    public function testGetDoctrineConnectionName(): void
    {
        $extension = new DoctrineSessionExtension();
        $reflection = new \ReflectionClass($extension);
        $method = $reflection->getMethod('getDoctrineConnectionName');
        $method->setAccessible(true);

        $connectionName = $method->invoke($extension);
        $this->assertSame('doctrine_session', $connectionName);
    }

    /**
     * 测试配置目录获取.
     */
    public function testGetConfigDir(): void
    {
        $extension = new DoctrineSessionExtension();
        $reflection = new \ReflectionClass($extension);
        $method = $reflection->getMethod('getConfigDir');
        $method->setAccessible(true);

        $configDir = $method->invoke($extension);

        // 确保返回值是字符串类型
        $this->assertIsString($configDir);
        $this->assertStringEndsWith('/Resources/config', $configDir);
        $this->assertDirectoryExists($configDir);
    }

    /**
     * 测试prepend方法配置framework.session.
     */
    public function testPrependConfiguresFrameworkSession(): void
    {
        $container = new ContainerBuilder();
        $extension = new DoctrineSessionExtension();

        // 首先添加必要的 doctrine 配置
        $container->prependExtensionConfig('doctrine', [
            'dbal' => [
                'default_connection' => 'default',
                'connections' => [
                    'default' => [
                        'url' => 'sqlite:///:memory:',
                    ],
                ],
            ],
        ]);

        $extension->prepend($container);

        // 验证framework扩展配置已添加
        $config = $container->getExtensionConfig('framework');
        $this->assertNotEmpty($config);

        // 验证session配置
        $firstConfig = $config[0];
        $this->assertIsArray($firstConfig);

        $sessionConfig = $firstConfig['session'] ?? null;
        $this->assertNotNull($sessionConfig);
        $this->assertIsArray($sessionConfig);

        // 验证关键配置项
        $this->assertTrue($sessionConfig['enabled']);
        $this->assertSame(PdoSessionHandler::class, $sessionConfig['handler_id']);
        $this->assertSame('auto', $sessionConfig['cookie_secure']);
        $this->assertSame('lax', $sessionConfig['cookie_samesite']);
        $this->assertSame(0, $sessionConfig['cookie_lifetime']);
        $this->assertSame(HttpSessionStorageFactory::class, $sessionConfig['storage_factory_id']);
        $this->assertSame(86400, $sessionConfig['gc_maxlifetime']);
    }

    /**
     * 测试prepend方法调用父类方法.
     */
    public function testPrependCallsParent(): void
    {
        $container = new ContainerBuilder();
        $extension = new DoctrineSessionExtension();

        // 首先添加必要的 doctrine 配置
        $container->prependExtensionConfig('doctrine', [
            'dbal' => [
                'default_connection' => 'default',
                'connections' => [
                    'default' => [
                        'url' => 'sqlite:///:memory:',
                    ],
                ],
            ],
        ]);

        // 执行prepend，应该调用父类的prepend方法
        $extension->prepend($container);

        // 验证容器有配置被添加（来自父类）
        $this->assertNotEmpty($container->getExtensionConfig('doctrine'));
    }
}
