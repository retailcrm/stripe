<?php

namespace App\Controller;

use App\Entity\Account;
use App\Entity\Integration;
use App\Service\CRMConnectManager;
use App\Service\ResponseManager;
use App\Service\StripeManager;
use App\Service\StripeWebhookManager;
use App\Service\ValidateModelManager;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AccountController extends AbstractController
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var StripeWebhookManager
     */
    private $webhookManager;

    /**
     * @var CRMConnectManager
     */
    private $connectManager;

    /** @var ResponseManager */
    private $responseManager;

    /** @var ValidateModelManager */
    private $validateModelManager;

    /** @var Serializer */
    private $serializer;

    public function __construct(
        EntityManagerInterface $em,
        TranslatorInterface $translator,
        StripeManager $stripeManager,
        StripeWebhookManager $webhookManager,
        ValidateModelManager $validateModelManager,
        ResponseManager $responseManager,
        CRMConnectManager $connectManager,
        SerializerInterface $serializer
    ) {
        $this->em = $em;
        $this->translator = $translator;
        $this->stripeManager = $stripeManager;
        $this->webhookManager = $webhookManager;
        $this->connectManager = $connectManager;
        $this->validateModelManager = $validateModelManager;
        $this->responseManager = $responseManager;
        $this->serializer = $serializer;
    }

    public function add(string $slug, Request $request, ValidatorInterface $validator): JsonResponse
    {
        if (!Uuid::isValid($slug)) {
            return $this->responseManager->notFoundResponse('error.integration_not_exists');
        }

        /** @var Integration|null $integration */
        $integration = $this->em->getRepository(Integration::class)->find($slug);

        if (null === $integration) {
            return $this->responseManager->notFoundResponse('error.integration_not_exists');
        }

        try {
            /** @var Account $addAccount */
            $addAccount = $this->serializer->deserialize(
                $request->get('account'),
                Account::class,
                'json',
                DeserializationContext::create()->setGroups(['set'])
            );
        } catch (NotEncodableValueException $e) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.invalid_request_data')],
                400
            );
        }

        $account = new Account();
        $account
            ->setIntegration($integration)
            ->setPublicKey($addAccount->getPublicKey())
            ->setSecretKey($addAccount->getSecretKey())
            ->setTest(false !== mb_strpos($addAccount->getPublicKey(), 'test'))
            ->setDeactivatedAt(null)
        ;

        try {
            $stripeAccount = $this->stripeManager->getAccountInfo($account);

            $account->setAccountId($stripeAccount['id']);
            $accountName = $stripeAccount['settings']['dashboard']['display_name'];
            $account->setName($accountName ? mb_substr($accountName, 0, 255) : 'Account: ' . $stripeAccount->id);
        } catch (\Exception $e) {
            return $this->responseManager->apiExceptionJsonResponse($e);
        }

        $errors = $validator->validate($account);
        if ($errors->count()) {
            $this->addFlash('error', (string) $errors->get(0)->getMessage());

            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => (string) $errors->get(0)->getMessage()],
                400
            );
        }

        $this->em->persist($account);
        $this->em->flush();

        try {
            $this->webhookManager->subscribe($account);
        } catch (\Exception $e) {
            return $this->responseManager->apiExceptionJsonResponse($e);
        }

        $account = $this->serializer->toArray(
            $account,
            SerializationContext::create()->setGroups(['get'])
        );

        return $this->responseManager->jsonResponse(
            ['success' => true, 'account' => $account],
            200
        );
    }

    /**
     * @param $id
     */
    public function account($id): JsonResponse
    {
        /** @var Account|null $account */
        $account = $this->em->getRepository(Account::class)->find($id);
        if ($notFoundResponse = $this->checkAccount($account)) {
            return $notFoundResponse;
        }
        $account = $this->serializer->toArray(
            $account,
            SerializationContext::create()->setGroups(['get'])
        );

        return $this->responseManager->jsonResponse(
            ['success' => true, 'account' => $account],
            200
        );
    }

    /**
     * @param $id
     */
    public function edit($id, Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $this->em->getRepository(Account::class)->find($id);
        if ($notFoundResponse = $this->checkAccount($account)) {
            return $notFoundResponse;
        }

        try {
            /** @var Account $updatedAccount */
            $updatedAccount = $this->serializer->deserialize(
                $request->get('account'),
                Account::class,
                'json',
                DeserializationContext::create()->setGroups(['set'])
            );
        } catch (NotEncodableValueException $e) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.invalid_request_data')],
                400
            );
        }

        $account
            ->setApproveManually($updatedAccount->isApproveManually());

        if ($invalidResponse = $this->validateModelManager->validateWithFields($account)) {
            return $invalidResponse;
        }

        if (!$this->connectManager->sendModuleInCRM($account->getIntegration())) {
            return $this->responseManager->crmNotSaveResponse();
        }

        $this->em->persist($account);
        $this->em->flush();

        $account = $this->serializer->toArray(
            $account,
            SerializationContext::create()->setGroups(['get'])
        );

        return $this->responseManager->jsonResponse([
            'success' => true,
            'account' => $account,
        ], 200);
    }

    /**
     * @param $id
     */
    public function sync($id): JsonResponse
    {
        /** @var Shop $shop */
        $account = $this->em->getRepository(Account::class)->find($id);
        if ($notFoundResponse = $this->checkAccount($account)) {
            return $notFoundResponse;
        }

        try {
            $stripeAccount = $this->stripeManager->getAccountInfo($account);

            $account->setAccountId($stripeAccount['id']);
            $accountName = $stripeAccount['settings']['dashboard']['display_name'];
            $account->setName($accountName ? mb_substr($accountName, 0, 255) : 'Account: ' . $stripeAccount->id);
        } catch (\Exception $e) {
            return $this->responseManager->apiExceptionJsonResponse($e);
        }

        $this->em->flush();

        try {
            $this->webhookManager->unsubscribe($account);
            $this->webhookManager->subscribe($account);
        } catch (\Exception $e) {
            return $this->responseManager->apiExceptionJsonResponse($e);
        }

        $account = $this->serializer->toArray(
            $account,
            SerializationContext::create()->setGroups(['get'])
        );

        return $this->responseManager->jsonResponse([
            'success' => true,
            'account' => $account,
        ], 200);
    }

    /**
     * @param $id
     */
    public function deactivate($id, LoggerInterface $logger): JsonResponse
    {
        /** @var Account $account */
        $account = $this->em->getRepository(Account::class)->find($id);
        if (null === $account) {
            return $this->responseManager->notFoundResponse();
        }

        if ($account->isDeactivated()) {
            return $this->responseManager->invalidJsonResponse(
                $this->translator->trans('flash.account_already_deactivated'),
                400
            );
        }

        $this->em->beginTransaction();

        try {
            $account->setDeactivatedAt(new \DateTime());
            $this->webhookManager->unsubscribe($account);

            if (!$this->connectManager->sendModuleInCRM($account->getIntegration())) {
                return $this->responseManager->crmNotSaveResponse();
            }

            $this->em->flush();
            $this->em->commit();
        } catch (\Exception $exception) {
            $this->em->rollback();
            $logger->error($exception->getMessage(), ['exception' => $exception]);

            return $this->responseManager->jsonResponse([
                'success' => false,
                'errorMsg' => $this->translator->trans('flash.account_error_deactivated'),
            ], 400);
        }

        $account = $this->serializer->toArray(
            $account,
            SerializationContext::create()->setGroups(['get'])
        );

        return $this->responseManager->jsonResponse([
            'success' => true,
            'account' => $account,
        ], 200);
    }

    /**
     * @return JsonResponse|null
     */
    private function checkAccount(?Account $account)
    {
        if (null === $account) {
            return $this->responseManager->notFoundResponse();
        }
        if ($account->isDeactivated()) {
            return $this->responseManager->notFoundResponse();
        }

        return null;
    }
}
