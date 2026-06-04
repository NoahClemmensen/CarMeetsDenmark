<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ProcessDueEvents;
use App\Service\EventLifecycleService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessDueEventsHandler
{
    public function __construct(private readonly EventLifecycleService $lifecycle)
    {
    }

    public function __invoke(ProcessDueEvents $message): void
    {
        $this->lifecycle->processDue();
    }
}
