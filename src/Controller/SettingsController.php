<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ChangePasswordDTO;
use App\Dto\UserSetupDTO;
use App\Entity\User;
use App\Enum\ToastTypes;
use App\Enum\UserRole;
use App\Form\ChangePasswordType;
use App\Form\UserSetupType;
use App\Http\TurboStreamHelper;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'app_settings')]
    public function index(
        Request $request,
        #[CurrentUser] User $user,
        UserService $userService,
        TurboStreamHelper $turboStreamHelper,
        Security $security,
        TranslatorInterface $translator,
    ): Response {
        $dto = $this->hydrateDtoFromUser($user);

        $form = $this->createForm(UserSetupType::class, $dto, [
            'select_language' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->get('avatarFile')->getData();
            $removeAvatar = (bool) $form->get('removeAvatar')->getData();
            $userService->updateFromUserSetup($user, $dto, $avatarFile, $removeAvatar);

            // The creator role can change here. Symfony 6.3+ logs out users when
            // their roles change between requests, so re-authenticate immediately
            // to keep the session token in sync.
            $security->login($user, 'form_login', 'main');

            return $turboStreamHelper
                ->addRedirect(
                    $this->generateUrl('app_settings'),
                    $translator->trans('settings.saved'),
                    ToastTypes::success->name,
                )
                ->makeResponse();
        }

        // Surface a too-long description (the column is VARCHAR(255)) as an
        // alert banner, like the email-verification message, instead of
        // letting it fail silently at the database.
        if ($form->isSubmitted()) {
            foreach ($form->get('description')->getErrors() as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('settings/index.html.twig', [
            'form' => $form->createView(),
            'roleSelected' => $dto->role !== null,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * GET: render the change-password form into the shared modal. Fetched by
     * the `modal-trigger` controller and rendered as a Turbo Stream.
     */
    #[Route('/settings/password', name: 'app_settings_password_form', methods: ['GET'])]
    public function passwordForm(): Response
    {
        $form = $this->createForm(ChangePasswordType::class, new ChangePasswordDTO());

        return $this->render('settings/_change_password_modal.html.twig', [
            'passwordForm' => $form->createView(),
        ]);
    }

    /**
     * POST: handle the change-password submission. Responds with Turbo Streams
     * so the page never reloads: on success the modal closes with a toast; on
     * failure the modal body is re-rendered in place with the validation errors.
     */
    #[Route('/settings/password', name: 'app_settings_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        #[CurrentUser] User $user,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        Security $security,
        TurboStreamHelper $turboStreamHelper,
        TranslatorInterface $translator,
    ): Response {
        $passwordDto = new ChangePasswordDTO();
        $form = $this->createForm(ChangePasswordType::class, $passwordDto);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $turboStreamHelper
                ->setCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->addStream($this->renderView('settings/_change_password_modal.html.twig', [
                    'passwordForm' => $form->createView(),
                ]))
                ->makeResponse();
        }

        $user->setPassword($passwordHasher->hashPassword($user, $passwordDto->newPassword));
        $em->flush();

        // Changing the password hash invalidates the session token on the next
        // request, so re-authenticate to keep the user logged in (same reason
        // the profile save above re-logs in after a role change).
        $security->login($user, 'form_login', 'main');

        return $turboStreamHelper
            ->hideModal()
            ->addToast($translator->trans('settings.password_updated'), ToastTypes::success->name)
            ->makeResponse();
    }

    private function hydrateDtoFromUser(User $user): UserSetupDTO
    {
        $dto = new UserSetupDTO();
        $dto->name = $user->getName() ?? '';
        $dto->description = $user->getDescription();
        $dto->instagramUrl = $user->getInstagramUrl();
        $dto->youtubeUrl = $user->getYoutubeUrl();
        $dto->facebookUrl = $user->getFacebookUrl();
        $dto->websiteUrl = $user->getWebsiteUrl();
        $dto->timezone = $user->getTimezone();
        $dto->language = $user->getLanguage();
        $dto->role = $this->currentProfileRole($user);

        return $dto;
    }

    private function currentProfileRole(User $user): ?UserRole
    {
        foreach ($user->getRoles() as $role) {
            $enum = UserRole::tryFrom($role);
            if ($enum !== null) {
                return $enum;
            }
        }

        return null;
    }
}
