<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\UserSetupDTO;
use App\Entity\User;
use App\Enum\ToastTypes;
use App\Enum\UserRole;
use App\Form\UserSetupType;
use App\Http\TurboStreamHelper;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
    ): Response {
        $dto = $this->hydrateDtoFromUser($user);

        $form = $this->createForm(UserSetupType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userService->updateFromUserSetup($user, $dto);

            // The creator role can change here. Symfony 6.3+ logs out users when
            // their roles change between requests, so re-authenticate immediately
            // to keep the session token in sync.
            $security->login($user, 'form_login', 'main');

            return $turboStreamHelper
                ->addRedirect(
                    $this->generateUrl('app_settings'),
                    'Settings saved.',
                    ToastTypes::success->name,
                )
                ->makeResponse();
        }

        // Surface a too-long description (the column is VARCHAR(255)) as an
        // alert banner — like the email-verification message — instead of
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
