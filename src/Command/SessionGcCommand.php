<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: self::NAME,
    description: '清理过期的 session 记录',
)]
class SessionGcCommand extends Command
{
    private const NAME = 'doctrine:session:gc';

    private string $table = 'sessions';

    private string $lifetimeCol = 'sess_lifetime';

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.doctrine_session_connection')] private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('清理过期 Session');

        try {
            // 删除已过期的会话记录
            $sql = "DELETE FROM {$this->table} WHERE {$this->lifetimeCol} < :time";
            $count = $this->connection->executeStatement($sql, [
                'time' => time(),
            ]);

            if ($count > 0) {
                $io->success(sprintf('成功清理 %d 个过期的 session 记录', $count));
            } else {
                $io->info('没有发现过期的 session 记录');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('清理失败: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
