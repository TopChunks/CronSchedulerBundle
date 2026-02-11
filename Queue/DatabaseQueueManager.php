<?php

namespace MauticPlugin\CronSchedulerBundle\Queue;

use Doctrine\ORM\EntityManager;
use MauticPlugin\CronSchedulerBundle\Entity\JobQueue;
use MauticPlugin\CronSchedulerBundle\Entity\JobQueueRepository;

class DatabaseQueueManager implements QueueManagerInterface
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var JobQueueRepository
     */
    private $repository;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
        $this->repository = $em->getRepository(JobQueue::class);
    }

    public function push(JobQueue $jobQueue): bool
    {
        try {
            $jobQueue->setQueueType('database');
            $this->em->persist($jobQueue);
            $this->em->flush();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function pop(): ?JobQueue
    {
        // Start transaction for SELECT FOR UPDATE locking
        $this->em->beginTransaction();
        
        try {
            // Use SELECT FOR UPDATE to prevent race conditions when multiple workers run
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            $conn = $this->em->getConnection();
            $tableName = $this->em->getClassMetadata(JobQueue::class)->getTableName();
            
            // Use raw SQL with SELECT FOR UPDATE for atomic locking
            $sql = "
                SELECT jq.* 
                FROM {$tableName} jq
                WHERE jq.status = :status 
                  AND (jq.trigger_at IS NULL OR jq.trigger_at <= :now)
                ORDER BY jq.priority DESC, jq.trigger_at ASC
                LIMIT 1
                FOR UPDATE
            ";
            
            $stmt = $conn->prepare($sql);
            $result = $stmt->executeQuery([
                'status' => JobQueue::STATUS_PENDING,
                'now' => $now->format('Y-m-d H:i:s'),
            ]);
            
            $row = $result->fetchAssociative();
            
            if (!$row) {
                $this->em->rollback();
                return null;
            }
            
            // Find the entity by ID (row is locked)
            $job = $this->em->find(JobQueue::class, $row['id']);
            
            if (!$job) {
                $this->em->rollback();
                return null;
            }
            
            // Row is locked, safe to update
            $job->setStatus(JobQueue::STATUS_PROCESSING);
            $job->setStartedAt($now);
            $this->em->flush();
            $this->em->commit();
            
            return $job;
        } catch (\Exception $e) {
            // If lock fails (e.g., timeout or deadlock), rollback and return null
            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->rollback();
            }
            return null;
        }
    }

    public function getQueueType(): string
    {
        return 'database';
    }

    public function isAvailable(): bool
    {
        return true; // Database is always available
    }
}
