<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Controller;

use MoonShine\Crud\Contracts\Notifications\MoonShineNotificationContract;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class NotificationController extends MoonShineController
{
    #[Route('/notifications/read-all', name: 'moonshine.notifications.readAll', methods: ['POST'])]
    public function readAll(Request $request, MoonShineNotificationContract $notifications): Response
    {
        $notifications->readAll();

        return $this->back($request);
    }

    #[Route('/notifications/{id}/read', name: 'moonshine.notifications.read', methods: ['POST'])]
    public function read(Request $request, string $id, MoonShineNotificationContract $notifications): Response
    {
        $notifications->markAsRead($id);

        return $this->back($request);
    }

    private function back(Request $request): Response
    {
        $referer = $request->headers->get('referer');

        // only redirect back inside the application
        if (\is_string($referer) && str_starts_with($referer, $request->getSchemeAndHttpHost() . '/')) {
            return $this->redirect($referer);
        }

        return $this->redirect($this->core->getRouter()->getEndpoints()->home());
    }
}
