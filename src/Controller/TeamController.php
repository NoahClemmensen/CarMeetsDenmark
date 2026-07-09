<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\SaveTeamDTO;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\ToastTypes;
use App\Form\TeamType;
use App\Http\TurboStreamHelper;
use App\Repository\EventRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRepository;
use App\Security\Voter\TeamVoter;
use App\Service\TeamService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/teams')]
class TeamController extends AbstractController
{
    private const string UUID_REQUIREMENT = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly TeamMemberRepository $teamMemberRepository,
    ) {
    }

    #[Route('', name: 'app_team_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('team/index.html.twig', [
            'teams' => $this->teamRepository->findAllActive(),
        ]);
    }

    #[Route('/save/{uuid}', name: 'app_team_save', requirements: ['uuid' => self::UUID_REQUIREMENT], defaults: ['uuid' => null], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function save(
        #[CurrentUser] User $user,
        Request $request,
        TurboStreamHelper $turbo,
        TeamService $teamService,
        TranslatorInterface $translator,
        ?string $uuid = null,
    ): Response {
        $team = null;
        if ($uuid !== null) {
            $team = $this->teamRepository->findOneActiveByUuid($uuid);
            if ($team === null) {
                throw $this->createNotFoundException();
            }
            $this->denyAccessUnlessGranted(TeamVoter::EDIT, $team);
        }

        $dto = $this->hydrateDtoFromTeam($team);
        $form = $this->createForm(TeamType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $bannerFile */
            $bannerFile = $form->get('bannerFile')->getData();
            /** @var UploadedFile|null $pfpFile */
            $pfpFile = $form->get('profilePictureFile')->getData();
            $removeBanner = (bool) $form->get('removeBanner')->getData();
            $removePfp = (bool) $form->get('removeProfilePicture')->getData();

            if ($team === null) {
                $team = $teamService->createTeam($user, $dto, $bannerFile, $pfpFile);
            } else {
                $teamService->updateTeam($team, $dto, $bannerFile, $pfpFile, $removeBanner, $removePfp);
            }

            return $turbo
                ->addRedirect(
                    $this->generateUrl('app_team_show', ['uuid' => $team->getUuid()]),
                    $translator->trans('team.saved'),
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

        return $this->render('team/save.html.twig', [
            'form' => $form->createView(),
            'team' => $team,
        ]);
    }

    #[Route('/{uuid}', name: 'app_team_show', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['GET'])]
    public function show(
        string $uuid,
        EventRepository $eventRepository,
        \App\Service\FollowService $followService,
        ?UserInterface $user = null,
    ): Response {
        $team = $this->teamRepository->findOneActiveByUuid($uuid);
        if ($team === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(TeamVoter::VIEW, $team);

        $viewer = $user instanceof User ? $user : null;
        $viewerMembership = $viewer === null
            ? null
            : $this->teamMemberRepository->findOneFor($team, $viewer);

        $isFollowing = $viewer !== null && $followService->isFollowingTeam($viewer, $team);

        return $this->render('team/show.html.twig', [
            'team' => $team,
            'members' => $team->getMembers(),
            'upcomingEvents' => $eventRepository->findUpcomingForTeam($team, $viewerMembership !== null),
            'pastEvents' => $eventRepository->findPastForTeam($team, $viewerMembership !== null),
            'viewerMembership' => $viewerMembership,
            'isFollowing' => $isFollowing,
        ]);
    }

    #[Route('/{uuid}/members', name: 'app_team_invite', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['POST'])]
    public function invite(
        string $uuid,
        Request $request,
        TeamService $teamService,
        TurboStreamHelper $turbo,
        TranslatorInterface $translator,
    ): Response {
        $team = $this->teamRepository->findOneActiveByUuid($uuid);
        if ($team === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(TeamVoter::INVITE, $team);

        if (!$this->isCsrfTokenValid('team-invite-' . $team->getUuid(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $email = trim((string) $request->request->get('email', ''));
        if ($email === '') {
            return $turbo->addToast($translator->trans('team.invite.email_required'), ToastTypes::error->name)
                ->setCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->makeResponse();
        }

        $result = $teamService->inviteMemberByEmail($team, $email);
        if ($result === null) {
            return $turbo->addToast($translator->trans('team.invite.no_user'), ToastTypes::error->name)
                ->setCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->makeResponse();
        }

        return $turbo->addRedirect(
            $this->generateUrl('app_team_show', ['uuid' => $team->getUuid()]),
            $translator->trans('team.invite.member_added'),
            ToastTypes::success->name,
        )->makeResponse();
    }

    #[Route('/{uuid}/members/{userUuid}/remove', name: 'app_team_remove_member', requirements: ['uuid' => self::UUID_REQUIREMENT, 'userUuid' => self::UUID_REQUIREMENT], methods: ['POST'])]
    public function removeMember(
        string $uuid,
        string $userUuid,
        Request $request,
        TeamService $teamService,
        TurboStreamHelper $turbo,
        TranslatorInterface $translator,
        \App\Repository\UserRepository $userRepository,
    ): Response {
        $team = $this->teamRepository->findOneActiveByUuid($uuid);
        if ($team === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(TeamVoter::REMOVE_MEMBER, $team);

        if (!$this->isCsrfTokenValid('team-remove-' . $team->getUuid(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $userRepository->findOneBy(['uuid' => $userUuid]);
        if ($user === null) {
            throw $this->createNotFoundException();
        }

        try {
            $teamService->removeMember($team, $user);
        } catch (\DomainException $e) {
            return $turbo->addToast($translator->trans($e->getMessage()), ToastTypes::error->name)
                ->setCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->makeResponse();
        }

        return $turbo->addRedirect(
            $this->generateUrl('app_team_show', ['uuid' => $team->getUuid()]),
            $translator->trans('team.member_removed'),
            ToastTypes::success->name,
        )->makeResponse();
    }

    #[Route('/{uuid}/leave', name: 'app_team_leave', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['POST'])]
    public function leave(
        #[CurrentUser] User $user,
        string $uuid,
        Request $request,
        TeamService $teamService,
        TurboStreamHelper $turbo,
        TranslatorInterface $translator,
    ): Response {
        $team = $this->teamRepository->findOneActiveByUuid($uuid);
        if ($team === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(TeamVoter::LEAVE, $team);

        if (!$this->isCsrfTokenValid('team-leave-' . $team->getUuid(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $teamService->leaveTeam($team, $user);
        } catch (\DomainException $e) {
            return $turbo->addToast($translator->trans($e->getMessage()), ToastTypes::error->name)
                ->setCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->makeResponse();
        }

        return $turbo->addRedirect(
            $this->generateUrl('app_team_index'),
            $translator->trans('team.left'),
            ToastTypes::success->name,
        )->makeResponse();
    }

    #[Route('/{uuid}/delete-confirm', name: 'app_team_delete_confirm', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['GET'])]
    public function deleteConfirm(string $uuid): Response
    {
        $team = $this->teamRepository->findOneActiveByUuid($uuid);
        if ($team === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(TeamVoter::DELETE, $team);

        return $this->render('team/_delete_modal.html.twig', ['team' => $team]);
    }

    #[Route('/{uuid}/delete', name: 'app_team_delete', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['POST'])]
    public function delete(
        string $uuid,
        Request $request,
        TeamService $teamService,
        TurboStreamHelper $turbo,
        TranslatorInterface $translator,
    ): Response {
        $team = $this->teamRepository->findOneActiveByUuid($uuid);
        if ($team === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(TeamVoter::DELETE, $team);

        if (!$this->isCsrfTokenValid('team-delete-' . $team->getUuid(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $teamService->deleteTeam($team);

        return $turbo->addRedirect(
            $this->generateUrl('app_team_index'),
            $translator->trans('team.deleted'),
            ToastTypes::success->name,
        )->makeResponse();
    }

    #[Route('/{uuid}/invite-modal', name: 'app_team_invite_modal', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['GET'])]
    public function inviteModal(string $uuid): Response
    {
        $team = $this->teamRepository->findOneActiveByUuid($uuid);
        if ($team === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(TeamVoter::INVITE, $team);

        return $this->render('team/_invite_modal.html.twig', ['team' => $team]);
    }

    private function hydrateDtoFromTeam(?Team $team): SaveTeamDTO
    {
        $dto = new SaveTeamDTO();
        if ($team !== null) {
            $dto->name = $team->getName() ?? '';
            $dto->description = $team->getDescription() ?? '';
        }
        return $dto;
    }
}
