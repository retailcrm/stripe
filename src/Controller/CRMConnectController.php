<?php

namespace App\Controller;

use App\Entity\Integration;
use App\Service\CRMConnectManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class CRMConnectController extends AbstractController implements LoggableController
{
    /**
     * @return JsonResponse
     *
     * @throws \RuntimeException
     */
    public function activity(
        Request $request,
        CRMConnectManager $connectManager,
        EntityManagerInterface $em
    ) {
        if (
            null === $request->get('clientId')
            || null === $request->get('activity')
            || null === $request->get('systemUrl')
        ) {
            throw new \RuntimeException('Not enough parameters', 500);
        }

        $activity = json_decode($request->get('activity'), true);
        if (!isset($activity['active'], $activity['freeze'])) {
            throw new \RuntimeException('Not enough parameters', 500);
        }

        $systemUrl = $request->get('systemUrl');
        $em = $this->getDoctrine()->getManager();

        /** @var Integration|null $integration */
        $integration = $em->getRepository(Integration::class)->find($request->get('clientId'));
        if (null === $integration) {
            return new JsonResponse(['success' => false], 200);
        }

        $integrationCrmUrl = $integration->getCrmUrl();

        $integration->setCrmUrl($systemUrl);
        $integration->setActive($activity['active']);
        $integration->setFreeze($activity['freeze']);
        $em->flush();

        if (null !== $systemUrl && $systemUrl !== $integrationCrmUrl) {
            $settingUpdate = $connectManager->sendModuleInCRM($integration);

            return new JsonResponse(['success' => $settingUpdate], 200);
        }

        return new JsonResponse(['success' => true], 200);
    }
}
