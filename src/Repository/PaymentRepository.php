<?php

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\ORM\EntityRepository;

class PaymentRepository extends EntityRepository
{
    /**
     * @return Payment|object|null
     */
    public function findByPaymentUuid(string $uuid)
    {
        return $this->findOneBy(['paymentUuid' => $uuid], ['createdAt' => 'DESC']);
    }
}
