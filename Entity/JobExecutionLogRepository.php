<?php

namespace MauticPlugin\CronSchedulerBundle\Entity;

use Doctrine\ORM\EntityRepository;

class JobExecutionLogRepository extends EntityRepository
{
    public function deleteOlderLogs(\DateTimeInterface $cutoff): int
    {
        return $this->createQueryBuilder('l')
            ->delete()
            ->where('l.startedAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }

    public function getLatestLogs($limit = 15)
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.scheduledJob', 'j')
            ->addSelect('j')
            ->orderBy('l.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
