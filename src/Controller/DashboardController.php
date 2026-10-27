<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class DashboardController extends MoonShineController
{
    #[Route('/', name: 'moonshine.index', methods: ['GET'])]
    public function index(): Response
    {
        $page = $this->core->getConfig()->getPage('dashboard', 'App\MoonShine\Pages\Dashboard');

        return $this->response($page->render());
    }
}
