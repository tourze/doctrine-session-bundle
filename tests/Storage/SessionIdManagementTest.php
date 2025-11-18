<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Tourze\DoctrineSessionBundle\Service\HttpSessionStorageFactory;
use Tourze\DoctrineSessionBundle\Service\PdoSessionHandler;
use Tourze\DoctrineSessionBundle\Storage\HttpSessionStorage;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(HttpSessionStorage::class)]
#[RunTestsInSeparateProcesses]
final class SessionIdManagementTest extends AbstractIntegrationTestCase
{
    private HttpSessionStorageFactory $factory;

    private MockObject&PdoSessionHandler $sessionHandler;

    private MockObject&LoggerInterface $logger;

    protected function onSetUp(): void
    {
        $this->sessionHandler = $this->createMock(PdoSessionHandler::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->factory = new HttpSessionStorageFactory(
            $this->sessionHandler,
            $this->logger,
            '_sf2_meta', // storageKey
            0, // updateThreshold
            'PHPSESSID' // sessionName
        );
    }

    public function testStart(): void
    {
        // Arrange
        $request = new Request();
        $this->sessionHandler->method('open')->willReturn(true);
        $this->sessionHandler->method('read')->willReturn('');

        // Act
        $storage = $this->factory->createStorage($request);
        $result = $storage->start();

        // Assert
        $this->assertTrue($result);
    }

    public function testRegisterBag(): void
    {
        // Arrange
        $request = new Request();
        $bag = new AttributeBag();
        $bag->setName('test_bag');

        // Act
        $storage = $this->factory->createStorage($request);
        $storage->registerBag($bag);

        // Assert
        $retrievedBag = $storage->getBag('test_bag');
        $this->assertSame($bag, $retrievedBag);
    }

    public function testCreateStorage(): void
    {
        // Arrange
        $request = new Request();

        // Act
        $storage = $this->factory->createStorage($request);

        // Assert
        $this->assertInstanceOf(HttpSessionStorage::class, $storage);
        $this->assertSame('PHPSESSID', $storage->getName());
    }

    public function testFactoryCreatesUniqueStorageInstances(): void
    {
        // Arrange
        $request1 = new Request();
        $request2 = new Request();

        // Act
        $storage1 = $this->factory->createStorage($request1);
        $storage2 = $this->factory->createStorage($request2);

        // Assert
        $this->assertNotSame($storage1, $storage2);
    }

    /**
     * 测试会话清理功能.
     */
    public function testClear(): void
    {
        // Arrange
        $request = new Request();
        $storage = $this->factory->createStorage($request);

        $bag = new AttributeBag();
        $bag->setName('test_bag');
        $bag->set('key', 'value');

        $storage->registerBag($bag);

        // Act
        $storage->clear();

        // Assert - 验证bag被清空
        $this->assertEmpty($bag->all());
    }

    /**
     * 测试会话销毁功能.
     */
    public function testDestroy(): void
    {
        // Arrange
        $request = new Request();
        $storage = $this->factory->createStorage($request);

        $this->sessionHandler->expects($this->once())
            ->method('destroy')
            ->willReturn(true)
        ;

        // Act
        /** @var HttpSessionStorage $storage */
        $result = $storage->destroy();

        // Assert
        $this->assertTrue($result);
        $this->assertFalse($storage->isStarted());
    }

    /**
     * 测试会话regenerate功能.
     */
    public function testRegenerate(): void
    {
        // Arrange
        $request = new Request();
        $storage = $this->factory->createStorage($request);

        // Act
        $result = $storage->regenerate(false);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试会话保存功能.
     */
    public function testSave(): void
    {
        // Arrange
        $request = new Request();
        $storage = $this->factory->createStorage($request);

        // 先注册bag，再启动session，然后修改数据才会触发write
        $bag = new AttributeBag();
        $bag->setName('test_bag');
        $storage->registerBag($bag);

        $this->sessionHandler->method('read')->willReturn('');
        $storage->start();

        // 设置数据来触发变化
        $bag->set('test_key', 'test_value');

        $this->sessionHandler->expects($this->once())
            ->method('write')
            ->willReturn(true)
        ;

        // Act
        $storage->save();
    }
}
