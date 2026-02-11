<?php

namespace MauticPlugin\CronSchedulerBundle\Queue;

use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Helper\CoreParametersHelper;

class QueueManagerFactory
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var CoreParametersHelper
     */
    private $coreParametersHelper;

    /**
     * @var QueueManagerInterface[]
     */
    private $managers = [];

    public function __construct(EntityManager $em, CoreParametersHelper $coreParametersHelper)
    {
        $this->em = $em;
        $this->coreParametersHelper = $coreParametersHelper;
    }

    /**
     * Get queue manager for a specific type
     *
     * @param string $queueType
     * @return QueueManagerInterface
     */
    public function getManager(string $queueType = null): QueueManagerInterface
    {
        if (!$queueType) {
            $queueType = $this->coreParametersHelper->get('job_queue_type', 'database');
        }

        if (!isset($this->managers[$queueType])) {
            $this->managers[$queueType] = $this->createManager($queueType);
        }

        return $this->managers[$queueType];
    }

    /**
     * Create a queue manager instance
     *
     * @param string $queueType
     * @return QueueManagerInterface
     */
    private function createManager(string $queueType): QueueManagerInterface
    {
        switch ($queueType) {
            case 'database':
                return new DatabaseQueueManager($this->em);
            
            case 'redis':
                // Future implementation
                // return new RedisQueueManager($this->redis);
                throw new \RuntimeException('Redis queue manager not yet implemented');
            
            case 'sqs':
                // Future implementation
                // return new SqsQueueManager($this->sqsClient);
                throw new \RuntimeException('SQS queue manager not yet implemented');
            
            case 'rabbitmq':
                // Future implementation - can integrate with existing QueueBundle
                // return new RabbitMqQueueManager($this->rabbitMq);
                throw new \RuntimeException('RabbitMQ queue manager not yet implemented');
            
            default:
                throw new \InvalidArgumentException("Unknown queue type: {$queueType}");
        }
    }
}
