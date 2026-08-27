<?php

namespace MauticPlugin\CronSchedulerBundle\Service;

use Cron\CronExpression;
use Doctrine\ORM\EntityManager;
use MauticPlugin\CronSchedulerBundle\Entity\JobExecutionLog;
use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Exception\NamespaceNotFoundException;
use Mautic\CoreBundle\Command\ModeratedCommand;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Mautic\CoreBundle\Helper\DateTimeHelper;

class SchedulerService
{
    public const LOCKED_MESSAGE = 'Job is locked by another process';

    private ?Application $application = null;
    private DateTimeHelper $dateTimeHelper;

    public function __construct(
        private EntityManager $em,
        private KernelInterface $kernel,
        private FailureAlertService $failureAlertService
    ) {
        $this->dateTimeHelper = new DateTimeHelper();
    }

    public function isDue(ScheduledJob $job): bool
    {
        $now = $this->dateTimeHelper->getLocalDateTime();

        if ($job->getPublishUp() && $job->getPublishUp() > $now) {
            return false;
        }

        if ($job->getPublishDown() && $job->getPublishDown() < $now) {
            return false;
        }

        switch ($job->getTriggerMode()) {
            case 'date':
                return $job->getTriggerDate()
                    && $now >= $job->getTriggerDate()
                    && !$job->getLastRunAt();

            case 'interval':
                return $this->isIntervalDue($job, $now);

            case 'cron':
                return $job->getCronNotation()
                    && $this->isCronDue($job, $now);

            default:
                return false;
        }
    }

    private function isIntervalDue(ScheduledJob $job, \DateTime $now): bool
    {
        if (
            $job->getLastRunAt()
            && intdiv($job->getLastRunAt()->getTimestamp(), 60) === intdiv($now->getTimestamp(), 60)
        ) {
            return false;
        }

        $nextRunAt = $job->getNextRunAt();
        if ($nextRunAt instanceof \DateTimeInterface) {
            return $now >= $nextRunAt;
        }

        if (null !== $job->getNextRunAt()) {
            return false;
        }

        $nextRunAt = $this->calculateNextIntervalRun($job, $job->getLastRunAt() ?? $now);

        if ($nextRunAt) {
            $job->setNextRunAt($nextRunAt);
            $this->em->persist($job);
            $this->em->flush();
        }
        return false;
    }

    private function isCronDue(ScheduledJob $job, \DateTime $now): bool
    {
        $lastRun = $job->getLastRunAt();
        if ($lastRun && intdiv($lastRun->getTimestamp(), 60) === intdiv($now->getTimestamp(), 60)) {
            return false;
        }

        if (!$this->meetsDayRestrictions($job, $now)) {
            return false;
        }

        $cronNotation = trim((string) $job->getCronNotation());
        if ($cronNotation === '') {
            return false;
        }

        $nextRunAt = $job->getNextRunAt();
        if ($nextRunAt instanceof \DateTimeInterface) {
            if ($now >= $nextRunAt) {
                return true;
            }
        } else {
            $calculatedNext = $this->calculateNextCronRun($job, $now);
            if ($calculatedNext instanceof \DateTimeInterface) {
                $job->setNextRunAt($calculatedNext);
                $this->em->persist($job);
                $this->em->flush();
            }

            return false;
        }

        try {
            // Use factory to be compatible with cron-expression v3+
            $cron = CronExpression::factory($cronNotation);
            if (!$cron->isDue($now)) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if current day meets day-of-week restrictions.
     */
    private function meetsDayRestrictions(ScheduledJob $job, \DateTime $now): bool
    {
        $allowedDays = array_values(array_filter(array_map(
            static fn($d) => is_numeric($d) ? (int) $d : null,
            $job->getTriggerRestrictedDaysOfWeek()
        ), static fn($d) => null !== $d));

        if (empty($allowedDays)) {
            return true;
        }

        $currentDayOfWeek = (int) $now->format('w');

        if (in_array(-1, $allowedDays, true)) {
            return $currentDayOfWeek >= 1 && $currentDayOfWeek <= 5;
        }

        return in_array($currentDayOfWeek, $allowedDays, true);
    }

    public function runJobManually(ScheduledJob $job)
    {
        $startTime = microtime(true);
        $startedAt = $this->dateTimeHelper->getLocalDateTime();

        try {
            $result       = $this->runJobCommands($job);
            $exitCode     = $result['exitCode'];
            $outputString = $result['output'];
            $success      = (0 === $exitCode);

            if (!$success) {
                $this->failureAlertService->send($job, null, [
                    'exitCode'   => $exitCode,
                    'failReason' => $outputString ?: 'Command failed',
                    'duration'   => microtime(true) - $startTime,
                    'executedAt' => $startedAt,
                ]);
            }

            return [
                'success'  => $success,
                'exitCode' => $exitCode,
                'message'  => $outputString,
            ];
        } catch (\Exception $e) {
            $this->failureAlertService->send($job, null, [
                'failReason' => $e->getMessage(),
                'duration'   => microtime(true) - $startTime,
                'executedAt' => $startedAt,
            ]);

            throw new \Exception("Failed to run job '{$job->getName()}': " . $e->getMessage());
        }
    }

    /**
     * Trigger a scheduled job by executing its command.
     *
     * @throws \Exception
     */
    public function triggerJob(ScheduledJob $job)
    {
        $shouldLog = !$job->getSystemcron();
        if (!$this->acquireLock($job)) {
            return [
                'success' => false,
                'message' => self::LOCKED_MESSAGE,
            ];
        }

        $startTime = microtime(true);
        $startedAt = $this->dateTimeHelper->getLocalDateTime();

        $log = null;

        if ($shouldLog) {
            $log = new JobExecutionLog();
            $log->setScheduledJob($job);
            $log->setStartedAt($startedAt);
        }

        $exitCode     = null;
        $outputString = '';
        $failedLog    = null;
        $shouldAlert  = false;
        $alertFallback = [];

        try {
            $result       = $this->runJobCommands($job);
            $exitCode     = $result['exitCode'];
            $outputString = $result['output'];
            $success      = (0 === $exitCode);

            $completedAt = $this->dateTimeHelper->getLocalDateTime();
            $duration    = microtime(true) - $startTime;

            if ($log) {
                $log->setCompletedAt($completedAt);
                $log->setExitCode($exitCode);
                $log->setOutput($outputString);
                $log->setDuration($duration);
                $log->setIsSuccess($success);
            }

            $now = $this->dateTimeHelper->getLocalDateTime();
            $job->setLastRunAt($now);

            $nextRunAt = $this->calculateNextRunTime($job);
            $job->setNextRunAt($nextRunAt);

            if ($log) {
                $this->em->persist($log);
            }
            $this->em->persist($job);

            if (!$success) {
                $shouldAlert = true;
                $failedLog   = $log;
                $alertFallback = [
                    'exitCode'   => $exitCode,
                    'failReason' => $outputString ?: 'Command failed',
                    'duration'   => $duration,
                    'executedAt' => $startedAt,
                ];
            }

            return [
                'success'  => $success,
                'exitCode' => $exitCode,
                'output'   => $outputString,
                'duration' => $duration,
            ];
        } catch (\Exception $e) {
            $completedAt = $this->dateTimeHelper->getLocalDateTime();
            $duration    = microtime(true) - $startTime;

            if ($log) {
                $log->setCompletedAt($completedAt);
                $log->setIsSuccess(false);
                $log->setErrorMessage($e->getMessage());
                $log->setDuration($duration);
                $log->setExitCode(null !== $exitCode ? $exitCode : 1);
            }

            if ($log && $outputString) {
                $log->setOutput($outputString);
            }

            if ($log) {
                $this->em->persist($log);
            }

            $now = $this->dateTimeHelper->getLocalDateTime();
            $job->setLastRunAt($now);
            $nextRunAt = $this->calculateNextRunTime($job, $now);
            $job->setNextRunAt($nextRunAt);
            $this->em->persist($job);

            $shouldAlert = true;
            $failedLog   = $log;
            $alertFallback = [
                'exitCode'   => $exitCode,
                'failReason' => $e->getMessage(),
                'duration'   => $duration,
                'executedAt' => $startedAt,
            ];

            throw new \Exception("Failed to trigger job '{$job->getName()}': " . $e->getMessage());
        } finally {
            $job->setLockedAt(null);
            $this->em->flush();

            if ($shouldAlert) {
                $this->failureAlertService->send($job, $failedLog, $alertFallback);
            }
        }
    }

    public function deleteOlderLogs(int $retentionDays): int
    {
        if ($retentionDays <= 0) {
            return 0;
        }

        // IMPORTANT: DateTimeHelper returns a mutable DateTime instance; cloning prevents
        // us from drifting the "current time" used by other scheduled jobs in the same run.
        $cutoff = (clone $this->dateTimeHelper->getLocalDateTime())->modify(sprintf('-%d days', $retentionDays));

        /** @var \MauticPlugin\CronSchedulerBundle\Entity\JobExecutionLogRepository $repo */
        $repo = $this->em->getRepository(JobExecutionLog::class);

        return $repo->deleteOlderLogs($cutoff);
    }

    public function calculateNextRunTime(ScheduledJob $job, ?\DateTime $now = null): ?\DateTime
    {
        if (!$now) {
            $now = $this->dateTimeHelper->getLocalDateTime();
        }

        switch ($job->getTriggerMode()) {
            case 'cron':
                return $this->calculateNextCronRun($job, $now);

            case 'date':
                return $job->getTriggerDate();

            case 'interval':
                return $this->calculateNextIntervalRun($job, $now);
        }

        return null;
    }

    private function calculateNextCronRun(ScheduledJob $job, \DateTime $now): ?\DateTime
    {
        $cronNotation = trim((string) $job->getCronNotation());
        if ($cronNotation === '') {
            return null;
        }

        try {
            // Use factory to be compatible with cron-expression v3+
            $cron = CronExpression::factory($cronNotation);
            $next = $cron->getNextRunDate($now);

            return $next;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function calculateNextIntervalRun(ScheduledJob $job, \DateTime $now): ?\DateTime
    {
        $interval = $job->getTriggerInterval();
        $unit     = $job->getTriggerIntervalUnit();

        if (!$interval || !$unit) {
            return null;
        }

        $allowedDays = $job->getTriggerRestrictedDaysOfWeek();

        $next = clone $now;

        $normalizedUnit = $this->normalizeIntervalUnit($unit);
        $next->modify(sprintf('+%d %s', $interval, $normalizedUnit));

        if ($unit === 'i' || $unit === 'h') {
            return $next;
        }

        if ($unit === 'd' || $unit === 'm' || $unit === 'y') {
            // Apply time restriction
            if ($job->getTriggerHour()) {
                $time = $job->getTriggerHour();
                $next->setTime((int) $time->format('H'), (int) $time->format('i'));
            } else {
                $next->setTime(0, 0, 0);
            }
        }

        if (!empty($allowedDays)) {
            $maxAttempts   = 366; // Safety limit
            $attempts      = 0;

            while ($attempts < $maxAttempts) {
                $next->modify('+1 day');

                $dayOfWeek = (int) $next->format('N');

                if (in_array(-1, $allowedDays, true)) {
                    if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                        break;
                    }
                } elseif (in_array($dayOfWeek, $allowedDays, true)) {
                    break;
                }

                ++$attempts;
            }
        }

        return $next;
    }

    /**
     * Normalize interval unit to PHP's modify() format.
     */
    private function normalizeIntervalUnit(string $unit): string
    {
        switch ($unit) {
            case 'i':
                return 'minutes';
            case 'h':
                return 'hours';
            case 'd':
                return 'days';
            case 'm':
                return 'months';
            case 'y':
                return 'years';
            default:
                throw new \InvalidArgumentException("Invalid interval unit: $unit");
        }
    }

    /**
     * Acquire lock for job execution
     * Prevents concurrent execution of the same job.
     */
    private function acquireLock(ScheduledJob $job): bool
    {
        $lockedAt = $job->getLockedAt();

        if ($lockedAt) {
            // Clone to avoid mutating DateTimeHelper's internal datetime.
            $thirtyMinutesAgo = (clone $this->dateTimeHelper->getLocalDateTime())->modify('-30 minutes');
            if ($lockedAt > $thirtyMinutesAgo) {
                // Job is still locked
                return false;
            }
        }

        $job->setLockedAt($this->dateTimeHelper->getLocalDateTime());
        $this->em->flush();

        return true;
    }

    private function getApplication(): Application
    {
        if (null === $this->application) {
            $this->application = new Application($this->kernel);
            $this->application->setAutoExit(false);
            $this->application->setCatchExceptions(false);
        }

        return $this->application;
    }

    /**
     * Run pipe-separated commands and return the combined exit code and output.
     *
     * Arguments are validated against each command's own options only, not
     * Symfony's global console options (--env, --no-debug, --quiet, etc.).
     *
     * @return array{exitCode: int, output: string}
     */
    private function runJobCommands(ScheduledJob $job): array
    {
        $application = $this->getApplication();
        $args        = trim((string) $job->getArguments());
        $commands    = $this->splitCommands((string) $job->getCommand());

        if (empty($commands)) {
            throw new \RuntimeException('Job command is empty.');
        }

        $outputString = '';
        $exitCode     = 0;

        foreach ($commands as $commandName) {
            $resolved = $application->find($commandName);
            $this->assertJobArgumentsAreValid($resolved, $args);

            $commandString = $this->buildSingleCommandString($commandName, $args, $application);
            $input         = new StringInput($commandString);
            $input->setInteractive(false);
            $output        = new BufferedOutput();

            $exitCode = $application->run($input, $output);
            $outputString .= $output->fetch();

            if (0 !== $exitCode) {
                break;
            }
        }

        return [
            'exitCode' => $exitCode,
            'output'   => $outputString,
        ];
    }

    /**
     * Reject flags the command does not define, including Symfony globals like --env.
     */
    private function assertJobArgumentsAreValid(Command $command, ?string $arguments): void
    {
        $input = new StringInput(trim((string) $arguments));

        try {
            $input->bind($this->getCommandOnlyDefinition($command));
            $input->validate();
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException('Invalid job arguments: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Command definition without Application-level options/arguments.
     *
     * Symfony merges --env, --no-debug, --help, etc. onto every command, so
     * those flags would otherwise be accepted and silently ignored.
     */
    private function getCommandOnlyDefinition(Command $command): InputDefinition
    {
        $applicationDefinition = $this->getApplication()->getDefinition();
        $native                = new InputDefinition();

        foreach ($command->getDefinition()->getArguments() as $argument) {
            if ($applicationDefinition->hasArgument($argument->getName())) {
                continue;
            }
            $native->addArgument($argument);
        }

        foreach ($command->getDefinition()->getOptions() as $option) {
            if ($applicationDefinition->hasOption($option->getName())) {
                continue;
            }
            $native->addOption($option);
        }

        return $native;
    }

    /**
     * Split a potentially pipe-separated command string.
     *
     * Example: "mautic:campaigns:rebuild|mautic:campaigns:update"
     * becomes ["mautic:campaigns:rebuild", "mautic:campaigns:update"].
     *
     * @return list<string>
     */
    private function splitCommands(string $command): array
    {
        $parts = array_filter(array_map(static fn(string $c): string => trim($c), explode('|', $command)));

        // Symfony console supports abbreviated commands; keep as-is (do not expand),
        // but we still need to ensure "cmd|cmd" is handled as two commands.
        return array_values($parts);
    }

    private function buildSingleCommandString(string $command, string $args, Application $application): string
    {
        if (str_contains($args, '--bypass-locking')) {
            return trim($command . ' ' . $args);
        }

        try {
            $resolved    = $application->find($command);

            $supportsBypass =
                $resolved instanceof ModeratedCommand
                || $resolved->getDefinition()->hasOption('bypass-locking');

            if ($supportsBypass) {
                $args = trim($args . ' --bypass-locking');
            }
        } catch (CommandNotFoundException | NamespaceNotFoundException $e) {
            throw new \Exception("Command not found: $command");
        }

        return trim($command . ' ' . $args);
    }
}
