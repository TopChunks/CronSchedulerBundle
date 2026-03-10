<?php

namespace MauticPlugin\CronSchedulerBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata as MappingClassMetadata;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\CommonEntity;

class JobExecutionLog extends CommonEntity
{
    private $id;
    private $scheduledJob;
    private $startedAt;
    private $completedAt;
    private $isSuccess;
    private $exitCode;
    private $output;
    private $errorMessage;
    private $duration;

    public static function loadMetadata(MappingClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);
        $builder
            ->setTable('job_execution_logs')
            ->setCustomRepositoryClass('MauticPlugin\CronSchedulerBundle\Entity\JobExecutionLogRepository');

        $builder->addBigIntIdField();

        $builder->createManyToOne('scheduledJob', 'ScheduledJob')
            ->inversedBy('executionLogs')
            ->addJoinColumn('scheduled_job_id', 'id', false, false, 'CASCADE')
            ->build();

        $builder->createField('startedAt', Types::DATETIME_MUTABLE)
            ->columnName('started_at')
            ->build();

        $builder->createField('completedAt', Types::DATETIME_MUTABLE)
            ->columnName('completed_at')
            ->nullable()
            ->build();

        $builder->createField('isSuccess', Types::BOOLEAN)
            ->columnName('is_success')
            ->nullable()
            ->build();

        $builder->createField('exitCode', Types::INTEGER)
            ->columnName('exit_code')
            ->nullable()
            ->build();

        $builder->createField('output', Types::TEXT)
            ->columnName('output')
            ->nullable()
            ->build();

        $builder->createField('errorMessage', Types::TEXT)
            ->columnName('error_message')
            ->nullable()
            ->build();

        $builder->createField('duration', Types::FLOAT)
            ->columnName('duration')
            ->nullable()
            ->build();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function getScheduledJob()
    {
        return $this->scheduledJob;
    }

    public function setScheduledJob(ScheduledJob $scheduledJob)
    {
        $this->scheduledJob = $scheduledJob;
        return $this;
    }

    public function getStartedAt()
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeInterface $startedAt)
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getCompletedAt()
    {
        return $this->completedAt;
    }

    public function setCompletedAt(\DateTimeInterface $completedAt = null)
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function isSuccess()
    {
        return $this->isSuccess;
    }

    public function setIsSuccess($isSuccess)
    {
        $this->isSuccess = $isSuccess;
        return $this;
    }

    public function getExitCode()
    {
        return $this->exitCode;
    }

    public function setExitCode($exitCode)
    {
        $this->exitCode = $exitCode;
        return $this;
    }

    public function getOutput()
    {
        return $this->output;
    }

    public function setOutput($output)
    {
        $this->output = $output;
        return $this;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    public function setErrorMessage($errorMessage)
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getDuration()
    {
        return $this->duration;
    }

    public function setDuration($duration)
    {
        $this->duration = $duration;
        return $this;
    }
}
