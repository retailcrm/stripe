<?php

namespace App\Controller;

use App\Entity\Account;
use App\Entity\Integration;
use App\Service\CRMConnectManager;
use App\Service\ResponseManager;
use App\Service\ValidateModelManager;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\Translation\TranslatorInterface;

class IntegrationController extends AbstractController
{
    /** @var TranslatorInterface */
    private $translator;

    /** @var ResponseManager */
    private $responseManager;

    /** @var ValidateModelManager */
    private $validateModelManager;

    /** @var EntityManagerInterface */
    private $em;

    /** @var Serializer */
    private $serializer;

    public function __construct(
        TranslatorInterface $translator,
        ValidateModelManager $validateModelManager,
        ResponseManager $responseManager,
        EntityManagerInterface $em,
        SerializerInterface $serializer
    ) {
        $this->translator = $translator;
        $this->validateModelManager = $validateModelManager;
        $this->responseManager = $responseManager;
        $this->em = $em;
        $this->serializer = $serializer;
    }

    public function index(): Response
    {
        return $this->render('base.html.twig');
    }

    public function settings(string $slug, Request $request, CRMConnectManager $connectManager): Response
    {
        if (Request::METHOD_POST === $request->getMethod()) {
            return $this->redirectToRoute('stripe_settings', ['slug' => $slug]);
        }

        if (!Uuid::isValid($slug)) {
            return $this->redirectToRoute('index');
        }
        /** @var Integration|null $integration */
        $integration = $this->em->getRepository(Integration::class)->find($slug);

        if (null === $integration) {
            return $this->redirectToRoute('index');
        }

        return $this->render('base.html.twig', [
            'brand' => $connectManager->getBrand($integration),
        ]);
    }

    public function connect(Request $request): JsonResponse
    {
        try {
            /** @var Integration $requestIntegration */
            $requestIntegration = $this->serializer->deserialize(
                $request->get('integration'),
                Integration::class,
                'json',
                DeserializationContext::create()->setGroups(['connect'])
            );
        } catch (NotEncodableValueException $e) {
            return $this->responseManager->jsonResponse(
                ['success' => false, 'errorMsg' => $this->translator->trans('api.error.invalid_request_data')],
                400
            );
        }

        $integration = new Integration();
        $integration
            ->setCrmApiKey($requestIntegration->getCrmApiKey())
            ->setCrmUrl($requestIntegration->getCrmUrl());

        if ($invalidResponse = $this->validateModelManager->validateWithFields($integration, ['connect'])) {
            return $invalidResponse;
        }

        $existsIntegration = $this->em->getRepository(Integration::class)->findOneBy([
            'crmApiKey' => $integration->getCrmApiKey(),
            'crmUrl' => $integration->getCrmUrl(),
        ]);

        if ($existsIntegration) {
            $url = $this->container->get('router')->generate('stripe_settings', [
                'slug' => $existsIntegration->getSlug(),
            ]);

            return $this->responseManager->jsonResponse(
                ['success' => true, 'redirectUrl' => $url],
                200
            );
        }

        $this->em->persist($integration);
        $this->em->flush();

        return $this->responseManager->jsonResponse(
            [
                'success' => true,
                'redirectUrl' => $this->container->get('router')->generate('stripe_settings', [
                    'slug' => $integration->getSlug(),
                ]),
            ],
            200
        );
    }

    public function getIntegrationData(string $slug): JsonResponse
    {
        if (!Uuid::isValid($slug)) {
            return $this->responseManager->notFoundResponse('error.integration_not_exists');
        }
        /** @var Integration|null $integration */
        $integration = $this->em->getRepository(Integration::class)->find($slug);

        if (null === $integration) {
            return $this->responseManager->notFoundResponse('error.integration_not_exists');
        }

        /** @var Account[] $accounts */
        $accounts = $this->em->getRepository(Account::class)->findBy(
            ['integration' => $integration->getId()],
            ['deactivatedAt' => 'desc', 'id' => 'desc']
        );

        $accounts = $this->serializer->toArray(
            $accounts,
            SerializationContext::create()->setGroups(['get'])
        );
        $integration = $this->serializer->toArray(
            $integration,
            SerializationContext::create()->setGroups(['get'])
        );

        $integration['crmApiKey'] = substr_replace(
            $integration['crmApiKey'],
            '************************',
            4,
            -4
        );

        return $this->responseManager->jsonResponse(
            [
                'success' => true,
                'accounts' => $accounts,
                'integration' => $integration,
                'moduleCode' => CRMConnectManager::MODULE_CODE,
            ],
            200,
            ['Cache-Control' => 'no-store']
        );
    }

    public function editIntegration(
        string $slug,
        Request $request,
        CRMConnectManager $CRMConnectManager
    ): JsonResponse {
        if (!Uuid::isValid($slug)) {
            return $this->responseManager->notFoundResponse('error.integration_not_exists');
        }
        /** @var Integration|null $integration */
        $integration = $this->em->getRepository(Integration::class)->find($slug);

        if (null === $integration) {
            return $this->responseManager->notFoundResponse('error.integration_not_exists');
        }

        $integration->setCrmApiKey($request->get('apiKey'));

        if ($invalidResponse = $this->validateModelManager->validateWithFields($integration, ['edit'])) {
            return $invalidResponse;
        }

        $this->em->flush();

        $integration = $this->serializer->toArray(
            $integration,
            SerializationContext::create()->setGroups(['get'])
        );

        return $this->responseManager->jsonResponse([
            'success' => true,
            'integration' => $integration,
        ], 200);
    }

    public function test(): JsonResponse
    {
        $stopwatch = new Stopwatch(true);
        $stopwatch->start('test');

        while (true) {
            echo date('Y-m-d H:i:s');
            for ($i = 0; $i <= 4096; ++$i) {
                echo '.';
            }
            printf("\n");
            sleep(1);
        }

        $stopwatch->stop('test');

        return new JsonResponse([
            'success' => true,
            'time' => $stopwatch->getEvent('test')->getDuration(),
        ]);
    }
}
