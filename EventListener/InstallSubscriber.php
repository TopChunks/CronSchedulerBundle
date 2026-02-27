<?php

namespace MauticPlugin\CronSchedulerBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\PluginBundle\Event\PluginInstallEvent;
use Mautic\PluginBundle\PluginEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;

class InstallSubscriber implements EventSubscriberInterface
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public static function getSubscribedEvents()
    {
        return [
            PluginEvents::ON_PLUGIN_INSTALL => ['onPluginInstall', 0],
        ];
    }

    public function onPluginInstall(PluginInstallEvent $event)
    {
        if ($event->getPlugin()->getName() !== 'Cron Scheduler') {
            return;
        }

        $defaultCrons = [
            [
                'name'        => 'Daily Campaign Sync',
                'command'     => 'mautic:campaigns:update',
                'cronNotation' => '* */1 * * *',
                'triggerMode' => 'cron',
                'systemCron'  => 0
            ],
            [
                'name'        => 'Daily Campaign Trigger',
                'command'     => 'mautic:campaigns:trigger',
                'cronNotation' => '* */3 * * *',
                'triggerMode' => 'cron',
                'systemCron'  => 0
            ],
            [
                'name'        => 'Segment Rebuild',
                'command'     => 'mautic:segments:update',
                'cronNotation' => '*/5 * * * *',
                'triggerMode' => 'cron',
                'systemCron'  => 0
            ],
            [
                'name'        => 'Cleanup Old Logs Data',
                'command'     => 'cronscheduler:delete:cronlogs',
                'cronNotation' => '0 3 * * *',
                'triggerMode' => 'cron',
                'systemCron'  => 1
            ],
        ];

        foreach ($defaultCrons as $cronData) {
            $exists = $this->em->getRepository(ScheduledJob::class)
                ->findOneBy(['command' => $cronData['command']]);

            if ($exists) {
                continue;
            }

            $job = new ScheduledJob();
            $job->setName($cronData['name']);
            $job->setCommand($cronData['command']);
            $job->setCronNotation($cronData['cronNotation']);
            $job->setTriggerMode($cronData['triggerMode']);
            $job->setSystemCron($cronData['systemCron']);
            $job->setIsPublished(true);
            $job->setRunOnRecovery(false);

            $this->em->persist($job);
        }

        $this->em->flush();
    }
}
