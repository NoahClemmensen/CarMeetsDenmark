<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ActivityPingRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:activity-ping:purge-expired',
    description: 'Deletes activity pings whose 3-hour lifetime has elapsed.',
)]
final class PurgeExpiredPingsCommand extends Command
{
    public function __construct(
        private readonly ActivityPingRepository $pingRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $deleted = $this->pingRepository->deleteExpired();

        $io->success(sprintf('Purged %d expired activity ping(s).', $deleted));

        return Command::SUCCESS;
    }
}
