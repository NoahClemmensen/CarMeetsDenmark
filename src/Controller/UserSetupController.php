<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\UserSetupDTO;
use App\Entity\User;
use App\Enum\ToastTypes;
use App\Form\UserSetupType;
use App\Http\TurboStreamHelper;
use App\Security\WebTargetPath;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * One-shot profile-completion form, shown the first time a user logs in.
 *
 * Reached because {@see \App\EventSubscriber\WebRouteSubscriber} redirects
 * any authenticated user without a `name` to `/setup`. After successful
 * submission, the user is sent back to the URI they originally requested
 * (stashed in session as `web_setup_target`), or to /discover if no safe
 * target is on file.
 *
 * If the user already has a name, GET /setup short-circuits and redirects
 * away — there's no "edit profile" UX here; that lives in /settings.
 */
#[IsGranted("IS_AUTHENTICATED_FULLY")]
#[Route('/setup')]
class UserSetupController extends AbstractController
{
    #[Route('', name: 'app_setup')]
    public function index(
        Request             $request,
        #[CurrentUser] User $user,
        UserService         $userService,
        TurboStreamHelper   $turboStreamHelper,
        Security            $security,
    ): Response {
        $session = $request->getSession();

        if ($user->getName()) {
            $stored = $session->remove('web_setup_target');
            $target = WebTargetPath::validate(is_string($stored) ? $stored : null)
                ?? $this->generateUrl('app_discover');

            return $this->redirect($target);
        }

        $dto = new UserSetupDTO();

        $form = $this->createForm(UserSetupType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userService->updateFromUserSetup($user, $dto);

            // Symfony 6.3+ logs out users when roles change between requests.
            // Re-authenticate immediately so the new token reflects the updated roles.
            $security->login($user, 'form_login', 'main');

            $stored = $session->remove('web_setup_target');
            $target = WebTargetPath::validate(is_string($stored) ? $stored : null)
                ?? $this->generateUrl('app_discover');

            return $turboStreamHelper
                ->addRedirect(
                    $target,
                    'Successfully completed your profile',
                    ToastTypes::success->name
                )
                ->makeResponse();
        }

        return $this->render('setup/index.html.twig', [
            'form' => $form->createView(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
