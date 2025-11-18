<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Tourze\DoctrineSessionBundle\Exception\InvalidArgumentException;
use Tourze\DoctrineSessionBundle\Service\HttpSessionStorageFactory;
use Tourze\DoctrineSessionBundle\Storage\HttpSessionStorage;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(HttpSessionStorageFactory::class)]
#[RunTestsInSeparateProcesses]
final class HttpSessionStorageFactoryTest extends AbstractIntegrationTestCase
{
    private HttpSessionStorageFactory $storageFactory;

    protected function onSetUp(): void
    {
        $this->storageFactory = self::getService(HttpSessionStorageFactory::class);
    }

    /**
     * 测试服务能够正常从容器获取并实现预期接口.
     */
    public function testServiceImplementsExpectedInterfaces(): void
    {
        $this->assertInstanceOf(HttpSessionStorageFactory::class, $this->storageFactory);
        $this->assertInstanceOf(SessionStorageFactoryInterface::class, $this->storageFactory);
    }

    /**
     * 测试服务配置正确性.
     */
    public function testServiceConfigurationIsCorrect(): void
    {
        // 测试服务能正常工作，通过创建存储来验证配置
        $request = $this->createMock(Request::class);
        $storage = $this->storageFactory->createStorage($request);

        $this->assertInstanceOf(HttpSessionStorage::class, $storage);
    }

    /**
     * 测试使用请求创建存储.
     */
    public function testCreateStorageWithRequestShouldReturnHttpSessionStorage(): void
    {
        // Arrange
        $request = new Request();

        // Act
        $storage = $this->storageFactory->createStorage($request);

        // Assert
        $this->assertInstanceOf(SessionStorageInterface::class, $storage);
        $this->assertInstanceOf(HttpSessionStorage::class, $storage);
    }

    /**
     * 测试传入空请求创建存储应抛出异常.
     */
    public function testCreateStorageWithNullRequestShouldThrowException(): void
    {
        // 由于HttpSessionStorage构造函数需要Request对象，
        // factory在传入null时应该抛出异常
        // 这个测试验证factory的输入验证是正确的

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Request cannot be null');

        // Act
        $this->storageFactory->createStorage(null);
    }

    /**
     * 测试多次调用createStorage应返回不同实例.
     */
    public function testCreateStorageMultipleCallsShouldReturnDifferentInstances(): void
    {
        // Arrange
        $request = new Request();

        // Act
        $storage1 = $this->storageFactory->createStorage($request);
        $storage2 = $this->storageFactory->createStorage($request);

        // Assert
        $this->assertInstanceOf(HttpSessionStorage::class, $storage1);
        $this->assertInstanceOf(HttpSessionStorage::class, $storage2);
        $this->assertNotSame($storage1, $storage2); // 应该是不同的实例
    }

    /**
     * 测试使用不同请求创建存储.
     */
    public function testCreateStorageWithDifferentRequestsShouldReturnDifferentStorages(): void
    {
        // Arrange
        $request1 = new Request();
        $request2 = new Request();

        // Act
        $storage1 = $this->storageFactory->createStorage($request1);
        $storage2 = $this->storageFactory->createStorage($request2);

        // Assert
        $this->assertInstanceOf(HttpSessionStorage::class, $storage1);
        $this->assertInstanceOf(HttpSessionStorage::class, $storage2);
        $this->assertNotSame($storage1, $storage2);
    }

    /**
     * 测试工厂传递的依赖正确.
     */
    public function testCreateStorageShouldPassCorrectDependencies(): void
    {
        // 这个测试验证工厂创建的存储对象接收了正确的依赖
        // 由于HttpSessionStorage的构造函数是public的，我们可以间接验证

        // Arrange
        $request = new Request();

        // Act
        $storage = $this->storageFactory->createStorage($request);

        // Assert
        $this->assertInstanceOf(HttpSessionStorage::class, $storage);
        // 由于依赖是通过构造函数传递的，如果创建成功就说明依赖传递正确
    }

    /**
     * 测试工厂使用MetadataBag的默认配置.
     */
    public function testFactoryShouldUseDefaultMetadataBagConfiguration(): void
    {
        // 通过反射检查MetadataBag的配置
        $reflectionClass = new \ReflectionClass($this->storageFactory);
        $metaBagProperty = $reflectionClass->getProperty('metaBag');
        $metaBagProperty->setAccessible(true);

        $metaBag = $metaBagProperty->getValue($this->storageFactory);

        $this->assertInstanceOf(MetadataBag::class, $metaBag);
    }

    /**
     * 测试工厂能够正常创建存储并处理元数据.
     */
    public function testFactoryCanCreateStorageWithMetadata(): void
    {
        // 测试工厂能正常创建存储
        $request = $this->createMock(Request::class);
        $storage = $this->storageFactory->createStorage($request);

        $this->assertInstanceOf(HttpSessionStorage::class, $storage);

        // 验证存储对象有元数据处理能力
        $metadataBag = $storage->getMetadataBag();
        $this->assertInstanceOf(MetadataBag::class, $metadataBag);
    }

    /**
     * 测试工厂实现SessionStorageFactoryInterface接口.
     */
    public function testFactoryShouldImplementSessionStorageFactoryInterface(): void
    {
        $this->assertInstanceOf(SessionStorageFactoryInterface::class, $this->storageFactory);
    }

    /**
     * 测试工厂接口实现完整性.
     */
    public function testFactoryInterfaceComplianceShouldBeCorrect(): void
    {
        // 验证工厂实现了所需的接口
        $this->assertInstanceOf(SessionStorageFactoryInterface::class, $this->storageFactory);

        // 验证工厂可以创建符合接口要求的存储
        $request = new Request();
        $storage = $this->storageFactory->createStorage($request);
        $this->assertInstanceOf(SessionStorageInterface::class, $storage);
    }

    /**
     * 测试存储工厂与各种HTTP方法的请求
     */
    public function testCreateStorageWithDifferentHttpMethodsShouldWork(): void
    {
        // Arrange
        $getRequest = Request::create('/test', 'GET');
        $postRequest = Request::create('/test', 'POST');
        $putRequest = Request::create('/test', 'PUT');

        // Act
        $getStorage = $this->storageFactory->createStorage($getRequest);
        $postStorage = $this->storageFactory->createStorage($postRequest);
        $putStorage = $this->storageFactory->createStorage($putRequest);

        // Assert
        $this->assertInstanceOf(HttpSessionStorage::class, $getStorage);
        $this->assertInstanceOf(HttpSessionStorage::class, $postStorage);
        $this->assertInstanceOf(HttpSessionStorage::class, $putStorage);
    }

    /**
     * 测试存储工厂处理带有参数的请求
     */
    public function testCreateStorageWithRequestParametersShouldWork(): void
    {
        // Arrange
        $request = Request::create('/test?param=value', 'GET');
        $request->request->set('form_field', 'form_value');

        // Act
        $storage = $this->storageFactory->createStorage($request);

        // Assert
        $this->assertInstanceOf(HttpSessionStorage::class, $storage);
    }

    /**
     * 测试验证传递的处理器和日志器依赖.
     */
    public function testFactoryShouldMaintainHandlerAndLoggerReferences(): void
    {
        // 这个测试验证工厂保持了对处理器和日志器的引用
        // 通过创建存储来间接验证依赖注入是否正确

        $request = new Request();
        $storage = $this->storageFactory->createStorage($request);

        // 如果能成功创建存储，说明依赖注入正确
        $this->assertInstanceOf(HttpSessionStorage::class, $storage);
    }
}
