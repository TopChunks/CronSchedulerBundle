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
                'name'        => 'Daily Campaign Sync',
                'command'     => 'app:campaigns:update',
                'triggerMode' => 'cron',
                'cronNotation'  => '* */1 * * *',
                'isPublished'   => 1,
                'systemCron'  => 0
            ],
            [
                'name'        => 'Daily Campaign Trigger',
                'command'     => 'app:campaigns:trigger',
                'triggerMode' => 'cron',
                'cronNotation'  => '* */3 * * *',
                'isPublished'   => 1,
                'systemCron'  => 0
            ],
            [
                'name'        => 'Segment Rebuild',
                'command'     => 'app:segments:update',
                'triggerMode' => 'cron',
                'cronNotation'  => '*/5 * * * *',
                'isPublished'   => 1,
                'systemCron'  => 0
            ],
            [
                'name'        => 'Cleanup Old Logs Data',
                'command'     => 'cronscheduler:delete:cronlogs',
                'triggerMode' => 'cron',
                'cronNotation'  => '0 3 * * *',
                'isPublished'   => 1,
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
