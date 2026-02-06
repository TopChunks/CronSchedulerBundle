<?php

namespace MauticPlugin\CronSchedulerBundle\Command;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Tenancy\Entity\Tenant;
use Mautic\CoreBundle\Tenancy\TenantRunner;
use MauticPlugin\CronSchedulerBundle\Service\SchedulerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteOlderLogsCommand extends Command
{
    /**
     * @var SchedulerService
     */
    protected $schedulerService;

    /**
     * @var TenantRunner
     */
    protected $tenantRunner;

    /**
     * @var CoreParametersHelper
     */
    protected $coreParametersHelper;

    public function __construct(SchedulerService $schedulerService, TenantRunner $tenantRunner, CoreParametersHelper $coreParametersHelper)
    {
        $this->schedulerService = $schedulerService;
        $this->tenantRunner = $tenantRunner;
        $this->coreParametersHelper = $coreParametersHelper;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('cronscheduler:delete:cronlogs')
            ->setDescription('Delete older logs to keeps database healthy')
            ->addOption(
                '--tenant-id',
                null,
                InputOption::VALUE_OPTIONAL,
                'The ID of the tenant to process.',
                null
            );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $status = $this->tenantRunner->runForTenants(
            $input->getOption('tenant-id'),
            $output,
            function (Tenant $tenant) use ($input, $output) {
                $io = new SymfonyStyle($input, $output);
                $retentionDays = $this->coreParametersHelper->get('log_retention_days');
                $deleted = $this->schedulerService->deleteOlderLogs($retentionDays);
                $io->writeln(sprintf(
                    '<info>Tenant %s: %d logs deleted (retention: %d days)</info>',
                    $tenant->getId(),
                    $deleted,
                    $retentionDays
                ));
            }
        );
        return $status;
    }
}
