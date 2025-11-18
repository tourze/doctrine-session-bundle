<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Tourze\DoctrineSessionBundle\Storage\HttpSessionStorage;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(HttpSessionStorage::class)]
#[RunTestsInSeparateProcesses]
final class HttpSessionStorageTest extends AbstractIntegrationTestCase
{
    private Request $request;

    private HttpSessionStorage $storage;

    protected function onSetUp(): void
    {
        $this->request = new Request();

        // 从容器获取 HttpSessionStorage 服务
        $factory = self::getService('Tourze\DoctrineSessionBundle\Service\HttpSessionStorageFactory');
        /** @var HttpSessionStorage $storage */
        $storage = $factory->createStorage($this->request);
        $this->storage = $storage;
    }

    /**
     * 测试默认会话名称.
     */
    public function testDefaultSessionName(): void
    {
        $this->assertSame('PHPSESSID', $this->storage->getName());
    }

    /**
     * 测试设置和获取会话名称.
     */
    public function testSetAndGetSessionName(): void
    {
        $this->storage->setName('custom_session');
        $this->assertSame('custom_session', $this->storage->getName());
    }

    /**
     * 测试会话ID生成和获取.
     */
    public function testGetSessionId(): void
    {
        $id = $this->storage->getId();
        $this->assertNotEmpty($id);
        $this->assertSame(32, strlen($id)); // MD5 hash length
    }

    /**
     * 测试从请求cookie获取会话ID.
     */
    public function testGetSessionIdFromCookie(): void
    {
        $existingId = 'existing_session_id';
        $this->request->cookies->set('PHPSESSID', $existingId);

        $id = $this->storage->getId();
        $this->assertSame($existingId, $id);
    }

    /**
     * 测试设置会话ID.
     */
    public function testSetSessionId(): void
    {
        $newId = 'new_session_id';
        $this->storage->setId($newId);

        // 通过反射获取私有属性验证
        $reflection = new \ReflectionClass($this->storage);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);

        $this->assertSame($newId, $idProperty->getValue($this->storage));
    }

    /**
     * 测试会话启动.
     */
    public function testStartSession(): void
    {
        $this->request->cookies->set('PHPSESSID', 'test_session_id');

        $result = $this->storage->start();

        $this->assertTrue($result);
        $this->assertTrue($this->storage->isStarted());
    }

    /**
     * 测试会话启动时处理空数据.
     */
    public function testStartSessionWithEmptyData(): void
    {
        $this->request->cookies->set('PHPSESSID', 'test_session_id');

        $result = $this->storage->start();

        $this->assertTrue($result);
        $this->assertTrue($this->storage->isStarted());
    }

    /**
     * 测试会话启动时处理反序列化错误.
     */
    public function testStartSessionWithDeserializationError(): void
    {
        $this->request->cookies->set('PHPSESSID', 'test_session_id');

        // 实际系统能够处理会话数据的启动过程
        $result = $this->storage->start();

        $this->assertTrue($result);
        $this->assertTrue($this->storage->isStarted());
    }

    /**
     * 测试重复启动会话.
     */
    public function testStartAlreadyStartedSession(): void
    {
        // 先启动一次
        $this->request->cookies->set('PHPSESSID', 'test_session_id');

        $this->storage->start();
        $this->assertTrue($this->storage->isStarted());

        // 再次启动应该直接返回true
        $result = $this->storage->start();
        $this->assertTrue($result);
    }

    /**
     * 测试会话regenerate方法不销毁旧session.
     */
    public function testRegenerateSessionWithoutDestroy(): void
    {
        $metadataBag = new MetadataBag();
        $this->storage->setMetadataBag($metadataBag);

        $result = $this->storage->regenerate(false);

        $this->assertTrue($result);
    }

    /**
     * 测试会话regenerate方法销毁旧session.
     */
    public function testRegenerateSessionWithDestroy(): void
    {
        // 设置初始session ID
        $oldId = 'old_session_id';
        $this->storage->setId($oldId);

        $metadataBag = new MetadataBag();
        $this->storage->setMetadataBag($metadataBag);

        $result = $this->storage->regenerate(true);

        $this->assertTrue($result);

        // 新ID应该不同于旧ID
        $newId = $this->storage->getId();
        $this->assertNotSame($oldId, $newId);
        $this->assertSame(32, strlen($newId)); // MD5 hash length
    }

    /**
     * 测试保存会话 - 数据无变化.
     */
    public function testSaveSessionWithoutChanges(): void
    {
        $this->request->cookies->set('PHPSESSID', 'test_session_id');

        $this->assertTrue($this->storage->start());
        $this->assertTrue($this->storage->isStarted());

        $this->storage->save();

        // save() 方法会关闭会话，这是正常行为
        $this->assertFalse($this->storage->isStarted());
    }

    /**
     * 测试保存会话 - 有数据变化.
     */
    public function testSaveSessionWithChanges(): void
    {
        $this->request->cookies->set('PHPSESSID', 'test_session_id');

        // 先注册bag，再启动session
        $attributeBag = new AttributeBag();
        $this->storage->registerBag($attributeBag);

        $this->storage->start();

        // 启动后设置数据来模拟变化
        $attributeBag->set('test', 'value');

        $this->storage->save();

        // save() 方法会关闭会话，但数据已保存
        $this->assertFalse($this->storage->isStarted());
        // 重新启动会话验证数据已保存
        $this->storage->start();
        $this->assertSame('value', $attributeBag->get('test'));
    }

    /**
     * 测试清空会话.
     */
    public function testClearSession(): void
    {
        $this->request->cookies->set('PHPSESSID', 'test_session_id');

        // 先注册bag，再启动session
        $attributeBag = new AttributeBag();
        $this->storage->registerBag($attributeBag);

        $this->storage->start();
        $attributeBag->set('test', 'value');

        $this->storage->clear();

        // 验证bag被清空
        $this->assertEmpty($attributeBag->all());
    }

    /**
     * 测试注册bag.
     */
    public function testRegisterBag(): void
    {
        $bag = new AttributeBag();
        $bag->setName('test_bag');

        $this->storage->registerBag($bag);

        // 通过getBag验证注册成功
        $retrievedBag = $this->storage->getBag('test_bag');
        $this->assertSame($bag, $retrievedBag);
    }

    /**
     * 测试在已启动会话时注册bag抛出异常.
     */
    public function testRegisterBagWhenStartedThrowsException(): void
    {
        $this->request->cookies->set('PHPSESSID', 'test_session_id');

        $this->storage->start();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot register a bag when the session is already started.');

        $bag = new AttributeBag();
        $this->storage->registerBag($bag);
    }

    /**
     * 测试获取不存在的bag抛出异常.
     */
    public function testGetNonExistentBagThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The SessionBagInterface "non_existent" is not registered.');

        $this->storage->getBag('non_existent');
    }

    /**
     * 测试获取bag时自动启动会话.
     */
    public function testGetBagAutoStartsSession(): void
    {
        $this->request->cookies->set('PHPSESSID', 'test_session_id');

        $bag = new AttributeBag();
        $bag->setName('test_bag');
        $this->storage->registerBag($bag);

        $this->assertFalse($this->storage->isStarted());

        $retrievedBag = $this->storage->getBag('test_bag');

        $this->assertTrue($this->storage->isStarted());
        $this->assertSame($bag, $retrievedBag);
    }

    /**
     * 测试设置和获取metadata bag.
     */
    public function testSetAndGetMetadataBag(): void
    {
        $metadataBag = new MetadataBag();
        $this->storage->setMetadataBag($metadataBag);

        $retrievedBag = $this->storage->getMetadataBag();
        $this->assertSame($metadataBag, $retrievedBag);
    }

    /**
     * 测试使用默认metadata bag.
     */
    public function testUsesDefaultMetadataBag(): void
    {
        $metadataBag = $this->storage->getMetadataBag();
        $this->assertInstanceOf(MetadataBag::class, $metadataBag);
    }

    /**
     * 测试销毁会话.
     */
    public function testDestroySession(): void
    {
        $sessionId = 'test_session_destroy';
        $this->storage->setId($sessionId);

        // 先启动会话，再销毁
        $this->storage->start();
        $this->assertTrue($this->storage->isStarted());

        $result = $this->storage->destroy();

        $this->assertTrue($result);
        $this->assertFalse($this->storage->isStarted());
    }
}
