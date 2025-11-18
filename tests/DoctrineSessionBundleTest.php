<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\DoctrineSessionBundle\DoctrineSessionBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(DoctrineSessionBundle::class)]
#[RunTestsInSeparateProcesses]
final class DoctrineSessionBundleTest extends AbstractBundleTestCase
{
    /**
     * 测试Bundle继承关系.
     */
    public function testBundleInheritance(): void
    {
        $bundleClass = self::getBundleClass();
        $bundle = new $bundleClass();
        $this->assertInstanceOf(DoctrineSessionBundle::class, $bundle);
        $this->assertInstanceOf(Bundle::class, $bundle);
        $this->assertInstanceOf(BundleDependencyInterface::class, $bundle);
    }

    /**
     * 测试Bundle名称.
     */
    public function testBundleName(): void
    {
        $bundleClass = self::getBundleClass();
        $bundle = new $bundleClass();
        $this->assertInstanceOf(DoctrineSessionBundle::class, $bundle);
        $this->assertSame('DoctrineSessionBundle', $bundle->getName());
    }

    /**
     * 测试Bundle可以正常实例化.
     */
    public function testBundleCanBeInstantiated(): void
    {
        $bundleClass = self::getBundleClass();
        $bundle = new $bundleClass();
        $this->assertInstanceOf(DoctrineSessionBundle::class, $bundle);
    }
}
