<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Http\TurboStreamHelper;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use App\Service\FollowService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/follow')]
#[IsGranted('ROLE_USER')]
class FollowController extends AbstractController
{
    private const string UUID_REQUIREMENT = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    #[Route('/user/{userUuid}', name: 'app_follow_user_toggle', requirements: ['userUuid' => self::UUID_REQUIREMENT], methods: ['POST'])]
    public function toggleUser(
        #[CurrentUser] User $current,
        string $userUuid,
        Request $request,
        UserRepository $userRepository,
        FollowService $followService,
        TurboStreamHelper $turbo,
    ): Response {
        if (!$this->isCsrfTokenValid('follow-' . $userUuid, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $target = $userRepository->findOneBy(['uuid' => $userUuid]);
        if ($target === null) {
            throw $this->createNotFoundException();
        }
        if ($target->getId() === $current->getId()) {
            throw $this->createAccessDeniedException('You cannot follow yourself.');
        }

        if ($followService->isFollowingUser($current, $target)) {
            $followService->unfollowUser($current, $target);
            $isFollowing = false;
        } else {
            $followService->followUser($current, $target);
            $isFollowing = true;
        }

        return $turbo
            ->replace('follow-' . $userUuid, 'follow/_button.html.twig', [
                'action' => $this->generateUrl('app_follow_user_toggle', ['userUuid' => $userUuid]),
                'targetUuid' => $userUuid,
                'isFollowing' => $isFollowing,
            ])
            ->makeResponse();
    }

    #[Route('/team/{teamUuid}', name: 'app_follow_team_toggle', requirements: ['teamUuid' => self::UUID_REQUIREMENT], methods: ['POST'])]
    public function toggleTeam(
        #[CurrentUser] User $current,
        string $teamUuid,
        Request $request,
        TeamRepository $teamRepository,
        FollowService $followService,
        TurboStreamHelper $turbo,
    ): Response {
        if (!$this->isCsrfTokenValid('follow-' . $teamUuid, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $target = $teamRepository->findOneActiveByUuid($teamUuid);
        if ($target === null) {
            throw $this->createNotFoundException();
        }

        if ($followService->isFollowingTeam($current, $target)) {
            $followService->unfollowTeam($current, $target);
            $isFollowing = false;
        } else {
            $followService->followTeam($current, $target);
            $isFollowing = true;
        }

        return $turbo
            ->replace('follow-' . $teamUuid, 'follow/_button.html.twig', [
                'action' => $this->generateUrl('app_follow_team_toggle', ['teamUuid' => $teamUuid]),
                'targetUuid' => $teamUuid,
                'isFollowing' => $isFollowing,
            ])
            ->makeResponse();
    }
}
