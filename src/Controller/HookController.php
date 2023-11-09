<?php

namespace App\Controller;

use App\Entity\Account;
use App\Entity\Payment;
use App\Entity\StripeNotification;
use App\Exception\RetailcrmApiException;
use App\Service\CRMConnectManager;
use App\Service\StripeManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RetailCrm\Exception\CurlException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class HookController extends AbstractController implements LoggableController
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
     * @var StripeManager
     */
    private $stripeManager;

    /**
     * @var CRMConnectManager
     */
    private $crmConnectManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        EntityManagerInterface $em,
        TranslatorInterface $translator,
        StripeManager $stripeManager,
        CRMConnectManager $CRMConnectManager,
        LoggerInterface $networkLogger
    ) {
        $this->em = $em;
        $this->translator = $translator;
        $this->stripeManager = $stripeManager;
        $this->crmConnectManager = $CRMConnectManager;
        $this->logger = $networkLogger;
    }

    public function hooks($id, Request $request, CRMConnectManager $CRMConnectManager)
    {
        /** @var Account $account */
        $account = $this->em->getRepository(Account::class)->find($id);
        if (null === $account) {
            return new Response('Account not found', 404);
        }

        if ($account->isDeactivated()) {
            return new Response('Account is deactivated', 404);
        }

        $payload = $request->getContent();
        $event = null;

        try {
            $event = \Stripe\Event::constructFrom(
                json_decode($payload, true)
            );
        } catch (\UnexpectedValueException $e) {
            return new Response('Invalid payload', 400);
        }

        // Handle the event
        switch ($event->type) {
            case 'payment_intent.amount_capturable_updated':

                $response = $this->handlePaymentIntentAmountCapturableUpdated($request, $event);
                break;

            case 'payment_intent.canceled':

                $response = $this->handlePaymentIntentCanceled($request, $event);
                break;

            case 'payment_intent.succeeded':

                $response = $this->handlePaymentIntentSucceeded($request, $event);
                break;

            case 'charge.refunded':

                $response = $this->handleChargeRefunded($request, $event);
                break;

            default:
                $this->logger->info('Unexpected event type');

                return new Response('Unexpected event type', 400);
        }

        return $response;
    }

    private function handlePaymentIntentAmountCapturableUpdated($request, $event): Response
    {
        $paymentIntent = $event->data->object;

        if (!isset($paymentIntent['metadata']['invoiceUuid']) || empty($paymentIntent['metadata']['invoiceUuid'])) {
            $this->logger->info('Payment intent amount capturable updating. Empty invoiceUuid');

            return new Response();
        }

        /** @var Payment|null $payment */
        $payment = $this->em->getRepository(Payment::class)->findOneBy([
            'invoiceUuid' => $paymentIntent['metadata']['invoiceUuid'],
        ]);
        if (!$payment) {
            $this->logger->info('Payment intent amount capturable updating. Someone else\'s payment');

            return new Response('someone else\'s payment');
        }

        if (!$this->createNotification($request, $payment, $event)) {
            $this->logger->info(sprintf('Can not create notification for payment %s', $payment->getId()));

            return new Response();
        }
        $this->em->flush();

        $this->updatePayment($payment, $paymentIntent);

        try {
            if ($payment->isCancelOnWaitingCapture()) {
                $this->stripeManager->cancelPayment($payment);

                return new Response();
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Payment intent amount capturable updating.' .
                'Error in canceling payment. Code: %s, message: %s', $e->getCode(), $e->getMessage()));

            return new Response($e->getMessage(), 500);
        }

        if (!$this->crmConnectManager->checkInvoice($payment)) {
            $this->logger->info(sprintf('Payment intent amount capturable updating.' .
                'There is not invoice for payment %s', $payment->getId()));

            $this->stripeManager->cancelPayment($payment);
            $payment->setCancellationDetails($this->translator->trans('api.check_order_cancel'));

            $this->em->flush();

            return new Response();
        }

        try {
            if (!$payment->getAccount()->isApproveManually()) {
                $this->stripeManager->capturePayment($payment);

                return new Response();
            }
        } catch (\Exception $e) {
            return new Response($e->getMessage(), 500);
        }

        try {
            $this->crmConnectManager->updateInvoice($payment);
        } catch (CurlException | RetailcrmApiException $e) {
            $this->logger->error(sprintf('Payment intent amount capturable updating.' .
            'Error in updating invoice for payment %s', $payment->getId()));

            return new Response($e->getMessage(), 500);
        }

        $this->em->flush();

        return new Response();
    }

    public function handlePaymentIntentCanceled($request, $event): Response
    {
        $paymentIntent = $event->data->object;

        if (!isset($paymentIntent['metadata']['invoiceUuid']) || empty($paymentIntent['metadata']['invoiceUuid'])) {
            $this->logger->info('Payment intent canceling. Empty invoiceUuid');

            return new Response();
        }

        /** @var Payment|null $payment */
        $payment = $this->em->getRepository(Payment::class)->findOneBy([
            'invoiceUuid' => $paymentIntent['metadata']['invoiceUuid'],
        ]);
        if (!$payment) {
            return new Response('someone else\'s payment');
        }

        if (!$this->createNotification($request, $payment, $event)) {
            $this->logger->info('Payment intent canceling. Empty invoiceUuid');

            return new Response();
        }
        $this->em->flush();

        $this->updatePayment($payment, $paymentIntent);

        try {
            $this->crmConnectManager->updateInvoice($payment, true);
        } catch (CurlException | RetailcrmApiException $e) {
            $this->logger->error(sprintf('Payment intent canceling.' .
                'Error in updating invoice for payment %s', $payment->getId()));

            return new Response($e->getMessage(), 500);
        }

        $this->em->flush();

        return new Response();
    }

    private function handlePaymentIntentSucceeded($request, $event): Response
    {
        $paymentIntent = $event->data->object;

        if (!isset($paymentIntent['metadata']['invoiceUuid']) || empty($paymentIntent['metadata']['invoiceUuid'])) {
            $this->logger->info('Empty invoiceUuid');

            return new Response();
        }

        /** @var Payment|null $payment */
        $payment = $this->em->getRepository(Payment::class)->findOneBy([
            'invoiceUuid' => $paymentIntent['metadata']['invoiceUuid'],
        ]);
        if (!$payment) {
            return new Response('someone else\'s payment');
        }

        if (!$this->createNotification($request, $payment, $event)) {
            $this->logger->info(sprintf('Can not create notification for payment %s', $payment->getId()));

            return new Response();
        }
        $this->em->flush();

        $this->updatePayment($payment, $paymentIntent);

        try {
            $this->crmConnectManager->updateInvoice($payment);
        } catch (CurlException | RetailcrmApiException $e) {
            $this->logger->error(sprintf('Payment intent succeeded.' .
                'Error in updating invoice for payment %s', $payment->getId()));

            return new Response($e->getMessage(), 500);
        }

        $this->em->flush();

        return new Response();
    }

    private function handleChargeRefunded($request, $event): Response
    {
        $charge = $event->data->object;

        /** @var Payment|null $payment */
        $payment = $this->em->getRepository(Payment::class)->findOneBy([
            'intentId' => $charge['payment_intent'],
        ]);
        if (!$payment) {
            return new Response('Charge refunded. someone else\'s payment');
        }

        if (StripeManager::STATUS_PAYMENT_CANCELED === $payment->getStatus()) {
            $this->logger->info('Charge refunding. Payment status: not canceled');

            return new Response();
        }

        $paymentIntent = $this->stripeManager->getPaymentInfo($payment);

        if (StripeManager::STATUS_PAYMENT_CANCELED === $paymentIntent['status']) {
            $this->logger->info('Charge refunding. Payment intent status: not canceled');

            return new Response();
        }

        if (!$this->createNotification($request, $payment, $event)) {
            $this->logger->info(sprintf('Charge refunding.' .
                'Can not create notification for payment %s', $payment->getId()));

            return new Response();
        }

        $fullCharge = isset($charge['refunds']['data']) && count($charge['refunds']['data'])
            ? $charge
            : $this->stripeManager->getCharge($payment, $charge['id'], true);

        if (isset($fullCharge['refunds']['data']) && is_array($fullCharge['refunds']['data'])) {
            $refundResponse = current($fullCharge['refunds']['data']);
        } elseif (is_array($fullCharge['refunds'])) {
            $refundResponse = current($fullCharge['refunds']);
        } else {
            $this->logger->info(sprintf('Charge refunding.' .
                'There is no refunds in charge of payment %s', $payment->getId()));

            return new Response();
        }

        if (empty($refundResponse['id'])) {
            $this->logger->info('Charge refunding. There is no refund id');

            return new Response('there is no refund id');
        }

        $refund = $this->stripeManager->createRefundIfNotExists($refundResponse, $payment, true);
        $this->em->flush();

        try {
            $this->crmConnectManager->updateInvoice($payment, true, $refund);
        } catch (CurlException | RetailcrmApiException $e) {
            $this->logger->error(sprintf('Charge refunding.' .
                'Error in updating invoice for payment %s', $payment->getId()));

            return new Response($e->getMessage(), 500);
        }

        $this->em->flush();

        return new Response();
    }

    private function createNotification(Request $request, $payment, $event): ?StripeNotification
    {
        if (null === $payment) {
            $this->logger->info('Creating notification. Payment is null');

            return null;
        }

        $integration = $payment->getAccount()->getIntegration();
        if (null === $integration || !$integration->isEnabled()) {
            $this->logger->info(sprintf('Creating notification.' .
                'Integration for payment %s is null or not enabled', $payment->getId()));

            return null;
        }

        $stripeNotification = new StripeNotification();
        $stripeNotification
            ->setResponse($request->getContent())
            ->setPayment($payment)
            ->setEvent($event->type)
        ;
        $this->em->persist($stripeNotification);
        $this->logger->info(sprintf('Created notification %s', $stripeNotification->getId()));

        return $stripeNotification;
    }

    private function updatePayment(Payment $payment, $paymentIntent)
    {
        $charge = [];
        if ($paymentIntent['latest_charge']) {
            $charge = $this->stripeManager->getCharge($payment, $paymentIntent['latest_charge']);
        }
        $cancellationDetailsReason = $payment->getCancellationDetails();

        if (isset($paymentIntent['cancellation_reason']) && !empty($paymentIntent['cancellation_reason'])) {
            $cancellationDetailsReason = $this->translator->trans(
                'api.cancellation_reasons.' . $paymentIntent['cancellation_reason']
            );
        }

        $capturedAt = null;
        if (isset($charge['created'])) {
            $capturedAt = new \DateTime();
            $capturedAt->setTimestamp($charge['created']);
        }

        $expiresAt = null;
        if (StripeManager::STATUS_PAYMENT_WAITING_CAPTURE === $paymentIntent['status']
            && null === $payment->getExpiresAt()
        ) {
            $expiresAt = new \DateTime('+7 DAYS');
        }

        $payment
            ->setIntentId($paymentIntent['id'])
            ->setAmount($paymentIntent['amount'] / 100)
            ->setStatus($paymentIntent['status'])
            ->setPaid(isset($charge['paid']) ? $charge['paid'] : false)
            ->setExpiresAt($expiresAt)
            ->setCapturedAt($capturedAt)
            ->setCancellationDetails($cancellationDetailsReason)
            ->setRefundable(isset($charge['refunded']) ? !$charge['refunded'] : false)
        ;

        $this->logger->info(sprintf('Update payment: %s', $payment->getId()));
    }
}
