<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\EventLifecycleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:event:process-due',
    description: 'Notifies started events, archives finished ones, and rolls repeating events forward.',
)]
final class ProcessDueEventsCommand extends Command
{
    public function __construct(private readonly EventLifecycleService $lifecycle)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $result = $this->lifecycle->processDue();

        $io->success(sprintf(
            'Done — notified: %d, archived: %d, repeated: %d.',
            $result['notified'],
            $result['archived'],
            $result['repeated'],
        ));

        return Command::SUCCESS;
    }
}
