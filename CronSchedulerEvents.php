<?php

declare(strict_types=1);

namespace MauticPlugin\CronSchedulerBundle;

final class CronSchedulerEvents
{
    /**
     * Dispatched when a scheduled job fails and failure alerts are enabled for that job.
     *
     * The listener receives a MauticPlugin\CronSchedulerBundle\Event\JobFailedEvent instance.
     *
     * @var string
     */
    public const JOB_FAILED = 'mautic.cronscheduler_job_failed';
}
