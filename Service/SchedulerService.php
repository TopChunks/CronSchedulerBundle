<?php

namespace MauticPlugin\CronSchedulerBundle\Service;

use Cron\CronExpression;
use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use MauticPlugin\CronSchedulerBundle\Entity\JobExecutionLog;
use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

class SchedulerService
{
    public const LOCKED_MESSAGE = 'Job is locked by another process';

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var KernelInterface
     */
    private $kernel;

    private ?Application $application = null;

    /**
     * System timezone for user display.
     *
     * @var DateTimeHelper
     */
    private $dateTimeHelper;

    /**
     * @var FailureAlertService
     */
    private $failureAlertService;

    public function __construct(EntityManager $em, KernelInterface $kernel, FailureAlertService $failureAlertService)
    {
        $this->em                  = $em;
        $this->kernel              = $kernel;
        $this->dateTimeHelper      = new DateTimeHelper(null, null, 'local');
        $this->failureAlertService = $failureAlertService;
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
            && $job->getLastRunAt()->format('Y-m-d H:i') === $now->format('Y-m-d H:i')
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
        if (
            $lastRun &&
            $lastRun->format('Y-m-d H:i') === $now->format('Y-m-d H:i')
        ) {
            return false;
        }

        try {
            $cron = new CronExpression($job->getCronNotation());
            if (!$cron->isDue($now)) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function meetsTimeRestrictions(ScheduledJob $job, \DateTime $now): bool
    {
        if ($job->getTriggerHour()) {
            $nowMinutes =
                ((int) $now->format('H')) * 60 + (int) $now->format('i');

            $targetMinutes =
                ((int) $job->getTriggerHour()->format('H')) * 60 +
                (int) $job->getTriggerHour()->format('i');

            return abs($nowMinutes - $targetMinutes) <= 1;
        }

        return true;
    }

    /**
     * Check if current day meets day-of-week restrictions.
     */
    private function meetsDayRestrictions(ScheduledJob $job, \DateTime $now): bool
    {
        $allowedDays = $job->getTriggerRestrictedDaysOfWeek();

        if (empty($allowedDays)) {
            return true;
        }

        $currentDayOfWeek = (int) $now->format('w');

        if (in_array(-1, $allowedDays, true)) {
            return $currentDayOfWeek >= 1 && $currentDayOfWeek <= 5;
        }

        return in_array($currentDayOfWeek, $allowedDays, true);
    }

    private function isNthValidDay(ScheduledJob $job, \DateTime $now): bool
    {
        $last = $job->getLastRunAt();
        if (!$last) {
            return $this->meetsDayRestrictions($job, $now);
        }

        $allowedDays = $job->getTriggerRestrictedDaysOfWeek();
        $interval    = $job->getTriggerInterval();

        if (empty($allowedDays) || !$interval) {
            return true;
        }

        // Count valid days since last run
        $validDayCount = 0;
        $cursor        = clone $last;
        $cursor->setTime(0, 0, 0); // Start of day
        $attempts    = 0;
        $maxAttempts = 366;

        $nowDate = clone $now;
        $nowDate->setTime(0, 0, 0);

        while ($cursor < $nowDate && $attempts < $maxAttempts) {
            $cursor->modify('+1 day');

            $dayOfWeek = (int) $cursor->format('w');

            // Handle special value -1 for weekdays
            if (in_array(-1, $allowedDays, true)) {
                if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                    ++$validDayCount;
                }
            } elseif (in_array($dayOfWeek, $allowedDays, true)) {
                ++$validDayCount;
            }
            ++$attempts;
        }

        return $validDayCount >= $interval && $this->meetsDayRestrictions($job, $now);
    }

    public function runJobManually(ScheduledJob $job)
    {
        $startTime = microtime(true);
        $startedAt = $this->dateTimeHelper->getLocalDateTime();

        try {
            $result       = $this->runJobCommand($job);
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

        try {
            $result       = $this->runJobCommand($job);
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

            if ($log && !$success) {
                $failedLog = $log;
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

            if ($log) {
                $failedLog = $log;
            }

            throw new \Exception("Failed to trigger job '{$job->getName()}': " . $e->getMessage());
        } finally {
            $job->setLockedAt(null);
            $this->em->flush();

            if ($failedLog) {
                $this->failureAlertService->send($job, $failedLog);
            }
        }
    }

    public function deleteOlderLogs(int $retentionDays): int
    {
        if ($retentionDays <= 0) {
            return 0;
        }

        $cutoff = $this->dateTimeHelper->getLocalDateTime()->modify(sprintf('-%d days', $retentionDays));

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
        if (!$job->getCronNotation()) {
            return null;
        }

        try {
            $cron = new CronExpression($job->getCronNotation());
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
     * Run the job command and return its exit code and output.
     *
     * Arguments are validated against the command's own options only, not
     * Symfony's global console options (--env, --no-debug, --quiet, etc.).
     *
     * @return array{exitCode: int, output: string}
     */
    private function runJobCommand(ScheduledJob $job): array
    {
        $application = $this->getConsoleApplication();
        $commandName = trim((string) $job->getCommand());

        if ('' === $commandName) {
            throw new \RuntimeException('Job command is empty.');
        }

        $command = $application->find($commandName);
        $this->assertJobArgumentsAreValid($command, $job->getArguments());

        $input = new StringInput(trim($commandName.' '.$job->getArguments()));
        $input->setInteractive(false);

        $output   = new BufferedOutput();
        $exitCode = $application->run($input, $output);

        return [
            'exitCode' => $exitCode,
            'output'   => $output->fetch(),
        ];
    }

    private function getConsoleApplication(): Application
    {
        if (null === $this->application) {
            $this->application = new Application($this->kernel);
            $this->application->setAutoExit(false);
            $this->application->setCatchExceptions(false);
        }

        return $this->application;
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
        $applicationDefinition = $this->getConsoleApplication()->getDefinition();
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
     * Acquire lock for job execution
     * Prevents concurrent execution of the same job.
     */
    private function acquireLock(ScheduledJob $job): bool
    {
        $lockedAt = $job->getLockedAt();

        if ($lockedAt) {
            $thirtyMinutesAgo = $this->dateTimeHelper->getLocalDateTime()->modify('-30 minutes');
            if ($lockedAt > $thirtyMinutesAgo) {
                // Job is still locked
                return false;
            }
        }

        $job->setLockedAt($this->dateTimeHelper->getLocalDateTime());
        $this->em->flush();

        return true;
    }
}
