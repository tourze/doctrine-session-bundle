<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tourze\DoctrineSessionBundle\DependencyInjection\Compiler\OverrideSessionFactoryPass;
use Tourze\DoctrineSessionBundle\Service\HttpSessionFactory;

/**
 * @internal
 */
#[CoversClass(OverrideSessionFactoryPass::class)]
final class OverrideSessionFactoryPassTest extends TestCase
{
    private OverrideSessionFactoryPass $compilerPass;

    protected function setUp(): void
    {
        $this->compilerPass = new OverrideSessionFactoryPass();
    }

    /**
     * 测试当session.factory服务不存在时不做任何处理.
     */
    public function testProcessShouldReturnEarlyWhenSessionFactoryNotExists(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        // 确保没有 session.factory 服务
        $this->assertFalse($container->hasDefinition('session.factory'));

        // Act
        $this->compilerPass->process($container);

        // Assert - 容器状态应该没有改变
        $this->assertFalse($container->hasDefinition('session.factory'));
    }

    /**
     * 测试替换默认session.factory服务
     */
    public function testProcessShouldReplaceSessionFactoryService(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        // 添加必要的服务依赖
        $loggerDefinition = new Definition('Psr\Log\LoggerInterface');
        $container->setDefinition('logger', $loggerDefinition);

        $pdoHandlerDefinition = new Definition('Tourze\DoctrineSessionBundle\Service\PdoSessionHandler');
        $container->setDefinition('Tourze\DoctrineSessionBundle\Service\PdoSessionHandler', $pdoHandlerDefinition);

        // 添加原始的session.factory服务
        $originalDefinition = new Definition('Symfony\Component\HttpFoundation\Session\SessionFactoryInterface');
        $container->setDefinition('session.factory', $originalDefinition);

        // Act
        $this->compilerPass->process($container);

        // Assert - 验证session.factory服务被替换
        $this->assertTrue($container->hasDefinition('session.factory'));

        $newDefinition = $container->getDefinition('session.factory');
        $this->assertSame(HttpSessionFactory::class, $newDefinition->getClass());
    }

    /**
     * 测试新定义的参数配置正确.
     */
    public function testProcessShouldConfigureArgumentsCorrectly(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        // 添加必要的服务依赖
        $container->setDefinition('logger', new Definition('Psr\Log\LoggerInterface'));
        $container->setDefinition('Tourze\DoctrineSessionBundle\Service\PdoSessionHandler',
            new Definition('Tourze\DoctrineSessionBundle\Service\PdoSessionHandler'));

        // 添加原始的session.factory服务
        $container->setDefinition('session.factory', new Definition('Original\Service'));

        // Act
        $this->compilerPass->process($container);

        // Assert - 验证参数配置
        $definition = $container->getDefinition('session.factory');
        $arguments = $definition->getArguments();

        $this->assertCount(3, $arguments);

        // 验证第一个参数是logger引用
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('logger', (string) $arguments[0]);

        // 验证第二个参数是PdoSessionHandler引用
        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('Tourze\DoctrineSessionBundle\Service\PdoSessionHandler', (string) $arguments[1]);

        // 验证第三个参数是环境变量配置
        $this->assertSame('%env(resolve:default:default_session_name:DOCTRINE_SESSION_NAME)%', $arguments[2]);
    }

    /**
     * 测试新定义被标记为懒加载.
     */
    public function testProcessShouldMarkDefinitionAsLazy(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        // 添加必要的服务依赖
        $container->setDefinition('logger', new Definition('Psr\Log\LoggerInterface'));
        $container->setDefinition('Tourze\DoctrineSessionBundle\Service\PdoSessionHandler',
            new Definition('Tourze\DoctrineSessionBundle\Service\PdoSessionHandler'));

        // 添加原始的session.factory服务
        $container->setDefinition('session.factory', new Definition('Original\Service'));

        // Act
        $this->compilerPass->process($container);

        // Assert - 验证懒加载配置
        $definition = $container->getDefinition('session.factory');
        $this->assertTrue($definition->isLazy());
    }

    /**
     * 测试多次调用process方法的幂等性.
     */
    public function testProcessShouldBeIdempotent(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        // 添加必要的服务依赖
        $container->setDefinition('logger', new Definition('Psr\Log\LoggerInterface'));
        $container->setDefinition('Tourze\DoctrineSessionBundle\Service\PdoSessionHandler',
            new Definition('Tourze\DoctrineSessionBundle\Service\PdoSessionHandler'));

        // 添加原始的session.factory服务
        $container->setDefinition('session.factory', new Definition('Original\Service'));

        // Act - 多次调用process
        $this->compilerPass->process($container);
        $firstDefinition = $container->getDefinition('session.factory');

        $this->compilerPass->process($container);
        $secondDefinition = $container->getDefinition('session.factory');

        // Assert - 结果应该相同（注意Reference对象会是新实例，所以比较内容）
        $this->assertSame($firstDefinition->getClass(), $secondDefinition->getClass());
        $this->assertSame($firstDefinition->isLazy(), $secondDefinition->isLazy());

        // 比较参数内容而不是对象引用
        $firstArgs = $firstDefinition->getArguments();
        $secondArgs = $secondDefinition->getArguments();
        $this->assertCount(3, $firstArgs);
        $this->assertCount(3, $secondArgs);

        // 安全地转换为字符串 - 先验证类型再转换
        $firstArg0 = $firstArgs[0];
        $secondArg0 = $secondArgs[0];
        $firstArg1 = $firstArgs[1];
        $secondArg1 = $secondArgs[1];

        // 将参数转换为字符串以进行比较
        $firstArg0Str = (is_object($firstArg0) || is_string($firstArg0)) && method_exists($firstArg0, '__toString') ? (string) $firstArg0 : '';
        $secondArg0Str = (is_object($secondArg0) || is_string($secondArg0)) && method_exists($secondArg0, '__toString') ? (string) $secondArg0 : '';
        $firstArg1Str = (is_object($firstArg1) || is_string($firstArg1)) && method_exists($firstArg1, '__toString') ? (string) $firstArg1 : '';
        $secondArg1Str = (is_object($secondArg1) || is_string($secondArg1)) && method_exists($secondArg1, '__toString') ? (string) $secondArg1 : '';

        $this->assertSame($firstArg0Str, $secondArg0Str);
        $this->assertSame($firstArg1Str, $secondArg1Str);
        $this->assertSame($firstArgs[2], $secondArgs[2]);
    }

    /**
     * 测试编译器通道实现正确的接口.
     */
    public function testCompilerPassShouldImplementCompilerPassInterface(): void
    {
        $this->assertInstanceOf(
            'Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface',
            $this->compilerPass
        );
    }

    /**
     * 测试process方法在缺少依赖服务时的行为.
     */
    public function testProcessShouldHandleMissingDependencyServices(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        // 只添加session.factory但不添加其依赖
        $container->setDefinition('session.factory', new Definition('Original\Service'));

        // Act & Assert - 应该不会抛出异常，但会创建带有未解析引用的定义
        $this->compilerPass->process($container);

        $definition = $container->getDefinition('session.factory');
        $this->assertSame(HttpSessionFactory::class, $definition->getClass());

        // 依赖引用仍然会被创建，即使服务不存在
        $this->assertCount(3, $definition->getArguments());
    }
}
