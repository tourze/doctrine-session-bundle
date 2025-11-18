<?php

namespace Tourze\DoctrineSessionBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Tourze\DoctrineSessionBundle\Service\HttpSessionStorageFactory;
use Tourze\DoctrineSessionBundle\Service\PdoSessionHandler;
use Tourze\SymfonyDependencyServiceLoader\AppendDoctrineConnectionExtension;

class DoctrineSessionExtension extends AppendDoctrineConnectionExtension implements PrependExtensionInterface
{
    protected function getConfigDir(): string
    {
        return __DIR__.'/../Resources/config';
    }

    /**
     * 注册一个专门用来处理session的数据库连接.
     */
    protected function getDoctrineConnectionName(): string
    {
        return 'doctrine_session';
    }

    public function prepend(ContainerBuilder $container): void
    {
        parent::prepend($container);

        // 自动配置 session
        $container->prependExtensionConfig('framework', [
            'session' => [
                'enabled' => true,
                'handler_id' => PdoSessionHandler::class,
                'cookie_secure' => 'auto',
                'cookie_samesite' => 'lax', // 这里固定是lax，在src/EventSubscriber/HttpSessionEventSubscriber.php我们可以动态根据变量来修改
                'cookie_lifetime' => 0, // Setting a cookie_lifetime to 0 will cause the cookie to live only as long as the browser remains open.
                'storage_factory_id' => HttpSessionStorageFactory::class,
                'gc_maxlifetime' => 86400, // https://symfony.com/doc/current/components/http_foundation/session_configuration.html#session-idle-time-keep-alive 参考这个，这样配置应该是无限长度了
            ],
        ]);
    }
}
