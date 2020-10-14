<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\PaymentAPIModel\CreatePayment;
use App\Entity\Url;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class UrlService
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    public function __construct(
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
    }

    public function create(Account $account, CreatePayment $createPayment): Url
    {
        $request = $this->serializer->serialize(
            $createPayment,
            'json',
            ['skip_null_values' => true]
        );

        $url = new Url();
        $url
            ->setSlug($this->generateSlug(6))
            ->setAccount($account)
            ->setRequest(json_decode($request, true));

        $this->entityManager->persist($url);

        return $url;
    }

    /**
     * @throws \Exception
     */
    private function generateSlug(int $length): string
    {
        static $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $strLength = strlen($characters);

        do {
            $slug = '';
            for ($i = 0; $i < $length; ++$i) {
                $slug .= $characters[random_int(0, $strLength - 1)];
            }

            $url = $this->entityManager->getRepository(Url::class)->findBySlug($slug);
        } while ($url);

        return $slug;
    }
}
