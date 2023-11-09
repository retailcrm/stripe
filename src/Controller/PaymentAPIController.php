<?php

namespace App\Controller;

use App\Entity\Account;
use App\Entity\Integration;
use App\Entity\Model\RefundRequest;
use App\Entity\Payment;
use App\Entity\PaymentAPIModel\ApprovePaymentModel;
use App\Entity\PaymentAPIModel\CancelPaymentModel;
use App\Entity\PaymentAPIModel\CreatePayment;
use App\Entity\PaymentAPIModel\CreatePaymentModel;
use App\Entity\PaymentAPIModel\PaymentModel;
use App\Entity\PaymentAPIModel\RefundPayment;
use App\Entity\PaymentAPIModel\RefundPaymentModel;
use App\Entity\PaymentAPIModel\StatusModel;
use App\Service\ResponseManager;
use App\Service\StripeManager;
use App\Service\UrlService;
use App\Service\ValidateModelManager;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PaymentAPIController extends AbstractController implements LoggableController
{
    /** @var StripeManager */
    private $stripeManager;

    /** @var TranslatorInterface */
    private $translator;

    /** @var ValidatorInterface */
    private $validator;

    /** @var ResponseManager */
    private $responseManager;

    /** @var ValidateModelManager */
    private $validateModelManager;

    public function __construct(
        StripeManager $stripeManager,
        TranslatorInterface $translator,
        ValidateModelManager $validateModelManager,
        ResponseManager $responseManager
    ) {
        $this->stripeManager = $stripeManager;
        $this->translator = $translator;
        $this->validateModelManager = $validateModelManager;
        $this->responseManager = $responseManager;
    }

    /**
     * @throws \Exception
     */
    public function create(UrlService $urlService, Request $request): JsonResponse
    {
        $serializer = $this->get('serializer');

        $createPaymentModel = new CreatePaymentModel();
        $createPaymentModel->setClientId($request->get('clientId'));

        if (empty($request->get('create'))) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.invalid_request_data')],
                400
            );
        }

        try {
            /** @var CreatePayment $createPayment */
            $createPayment = $serializer->deserialize($request->get('create'), CreatePayment::class, 'json');
        } catch (NotEncodableValueException $e) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.invalid_request_data')],
                400
            );
        }
        $createPaymentModel->setCreate($createPayment);
        if ($invalidResponse = $this->validateModelManager->validate($createPaymentModel, ['api'])) {
            return $invalidResponse;
        }

        /** @var EntityManagerInterface $em */
        $em = $this->getDoctrine()->getManager();

        /** @var Integration $integration */
        $integration = $em->getRepository(Integration::class)->find($createPaymentModel->getClientId());
        if (!$integration->isEnabled()) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.integration_disabled')],
                400
            );
        }

        /** @var Account $account */
        $account = $em->getRepository(Account::class)->find($createPayment->getShopId());

        if ($account->getIntegration()->getSlug() !== $integration->getSlug()) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.alien_account')],
                400
            );
        }

        if ($account->isDeactivated()) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.deactivated_account')],
                400
            );
        }

        try {
            $payment = $this->stripeManager->createPayment(
                $account,
                $createPayment,
                Uuid::uuid4()->toString()
            );

            $url = $urlService->create($account, $createPayment);
            $payment->setUrl($url);

            $em->flush();
        } catch (\Exception $e) {
            return $this->responseManager->apiExceptionJsonResponse($e);
        }

        return $this->responseManager->jsonResponse([
            'success' => true,
            'result' => [
                'invoiceUrl' => $this->generateUrl('short_url', ['slug' => $url->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'paymentId' => $payment->getPaymentUuid(),
                'cancellable' => true,
            ],
        ], 200);
    }

    /**
     * @throws \Exception
     */
    public function cancel(Request $request): JsonResponse
    {
        $serializer = $this->get('serializer');

        $cancelPaymentModel = new CancelPaymentModel();
        $cancelPaymentModel->setClientId($request->get('clientId'));

        if (empty($request->get('cancel'))) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.invalid_request_data')],
                400
            );
        }

        try {
            /** @var PaymentModel $cancelPayment */
            $cancelPayment = $serializer->deserialize($request->get('cancel'), PaymentModel::class, 'json');
        } catch (NotEncodableValueException $e) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.invalid_request_data')],
                400
            );
        }
        $cancelPaymentModel->setCancel($cancelPayment);
        if ($invalidResponse = $this->validateModelManager->validate($cancelPaymentModel, ['api'])) {
            return $invalidResponse;
        }

        $em = $this->getDoctrine()->getManager();
        /** @var Integration $integration */
        $integration = $em->getRepository(Integration::class)->find($cancelPaymentModel->getClientId());
        if (!$integration->isEnabled()) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.integration_disabled')],
                400
            );
        }
        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findByPaymentUuid($cancelPayment->getPaymentId());

        if ($payment->getAccount()->isDeactivated()) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.deactivated_account')],
                400
            );
        }

        if ($payment->getAccount()->getIntegration()->getId() !== $integration->getId()) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.alien_account')],
                400
            );
        }

        $payment->getUrl()->setCanceledAt(new \DateTime());
        $em->flush();

        if (StripeManager::STATUS_PAYMENT_WAITING_CAPTURE === $payment->getStatus()) {
            try {
                $this->stripeManager->cancelPayment($payment);
            } catch (\Exception $e) {
                return $this->responseManager->apiExceptionJsonResponse($e);
            }
        } else {
            $payment
                ->setCancelOnWaitingCapture(true)
                ->setCancellationDetails($this->translator->trans('api.cancelation_detail'));
            $em->flush();
        }

        return $this->responseManager->jsonResponse(['success' => true], 200);
    }

    public function approve(Request $request): JsonResponse
    {
        $serializer = $this->get('serializer');

        $approvePaymentModel = new ApprovePaymentModel();
        $approvePaymentModel->setClientId($request->get('clientId'));

        if (empty($request->get('approve'))) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.invalid_request_data')],
                400
            );
        }

        try {
            /** @var PaymentModel $approvePayment */
            $approvePayment = $serializer->deserialize($request->get('approve'), PaymentModel::class, 'json');
        } catch (NotEncodableValueException $e) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.invalid_request_data')],
                400
            );
        }
        $approvePaymentModel->setApprove($approvePayment);
        if ($invalidResponse = $this->validateModelManager->validate($approvePaymentModel, ['api'])) {
            return $invalidResponse;
        }

        $em = $this->getDoctrine()->getManager();
        /** @var Integration $integration */
        $integration = $em->getRepository(Integration::class)->find($approvePaymentModel->getClientId());
        if (!$integration->isEnabled()) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.integration_disabled')],
                400
            );
        }
        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findByPaymentUuid($approvePayment->getPaymentId());

        if ($payment->getAccount()->isDeactivated()) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.deactivated_account')],
                400
            );
        }

        if ($error = $this->checkApproveErrors($payment, $integration, $this->translator)) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $error],
                400
            );
        }

        try {
            $this->stripeManager->capturePayment($payment);
        } catch (ApiException $e) {
            return $this->responseManager->apiExceptionJsonResponse($e);
        }

        return $this->responseManager->jsonResponse(['success' => true], 200);
    }

    public function status(Request $request): JsonResponse
    {
        $statusModel = new StatusModel();
        $statusModel
            ->setPaymentId($request->get('paymentId'))
            ->setClientId($request->get('clientId'))
        ;
        if ($invalidResponse = $this->validateModelManager->validate($statusModel, ['api'])) {
            return $invalidResponse;
        }

        $em = $this->getDoctrine()->getManager();
        /** @var Integration $integration */
        $integration = $em->getRepository(Integration::class)->find($statusModel->getClientId());
        if (!$integration->isEnabled()) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.integration_disabled')],
                400
            );
        }
        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findByPaymentUuid($statusModel->getPaymentId());

        if ($payment->getAccount()->getIntegration()->getId() !== $integration->getId()) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.alien_account')],
                400
            );
        }

        if ($payment->getAccount()->isDeactivated()) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.deactivated_account')],
                400
            );
        }

        try {
            $this->stripeManager->updatePayment($payment);
        } catch (ApiException $e) {
            return $this->responseManager->apiExceptionJsonResponse($e);
        }

        return $this->responseManager->jsonResponse([
            'success' => true,
            'result' => [
                'status' => $payment->getStatus(),
                'amount' => $payment->getAmount(),
                'cancellationDetails' => $payment->getCancellationDetails(),
                'expiredAt' => $payment->getExpiresAt() ? $payment->getExpiresAt()->format('Y-m-d H:i:s') : null,
                'paidAt' => $payment->getCapturedAt() ? $payment->getCapturedAt()->format('Y-m-d H:i:s') : null,
                'paymentId' => $payment->getPaymentUuid()->toString(),
                'invoiceUrl' => $this->generateUrl('short_url', ['slug' => $payment->getUrl()->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'refundable' => $payment->isRefundable(),
            ],
        ], 200);
    }

    public function refund(Request $request): JsonResponse
    {
        $serializer = $this->get('serializer');

        $refundPaymentModel = new RefundPaymentModel();
        $refundPaymentModel->setClientId($request->get('clientId'));

        if (empty($request->get('refund'))) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.invalid_request_data')],
                400
            );
        }

        try {
            /** @var RefundPayment $refundPayment */
            $refundPayment = $serializer->deserialize($request->get('refund'), RefundPayment::class, 'json');
        } catch (NotEncodableValueException $e) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.invalid_request_data')],
                400
            );
        }
        $refundPaymentModel->setRefund($refundPayment);
        if ($invalidResponse = $this->validateModelManager->validate($refundPaymentModel, ['api'])) {
            return $invalidResponse;
        }

        $em = $this->getDoctrine()->getManager();
        /** @var Integration $integration */
        $integration = $em->getRepository(Integration::class)->find($refundPaymentModel->getClientId());
        if (!$integration->isEnabled()) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.integration_disabled')],
                400
            );
        }
        /** @var Payment $payment */
        $payment = $em->getRepository(Payment::class)->findByPaymentUuid($refundPayment->getPaymentId());

        if ($payment->getAccount()->isDeactivated()) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.deactivated_account')],
                400
            );
        }

        if ($error = $this->checkRefundErrors($payment, $integration, $this->translator)) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $error],
                400
            );
        }

        try {
            $amount = $refundPayment->getAmount() ?? $payment->getAmount();
            $refund = $this->stripeManager->refundPayment($payment, $amount);
        } catch (ApiException $e) {
            return $this->responseManager->apiExceptionJsonResponse($e);
        }

        $refundRequest = new RefundRequest();
        $refundRequest
            ->setId($refund->getId())
            ->setStatus($refund->getStatus())
            ->setAmount($refund->getAmount())
            ->setComment($refund->getComment());

        return $this->responseManager->jsonResponse([
            'success' => true,
            'result' => $serializer->normalize($refundRequest, 'array'),
        ], 200);
    }

    /**
     * @return bool|string
     */
    private function checkApproveErrors(Payment $payment, Integration $integration, TranslatorInterface $translator)
    {
        if (StripeManager::STATUS_PAYMENT_WAITING_CAPTURE !== $payment->getStatus()) {
            return $translator->trans('api.error.approve_impossible_by_status');
        }
        if (!$payment->getAccount()->isApproveManually()) {
            return $translator->trans('api.error.approve_manually_deny');
        }
        if ($payment->getAccount()->getIntegration()->getId() !== $integration->getId()) {
            return $translator->trans('api.error.alien_account');
        }

        return false;
    }

    /**
     * @return bool|string
     */
    private function checkRefundErrors(Payment $payment, Integration $integration, TranslatorInterface $translator)
    {
        if (StripeManager::STATUS_PAYMENT_SUCCEEDED !== $payment->getStatus()) {
            return $translator->trans('api.error.refund_impossible_by_status');
        }
        if ($payment->getAccount()->getIntegration()->getId() !== $integration->getId()) {
            return $translator->trans('api.error.alien_account');
        }

        return false;
    }
}
