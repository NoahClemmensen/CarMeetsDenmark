<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Enum\EventRepeatFrequency;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class EventLifecycleService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventRepository $eventRepository,
        private readonly NotificationService $notificationService,
        private readonly FileUploader $fileUploader,
        #[Autowire('%event_banners_directory%')]
        private readonly string $bannerDirectory,
    ) {
    }

    /**
     * One processing pass: notify newly-started events, then archive events
     * that are over (rolling repeating ones forward).
     *
     * @return array{notified:int, archived:int, repeated:int}
     */
    public function processDue(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $notified = 0;
        $archived = 0;
        $repeated = 0;

        foreach ($this->eventRepository->findPendingStartNotification($now) as $event) {
            $this->notificationService->notifyEventStarted($event);
            $event->setStartNotifiedAt($now->getTimestamp());
            ++$notified;
        }

        foreach ($this->eventRepository->findArchivable($now) as $event) {
            $event->setArchived(true);
            ++$archived;

            if ($event->getRepeatFrequency() !== null && $event->getHost() !== null) {
                $this->em->persist($this->rollForward($event));
                ++$repeated;
            }
        }

        $this->em->flush();

        return ['notified' => $notified, 'archived' => $archived, 'repeated' => $repeated];
    }

    /**
     * Builds the next occurrence of a repeating event, shifted forward by
     * frequency x amount. The host is guaranteed non-null by the caller.
     */
    public function rollForward(Event $event): Event
    {
        $interval = $this->intervalFor($event);

        /** @var \App\Entity\User $host */
        $host = $event->getHost();
        $next = new Event($host);
        $next->setName((string) $event->getName());
        $next->setDescription($event->getDescription());
        $next->setLocation($event->getLocation());
        $next->setTimezone($event->getTimezone());
        $next->setPrivate($event->isPrivate());
        $next->setRepeatFrequency($event->getRepeatFrequency());
        $next->setRepeatAmount($event->getRepeatAmount());
        $next->setTeam($event->getTeam());

        $next->setStartDate(\DateTime::createFromInterface($event->getStartDate())->add($interval));
        if ($event->getEndDate() !== null) {
            $next->setEndDate(\DateTime::createFromInterface($event->getEndDate())->add($interval));
        }

        if ($event->getImageFilename() !== null) {
            $next->setImageFilename($this->fileUploader->copy($this->bannerDirectory, $event->getImageFilename()));
        }

        return $next;
    }

    private function intervalFor(Event $event): \DateInterval
    {
        $amount = max(1, $event->getRepeatAmount() ?? 1);
        $code = match ($event->getRepeatFrequency()) {
            EventRepeatFrequency::Daily => 'D',
            EventRepeatFrequency::Weekly => 'W',
            EventRepeatFrequency::Monthly => 'M',
            EventRepeatFrequency::Yearly => 'Y',
            null => 'D',
        };

        return new \DateInterval(sprintf('P%d%s', $amount, $code));
    }
}
