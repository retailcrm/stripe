<?php

namespace App\Service;

use App\Utils\StringHelper;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\RateLimitException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Contracts\Translation\TranslatorInterface;

class ResponseManager
{
    /** @var TranslatorInterface */
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function jsonResponse(array $data, $statusCode, $headers = []): JsonResponse
    {
        return new JsonResponse(json_encode($data), $statusCode, $headers, true);
    }

    public function invalidJsonResponse(string $errorMsg, $errors = null, $statusCode = 400): JsonResponse
    {
        $responseErrors = $this->getErrorsArray($errors, false);

        $data = json_encode([
            'success' => false,
            'errorMsg' => $errorMsg,
            'errors' => $responseErrors,
        ]);

        return new JsonResponse($data, $statusCode, [], true);
    }

    public function invalidNamedFieldsJsonResponse(string $errorMsg, $errors = null, $statusCode = 400): JsonResponse
    {
        $responseErrors = $this->getErrorsArray($errors, true);

        $data = json_encode([
            'success' => false,
            'errorMsg' => $errorMsg,
            'errors' => $responseErrors,
        ]);

        return new JsonResponse($data, $statusCode, [], true);
    }

    public function notFoundResponse($customMsg = 'error.entity_not_exists'): JsonResponse
    {
        return $this->jsonResponse(
            [
                'success' => false,
                'errorMsg' => $this->translator->trans($customMsg, [], 'validators'),
            ],
            404
        );
    }

    public function crmNotSaveResponse(): JsonResponse
    {
        return $this->jsonResponse([
            'success' => false,
            'errorMsg' => $this->translator->trans('flash.crm_not_save', [], 'messages'),
        ], 400);
    }

    public function apiExceptionJsonResponse(\Exception $e, int $statusCode = 400): JsonResponse
    {
        $data = ['success' => false];
        if ($e instanceof CardException) {
            $data['errorMsg'] = $e->getError()->message;
            $statusCode = $e->getHttpStatus();
        } elseif ($e instanceof RateLimitException) {
            $data['errorMsg'] = $e->getError()->message;
            $statusCode = $e->getHttpStatus();
        } elseif ($e instanceof InvalidRequestException) {
            $data['errorMsg'] = $e->getError()->message;
            $statusCode = $e->getHttpStatus();
        } elseif ($e instanceof AuthenticationException) {
            $data['errorMsg'] = $e->getError()->message;
            $statusCode = $e->getHttpStatus();
        } elseif ($e instanceof ApiConnectionException) {
            $data['errorMsg'] = $e->getError()->message;
            $statusCode = $e->getHttpStatus();
        } elseif ($e instanceof ApiErrorException) {
            $data['errorMsg'] = $e->getError()->message;
            $statusCode = $e->getHttpStatus();
        } else {
            $data['errorMsg'] = 'Error: ' . $e->getMessage();
        }

        return $this->jsonResponse($data, $statusCode);
    }

    private function getErrorsArray($errors, $keyIsProperty = false): array
    {
        $responseErrors = [];

        if (null !== $errors) {
            /** @var ConstraintViolation $error */
            foreach ($errors as $error) {
                if (!($error instanceof ConstraintViolation)) {
                    continue;
                }
                $property = $error->getPropertyPath();
                if ($keyIsProperty) { // В качестве ключей - названия полей.
                    $errorMessage = StringHelper::mbUcFirst($error->getMessage());
                    if ($property && $fieldName = $this->getFieldNameByPath($property)) {
                        $responseErrors[$fieldName] = $errorMessage;
                    } else {
                        $responseErrors[] = $errorMessage;
                    }
                } else { // Обычный массив.
                    $errorMessage = $error->getMessage();
                    if ($property) {
                        $fieldName = $this->getMessageByPath($property);
                        $errorMessage = $fieldName ? $fieldName . ': ' . $errorMessage : $errorMessage;
                    }
                    $responseErrors[] = StringHelper::mbUcFirst($errorMessage);
                }
            }
        }

        return $responseErrors;
    }

    private function getMessageByPath(string $path): string
    {
        $pathName = 'fields.' . $this->getFieldNameByPath($path);
        $translate = $this->translator->trans($pathName, [], 'entities');
        if ($translate === $pathName) {
            return '';
        }

        return $translate;
    }

    private function getFieldNameByPath(string $path): string
    {
        $pathName = $path;
        $paths = explode('.', $path);
        if (count($paths) > 1) {
            $pathName = $paths[count($paths) - 1];
        }

        return $pathName;
    }
}
