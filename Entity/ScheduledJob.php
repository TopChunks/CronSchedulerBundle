<?php

namespace MauticPlugin\CronSchedulerBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata as MappingClassMetadata;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\FormEntity;
use Mautic\CoreBundle\Entity\VariantEntityTrait;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class ScheduledJob extends FormEntity
{
    use VariantEntityTrait;

    private $id;
    private $name;
    public $command;
    private $arguments;
    private $lastRunAt;
    private $nextRunAt;

    /**
     * @var \DateTime
     */
    private $publishUp;

    /**
     * @var \DateTime
     */
    private $publishDown;

    /**
     * @var \Mautic\CategoryBundle\Entity\Category
     */
    protected $category;

    private $lockedAt;
    private $executionLogs;
    private $cronNotation;
    private $systemCron = false;

    /**
     * @var \DateTime|null
     */
    private $triggerDate;

    /**
     * @var int
     */
    private $triggerInterval = 1;

    /**
     * @var string
     */
    private $triggerIntervalUnit;

    /**
     * @var \DateTime|null
     */
    private $triggerHour;

    /**
     * @var array|null
     */
    private $triggerRestrictedDaysOfWeek = [];

    /**
     * @var string
     */
    private $triggerMode;

    /**
     * @var int
     */
    private $priority = 0;

    public static function loadMetadata(MappingClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);
        $builder
            ->setTable('scheduled_jobs')
            ->setCustomRepositoryClass('MauticPlugin\CronSchedulerBundle\Entity\ScheduledJobRepository');
        $builder->addId();
        $builder->createField('name', Types::STRING)->columnName('name')->build();
        $builder->createField('command', Types::STRING)->columnName('command')->build();
        $builder->createField('arguments', Types::TEXT)->columnName('arguments')->nullable()->build();
        $builder->createField('triggerDate', 'datetime')
            ->columnName('trigger_date')
            ->nullable()
            ->build();

        $builder->createField('triggerInterval', 'integer')
            ->columnName('trigger_interval')
            ->nullable()
            ->build();

        $builder->createField('triggerIntervalUnit', 'string')
            ->columnName('trigger_interval_unit')
            ->length(1)
            ->nullable()
            ->build();

        $builder->createField('triggerHour', 'time')
            ->columnName('trigger_hour')
            ->nullable()
            ->build();

        $builder->createField('triggerRestrictedDaysOfWeek', 'array')
            ->columnName('trigger_restricted_dow')
            ->nullable()
            ->build();

        $builder->createField('triggerMode', 'string')
            ->columnName('trigger_mode')
            ->length(10)
            ->nullable()
            ->build();
        $builder->createField('lastRunAt', Types::DATETIME_MUTABLE)->columnName('last_run_at')->nullable()->build();
        $builder->createField('nextRunAt', Types::DATETIME_MUTABLE)->columnName('next_run_at')->nullable()->build();
        $builder->createField('lockedAt', Types::DATETIME_MUTABLE)->columnName('locked_at')->nullable()->build();
        $builder->createField('cronNotation', Types::TEXT)->columnName('cron_notation')->nullable()->build();
        $builder->createField('systemCron', Types::BOOLEAN)->columnName('system_cron')->build();
        $builder->createField('priority', Types::INTEGER)->columnName('priority')->build();
        $builder->addPublishDates();
        $builder->addCategory();

        $builder->createOneToMany('executionLogs', 'JobExecutionLog')
            ->setOrderBy(['startedAt' => 'DESC'])
            ->mappedBy('scheduledJob')
            ->cascadePersist()
            ->cascadeMerge()
            ->cascadeDetach()
            ->fetchExtraLazy()
            ->build();
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint(
            'name',
            new NotBlank(
                [
                    'message' => 'mautic.cron_scheduler.form.name.notblank',
                ]
            )
        );
        $metadata->addPropertyConstraint(
            'command',
            new NotBlank(
                [
                    'message' => 'mautic.cron_scheduler.form.command.notblank',
                ]
            )
        );
    }

    public static function loadApiMetadata(ApiMetadataDriver $metadata): void
    {
        $metadata->setGroupPrefix('scheduledjob')
            ->addListProperties(
                [
                    'id',
                    'name',
                    'command',
                    'arguments',
                    'lastRunAt',
                    'nextRunAt',
                    'scheduledType',
                    'intervalValue',
                ]
            )
            ->addProperties(
                [
                    'id',
                    'name',
                    'command',
                    'arguments',
                    'scheduledType',
                    'intervalValue',
                    'lastRunAt',
                    'nextRunAt',
                    'publishUp',
                    'publishDown',
                ]
            )
            ->build();
    }

    /**
     * @param $prop
     * @param $val
     */
    protected function isChanged($prop, $val)
    {
        $getter  = 'get' . ucfirst($prop);
        $current = $this->$getter();

        if ('category' == $prop || 'list' == $prop) {
            $currentId = ($current) ? $current->getId() : '';
            $newId     = ($val) ? $val->getId() : null;
            if ($currentId != $newId) {
                $this->changes[$prop] = [$currentId, $newId];
            }
        } else {
            parent::isChanged($prop, $val);
        }
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

    public function getCronNotation()
    {
        return $this->cronNotation;
    }

    public function setCronNotation($cronNotation)
    {
        $this->isChanged('cronNotation', $cronNotation);
        $this->cronNotation = $cronNotation;
    }

    /**
     * @return mixed
     */
    public function getTriggerDate()
    {
        return $this->triggerDate;
    }

    /**
     * @param mixed $triggerDate
     */
    public function setTriggerDate($triggerDate)
    {
        $this->isChanged('triggerDate', $triggerDate);
        $this->triggerDate = $triggerDate;
    }

    /**
     * @return int
     */
    public function getTriggerInterval()
    {
        return $this->triggerInterval;
    }

    /**
     * @param int $triggerInterval
     */
    public function setTriggerInterval($triggerInterval)
    {
        $this->isChanged('triggerInterval', $triggerInterval);
        $this->triggerInterval = $triggerInterval;
    }

    /**
     * @return \DateTime
     */
    public function getTriggerHour()
    {
        return $this->triggerHour;
    }

    /**
     * @param string $triggerHour
     *
     * @return Event
     */
    public function setTriggerHour($triggerHour)
    {
        if (empty($triggerHour)) {
            $triggerHour = null;
        } elseif (!$triggerHour instanceof \DateTime) {
            $triggerHour = new \DateTime($triggerHour);
        }

        $this->isChanged('triggerHour', $triggerHour ? $triggerHour->format('H:i') : $triggerHour);
        $this->triggerHour = $triggerHour;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTriggerIntervalUnit()
    {
        return $this->triggerIntervalUnit;
    }

    /**
     * @param mixed $triggerIntervalUnit
     */
    public function setTriggerIntervalUnit($triggerIntervalUnit)
    {
        $this->isChanged('triggerIntervalUnit', $triggerIntervalUnit);
        $this->triggerIntervalUnit = $triggerIntervalUnit;
    }

    /**
     * @return mixed
     */
    public function getTriggerMode()
    {
        return $this->triggerMode;
    }

    /**
     * @param mixed $triggerMode
     */
    public function setTriggerMode($triggerMode)
    {
        $this->isChanged('triggerMode', $triggerMode);
        $this->triggerMode = $triggerMode;
    }

    /**
     * Get the value of triggerRestrictedDaysOfWeek.
     *
     * @return array
     */
    public function getTriggerRestrictedDaysOfWeek()
    {
        return (array) $this->triggerRestrictedDaysOfWeek;
    }

    /**
     * Set the value of triggerRestrictedDaysOfWeek.
     *
     * @return self
     */
    public function setTriggerRestrictedDaysOfWeek(array $triggerRestrictedDaysOfWeek = null)
    {
        $this->triggerRestrictedDaysOfWeek = $triggerRestrictedDaysOfWeek;
        $this->isChanged('triggerRestrictedDaysOfWeek', $triggerRestrictedDaysOfWeek);

        return $this;
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

    public function getSystemCron()
    {
        return $this->systemCron;
    }

    public function setSystemCron($systemCron)
    {
        $this->isChanged('systemCron', $systemCron);
        $this->systemCron = $systemCron;
    }

    public function getLockedAt()
    {
        return $this->lockedAt;
    }

    public function setLockedAt($lockedAt)
    {
        $this->isChanged('lockedAt', $lockedAt);
        $this->lockedAt = $lockedAt;
    }

    public function getLastRunAt()
    {
        return $this->lastRunAt;
    }

    public function setLastRunAt($lastRunAt)
    {
        $this->isChanged('lastRunAt', $lastRunAt);
        $this->lastRunAt = $lastRunAt;
    }

    public function getNextRunAt()
    {
        return $this->nextRunAt;
    }

    public function setNextRunAt($nextRunAt)
    {
        $this->isChanged('nextRunAt', $nextRunAt);
        $this->nextRunAt = $nextRunAt;
    }

    /**
     * @return mixed
     */
    public function getPublishDown()
    {
        return $this->publishDown;
    }

    /**
     * @param $publishDown
     *
     * @return $this
     */
    public function setPublishDown($publishDown)
    {
        $this->isChanged('publishDown', $publishDown);
        $this->publishDown = $publishDown;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPublishUp()
    {
        return $this->publishUp;
    }

    /**
     * @param $publishUp
     *
     * @return $this
     */
    public function setPublishUp($publishUp)
    {
        $this->isChanged('publishUp', $publishUp);
        $this->publishUp = $publishUp;

        return $this;
    }

    public function getCategory()
    {
        return $this->category;
    }

    public function setCategory($category)
    {
        $this->isChanged('category', $category);
        $this->category = $category;
    }

    public function getExecutionLogs()
    {
        return $this->executionLogs;
    }

    public function setExecutionLogs($executionLogs)
    {
        $this->executionLogs = $executionLogs;
        return $this;
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
}
