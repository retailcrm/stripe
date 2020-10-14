<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

class ApiAccess extends Constraint
{
    /**
     * {@inheritdoc}
     */
    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
