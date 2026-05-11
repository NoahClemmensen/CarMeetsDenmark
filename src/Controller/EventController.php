<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\SaveEventDTO;
use App\Entity\User;
use App\Enum\ToastTypes;
use App\Form\EventType;
use App\Http\TurboStreamHelper;
use App\Repository\EventRepository;
use App\Security\Voter\EventVoter;
use App\Service\EventService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/event')]
class EventController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
    ) {
    }

    #[Route('', name: 'app_event_index', methods: ['GET'])]
    public function index(): Response
    {
        $events = $this->eventRepository->findBy(['isDeleted' => false], ['id' => 'DESC']);

        return $this->render('web/event/index.html.twig', [
            'events' => $events,
        ]);
    }


    #[Route('/{uuid}', name: 'app_event_show', requirements: ['uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'], methods: ['GET'])]
    public function show(string $uuid): Response
    {
        $event = $this->eventRepository->findOneBy(['uuid' => $uuid, 'isDeleted' => false]);
        $this->denyAccessUnlessGranted(EventVoter::VIEW, $event);

        return $this->render('web/event/show.html.twig', [
            'event' => $event,
        ]);
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

        $dto = new SaveEventDTO();
        if ($event !== null) {
            $dto->name = $event->getName() ?? '';
            $dto->description = $event->getDescription();
        }

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

        return $this->render('web/event/save.html.twig', [
            'form' => $form->createView(),
            'event' => $event,
        ]);
    }
}
