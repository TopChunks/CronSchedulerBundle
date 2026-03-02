<?php

namespace MauticPlugin\CronSchedulerBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

class ScheduledJobRepository extends CommonRepository
{
    /**
     * Find all published scheduled jobs
     *
     * @return ScheduledJob[]
     */
    public function findActive()
    {
        $now = new \DateTimeImmutable();
        $qb = $this->createQueryBuilder('s');

        $qb->where('(s.publishUp IS NULL OR s.publishUp <= :now)')
            ->andWhere('(s.publishDown IS NULL OR s.publishDown >= :now)')
            ->setParameter('now', $now);

        return $qb->getQuery()->getResult();
    }

    public function getTableAlias(): string
    {
        return 'sj';
    }

    /**
     * Find jobs that are due to run
     *
     * @return ScheduledJob[]
     */
    public function findDue()
    {
        $now = new \DateTimeImmutable();
        $qb = $this->createQueryBuilder('s');

        return $qb->where('(s.publishUp IS NULL OR s.publishUp <= :now)')
            ->andWhere('(s.publishDown IS NULL OR s.publishDown >= :now)')
            ->andWhere('s.nextRunAt IS NULL OR s.nextRunAt <= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a job by command name
     *
     * @param string $command
     * @return ScheduledJob|null
     */
    public function findByCommand(string $command): ?ScheduledJob
    {
        return $this->findOneBy(['command' => $command]);
    }
}
