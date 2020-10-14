<?php

namespace App\Validator\Constraints;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validate than entity(-ies) with field=value exists.
 */
class EntityExistsValidator extends ConstraintValidator
{
    protected $em;
    protected $entities = [];

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof EntityExists) {
            throw new UnexpectedTypeException($constraint, EntityExists::class);
        }

        if (null === $value || '' === $value || [] === $value) {
            return;
        }

        $values = is_array($value) ? $value : [$value];

        if (($method = $constraint->methodAll) && !isset($this->entities[$constraint->entity])) {
            $class = $this->em->getClassMetadata($constraint->entity);

            if (!$class->hasField($constraint->field) && !$class->hasAssociation($constraint->field)) {
                throw new ConstraintDefinitionException(sprintf(
                    "The field '%s' is not mapped by Doctrine, so it cannot be used as objects source.",
                    $constraint->field
                ));
            }

            $entities = $this->em->getRepository($constraint->entity)->$method();

            if (count($entities)) {
                foreach ($entities as $entity) {
                    $this->entities[$constraint->entity][$class->reflFields[$constraint->field]->getValue($entity)] =
                        $entity;
                }
            }
        }

        $method = $constraint->methodCollection;
        if (null !== $method) {
            $class = $this->em->getClassMetadata($constraint->entity);
            $entities = $this->em->getRepository($constraint->entity)->$method($values);

            $entityValues = array_map(function ($entity) use ($class, $constraint) {
                return $class->reflFields[$constraint->field]->getValue($entity);
            }, $entities);

            $r = new \ReflectionClass($constraint->entity);
            $entityShortName = $r->getShortName();
            foreach ($values as $val) {
                if (!in_array($val, $entityValues)) {
                    $this->context->addViolation($constraint->message, [
                        '{{ entity }}' => $this->formatValue($entityShortName),
                        '{{ field }}' => $this->formatValue($constraint->field),
                        '{{ value }}' => $this->formatValue($val, self::OBJECT_TO_STRING),
                    ]);
                }
            }
        } else {
            $entityShortName = null;
            try {
                foreach ($values as $v) {
                    if ($constraint->methodAll) {
                        $entity = $this->entities[$constraint->entity][$v] ?? null;
                    } elseif ($method = $constraint->method) {
                        $entity = $this->em->getRepository($constraint->entity)->$method($v);
                    } else {
                        $entity = $this->em->getRepository($constraint->entity)->findOneBy([
                            $constraint->field => $v,
                        ]);
                    }

                    if (!$entity) {
                        if (!$entityShortName) {
                            $r = new \ReflectionClass($constraint->entity);
                            $entityShortName = $r->getShortName();
                        }
                        $this->context->addViolation($constraint->message, [
                            '{{ entity }}' => $this->formatValue($entityShortName),
                            '{{ field }}' => $this->formatValue($constraint->field),
                            '{{ value }}' => $this->formatValue($v, self::OBJECT_TO_STRING),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $this->context->addViolation($e->getMessage());
            }
        }
    }
}
