<?php

namespace MauticPlugin\CronSchedulerBundle\Model;

use Exception;
use Mautic\CoreBundle\Model\AjaxLookupModelInterface;
use Mautic\CoreBundle\Model\FormModel;
use MauticPlugin\CronSchedulerBundle\Entity\JobExecutionLog;
use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;
use MauticPlugin\CronSchedulerBundle\Form\Type\CronSchedulerType;
use MauticPlugin\CronSchedulerBundle\Service\SchedulerService;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class CronSchedulerModel extends FormModel implements AjaxLookupModelInterface
{
    /**
     * @var SchedulerService
     */
    private $schedulerService;

    public function __construct(SchedulerService $schedulerService)
    {
        $this->schedulerService = $schedulerService;
    }

    public function getRepository()
    {
        return $this->em->getRepository(ScheduledJob::class);
    }

    public function getLogsRepository()
    {
        return $this->em->getRepository(JobExecutionLog::class);
    }

    public function getPermissionBase()
    {
        return 'cronscheduler:cronscheduler';
    }

    public function createForm($entity, $formFactory, $action = null, $options = [])
    {
        if (!$entity instanceof ScheduledJob) {
            throw new MethodNotAllowedHttpException(['cronscheduler']);
        }

        if (!empty($action)) {
            $options['action'] = $action;
        }

        return $formFactory->create(CronSchedulerType::class, $entity, $options);
    }

    public function getEntity($id = null)
    {
        if (null == $id) {
            $entity = new ScheduledJob();
        } else {
            $entity = parent::getEntity($id);
        }
        return $entity;
    }

    public function saveEntity($entity, $unlock = true)
    {
        $this->convertToUTC($entity);

        if (!$entity->getId()) {
            $this->setInitialNextRunAt($entity);
        } else {
            if ($this->hasTriggerSettingsChanged($entity)) {
                $now = new \DateTime('now', new \DateTimeZone('UTC'));
                $nextRunAt = $this->schedulerService->calculateNextRunTime($entity, $now);
                $entity->setNextRunAt($nextRunAt);
            }
        }

        return parent::saveEntity($entity, $unlock);
    }

    private function convertToUTC(ScheduledJob $entity)
    {
        if ($entity->getPublishUp()) {
            $publishUp = $entity->getPublishUp();
            if ($publishUp->getTimezone()->getName() !== 'UTC') {
                $utcDate = clone $publishUp;
                $utcDate->setTimezone(new \DateTimeZone('UTC'));
                $entity->setPublishUp($utcDate);
            }
        }

        if ($entity->getPublishDown()) {
            $publishDown = $entity->getPublishDown();
            if ($publishDown->getTimezone()->getName() !== 'UTC') {
                $utcDate = clone $publishDown;
                $utcDate->setTimezone(new \DateTimeZone('UTC'));
                $entity->setPublishDown($utcDate);
            }
        }

        if ($entity->getTriggerDate()) {
            $triggerDate = $entity->getTriggerDate();
            if ($triggerDate->getTimezone()->getName() !== 'UTC') {
                $utcDate = clone $triggerDate;
                $utcDate->setTimezone(new \DateTimeZone('UTC'));
                $entity->setTriggerDate($utcDate);
            }
        }
    }

    private function setInitialNextRunAt(ScheduledJob $entity)
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));

        switch ($entity->getTriggerMode()) {
            case 'date':
                if ($entity->getTriggerDate()) {
                    $entity->setNextRunAt($entity->getTriggerDate());
                }
                break;

            case 'interval':
            case 'cron':
                $nextRunAt = $this->schedulerService->calculateNextRunTime($entity, $now);
                $entity->setNextRunAt($nextRunAt);
                break;
        }
    }

    private function hasTriggerSettingsChanged(ScheduledJob $entity): bool
    {
        $changes = $entity->getChanges();

        $triggerFields = [
            'triggerMode',
            'triggerDate',
            'triggerInterval',
            'triggerIntervalUnit',
            'triggerHour',
            'triggerRestrictedDaysOfWeek',
            'cronNotation',
        ];

        foreach ($triggerFields as $field) {
            if (isset($changes[$field])) {
                return true;
            }
        }

        return false;
    }

    public function getLookupResults($type, $filter = '', $limit = 10, $start = 0)
    {
        $results = [];
        switch ($type) {
            case 'cronscheduler':
                $entities = $this->getRepository()->getEntities([
                    'filter' => [
                        'force' => [
                            [
                                'column' => 'sj.name',
                                'expr'   => 'like',
                                'value'  => $filter . '%',
                            ],
                        ],
                    ],
                    'limit'  => $limit,
                    'start'  => $start,
                ]);

                foreach ($entities as $entity) {
                    $results[] = [
                        'label' => $entity->getName(),
                        'value' => $entity->getId(),
                    ];
                }
                break;
        }

        return $results;
    }
}
