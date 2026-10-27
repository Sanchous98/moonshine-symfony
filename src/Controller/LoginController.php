<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Controller;

use LogicException;
use MoonShine\Symfony\Security\Auth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[AsController]
final class LoginController extends MoonShineController
{
    #[Route('/login', name: 'moonshine.login', methods: ['GET'])]
    public function index(Request $request, Auth $auth, ?AuthenticationUtils $authenticationUtils = null): Response
    {
        $home = $this->core->getRouter()->getEndpoints()->home();

        if ($auth->isEnabled() && $auth->isGranted()) {
            return $this->redirect($home);
        }

        $error = $authenticationUtils?->getLastAuthenticationError();

        $session = $request->hasSession() ? $request->getSession() : null;

        if ($error !== null && $session instanceof FlashBagAwareSessionInterface) {
            $message = $this->core->getTranslator()->get('moonshine::auth.failed');

            $session->getFlashBag()->add('alert', \is_string($message) ? $message : $error->getMessageKey());
        }

        $page = $this->core->getConfig()->getPage('login', 'App\MoonShine\Pages\LoginPage');

        return $this->response($page->render());
    }

    /**
     * Handled by the "form_login" authenticator of the firewall when authentication is enabled
     */
    #[Route('/login', name: 'moonshine.authenticate', methods: ['POST'])]
    public function authenticate(Auth $auth): Response
    {
        if ($auth->isEnabled()) {
            throw new LogicException('Authentication is enabled but the "moonshine" firewall does not handle the login form.');
        }

        return $this->redirect($this->core->getRouter()->getEndpoints()->home());
    }

    /**
     * Intercepted by the logout listener of the firewall
     */
    #[Route('/logout', name: 'moonshine.logout', methods: ['GET', 'POST', 'DELETE'])]
    public function logout(): Response
    {
        return $this->redirect($this->core->getRouter()->to('login'));
    }
}
