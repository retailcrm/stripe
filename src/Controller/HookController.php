<?php

namespace App\Controller;

use App\Entity\Account;
use App\Entity\Payment;
use App\Entity\StripeNotification;
use App\Exception\RetailcrmApiException;
use App\Service\CRMConnectManager;
use App\Service\StripeManager;
use Doctrine\ORM\EntityManagerInterface;
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

    public function __construct(
        EntityManagerInterface $em,
        TranslatorInterface $translator,
        StripeManager $stripeManager,
        CRMConnectManager $CRMConnectManager
    ) {
        $this->em = $em;
        $this->translator = $translator;
        $this->stripeManager = $stripeManager;
        $this->crmConnectManager = $CRMConnectManager;
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

                $this->handlePaymentIntentAmountCapturableUpdated($request, $event);
                break;

            case 'payment_intent.canceled':

                $this->handlePaymentIntentCanceled($request, $event);
                break;

            case 'payment_intent.succeeded':

                $this->handlePaymentIntentSucceeded($request, $event);
                break;

            case 'charge.refunded':

                $this->handleChargeRefunded($request, $event);
                break;

            default:

                return new Response('Unexpected event type', 400);
        }

        return new Response();
    }

    private function handlePaymentIntentAmountCapturableUpdated($request, $event): Response
    {
        $paymentIntent = $event->data->object;

        if (!isset($paymentIntent['metadata']['invoiceUuid']) || empty($paymentIntent['metadata']['invoiceUuid'])) {
            return new Response();
        }

        /** @var Payment|null $payment */
        $payment = $this->em->getRepository(Payment::class)->find($paymentIntent['id']);
        if (!$payment) {
            return new Response('someone else\'s payment');
        }

        if (!$this->createNotification($request, $payment, $event)) {
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
            return new Response($e->getMessage(), 500);
        }

        if (!$this->crmConnectManager->checkInvoice($payment)) {
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
            return new Response('', 500);
        }

        $this->em->flush();

        return new Response();
    }

    public function handlePaymentIntentCanceled($request, $event): Response
    {
        $paymentIntent = $event->data->object;

        if (!isset($paymentIntent['metadata']['invoiceUuid']) || empty($paymentIntent['metadata']['invoiceUuid'])) {
            return new Response();
        }

        /** @var Payment|null $payment */
        $payment = $this->em->getRepository(Payment::class)->find($paymentIntent['id']);
        if (!$payment) {
            return new Response('someone else\'s payment');
        }

        if (!$this->createNotification($request, $payment, $event)) {
            return new Response();
        }
        $this->em->flush();

        $this->updatePayment($payment, $paymentIntent);

        try {
            $this->crmConnectManager->updateInvoice($payment, true);
        } catch (CurlException | RetailcrmApiException $e) {
            return new Response('', 500);
        }

        $this->em->flush();

        return new Response();
    }

    private function handlePaymentIntentSucceeded($request, $event): Response
    {
        $paymentIntent = $event->data->object;

        if (!isset($paymentIntent['metadata']['invoiceUuid']) || empty($paymentIntent['metadata']['invoiceUuid'])) {
            return new Response();
        }

        /** @var Payment|null $payment */
        $payment = $this->em->getRepository(Payment::class)->find($paymentIntent['id']);
        if (!$payment) {
            return new Response('someone else\'s payment');
        }

        if (!$this->createNotification($request, $payment, $event)) {
            return new Response();
        }
        $this->em->flush();

        $this->updatePayment($payment, $paymentIntent);

        try {
            $this->crmConnectManager->updateInvoice($payment);
        } catch (CurlException | RetailcrmApiException $e) {
            return new Response('', 500);
        }

        $this->em->flush();

        return new Response();
    }

    private function handleChargeRefunded($request, $event): Response
    {
        $charge = $event->data->object;

        /** @var Payment|null $payment */
        $payment = $this->em->getRepository(Payment::class)->find($charge['payment_intent']);
        if (!$payment) {
            return new Response('someone else\'s payment');
        }

        if (StripeManager::STATUS_PAYMENT_CANCELED === $payment->getStatus()) {
            return new Response();
        }

        $paymentIntent = $this->stripeManager->getPaymentInfo($payment);

        if (StripeManager::STATUS_PAYMENT_CANCELED === $paymentIntent['status']) {
            return new Response();
        }

        if (!$this->createNotification($request, $payment, $event)) {
            return new Response();
        }

        if (isset($charge['refunds']['data']) && is_array($charge['refunds']['data'])) {
            $refundResponse = current($charge['refunds']['data']);
        } elseif (is_array($charge['refunds'])) {
            $refundResponse = current($charge['refunds']);
        } else {
            return new Response();
        }

        if (empty($refundResponse['id'])) {
            return new Response('there is no refund id');
        }

        $refund = $this->stripeManager->createRefundIfNotExists($refundResponse, $payment, true);
        $this->em->flush();

        try {
            $this->crmConnectManager->updateInvoice($payment, true, $refund);
        } catch (CurlException | RetailcrmApiException $e) {
            return new Response('', 500);
        }

        $this->em->flush();

        return new Response();
    }

    private function createNotification(Request $request, $payment, $event): ?StripeNotification
    {
        if (null === $payment) {
            return null;
        }

        $integration = $payment->getAccount()->getIntegration();
        if (null === $integration || !$integration->isEnabled()) {
            return null;
        }

        $stripeNotification = new StripeNotification();
        $stripeNotification
            ->setResponse($request->getContent())
            ->setPayment($payment)
            ->setEvent($event->type)
        ;
        $this->em->persist($stripeNotification);

        return $stripeNotification;
    }

    private function updatePayment(Payment $payment, $paymentIntent)
    {
        $charge = [];
        if ($paymentIntent['charges']['data']) {
            $charge = current($paymentIntent['charges']['data']);
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
            ->setAmount($paymentIntent['amount'] / 100)
            ->setStatus($paymentIntent['status'])
            ->setPaid(isset($charge['paid']) ? $charge['paid'] : false)
            ->setExpiresAt($expiresAt)
            ->setCapturedAt($capturedAt)
            ->setCancellationDetails($cancellationDetailsReason)
            ->setRefundable(isset($charge['refunded']) ? !$charge['refunded'] : false)
        ;
    }
}
