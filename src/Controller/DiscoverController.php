<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/discover")]
class DiscoverController extends AbstractController
{
    #[Route("", name: "app_discover")]
    public function index(): Response
    {
        return $this->render("web/discover/discover.html.twig");
    }
}
