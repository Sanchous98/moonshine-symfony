<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class LoginController extends MoonShineController
{
    #[Route('/login', name: 'moonshine.login', methods: ['GET'])]
    public function index(): Response
    {
        $page = $this->core->getConfig()->getPage('login', 'App\MoonShine\Pages\LoginPage');

        return $this->response($page->render());
    }

    #[Route('/login', name: 'moonshine.authenticate', methods: ['POST'])]
    public function authenticate(): Response
    {
        return $this->redirect($this->core->getRouter()->getEndpoints()->home());
    }
}
