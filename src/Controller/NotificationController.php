<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\ToastTypes;
use App\Http\TurboStreamHelper;
use App\Repository\NotificationRepository;
use App\Security\Voter\NotificationVoter;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    private const string UUID_REQUIREMENT = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
    private const int DROPDOWN_LIMIT = 50;

    #[Route('/dropdown', name: 'app_notification_dropdown', methods: ['GET'])]
    public function dropdown(
        #[CurrentUser] User $user,
        NotificationService $service,
        NotificationRepository $repository,
    ): Response {
        $service->markAllRead($user);
        $notifications = $repository->findRecentForUser($user, self::DROPDOWN_LIMIT);

        return $this->render('notification/_dropdown.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    #[Route('/unread-count', name: 'app_notification_unread_count', methods: ['GET'])]
    public function unreadCount(
        #[CurrentUser] User $user,
        NotificationService $service,
    ): Response {
        $count = $service->unreadCount($user);
        return new Response((string) $count);
    }

    #[Route('/{uuid}/delete', name: 'app_notification_delete', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['POST'])]
    public function delete(
        string $uuid,
        Request $request,
        NotificationRepository $repository,
        NotificationService $service,
        TurboStreamHelper $turbo,
    ): Response {
        $notification = $repository->findOneByUuid($uuid);
        if ($notification === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(NotificationVoter::DELETE, $notification);

        if (!$this->isCsrfTokenValid('notification-delete-' . $notification->getUuid(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $service->delete($notification);

        return $turbo
            ->remove('notification-row-' . $uuid)
            ->makeResponse();
    }

    #[Route('/clear-all', name: 'app_notification_clear_all', methods: ['POST'])]
    public function clearAll(
        #[CurrentUser] User $user,
        Request $request,
        NotificationService $service,
        TurboStreamHelper $turbo,
        TranslatorInterface $translator,
    ): Response {
        if (!$this->isCsrfTokenValid('notification-clear-all', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $service->clearAll($user);

        return $turbo
            ->replace('notification-dropdown-frame', 'notification/_dropdown.html.twig', ['notifications' => []])
            ->addToast($translator->trans('notification.cleared'), ToastTypes::success->name)
            ->makeResponse();
    }
}
