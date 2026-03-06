<?php

namespace MauticPlugin\CronSchedulerBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;

class LoadDefaultCrons extends AbstractFixture implements OrderedFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
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
            // OPTIONAL: avoid duplicates
            $existing = $manager->getRepository(ScheduledJob::class)
                ->findOneBy(['command' => $cronData['command']]);

            if ($existing) {
                continue;
            }

            $job = new ScheduledJob();
            $job->setName($cronData['name']);
            $job->setCommand($cronData['command']);
            $job->setCronNotation($cronData['cronNotation']);
            $job->setIsPublished(true);
            $job->setPriority($cronData['priority']);
            $job->setTriggerMode($cronData['triggerMode']);
            $job->setSystemCron($cronData['systemCron']);

            $manager->persist($job);
        }

        $manager->flush();
    }

    public function getOrder(): int
    {
        return 1;
    }
}
