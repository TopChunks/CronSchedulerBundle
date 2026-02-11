<?php

namespace MauticPlugin\CronSchedulerBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

class JobQueueRepository extends CommonRepository
{
    public function getTableAlias(): string
    {
        return 'jq';
    }

    /**
     * Find pending jobs that are due to run
     *
     * @param int $limit
     * @return JobQueue[]
     */
    public function findDueJobs(int $limit = 100): array
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $qb = $this->createQueryBuilder('jq');

        return $qb->where('jq.status = :status')
            ->andWhere('(jq.triggerAt IS NULL OR jq.triggerAt <= :now)')
            ->setParameter('status', JobQueue::STATUS_PENDING)
            ->setParameter('now', $now)
            ->orderBy('jq.priority', 'DESC')
            ->addOrderBy('jq.triggerAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find jobs by scheduled job
     *
     * @param ScheduledJob $scheduledJob
     * @return JobQueue[]
     */
    public function findByScheduledJob(ScheduledJob $scheduledJob): array
    {
        return $this->findBy(['scheduledJob' => $scheduledJob]);
    }

    /**
     * Find pending jobs by queue type
     *
     * @param string $queueType
     * @param int $limit
     * @return JobQueue[]
     */
    public function findPendingByQueueType(string $queueType, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('jq');

        return $qb->where('jq.status = :status')
            ->andWhere('jq.queueType = :queueType')
            ->setParameter('status', JobQueue::STATUS_PENDING)
            ->setParameter('queueType', $queueType)
            ->orderBy('jq.priority', 'DESC')
            ->addOrderBy('jq.triggerAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find failed jobs that can be retried
     *
     * @param int $limit
     * @return JobQueue[]
     */
    public function findRetryableJobs(int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('jq');

        return $qb->where('jq.status = :status')
            ->andWhere('jq.attempts < jq.maxAttempts')
            ->setParameter('status', JobQueue::STATUS_FAILED)
            ->orderBy('jq.attempts', 'ASC')
            ->addOrderBy('jq.triggerAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Clean up old completed/failed jobs
     *
     * @param int $daysOld
     * @return int Number of deleted records
     */
    public function deleteOldJobs(int $daysOld = 30): int
    {
        $cutoff = new \DateTime(sprintf('-%d days', $daysOld), new \DateTimeZone('UTC'));
        $qb = $this->createQueryBuilder('jq');

        $qb->delete()
            ->where('jq.status IN (:statuses)')
            ->andWhere('jq.completedAt < :cutoff')
            ->setParameter('statuses', [JobQueue::STATUS_COMPLETED, JobQueue::STATUS_CANCELLED])
            ->setParameter('cutoff', $cutoff);

        return $qb->getQuery()->execute();
    }
}
