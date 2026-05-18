<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\SaveEventDTO;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\ParticipationStatus;
use App\Enum\ToastTypes;
use App\Form\EventType;
use App\Http\TurboStreamHelper;
use App\Repository\EventReactionRepository;
use App\Repository\EventRepository;
use App\Repository\ParticipationRepository;
use App\Security\Voter\EventVoter;
use App\Service\EventService;
use App\Service\ParticipationService;
use App\Service\ReactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/event')]
class EventController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
    ) {
    }

    #[Route('', name: 'app_event_index', methods: ['GET'])]
    public function index(#[CurrentUser] ?User $user = null): Response
    {
        $events = $this->eventRepository->findVisibleTo($user);

        return $this->render('event/index.html.twig', [
            'events' => $events,
        ]);
    }


    private const string UUID_REQUIREMENT = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    #[Route('/{uuid}', name: 'app_event_show', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['GET'])]
    public function show(
        string $uuid,
        ParticipationService $participationService,
        ParticipationRepository $participationRepository,
        EventReactionRepository $eventReactions,
        \App\Repository\PostRepository $postRepository,
        \App\Repository\PostReactionRepository $postReactions,
        #[CurrentUser] ?User $user = null,
    ): Response {
        $event = $this->eventRepository->findOneBy(['uuid' => $uuid, 'isDeleted' => false]);
        if ($event === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(EventVoter::VIEW, $event);

        $feedPosts = $postRepository->findFeedPage($event, 20);
        $hypedPostIds = $user
            ? $postReactions->findPostIdsHypedBy($user, array_map(fn($p) => $p->getId(), $feedPosts))
            : [];

        $lastUnpinned = null;
        foreach (array_reverse($feedPosts) as $p) {
            if (!$p->isPinned()) { $lastUnpinned = $p; break; }
        }

        return $this->render('event/show.html.twig', [
            'event' => $event,
            'currentUserStatus' => $user ? $participationService->getStatus($event, $user) : null,
            'participationCounts' => $participationRepository->countsByStatusForEvent($event),
            'eventReactions' => $eventReactions,
            'feedPosts' => $feedPosts,
            'hypedPostIds' => $hypedPostIds,
            'nextCursor' => $lastUnpinned?->getCreatedAt(),
        ]);
    }

    #[Route('/{uuid}/participate/{status}', name: 'app_event_participate', requirements: ['uuid' => self::UUID_REQUIREMENT, 'status' => 'going|maybe|declined'], methods: ['POST'])]
    public function participate(
        #[CurrentUser] User $user,
        string $uuid,
        string $status,
        Request $request,
        ParticipationService $participationService,
        ParticipationRepository $participationRepository,
        TurboStreamHelper $turbo,
    ): Response {
        $event = $this->eventRepository->findOneBy(['uuid' => $uuid, 'isDeleted' => false]);
        if ($event === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(EventVoter::INTERACT, $event);

        if (!$this->isCsrfTokenValid('participate-' . $event->getUuid(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $newStatus = ParticipationStatus::from($status);
        $participationService->setStatus($event, $user, $newStatus);

        return $turbo
            ->replace('event-participation-' . $event->getUuid(), 'event/_participation_widget.html.twig', [
                'event' => $event,
                'currentUserStatus' => $newStatus,
            ])
            ->replace('event-counts-' . $event->getUuid(), 'event/_participation_counts.html.twig', [
                'event' => $event,
                'participationCounts' => $participationRepository->countsByStatusForEvent($event),
            ])
            ->replace('event-feed-composer-' . $event->getUuid(), 'event/feed/_composer.html.twig', [
                'event' => $event,
            ])
            ->makeResponse();
    }

    #[Route('/{uuid}/hype', name: 'app_event_hype', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['POST'])]
    #[\Symfony\Component\Security\Http\Attribute\IsGranted('ROLE_USER')]
    public function hype(
        #[CurrentUser] User $user,
        string $uuid,
        Request $request,
        ReactionService $reactionService,
        TurboStreamHelper $turbo,
        EventReactionRepository $eventReactions,
    ): Response {
        $event = $this->eventRepository->findOneBy(['uuid' => $uuid, 'isDeleted' => false]);
        if ($event === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(EventVoter::VIEW, $event);

        if (!$this->isCsrfTokenValid('hype-event-' . $event->getUuid(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $reactionService->toggleEventHype($event, $user);
        $isHyped = $eventReactions->isHypedBy($event, $user);

        return $turbo
            ->replace('event-hype-' . $event->getUuid(), '_hype_button.html.twig', [
                'count' => $event->getHypeCount(),
                'isHyped' => $isHyped,
                'formAction' => $this->generateUrl('app_event_hype', ['uuid' => $event->getUuid()]),
                'csrfTokenName' => 'hype-event-' . $event->getUuid(),
                'targetId' => 'event-hype-' . $event->getUuid(),
            ])
            ->makeResponse();
    }

    #[Route('/{uuid}/share', name: 'app_event_share', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['GET'])]
    public function share(string $uuid): Response
    {
        $event = $this->eventRepository->findOneBy(['uuid' => $uuid, 'isDeleted' => false]);
        if ($event === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(EventVoter::VIEW, $event);

        $shareUrl = $this->generateUrl(
            'app_event_show',
            ['uuid' => $event->getUuid()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return $this->render('event/_share_modal.html.twig', [
            'event' => $event,
            'shareUrl' => $shareUrl,
        ]);
    }

    #[Route('/{uuid}/delete-confirm', name: 'app_event_delete_confirm', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['GET'])]
    public function deleteConfirm(string $uuid): Response
    {
        $event = $this->eventRepository->findOneBy(['uuid' => $uuid, 'isDeleted' => false]);
        if ($event === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(EventVoter::DELETE, $event);

        return $this->render('event/_delete_modal.html.twig', [
            'event' => $event,
        ]);
    }

    #[Route('/{uuid}/delete', name: 'app_event_delete', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['POST'])]
    public function delete(
        string $uuid,
        Request $request,
        EventService $eventService,
        TurboStreamHelper $turbo,
    ): Response {
        $event = $this->eventRepository->findOneBy(['uuid' => $uuid, 'isDeleted' => false]);
        if ($event === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(EventVoter::DELETE, $event);

        if (!$this->isCsrfTokenValid('delete-event-' . $event->getUuid(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $eventService->softDelete($event);

        return $turbo
            ->addRedirect(
                $this->generateUrl('app_event_index'),
                'Event deleted.',
                ToastTypes::success->name,
            )
            ->makeResponse();
    }

    #[Route('/save/{uuid?}', name: 'app_event_save')]
    public function save(
        #[CurrentUser] User $user,
        Request $request,
        TurboStreamHelper $turbo,
        EventService $eventService,
        ?string $uuid = null,
    ): Response {
        $event = null;
        if ($uuid !== null) {
            $event = $this->eventRepository->findOneBy(['uuid' => $uuid, 'isDeleted' => false]);
            $this->denyAccessUnlessGranted(EventVoter::SAVE, $event);
        }

        $dto = $this->hydrateDtoFromEvent($event);

        $form = $this->createForm(EventType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $bannerFile */
            $bannerFile = $form->get('imageFile')->getData();
            $removeBanner = (bool) $form->get('removeImage')->getData();
            $event = $eventService->saveFromDto($event, $dto, $bannerFile, $removeBanner, $user);

            return $turbo
                ->addRedirect(
                    $this->generateUrl('app_event_show', ['uuid' => $event->getUuid()]),
                    'Event saved.',
                    ToastTypes::success->name,
                )
                ->makeResponse();
        }

        if ($form->isSubmitted()) {
            $message = 'Invalid form submission.';
            foreach ($form->getErrors(true) as $error) {
                $message = $error->getMessage();
                break;
            }

            return $turbo
                ->addToast($message, ToastTypes::error->name)
                ->setCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->makeResponse();
        }

        return $this->render('event/save.html.twig', [
            'form' => $form->createView(),
            'event' => $event,
        ]);
    }

    private function hydrateDtoFromEvent(?Event $event): SaveEventDTO
    {
        $dto = new SaveEventDTO();
        if ($event !== null) {
            $dto->name = $event->getName() ?? '';
            $dto->description = $event->getDescription();
            $dto->startDate = $event->getStartDate();
            $dto->endDate = $event->getEndDate();
            $dto->location = $event->getLocation() ?? '';
            $dto->timezone = $event->getTimezone();
            $dto->repeatFrequency = $event->getRepeatFrequency();
            $dto->repeatAmount = $event->getRepeatAmount();
            $dto->private = $event->isPrivate();
        }

        return $dto;
    }
}
