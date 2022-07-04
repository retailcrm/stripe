<?php

namespace App\Controller;

use App\Dto\Register\ConfigResponse;
use App\Dto\Register\RegisterRequest;
use App\Dto\Register\RegisterResponse;
use App\Service\RegisterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Class RegisterController
 */
class RegisterController extends AbstractController implements LoggableController
{
    public function config(RegisterService $registerService): JsonResponse
    {
        $configResponse = new ConfigResponse();

        try {
            $configResponse->success = true;
            $configResponse->scopes = $registerService->getScopesList();
            $configResponse->registerUrl = $registerService->getRegisterUrl();
        } catch (\Throwable $e) {
            $configResponse->success = false;
            $configResponse->errorMsg = $e->getMessage();
        }

        return new JsonResponse($configResponse);
    }

    public function register(
        RegisterService $registerService,
        RegisterRequest $request
    ): JsonResponse {
        $registryResponse = new RegisterResponse();
        $registryResponse->success = false;

        try {
            $registryResponse->accountUrl = $registerService->register($request);
            $registryResponse->success = true;

            $responseStatusCode = 201;
        } catch (UnauthorizedHttpException $e) {
            $responseStatusCode = 401;
            $registryResponse->errorMsg = 'Invalid token';
        } catch (\Throwable $e) {
            $responseStatusCode = 400;
            $registryResponse->errorMsg = $e->getMessage();
        }

        return new JsonResponse($registryResponse, $responseStatusCode);
    }
}
