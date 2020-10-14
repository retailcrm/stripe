<?php

namespace App\DataFixtures\Test;

use App\Entity\Account;
use App\Entity\Integration;
use Doctrine\Persistence\ObjectManager;

class IntegrationData extends AbstractTestFixture
{
    public function __construct($clear = true, $count = 2)
    {
        parent::__construct($clear, $count);

        $this->count = $count;
    }

    protected function getTableNames(ObjectManager $em)
    {
        return [
            $em->getClassMetadata(Integration::class)->getTableName(),
            $em->getClassMetadata(Account::class)->getTableName(),
        ];
    }

    public function load(ObjectManager $em)
    {
        if ($this->clear) {
            $this->truncate($em);
        }

        for ($i = 0; $i < $this->count; ++$i) {
            $integration = new Integration();
            $integration
                ->setCrmUrl('https://demo.retailcrm.ru')
                ->setCrmApiKey('asdasdasda12asda' . $i)
                ->setActive(true)
            ;
            $em->persist($integration);

            for ($j = 0; $j < 3; ++$j) {
                $account = new Account();
                $account->setIntegration($integration)
                    ->setAccountId('acct_' . $i . $j)
                    ->setPublicKey('pk_test_000000000000000000' . $i . $j)
                    ->setSecretKey('sk_test_000000000000000000' . $i . $j)
                    ->setName('Account name ' . $j);
                $em->persist($account);
            }
        }

        $em->flush();
    }
}
