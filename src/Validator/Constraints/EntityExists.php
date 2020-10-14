<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Validator constraint for entity existing.
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
class EntityExists extends Constraint
{
    public $entity;
    public $field = 'slug';

    /**
     * Method for a single entity fetching.
     */
    public $method = null;

    /**
     * Method for all entities fetching.
     */
    public $methodCollection = null;

    /**
     * Method for all entities fetching.
     */
    public $methodAll = null;

    public $message = '{{ entity }} with {{ field }}={{ value }} does not exist.';

    public function validatedBy()
    {
        return static::class . 'Validator';
    }
}
