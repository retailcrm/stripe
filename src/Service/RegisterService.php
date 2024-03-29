<?php

namespace App\Service;

use App\Dto\Register\RegisterRequest;
use App\Entity\Integration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class RegisterService
{
    /**
     * @var ParameterBagInterface
     */
    private $params;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var CRMConnectManager
     */
    private $connectManager;
    /**
     * @var ValidateModelManager
     */
    private $validateModelManager;
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        ParameterBagInterface $params,
        EntityManagerInterface $entityManager,
        CRMConnectManager $connectManager,
        ValidateModelManager $validateModelManager
    ) {
        $this->params = $params;
        $this->entityManager = $entityManager;

        $this->connectManager = $connectManager;
        $this->validateModelManager = $validateModelManager;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @return string[]
     */
    public function getScopesList(): array
    {
        return $this->params->get('register')['api-scopes'];
    }

    public function getRegisterUrl(): string
    {
        return $this->urlGenerator->generate(
            'crm_simple_connect_register',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    /**
     * @throws \Exception
     * @throws ExceptionInterface
     */
    public function register(RegisterRequest $request): string
    {
        $this->validateRequest($request);

        $integration = $this->createConnection($request);
        $this->connectManager->sendModuleInCRM($integration);

        $this->entityManager->flush();

        return $this->getAccountUrl($integration);
    }

    /**
     * @throws UnauthorizedHttpException
     */
    private function validateRequest(RegisterRequest $request): void
    {
        $connectSecret = $this->params->get('register')['secret'];

        if (hash_hmac('sha256', $request->apiKey, $connectSecret) !== $request->token) {
            throw new UnauthorizedHttpException('');
        }
    }

    /**
     * @throws \Exception
     */
    public function getPublicUrl(string $systemUrl): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $systemUrl . '/api/system-info');
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $publicUrl = json_decode(curl_exec($ch), true, 512, JSON_THROW_ON_ERROR)['publicUrl'];
        curl_close($ch);

        return $publicUrl;
    }

    private function createConnection(RegisterRequest $request): Integration
    {
        $publicUrl = $request->systemUrl;
        try {
            $publicUrl = $this->getPublicUrl($request->systemUrl);
        } catch (\Throwable $e) {
        }

        $integration = new Integration();
        $integration
            ->setCrmApiKey($request->apiKey)
            ->setCrmUrl($publicUrl);

        if (null !== $this->validateModelManager->validateWithFields($integration, ['connect'])) {
            throw new \InvalidArgumentException('Invalid request data'); // todo proper error handling
        }

        /** @var Integration $existsIntegration */
        $existsIntegration = $this->entityManager->getRepository(Integration::class)->findOneBy(
            [
                'crmApiKey' => $integration->getCrmApiKey(),
                'crmUrl' => $integration->getCrmUrl(),
            ]
        );

        if (!$existsIntegration) {
            $this->entityManager->persist($integration);

            return $integration;
        }

        return $existsIntegration;
    }

    public function getAccountUrl(Integration $integration): string
    {
        return $this->urlGenerator->generate(
            'stripe_settings',
            ['slug' => $integration->getSlug()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}
