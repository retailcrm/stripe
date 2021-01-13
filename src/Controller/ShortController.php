<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Url;
use App\Service\StripeManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ShortController extends AbstractController
{
    public function index(string $slug, Request $request, StripeManager $stripeManager): Response
    {
        /** @var Url $url */
        $url = $this->getDoctrine()->getRepository(Url::class)->findBySlug($slug);

        if (!$url) {
            throw $this->createNotFoundException();
        }

        if ($url->getCanceledAt()) {
            return $this->render('short/cancel.html.twig', [
                'slug' => $slug,
            ]);
        }

        /** @var Payment $lastPayment */
        $lastPayment = $url->getPayments()->first();

        if (in_array($lastPayment->getStatus(), [
            StripeManager::STATUS_PAYMENT_SUCCEEDED,
            StripeManager::STATUS_PAYMENT_WAITING_CAPTURE,
        ], true)) {
            return $this->render('short/payment.html.twig', [
                'slug' => $slug,
            ]);
        }

        if ($url->getAccount()->isDeactivated()) {
            return $this->render('short/cancel.html.twig', [
                'slug' => $slug,
            ]);
        }

        $payment = $this->getDoctrine()->getRepository(Payment::class)->findOneBy([
            'url' => $url,
            'status' => [
                StripeManager::STATUS_PAYMENT_PENDING,
                StripeManager::STATUS_PAYMENT_PENDING_OLD,
            ],
        ]);

        if (!$payment) {
            return $this->render('short/notFound.html.twig', [
                'slug' => $slug,
            ]);
        }

        return $this->render('short/index.html.twig', [
            'public_key' => $payment->getAccount()->getPublicKey(),
            'session_id' => trim($payment->getSessionId()),
        ]);
    }
}
