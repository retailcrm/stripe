<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validator constraint for INN number.
 */
class InnValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        $error = false;
        if (!$constraint instanceof Inn) {
            throw new UnexpectedTypeException($constraint, Inn::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if (!preg_match('/^\d+$/', $value)) {
            $error = true;
        }

        $inn = str_split($value);
        if (10 === count($inn)) {
            $n10 = $this->checkDigit($inn, [2, 4, 10, 3, 5, 9, 4, 6, 8]);
            if ($n10 !== (int) $inn[9]) {
                $error = true;
            }
        } elseif (12 === count($inn)) {
            $n11 = $this->checkDigit($inn, [7, 2, 4, 10, 3, 5, 9, 4, 6, 8]);
            $n12 = $this->checkDigit($inn, [3, 7, 2, 4, 10, 3, 5, 9, 4, 6, 8]);

            if ($n11 !== (int) $inn[10] || $n12 !== (int) $inn[11]) {
                $error = true;
            }
        } else {
            $error = true;
        }
        if ($error) {
            $this->context->addViolation($constraint->message);
        }
    }

    private function checkDigit($inn, $coefficients): int
    {
        $n = 0;
        foreach ($coefficients as $i => $k) {
            $n += $k * (int) $inn[$i];
        }

        return $n % 11 % 10;
    }
}
