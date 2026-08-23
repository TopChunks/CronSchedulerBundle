<?php

declare(strict_types=1);

namespace MauticPlugin\CronSchedulerBundle\Event;

use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;
use Symfony\Component\EventDispatcher\Event;

class JobFailedEvent extends Event
{
    /**
     * @var ScheduledJob
     */
    private $job;

    /**
     * @var array
     */
    private $payload;

    public function __construct(ScheduledJob $job, array $payload)
    {
        $this->job     = $job;
        $this->payload = $payload;
    }

    public function getJob(): ScheduledJob
    {
        return $this->job;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }
}
