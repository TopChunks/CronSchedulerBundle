<?php

namespace MauticPlugin\CronSchedulerBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\PluginBundle\Event\PluginInstallEvent;
use Mautic\PluginBundle\PluginEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;

class InstallSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::ON_PLUGIN_INSTALL => ['onPluginInstall', 0],
        ];
    }

    public function onPluginInstall(PluginInstallEvent $event): void
    {
        if ($event->getPlugin()->getName() !== 'Scheduled Jobs') {
            return;
        }

        $defaultCrons = [
            [
                'name'        => 'Segment Rebuild',
                'command'     => 'mautic:segments:update',
                'triggerMode' => 'cron',
                'cronNotation'  => '0,15,30,45 * * * *',
                'isPublished'   => 1,
                'priority'      => 10,
                'systemCron'  => 0
            ],
            [
                'name'        => 'Campaign Update',
                'command'     => 'mautic:campaigns:update',
                'triggerMode' => 'cron',
                'cronNotation'  => '5,20,35,50 * * * *',
                'isPublished'   => 1,
                'priority'      => 9,
                'systemCron'  => 0
            ],
            [
                'name'        => 'Campaign Trigger',
                'command'     => 'mautic:campaigns:trigger',
                'triggerMode' => 'cron',
                'cronNotation'  => '10,25,40,55 * * * *',
                'isPublished'   => 1,
                'priority'      => 8,
                'systemCron'  => 0
            ],
            [
                'name'        => 'Cleanup Old Logs Data',
                'command'     => 'mautic:delete:joblogs',
                'triggerMode' => 'cron',
                'cronNotation'  => '0 3 * * *',
                'isPublished'   => 1,
                'priority'      => 0,
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
            $job->setPriority($cronData['priority']);

            $this->em->persist($job);
        }

        $this->em->flush();
    }
}
