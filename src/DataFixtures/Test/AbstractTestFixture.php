<?php

namespace App\DataFixtures\Test;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

abstract class AbstractTestFixture extends Fixture
{
    // нужно ли очищать
    protected $clear = false;
    protected $count = 1;

    /**
     * получаем имя таблиц(ы) для очистки (может быть несколько)
     *
     * @return array
     */
    abstract protected function getTableNames(ObjectManager $em);

    public function __construct($clear = false, $count = null)
    {
        $this->clear = $clear;

        if (null !== $count) {
            $this->count = $count;
        }
    }

    protected function getValue($values, $i)
    {
        $i = (int) $i;

        if (!is_array($values) || !$values) {
            throw new \InvalidArgumentException('Values must be an array');
        }
        $ind = $i % count($values);

        return $values[$ind];
    }

    protected function truncate(ObjectManager $em, $cascade = true)
    {
        $connection = $em->getConnection();
        $platform = $connection->getDatabasePlatform();

        foreach ($this->getTableNames($em) as $table) {
            $connection->executeUpdate(
                $platform->getTruncateTableSQL($table, $cascade)
            );
        }
    }

    protected function delete(ObjectManager $em)
    {
        $connection = $em->getConnection();

        foreach ($this->getTableNames($em) as $table) {
            $connection->executeQuery('DELETE FROM ' . $table);
        }
    }

    protected function flushWithoutListeners(ObjectManager $em)
    {
        // а все для того чтобы CustomFieldChangeSubscriber не падал
        $uow = $em->getUnitOfWork();
        $reflObj = new \ReflectionObject($uow);
        $prop = $reflObj->getProperty('evm');
        $prop->setAccessible(true);
        $evm = $prop->getValue($uow);

        $reflObj = new \ReflectionObject($evm);
        $prop = $reflObj->getProperty('listeners');
        $prop->setAccessible(true);

        $listeners = $prop->getValue($evm);
        $prop->setValue($evm, []);

        $em->flush();

        $prop->setValue($evm, $listeners);
    }

    public function getFixtureCacheKey()
    {
        return static::class . $this->count;
    }
}
