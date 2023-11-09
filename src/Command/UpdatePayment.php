<?php

namespace App\Command;

use App\Entity\Payment;
use App\Exception\CreateUpdateInvoiceException;
use App\Service\CRMConnectManager;
use App\Service\StripeManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdatePayment extends Command
{
    protected static $defaultName = 'payment:update';
    private EntityManagerInterface $em;
    private CRMConnectManager $crmManager;
    private StripeManager $stripeManager;

    public function __construct(EntityManagerInterface $em, CRMConnectManager $crmManager, StripeManager $stripeManager)
    {
        $this->em = $em;
        $this->crmManager = $crmManager;
        $this->stripeManager = $stripeManager;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Update payments')
            ->setHelp('Update data for payment')
            ->addArgument('payment', InputArgument::IS_ARRAY, 'For which payment(s) update daya')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $payments = $input->getArgument('payment');

        if (empty($payments)) {
            $output->writeln('Specify payment(s) id');

            return self::FAILURE;
        }

        foreach ($payments as $paymentId) {
            $output->writeln(sprintf('Updating %s ', $paymentId));
            /** @var Payment $payment */
            $payment = $this->em->getRepository(Payment::class)->find($paymentId);

            try {
                $this->stripeManager->updatePayment($payment);
                $this->crmManager->updateInvoice($payment);
            } catch (CreateUpdateInvoiceException | \Exception $e) {
                $output->writeln(sprintf('- Error. Code: %s, message: %s', $e->getCode(), $e->getMessage()));
                continue;
            }
            $output->writeln('- done');
        }

        return self::SUCCESS;
    }
}
