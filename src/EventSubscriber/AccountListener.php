<?php

namespace App\EventSubscriber;

use App\Entity\Account;
use App\Service\CRMConnectManager;
use Doctrine\Common\EventSubscriber;
// for Doctrine < 2.4: use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AccountListener implements EventSubscriber
{
    /**
     * @var CRMConnectManager
     */
    private $connectManager;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(
        CRMConnectManager $connectManager,
        SessionInterface $session,
        TranslatorInterface $translator
    ) {
        $this->connectManager = $connectManager;
        $this->session = $session;
        $this->translator = $translator;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::postPersist,
            Events::postUpdate,
        ];
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $this->switchIntegrationActivity($args);
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $this->switchIntegrationActivity($args);
    }

    public function switchIntegrationActivity(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof Account) {
            $em = $args->getObjectManager();
            $integration = $entity->getIntegration();

            $accountsCount = $em->getRepository(Account::class)->count([
                'integration' => $integration,
                'deactivatedAt' => null,
            ]);

            $integration->setActive((bool) $accountsCount);
            if (!$this->connectManager->sendModuleInCRM($integration)) {
                $this->session->getFlashBag()->add('error', $this->translator->trans('flash.crm_not_save'));
            }

            $em->flush();
        }
    }
}
