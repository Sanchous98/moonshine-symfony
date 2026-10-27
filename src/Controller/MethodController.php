<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Controller;

use Leeto\FastAttributes\Attributes;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\Core\DependencyInjection\RequestContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\ToastType;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[AsController]
#[Route('/method/{pageUri}/{resourceUri?}', name: 'moonshine.method', methods: ['GET', 'POST'])]
final class MethodController extends MoonShineController
{
    public function __invoke(
        CrudRequestContract $crudRequest,
        RequestContract $request,
        ?LoggerInterface $logger = null,
    ): Response {
        $toast = [
            'type' => 'info',
            'message' => (string) $request->getScalar('message', ''),
        ];

        try {
            $method = $request->getScalar('method');

            if (! \is_string($method) || $method === '') {
                throw new RuntimeException('Method name is required');
            }

            $page = $crudRequest->getPage();
            $target = method_exists($page, $method) ? $page : $crudRequest->getResource();

            if ($target === null
                || ! method_exists($target, $method)
                || ! Attributes::for($target, AsyncMethod::class)->method($method)->first() instanceof AsyncMethod
            ) {
                throw new RuntimeException("$method does not exist");
            }

            $result = $target->{$method}($crudRequest);

            $sessionToast = $request->getSession('toast');

            if (\is_array($sessionToast)) {
                $toast = [
                    'type' => \is_string($sessionToast['type'] ?? null) ? $sessionToast['type'] : $toast['type'],
                    'message' => \is_string($sessionToast['message'] ?? null) ? $sessionToast['message'] : $toast['message'],
                ];
            }
        } catch (Throwable $e) {
            $logger?->error($e->getMessage(), ['exception' => $e]);
            $result = $e;
        }

        if ($result instanceof SymfonyJsonResponse
            || $result instanceof BinaryFileResponse
            || $result instanceof StreamedResponse
        ) {
            return $result;
        }

        if (\is_string($result)) {
            return new Response($result);
        }

        $redirect = $result instanceof RedirectResponse ? $result->getTargetUrl() : null;

        return JsonResponse::make()
            ->setStatusCode($result instanceof Throwable ? Response::HTTP_INTERNAL_SERVER_ERROR : Response::HTTP_OK)
            ->when(
                $redirect !== null,
                static fn (JsonResponse $response): JsonResponse => $response->redirect((string) $redirect),
            )
            ->toast(
                $result instanceof Throwable ? $result->getMessage() : $toast['message'],
                $result instanceof Throwable ? ToastType::ERROR : (ToastType::tryFrom($toast['type']) ?? ToastType::INFO),
            );
    }
}
