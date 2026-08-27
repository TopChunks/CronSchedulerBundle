<?php

namespace MauticPlugin\CronSchedulerBundle\Command;

use MauticPlugin\CronSchedulerBundle\Model\CronSchedulerModel;
use MauticPlugin\CronSchedulerBundle\Service\SchedulerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TriggerSchedulerJobs extends Command
{
    /**
     * @var CronSchedulerModel
     */
    protected $model;

    /**
     * @var SchedulerService
     */
    protected $schedulerService;

    public function __construct(CronSchedulerModel $model, SchedulerService $schedulerService)
    {
        $this->model            = $model;
        $this->schedulerService = $schedulerService;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('mautic:jobs:trigger')
            ->setDescription('Trigger scheduled jobs as per configuration')
            ->addOption(
                '--force',
                null,
                InputOption::VALUE_NONE,
                'Force trigger all scheduled jobs regardless of schedule'
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
        $io    = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');
        $debug = $input->getOption('debug');

        // Get all published jobs
        $scheduledJobs = $this->model->getRepository()->findBy([
            'isPublished' => true,
        ], ['priority' => 'DESC']);

        if (empty($scheduledJobs)) {
            $io->warning('No active scheduled jobs found.');

            return 0;
        }

        if ($debug) {
            $io->note(sprintf('Found %d published job(s)', count($scheduledJobs)));
        }

        $triggered = 0;
        $skipped   = 0;
        $failed    = 0;

        foreach ($scheduledJobs as $job) {
            try {
                if ($debug) {
                    $io->writeln('  Trigger Mode: ' . $job->getTriggerMode());
                    $io->writeln('  Cron: ' . ($job->getCronNotation() ?: 'none'));
                    $io->writeln('  Trigger Date: ' . ($job->getTriggerDate() ? $job->getTriggerDate()->format('Y-m-d H:i:s') : 'none'));
                    $io->writeln('  Interval: ' . $job->getTriggerInterval() . ' ' . $job->getTriggerIntervalUnit());
                    $io->writeln('  Last Run: ' . ($job->getLastRunAt() ? $job->getLastRunAt()->format('Y-m-d H:i:s') : 'Never'));
                    $io->writeln('  Next Run: ' . ($job->getNextRunAt() ? $job->getNextRunAt()->format('Y-m-d H:i:s') : 'NULL'));
                    $io->writeln('  Priority: ' . $job->getPriority());
                }

                $isDue = $force ? true : $this->schedulerService->isDue($job);

                if ($debug) {
                    $io->writeln('  Is Due: ' . ($isDue ? 'Yes' : 'No'));
                }

                if ($isDue) {
                    if (!$debug) {
                        $io->writeln('Triggering command: ' . $job->getCommand() . '' . $job->getArguments());
                    }

                    $result = $this->schedulerService->triggerJob($job);

                    if ($result && isset($result['success']) && $result['success']) {
                        ++$triggered;
                        $io->writeln('  <info> Success</info>');

                        if ($debug && isset($result['output']) && $result['output']) {
                            $io->writeln('  Output: ' . substr($result['output'], 0, 200));
                        }
                    } elseif (
                        $result
                        && isset($result['message'])
                        && $result['message'] === SchedulerService::LOCKED_MESSAGE
                    ) {
                        ++$skipped;
                        $io->writeln('  <comment> Skipped (locked)</comment>');
                    } else {
                        ++$failed;
                        $reason = $result['output'] ?? $result['message'] ?? 'Command failed';
                        $io->writeln('  <error> Failed: ' . substr((string) $reason, 0, 500) . '</error>');

                        if ($debug && isset($result['output']) && $result['output']) {
                            $io->writeln('  Output: ' . substr($result['output'], 0, 200));
                        }
                    }
                } else {
                    ++$skipped;
                    if ($debug) {
                        $io->writeln('  <comment> Skipped (not due)</comment>');
                    }
                }
            } catch (\Exception $e) {
                ++$failed;
                $io->writeln('  <error>✗ Failed: ' . $e->getMessage() . '</error>');

                if ($debug) {
                    $io->writeln('  Stack trace:');
                    $io->writeln('  ' . $e->getTraceAsString());
                }
            }
        }

        $io->writeln('');
        $io->success(sprintf(
            'Scheduled commands processing completed. Triggered: %d, Skipped: %d, Failed: %d',
            $triggered,
            $skipped,
            $failed
        ));

        return 0;
    }
}
