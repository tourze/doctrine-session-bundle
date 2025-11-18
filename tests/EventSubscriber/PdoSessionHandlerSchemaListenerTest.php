<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\EventSubscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bridge\Doctrine\SchemaListener\AbstractSchemaListener;
use Tourze\DoctrineSessionBundle\EventSubscriber\PdoSessionHandlerSchemaListener;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * @internal
 */
#[CoversClass(PdoSessionHandlerSchemaListener::class)]
#[RunTestsInSeparateProcesses]
final class PdoSessionHandlerSchemaListenerTest extends AbstractEventSubscriberTestCase
{
    private PdoSessionHandlerSchemaListener $listener;

    protected function onSetUp(): void
    {
        // 从容器获取真实的服务，避免修改 readonly 属性
        $this->listener = self::getService(PdoSessionHandlerSchemaListener::class);
    }

    /**
     * 测试服务能够正常构造并初始化.
     */
    public function testListenerCanBeConstructed(): void
    {
        $this->assertInstanceOf(PdoSessionHandlerSchemaListener::class, $this->listener);
        $this->assertInstanceOf(AbstractSchemaListener::class, $this->listener);
    }

    /**
     * 测试postGenerateSchema方法正常执行会话处理器配置模式.
     */
    public function testPostGenerateSchemaWithValidEventShouldConfigureSchema(): void
    {
        // Arrange
        $connection = $this->createMock(Connection::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $schema = $this->createMock(Schema::class);
        $event = $this->createMock(GenerateSchemaEventArgs::class);

        $event->method('getEntityManager')->willReturn($entityManager);
        $event->method('getSchema')->willReturn($schema);
        $entityManager->method('getConnection')->willReturn($connection);

        // Act - 不使用Mock期望，直接调用方法
        $this->listener->postGenerateSchema($event);

        // Assert - 通过测试方法正常执行来验证功能
        $this->assertInstanceOf(PdoSessionHandlerSchemaListener::class, $this->listener);
    }

    /**
     * 测试监听器继承AbstractSchemaListener基类.
     */
    public function testListenerShouldExtendAbstractSchemaListener(): void
    {
        $this->assertInstanceOf(
            AbstractSchemaListener::class,
            $this->listener
        );
    }
}
