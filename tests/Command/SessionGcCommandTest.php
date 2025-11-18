<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Tests\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\DoctrineSessionBundle\Command\SessionGcCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(SessionGcCommand::class)]
#[RunTestsInSeparateProcesses]
final class SessionGcCommandTest extends AbstractCommandTestCase
{
    private Connection $connection;

    private SessionGcCommand $command;

    protected function onSetUp(): void
    {
        $connection = self::getContainer()->get('doctrine.dbal.doctrine_session_connection');
        $this->assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;

        $command = self::getContainer()->get(SessionGcCommand::class);
        $this->assertInstanceOf(SessionGcCommand::class, $command);
        $this->command = $command;

        // 确保有 sessions 表
        $this->createSessionsTableIfNotExists();

        // 清理测试数据
        $this->connection->executeStatement('DELETE FROM sessions');
    }

    protected function getCommandTester(): CommandTester
    {
        return new CommandTester($this->command);
    }

    private function createSessionsTableIfNotExists(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $sql = '
                CREATE TABLE IF NOT EXISTS sessions (
                    sess_id TEXT PRIMARY KEY,
                    sess_data TEXT NOT NULL,
                    sess_lifetime INTEGER NOT NULL,
                    sess_time INTEGER NOT NULL
                )
            ';
        } else {
            $sql = '
                CREATE TABLE IF NOT EXISTS sessions (
                    sess_id VARBINARY(128) NOT NULL PRIMARY KEY,
                    sess_data BLOB NOT NULL,
                    sess_lifetime INTEGER UNSIGNED NOT NULL,
                    sess_time INTEGER UNSIGNED NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin
            ';
        }

        $this->connection->executeStatement($sql);
    }

    private function insertTestSession(int $lifetime): void
    {
        $this->connection->executeStatement(
            'INSERT INTO sessions (sess_id, sess_data, sess_lifetime, sess_time) VALUES (?, ?, ?, ?)',
            [
                'test_session_'.bin2hex(random_bytes(8)),
                'test_data',
                $lifetime,
                time(),
            ]
        );
    }

    public function testExecuteWithExpiredSessionsShouldCleanupAndShowSuccess(): void
    {
        // 插入过期的 session 记录
        $this->insertTestSession(time() - 3600); // 1小时前过期
        $this->insertTestSession(time() - 7200); // 2小时前过期

        // 插入未过期的 session 记录
        $this->insertTestSession(time() + 3600); // 1小时后过期

        // 执行命令
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([]);

        // 验证退出代码
        $this->assertEquals(Command::SUCCESS, $exitCode);

        // 验证输出
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('成功清理 2 个过期的 session 记录', $output);

        // 验证数据库中只剩1个未过期的记录
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM sessions');
        $this->assertEquals(1, $count);
    }

    public function testExecuteWithNoExpiredSessionsShouldShowInfoMessage(): void
    {
        // 插入未过期的 session 记录
        $this->insertTestSession(time() + 3600); // 1小时后过期
        $this->insertTestSession(time() + 7200); // 2小时后过期

        // 执行命令
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([]);

        // 验证退出代码
        $this->assertEquals(Command::SUCCESS, $exitCode);

        // 验证输出
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('没有发现过期的 session 记录', $output);

        // 验证数据库中仍有2个记录
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM sessions');
        $this->assertEquals(2, $count);
    }

    public function testExecuteWithNoSessionsShouldShowInfoMessage(): void
    {
        // 不插入任何记录

        // 执行命令
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([]);

        // 验证退出代码
        $this->assertEquals(Command::SUCCESS, $exitCode);

        // 验证输出
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('没有发现过期的 session 记录', $output);
    }

    protected function onTearDown(): void
    {
        // 清理测试数据
        $this->connection->executeStatement('DELETE FROM sessions');
    }
}
