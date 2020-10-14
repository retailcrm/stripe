<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

class UrlRepository extends EntityRepository
{
    /**
     * @return object|null
     */
    public function findBySlug(string $slug)
    {
        return $this->findOneBy([
            'slug' => $slug,
        ]);
    }
}
