<?php

namespace MauticPlugin\CronSchedulerBundle\Command;

use Mautic\CoreBundle\Tenancy\Entity\Tenant;
use Mautic\CoreBundle\Tenancy\TenantRunner;
use MauticPlugin\CronSchedulerBundle\Service\JobQueueProcessor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ProcessJobQueuesCommand extends Command
{
    /**
     * @var JobQueueProcessor
     */
    protected $jobQueueProcessor;

    /**
     * @var TenantRunner
     */
    protected $tenantRunner;

    public function __construct(JobQueueProcessor $jobQueueProcessor, TenantRunner $tenantRunner)
    {
        $this->jobQueueProcessor = $jobQueueProcessor;
        $this->tenantRunner = $tenantRunner;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('cronscheduler:process:queues')
            ->setDescription('Create job queues from scheduled jobs and process pending jobs')
            ->addOption(
                '--tenant-id',
                null,
                InputOption::VALUE_OPTIONAL,
                'The ID of the tenant to process.',
                null
            )
            ->addOption(
                '--limit',
                null,
                InputOption::VALUE_OPTIONAL,
                'Maximum number of jobs to process',
                100
            )
            ->addOption(
                '--skip-create',
                null,
                InputOption::VALUE_NONE,
                'Skip creating job queues from scheduled jobs (only process existing queues)'
            )
            ->addOption(
                '--debug',
                null,
                InputOption::VALUE_NONE,
                'Show detailed debug information'
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
                $limit = (int) $input->getOption('limit');
                $debug = $input->getOption('debug');
                $skipCreate = $input->getOption('skip-create');

                // Step 1: Create job queues from scheduled jobs
                if (!$skipCreate) {
                    if ($debug) {
                        $io->note('Creating job queues from scheduled jobs...');
                    }
                    
                    $created = $this->jobQueueProcessor->createJobQueuesFromScheduledJobs();
                    
                    if ($debug) {
                        $io->writeln(sprintf('Created %d new job queue(s) from scheduled jobs', $created));
                    } elseif ($created > 0) {
                        $io->info(sprintf('Created %d new job queue(s)', $created));
                    }
                }

                // Step 2: Process job queues
                if ($debug) {
                    $io->note(sprintf('Processing up to %d jobs from queue', $limit));
                }

                $results = $this->jobQueueProcessor->processJobs($limit);

                if ($debug) {
                    $io->writeln(sprintf('Processed: %d', $results['processed']));
                    $io->writeln(sprintf('Succeeded: %d', $results['succeeded']));
                    $io->writeln(sprintf('Failed: %d', $results['failed']));
                }

                if ($results['processed'] > 0) {
                    $io->success(sprintf(
                        "Processed %d job(s). Succeeded: %d, Failed: %d",
                        $results['processed'],
                        $results['succeeded'],
                        $results['failed']
                    ));
                } else {
                    $io->info('No jobs to process');
                }

                return 0;
            }
        );

        return $status;
    }
}
