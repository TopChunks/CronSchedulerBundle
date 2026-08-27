<?php

declare(strict_types=1);

namespace MauticPlugin\CronSchedulerBundle\Event;

use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;
use Symfony\Contracts\EventDispatcher\Event;

class JobFailedEvent extends Event
{
    public function __construct(
        private ScheduledJob $job,
        private array $payload
    ) {
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
