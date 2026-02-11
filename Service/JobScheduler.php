<?php

namespace MauticPlugin\CronSchedulerBundle\Service;

use Doctrine\ORM\EntityManager;
use MauticPlugin\CronSchedulerBundle\Entity\JobQueue;
use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;
use MauticPlugin\CronSchedulerBundle\Queue\QueueManagerFactory;

class JobScheduler
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var QueueManagerFactory
     */
    private $queueManagerFactory;

    public function __construct(EntityManager $em, QueueManagerFactory $queueManagerFactory)
    {
        $this->em = $em;
        $this->queueManagerFactory = $queueManagerFactory;
    }

    /**
     * Schedule a job to run at a specific time
     *
     * @param string $name
     * @param string $command
     * @param string|null $arguments
     * @param \DateTime|null $triggerAt
     * @param int $priority
     * @param ScheduledJob|null $scheduledJob
     * @return JobQueue
     */
    public function scheduleJob(
        string $name,
        string $command,
        ?string $arguments = null,
        ?\DateTime $triggerAt = null,
        int $priority = 0,
        ?ScheduledJob $scheduledJob = null
    ): JobQueue {
        $jobQueue = new JobQueue();
        $jobQueue->setName($name);
        $jobQueue->setCommand($command);
        $jobQueue->setArguments($arguments);
        $jobQueue->setTriggerAt($triggerAt ?? new \DateTime('now', new \DateTimeZone('UTC')));
        $jobQueue->setPriority($priority);
        $jobQueue->setStatus(JobQueue::STATUS_PENDING);
        
        if ($scheduledJob) {
            $jobQueue->setScheduledJob($scheduledJob);
        }

        $queueManager = $this->queueManagerFactory->getManager();
        $queueManager->push($jobQueue);

        return $jobQueue;
    }

    /**
     * Schedule a callback function to run at a specific time
     * 
     * Note: Use service:method format for callback (e.g., 'mautic.email.model.email:sendEmail')
     *
     * @param string $name
     * @param string $callback Service and method name in format 'service:method'
     * @param array $args Arguments to pass to the callback
     * @param \DateTime|null $triggerAt
     * @param int $priority
     * @return JobQueue
     */
    public function scheduleCallback(
        string $name,
        string $callback,
        array $args = [],
        ?\DateTime $triggerAt = null,
        int $priority = 0
    ): JobQueue {
        $jobQueue = new JobQueue();
        $jobQueue->setName($name);
        $jobQueue->setTriggerAt($triggerAt ?? new \DateTime('now', new \DateTimeZone('UTC')));
        $jobQueue->setPriority($priority);
        $jobQueue->setStatus(JobQueue::STATUS_PENDING);
        
        // Store callback in payload (service:method format)
        $payload = [
            'callback' => $callback,
            'args' => $args,
        ];
        $jobQueue->setPayload($payload);

        $queueManager = $this->queueManagerFactory->getManager();
        $queueManager->push($jobQueue);

        return $jobQueue;
    }

    /**
     * Create job queues from scheduled jobs based on their intervals
     *
     * @param ScheduledJob $scheduledJob
     * @return JobQueue|null
     */
    public function createJobQueueFromScheduledJob(ScheduledJob $scheduledJob): ?JobQueue
    {
        // Only create if job has an interval (not one-time date jobs)
        if ($scheduledJob->getTriggerMode() === 'date' && $scheduledJob->getLastRunAt()) {
            return null; // One-time job already executed
        }

        $nextRunAt = $scheduledJob->getNextRunAt();
        if (!$nextRunAt) {
            return null;
        }

        $jobQueue = new JobQueue();
        $jobQueue->setName($scheduledJob->getName());
        $jobQueue->setCommand($scheduledJob->getCommand());
        $jobQueue->setArguments($scheduledJob->getArguments());
        $jobQueue->setTriggerAt($nextRunAt);
        $jobQueue->setPriority(0);
        $jobQueue->setStatus(JobQueue::STATUS_PENDING);
        $jobQueue->setScheduledJob($scheduledJob);

        $queueManager = $this->queueManagerFactory->getManager();
        $queueManager->push($jobQueue);

        return $jobQueue;
    }

    /**
     * Cancel a pending job
     *
     * @param JobQueue $jobQueue
     * @return bool
     */
    public function cancelJob(JobQueue $jobQueue): bool
    {
        if ($jobQueue->getStatus() === JobQueue::STATUS_PENDING) {
            $jobQueue->setStatus(JobQueue::STATUS_CANCELLED);
            $jobQueue->setCompletedAt(new \DateTime('now', new \DateTimeZone('UTC')));
            $this->em->persist($jobQueue);
            $this->em->flush();
            return true;
        }

        return false;
    }
}
