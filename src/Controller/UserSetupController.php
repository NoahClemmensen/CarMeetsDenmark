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

#[IsGranted("IS_AUTHENTICATED_FULLY")]
#[Route('/setup')]
class UserSetupController extends AbstractController
{
    #[Route('', name: 'web_setup')]
    public function index(
        Request             $request,
        #[CurrentUser] User $user,
        UserService         $userService,
        TurboStreamHelper   $turboStreamHelper,
        Security            $security,
    ): Response
    {
        $session = $request->getSession();

        if ($user->getName()) {
            $stored = $session->remove('web_setup_target');
            $target = WebTargetPath::validate(is_string($stored) ? $stored : null)
                ?? $this->generateUrl('web_home');

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
                ?? $this->generateUrl('web_home');

            return $turboStreamHelper
                ->addRedirect(
                    $target,
                    'Successfully completed your profile',
                    ToastTypes::success->name)
                ->makeResponse();
        }

        return $this->render('web/setup/index.html.twig', [
            'form' => $form->createView(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
