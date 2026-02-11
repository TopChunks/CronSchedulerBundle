<?php

namespace MauticPlugin\CronSchedulerBundle\Queue;

use MauticPlugin\CronSchedulerBundle\Entity\JobQueue;

interface QueueManagerInterface
{
    /**
     * Push a job to the queue
     *
     * @param JobQueue $jobQueue
     * @return bool
     */
    public function push(JobQueue $jobQueue): bool;

    /**
     * Pop a job from the queue
     *
     * @return JobQueue|null
     */
    public function pop(): ?JobQueue;

    /**
     * Get queue type name
     *
     * @return string
     */
    public function getQueueType(): string;

    /**
     * Check if queue is available
     *
     * @return bool
     */
    public function isAvailable(): bool;
}
