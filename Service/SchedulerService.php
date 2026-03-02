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

class SchedulerService
{
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
     * @var \DateTimeZone
     */
    private $systemTimezone;

    public function __construct(EntityManager $em, KernelInterface $kernel)
    {
        $this->em     = $em;
        $this->kernel = $kernel;
        // Store system timezone, but all DB operations will be in UTC
        $this->systemTimezone = new \DateTimeZone(date_default_timezone_get());
    }

    public function isDue(ScheduledJob $job): bool
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));

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

        if (
            $job->getRunOnRecovery()
            && $job->getNextRunAt()
            && $job->getNextRunAt() < $now
        ) {
            $lastRun = $job->getLastRunAt();
            if (!$lastRun || $lastRun < $job->getNextRunAt()) {
                return true;
            }
        }

        if ($job->getNextRunAt() && $now < $job->getNextRunAt()) {
            return false;
        }

        $unit = $job->getTriggerIntervalUnit();

        if ('i' === $unit || 'h' === $unit) {
            return $this->meetsTimeRestrictions($job, $now);
        }

        return $this->meetsTimeRestrictions($job, $now)
            && $this->isNthValidDay($job, $now);
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

        if ($job->getRunOnRecovery() && $job->getNextRunAt() && $job->getNextRunAt() < $now) {
            $lastRun = $job->getLastRunAt();
            if (!$lastRun || $lastRun < $job->getNextRunAt()) {
                return true;
            }
        }

        try {
            $cron = CronExpression::factory($job->getCronNotation());

            if (!$cron->isDue($now)) {
                return false;
            }

            return $this->meetsTimeRestrictions($job, $now)
                && $this->meetsDayRestrictions($job, $now);
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
        $startedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

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

            $completedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $duration    = microtime(true) - $startTime;

            if ($log) {
                $log->setCompletedAt($completedAt);
                $log->setExitCode($exitCode);
                $log->setOutput($outputString);
                $log->setDuration($duration);
                $log->setIsSuccess($success);
            }

            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            $job->setLastRunAt($now);

            $nextRunAt = $this->calculateNextRunTime($job, $now);
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
            $completedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
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

            $now = new \DateTime('now', new \DateTimeZone('UTC'));
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

        $cutoff = new \DateTimeImmutable(
            sprintf('-%d days', $retentionDays),
            new \DateTimeZone('UTC')
        );

        /** @var \MauticPlugin\CronSchedulerBundle\Entity\JobExecutionLogRepository $repo */
        $repo = $this->em->getRepository(JobExecutionLog::class);

        return $repo->deleteOlderLogs($cutoff);
    }

    public function calculateNextRunTime(ScheduledJob $job, \DateTime $now = null): ?\DateTime
    {
        if (!$now) {
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
        }

        switch ($job->getTriggerMode()) {
            case 'cron':
                return $this->calculateNextCronRun($job, $now);

            case 'date':
                return null; // one-time job

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
            $cron = CronExpression::factory($job->getCronNotation());
            $next = $cron->getNextRunDate($now);

            $maxAttempts = 366; // Prevent infinite loops
            $attempts    = 0;

            while ($attempts < $maxAttempts) {
                if ($this->meetsTimeRestrictions($job, $next) && $this->meetsDayRestrictions($job, $next)) {
                    return $next;
                }

                $next = $cron->getNextRunDate($next);
                ++$attempts;
            }

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

        if ('i' === $unit || 'h' === $unit) {
            $next = clone $now;

            $normalizedUnit = $this->normalizeIntervalUnit($unit);
            $next->modify(sprintf('+%d %s', $interval, $normalizedUnit));

            if ($job->getTriggerHour()) {
                $time = $job->getTriggerHour();
                $next->setTime(
                    (int) $time->format('H'),
                    (int) $time->format('i')
                );
            }

            return $next;
        }

        return $this->calculateNextRestrictedIntervalRun($job, $now);
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

    private function calculateNextRestrictedIntervalRun(ScheduledJob $job, \DateTime $now): ?\DateTime
    {
        $interval    = $job->getTriggerInterval();
        $unit        = $job->getTriggerIntervalUnit();
        $allowedDays = $job->getTriggerRestrictedDaysOfWeek();

        $next = clone $now;

        // If no day restrictions, simple calculation
        if (empty($allowedDays)) {
            $normalizedUnit = $this->normalizeIntervalUnit($unit);
            $next->modify(sprintf('+%d %s', $interval, $normalizedUnit));

            // Apply time restriction
            if ($job->getTriggerHour()) {
                $time = $job->getTriggerHour();
                $next->setTime((int) $time->format('H'), (int) $time->format('i'));
            }

            return $next;
        }

        $validDayCount = 0;
        $maxAttempts   = 366; // Safety limit
        $attempts      = 0;

        while ($validDayCount < $interval && $attempts < $maxAttempts) {
            $next->modify('+1 day');

            $dayOfWeek = (int) $next->format('w');

            if (in_array(-1, $allowedDays, true)) {
                if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                    ++$validDayCount;
                }
            } elseif (in_array($dayOfWeek, $allowedDays, true)) {
                ++$validDayCount;
            }

            ++$attempts;
        }

        if ($job->getTriggerHour()) {
            $time = $job->getTriggerHour();
            $next->setTime((int) $time->format('H'), (int) $time->format('i'));
        }

        return $next;
    }

    /**
     * Acquire lock for job execution
     * Prevents concurrent execution of the same job.
     */
    private function acquireLock(ScheduledJob $job): bool
    {
        $lockedAt = $job->getLockedAt();

        if ($lockedAt) {
            $thirtyMinutesAgo = new \DateTime('-30 minutes', new \DateTimeZone('UTC'));
            if ($lockedAt > $thirtyMinutesAgo) {
                // Job is still locked
                return false;
            }
        }

        $job->setLockedAt(new \DateTime('now', new \DateTimeZone('UTC')));
        $this->em->flush();

        return true;
    }
}
