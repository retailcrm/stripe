<?php

namespace App\Command;

use App\Entity\Integration;
use App\Service\RegisterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ChangeSystemUrl extends Command
{
    protected static $defaultName = 'change:url';

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var RegisterService
     */
    private $registerService;

    public function __construct(EntityManagerInterface $entityManager, RegisterService $registerService)
    {
        $this->em = $entityManager;
        $this->registerService = $registerService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Change systemUrl to publicUrl');
        $integrations = $this->em->getRepository(Integration::class)->findAll();

        if (!count($integrations)) {
            $output->writeln('No integrations found');

            return Command::SUCCESS;
        }

        foreach ($integrations as $integration) {
            if (preg_match('/.io$/', $integration->getCrmUrl())) {
                $publicUrl = $integration->getCrmUrl();

                try {
                    $publicUrl = $this->registerService->getPublicUrl($integration->getCrmUrl());
                } catch (\Throwable $e) {
                    $output->writeln($e->getMessage());
                    continue;
                }

                $output->write($integration->getCrmUrl() . '  to  ' . $publicUrl);
                $integration->setCrmUrl($publicUrl);
                $this->em->flush($integration);
                $output->writeln('  - changed');
            }
        }

        return Command::SUCCESS;
    }
}
