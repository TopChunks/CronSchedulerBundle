<?php

namespace MauticPlugin\CronSchedulerBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata as MappingClassMetadata;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\FormEntity;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class JobQueue extends FormEntity
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    private $id;
    private $name;
    private $command;
    private $arguments;
    private $triggerAt;
    private $status = self::STATUS_PENDING;
    private $scheduledJob;
    private $priority = 0;
    private $attempts = 0;
    private $maxAttempts = 3;
    private $errorMessage;
    private $startedAt;
    private $completedAt;
    private $queueType = 'database'; // database, redis, sqs, rabbitmq
    private $queueId; // External queue ID (for Redis/SQS/RabbitMQ)
    private $payload; // Additional data for job execution

    public static function loadMetadata(MappingClassMetadata $metadata)
    {
        $builder = new ClassMetadataBuilder($metadata);
        $builder
            ->setTable('job_queues')
            ->setCustomRepositoryClass('MauticPlugin\CronSchedulerBundle\Entity\JobQueueRepository');
        
        $builder->addId();
        $builder->createField('name', Types::STRING)->columnName('name')->build();
        $builder->createField('command', Types::STRING)->columnName('command')->build();
        $builder->createField('arguments', Types::TEXT)->columnName('arguments')->nullable()->build();
        $builder->createField('triggerAt', 'datetime')
            ->columnName('trigger_at')
            ->nullable()
            ->build();
        $builder->createField('status', Types::STRING)
            ->columnName('status')
            ->length(20)
            ->build();
        $builder->createField('priority', Types::INTEGER)
            ->columnName('priority')
            ->build();
        $builder->createField('attempts', Types::INTEGER)
            ->columnName('attempts')
            ->build();
        $builder->createField('maxAttempts', Types::INTEGER)
            ->columnName('max_attempts')
            ->build();
        $builder->createField('errorMessage', Types::TEXT)
            ->columnName('error_message')
            ->nullable()
            ->build();
        $builder->createField('startedAt', 'datetime')
            ->columnName('started_at')
            ->nullable()
            ->build();
        $builder->createField('completedAt', 'datetime')
            ->columnName('completed_at')
            ->nullable()
            ->build();
        $builder->createField('queueType', Types::STRING)
            ->columnName('queue_type')
            ->length(20)
            ->build();
        $builder->createField('queueId', Types::STRING)
            ->columnName('queue_id')
            ->nullable()
            ->build();
        $builder->createField('payload', Types::JSON)
            ->columnName('payload')
            ->nullable()
            ->build();

        $builder->createManyToOne('scheduledJob', ScheduledJob::class)
            ->addJoinColumn('scheduled_job_id', 'id', true, false, 'SET NULL')
            ->build();
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint(
            'name',
            new NotBlank([
                'message' => 'mautic.job_queue.form.name.notblank',
            ])
        );
        $metadata->addPropertyConstraint(
            'command',
            new NotBlank([
                'message' => 'mautic.job_queue.form.command.notblank',
            ])
        );
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->isChanged('id', $id);
        $this->id = $id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->isChanged('name', $name);
        $this->name = $name;
    }

    public function getCommand()
    {
        return $this->command;
    }

    public function setCommand($command)
    {
        $this->isChanged('command', $command);
        $this->command = $command;
    }

    public function getArguments()
    {
        return $this->arguments;
    }

    public function setArguments($arguments)
    {
        $this->isChanged('arguments', $arguments);
        $this->arguments = $arguments;
    }

    public function getTriggerAt()
    {
        return $this->triggerAt;
    }

    public function setTriggerAt($triggerAt)
    {
        $this->isChanged('triggerAt', $triggerAt);
        $this->triggerAt = $triggerAt;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->isChanged('status', $status);
        $this->status = $status;
    }

    public function getScheduledJob()
    {
        return $this->scheduledJob;
    }

    public function setScheduledJob($scheduledJob)
    {
        $this->isChanged('scheduledJob', $scheduledJob);
        $this->scheduledJob = $scheduledJob;
    }

    public function getPriority()
    {
        return $this->priority;
    }

    public function setPriority($priority)
    {
        $this->isChanged('priority', $priority);
        $this->priority = $priority;
    }

    public function getAttempts()
    {
        return $this->attempts;
    }

    public function setAttempts($attempts)
    {
        $this->isChanged('attempts', $attempts);
        $this->attempts = $attempts;
    }

    public function incrementAttempts()
    {
        $this->setAttempts($this->attempts + 1);
    }

    public function getMaxAttempts()
    {
        return $this->maxAttempts;
    }

    public function setMaxAttempts($maxAttempts)
    {
        $this->isChanged('maxAttempts', $maxAttempts);
        $this->maxAttempts = $maxAttempts;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    public function setErrorMessage($errorMessage)
    {
        $this->isChanged('errorMessage', $errorMessage);
        $this->errorMessage = $errorMessage;
    }

    public function getStartedAt()
    {
        return $this->startedAt;
    }

    public function setStartedAt($startedAt)
    {
        $this->isChanged('startedAt', $startedAt);
        $this->startedAt = $startedAt;
    }

    public function getCompletedAt()
    {
        return $this->completedAt;
    }

    public function setCompletedAt($completedAt)
    {
        $this->isChanged('completedAt', $completedAt);
        $this->completedAt = $completedAt;
    }

    public function getQueueType()
    {
        return $this->queueType;
    }

    public function setQueueType($queueType)
    {
        $this->isChanged('queueType', $queueType);
        $this->queueType = $queueType;
    }

    public function getQueueId()
    {
        return $this->queueId;
    }

    public function setQueueId($queueId)
    {
        $this->isChanged('queueId', $queueId);
        $this->queueId = $queueId;
    }

    public function getPayload()
    {
        return $this->payload;
    }

    public function setPayload($payload)
    {
        $this->isChanged('payload', $payload);
        $this->payload = $payload;
    }

    public function isDue(): bool
    {
        if (!$this->triggerAt) {
            return true;
        }

        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        return $now >= $this->triggerAt;
    }

    public function canRetry(): bool
    {
        return $this->attempts < $this->maxAttempts;
    }
}
