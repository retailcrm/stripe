<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ValidateModelManager
{
    /** @var TranslatorInterface */
    private $translator;

    /** @var ValidatorInterface */
    private $validator;

    /** @var ResponseManager */
    private $responseManager;

    public function __construct(
        TranslatorInterface $translator,
        ValidatorInterface $validator,
        ResponseManager $responseManager
    ) {
        $this->translator = $translator;
        $this->validator = $validator;
        $this->responseManager = $responseManager;
    }

    /**
     * @param $model
     * @param $groups
     */
    public function validate($model, $groups = null): ?JsonResponse
    {
        $errors = $this->validator->validate($model, null, $groups);
        if ($errors->count() > 0) {
            return $this->responseManager->invalidJsonResponse(
                $this->translator->trans('api.error.invalid_request_data'),
                $errors
            );
        }

        return null;
    }

    /**
     * @param $model
     * @param $groups
     */
    public function validateWithFields($model, $groups = null): ?JsonResponse
    {
        $errors = $this->validator->validate($model, null, $groups);
        if ($errors->count() > 0) {
            return $this->responseManager->invalidNamedFieldsJsonResponse(
                $this->translator->trans('api.error.invalid_request_data'),
                $errors
            );
        }

        return null;
    }
}
