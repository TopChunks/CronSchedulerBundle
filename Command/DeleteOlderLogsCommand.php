<?php

namespace MauticPlugin\CronSchedulerBundle\Command;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use MauticPlugin\CronSchedulerBundle\Service\SchedulerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteOlderLogsCommand extends Command
{
    /**
     * @var SchedulerService
     */
    protected $schedulerService;

    /**
     * @var CoreParametersHelper
     */
    protected $coreParametersHelper;

    public function __construct(SchedulerService $schedulerService, CoreParametersHelper $coreParametersHelper)
    {
        $this->schedulerService     = $schedulerService;
        $this->coreParametersHelper = $coreParametersHelper;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('mautic:delete:joblogs')
            ->setDescription('Delete older job logs to keeps database healthy');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io            = new SymfonyStyle($input, $output);
        $retentionDays = $this->coreParametersHelper->get('log_retention_days');
        $deleted       = $this->schedulerService->deleteOlderLogs($retentionDays);
        $io->writeln(sprintf(
            '<info>%d logs deleted (retention: %d days)</info>',
            $deleted,
            $retentionDays
        ));

        return Command::SUCCESS;
    }
}
