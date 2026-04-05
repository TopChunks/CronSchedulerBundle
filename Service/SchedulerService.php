<?php

namespace MauticPlugin\CronSchedulerBundle\Service;

use Cron\CronExpression;
use Doctrine\ORM\EntityManager;
use MauticPlugin\CronSchedulerBundle\Entity\JobExecutionLog;
use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\NamespaceNotFoundException;
use Mautic\CoreBundle\Command\ModeratedCommand;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Mautic\CoreBundle\Helper\DateTimeHelper;

class SchedulerService
{
    private ?Application $application = null;
    private DateTimeHelper $dateTimeHelper;

    public function __construct(
        private EntityManager $em,
        private KernelInterface $kernel
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

        if ($job->getNextRunAt() && $now >= $job->getNextRunAt()) {
            return true;
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
        try {
            $application = $this->getApplication();
            $args         = trim((string) $job->getArguments());

            $commands = $this->splitCommands((string) $job->getCommand());

            if (count($commands) === 1) {
                $commandString = $this->buildSingleCommandString($commands[0], $args, $application);
                $input         = new StringInput($commandString);
                $output        = new BufferedOutput();

                $exitCode     = $application->run($input, $output);
                $outputString = $output->fetch();
                $success      = (0 === $exitCode);
            } else {
                $success      = true;
                $exitCode     = 0;
                $outputString = '';

                foreach ($commands as $command) {
                    $commandString = $this->buildSingleCommandString($command, $args, $application);
                    $input         = new StringInput($commandString);
                    $output        = new BufferedOutput();

                    $exitCode     = $application->run($input, $output);
                    $chunkOutput  = $output->fetch();
                    $outputString .= $chunkOutput;

                    if (0 !== $exitCode) {
                        $success = false;
                        break;
                    }
                }
            }

            return [
                'success'  => $success,
                'exitCode' => $exitCode,
                'message'  => $outputString,
            ];
        } catch (\Exception $e) {
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
                'message' => 'Job is locked by another process',
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

        try {
            $application = $this->getApplication();
            $args        = trim((string) $job->getArguments());
            $commands    = $this->splitCommands((string) $job->getCommand());

            $outputString = '';
            $exitCode     = 0;

            $success = true;

            foreach ($commands as $command) {
                $commandString = $this->buildSingleCommandString($command, $args, $application);
                $input         = new StringInput($commandString);
                $output        = new BufferedOutput();

                $exitCode     = $application->run($input, $output);
                $outputString .= $output->fetch();

                if (0 !== $exitCode) {
                    $success = false;
                    break;
                }
            }

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
            }

            if ($log && null !== $exitCode) {
                $log->setExitCode($exitCode);
            }

            if ($outputString) {
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

            throw new \Exception("Failed to trigger job '{$job->getName()}': " . $e->getMessage());
        } finally {
            $job->setLockedAt(null);
            $this->em->flush();
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
            $this->application->setCatchExceptions(true);
        }

        return $this->application;
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
