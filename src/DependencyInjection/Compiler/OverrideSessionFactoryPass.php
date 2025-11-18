<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tourze\DoctrineSessionBundle\Service\HttpSessionFactory;

class OverrideSessionFactoryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // 检查是否存在默认的 session.factory 服务
        if (!$container->hasDefinition('session.factory')) {
            return;
        }

        // 创建我们的 HttpSessionFactory 定义
        $definition = new Definition(HttpSessionFactory::class);
        $definition->setArguments([
            new Reference('logger'),
            new Reference('Tourze\DoctrineSessionBundle\Service\PdoSessionHandler'),
            '%env(resolve:default:default_session_name:DOCTRINE_SESSION_NAME)%',
        ]);

        // 标记为懒加载
        $definition->setLazy(true);

        // 替换默认的 session.factory 服务
        $container->setDefinition('session.factory', $definition);
    }
}
