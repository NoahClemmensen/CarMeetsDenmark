<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/home")]
class HomeController extends AbstractController
{
    #[Route("", name: "web_home")]
    #[Route("", name: "app_home")]
    public function index(): Response
    {
        return $this->render("web/home/home.html.twig");
    }
}
