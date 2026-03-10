<?php

namespace MauticPlugin\CronSchedulerBundle\Service;

use Cron\CronExpression;
use Doctrine\ORM\EntityManager;
use MauticPlugin\CronSchedulerBundle\Entity\JobExecutionLog;
use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;
use Symfony\Bundle\FrameworkBundle\Console\Application;
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
            && $job->getLastRunAt()->format('Y-m-d H:i') === $now->format('Y-m-d H:i')
        ) {
            return false;
        }

        if ($job->getNextRunAt() && $now >= $job->getNextRunAt()) {
            return true;
        }

        //If the next run time is not set, then calculate it and return false. So that the next run time is set and the job is scheduled to run in the next run time.
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
            // Use factory to be compatible with cron-expression v3+
            $cron = CronExpression::factory($job->getCronNotation());
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
        try {
            $commandString = trim($job->getCommand() . ' ' . $job->getArguments());
            $commandString .= ' --bypass-locking';
            $input         = new StringInput($commandString);

            if (null === $this->application) {
                $this->application = new Application($this->kernel);
                $this->application->setAutoExit(false);
                $this->application->setCatchExceptions(true);
            }

            $output = new BufferedOutput();

            $exitCode     = $this->application->run($input, $output);
            $outputString = $output->fetch();
            $success      = (0 === $exitCode);

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
            $commandString = trim($job->getCommand() . ' ' . $job->getArguments());
            $input         = new StringInput($commandString);

            if (null === $this->application) {
                $this->application = new Application($this->kernel);
                $this->application->setAutoExit(false);
                $this->application->setCatchExceptions(true);
            }

            $output = new BufferedOutput();

            $exitCode     = $this->application->run($input, $output);
            $outputString = $output->fetch();
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
            // Use factory to be compatible with cron-expression v3+
            $cron = CronExpression::factory($job->getCronNotation());
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
