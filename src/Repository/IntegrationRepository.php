<?php

namespace App\Repository;

use App\Entity\Integration;
use Doctrine\ORM\EntityRepository;

class IntegrationRepository extends EntityRepository
{
    /**
     * @return Integration|object|null
     */
    public function findActive(array $criteria): ?Integration
    {
        if (!isset($criteria['crmUrl'])) {
            return null;
        }

        $params = [
            'crmUrl' => $criteria['crmUrl'],
            'active' => true,
        ];

        if (isset($criteria['crmApiKey'])) {
            $params['crmApiKey'] = $criteria['crmApiKey'];
        }

        return $this->findOneBy($params);
    }
}
