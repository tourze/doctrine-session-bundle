<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Tourze\DoctrineSessionBundle\Storage\HttpSessionStorage;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(HttpSessionStorage::class)]
#[RunTestsInSeparateProcesses]
final class HttpSessionStorageBoundaryTest extends AbstractIntegrationTestCase
{
    private HttpSessionStorage $storage;

    protected function onSetUp(): void
    {
        // 从容器获取 HttpSessionStorage 服务
        $factory = self::getService('Tourze\DoctrineSessionBundle\Service\HttpSessionStorageFactory');
        /** @var HttpSessionStorage $storage */
        $storage = $factory->createStorage(new Request());
        $this->storage = $storage;
    }

    public function testHttpSessionStorageCanBeConstructed(): void
    {
        $this->assertInstanceOf(HttpSessionStorage::class, $this->storage);
    }

    /**
     * 测试会话存储基本操作.
     */
    public function testSessionStorageBasicOperation(): void
    {
        // Act
        $result = $this->storage->start();

        // Assert
        $this->assertTrue($result);
        $this->assertTrue($this->storage->isStarted());
    }

    /**
     * 测试启动方法.
     */
    public function testStart(): void
    {
        // Act
        $result = $this->storage->start();

        // Assert
        $this->assertTrue($result);
        $this->assertTrue($this->storage->isStarted());
    }

    public function testRegisterBag(): void
    {
        // Arrange
        $bag = new AttributeBag();
        $bag->setName('test_bag');

        // Act
        $this->storage->registerBag($bag);

        // Assert
        $retrievedBag = $this->storage->getBag('test_bag');
        $this->assertSame($bag, $retrievedBag);
    }

    public function testGetName(): void
    {
        // Act & Assert
        $this->assertSame('PHPSESSID', $this->storage->getName());
    }

    public function testId(): void
    {
        // Act
        $this->storage->setId('test_session_id');

        // Assert
        $this->assertSame('test_session_id', $this->storage->getId());
    }

    /**
     * 测试会话清除功能.
     */
    public function testClear(): void
    {
        // Arrange
        $bag = new AttributeBag();
        $bag->setName('test_bag');
        $bag->set('key', 'value');

        $this->storage->registerBag($bag);

        // Act
        $this->storage->clear();

        // Assert - 验证bag被清空
        $this->assertEmpty($bag->all());
    }

    /**
     * 测试会话regenerate功能.
     */
    public function testRegenerate(): void
    {
        // Arrange
        $this->storage->setId('old_session_id');

        // Act
        $result = $this->storage->regenerate(false);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试会话保存功能.
     */
    public function testSave(): void
    {
        // Arrange - 先注册bag，再启动session，然后修改数据才会触发write
        $bag = new AttributeBag();
        $bag->setName('test_bag');
        $this->storage->registerBag($bag);

        $this->storage->start();

        // 设置数据来触发变化
        $bag->set('test_key', 'test_value');

        // Act & Assert - 保存会话应该正常执行
        $this->storage->save();

        // 验证数据已保存到bag中
        $this->assertSame('test_value', $bag->get('test_key'));
    }

    /**
     * 测试会话销毁功能.
     */
    public function testDestroy(): void
    {
        // Arrange
        $this->storage->setId('test_session_id');

        // Act
        $result = $this->storage->destroy();

        // Assert
        $this->assertTrue($result);
        $this->assertFalse($this->storage->isStarted());
    }
}
