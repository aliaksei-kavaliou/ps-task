<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI command to calculate and output fees
 *
 * @author aliaksei
 */
class CalculateCommand extends ContainerAwareCommand
{
    const FILE_ARG = "file";

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("fee:calculate:cash-operation")
            ->setDescription("Calculate cash in/out operation fee. Expects file name as argument")
            ->addArgument(self::FILE_ARG, InputArgument::REQUIRED, 'Path to csv file with operation records');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $calc = $this->getContainer()->get('app.fee_calculator');

        try {
            foreach ($calc->calculate($input->getArgument(self::FILE_ARG)) as $fee) {
                $decimals = 1 === $this->getContainer()->getParameter('rates')[$fee['record']->currency]['cnt'] ? 0 : 2;
                $output->writeln(number_format($fee['amount'], $decimals, '.', ''));
            }
        } catch (\Exception $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return 1;
        }

        return 0;
    }
}
