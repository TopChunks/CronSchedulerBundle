<?php

declare(strict_types=1);

namespace MauticPlugin\CronSchedulerBundle\EventListener;

use Mautic\WebhookBundle\Event\WebhookBuilderEvent;
use Mautic\WebhookBundle\Model\WebhookModel;
use Mautic\WebhookBundle\WebhookEvents;
use MauticPlugin\CronSchedulerBundle\CronSchedulerEvents;
use MauticPlugin\CronSchedulerBundle\Event\JobFailedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class WebhookSubscriber implements EventSubscriberInterface
{
    /**
     * @var WebhookModel
     */
    private $webhookModel;

    public function __construct(WebhookModel $webhookModel)
    {
        $this->webhookModel = $webhookModel;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WebhookEvents::WEBHOOK_ON_BUILD    => ['onWebhookBuild', 0],
            CronSchedulerEvents::JOB_FAILED    => ['onJobFailed', 0],
        ];
    }

    public function onWebhookBuild(WebhookBuilderEvent $event): void
    {
        $event->addEvent(
            CronSchedulerEvents::JOB_FAILED,
            [
                'label'       => 'mautic.cron_scheduler.webhook.event.job_failed',
                'description' => 'mautic.cron_scheduler.webhook.event.job_failed_desc',
            ]
        );
    }

    public function onJobFailed(JobFailedEvent $event): void
    {
        $this->webhookModel->queueWebhooksByType(
            CronSchedulerEvents::JOB_FAILED,
            $event->getPayload()
        );
    }
}
