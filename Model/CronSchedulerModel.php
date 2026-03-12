<?php

namespace MauticPlugin\CronSchedulerBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Model\AjaxLookupModelInterface;
use Mautic\CoreBundle\Model\FormModel;
use MauticPlugin\CronSchedulerBundle\Entity\JobExecutionLog;
use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;
use MauticPlugin\CronSchedulerBundle\Form\Type\CronSchedulerType;
use MauticPlugin\CronSchedulerBundle\Service\SchedulerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\CoreBundle\Helper\UserHelper;

class CronSchedulerModel extends FormModel implements AjaxLookupModelInterface
{
    public function __construct(
        EntityManagerInterface $em,
        CorePermissions $security,
        EventDispatcherInterface $dispatcher,
        UrlGeneratorInterface $router,
        Translator $translator,
        UserHelper $userHelper,
        LoggerInterface $logger,
        CoreParametersHelper $coreParametersHelper,
        private SchedulerService $schedulerService
    ) {
        parent::__construct($em, $security, $dispatcher, $router, $translator, $userHelper, $logger, $coreParametersHelper);
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

    public function createForm($entity, FormFactoryInterface $formFactory, $action = null, $options = []): FormInterface
    {
        if (!$entity instanceof ScheduledJob) {
            throw new MethodNotAllowedHttpException(['cronscheduler']);
        }

        if (!empty($action)) {
            $options['action'] = $action;
        }

        return $formFactory->create(CronSchedulerType::class, $entity, $options);
    }

    public function getEntity($id = null): ?ScheduledJob
    {
        if (null == $id) {
            $entity = new ScheduledJob();
        } else {
            $entity = parent::getEntity($id);
        }
        return $entity;
    }

    public function saveEntity($entity, $unlock = true): void
    {
        if (!$entity->getId() || $this->hasTriggerSettingsChanged($entity)) {
            $this->setNextRunAtIfChanged($entity);
        }

        parent::saveEntity($entity, $unlock);
    }

    private function setNextRunAtIfChanged(ScheduledJob $entity)
    {
        $dateTimeHelper = new DateTimeHelper();
        $publishUp = $entity->getPublishUp();

        if ($publishUp && $publishUp >= $dateTimeHelper->getLocalDateTime()) {
            $nextRunAt = $this->schedulerService->calculateNextRunTime($entity, $publishUp);
        } else {
            $nextRunAt = $this->schedulerService->calculateNextRunTime($entity);
        }

        $entity->setNextRunAt($nextRunAt);
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
            'publishUp',
        ];

        foreach ($triggerFields as $field) {
            if (isset($changes[$field])) {

                //Special case for triggerInterval
                if ($field == 'triggerInterval') {
                    $oldValue = $changes[$field][0];
                    $newValue = $changes[$field][1];
                    if ($oldValue != $newValue) {
                        return true;
                    }

                    //Special case for triggerHour
                } elseif ($field == 'triggerHour') {
                    $oldValue = $changes[$field][0];
                    $newValue = $changes[$field][1];

                    if (empty($oldValue) && !empty($newValue)) {
                        return true;
                    }
                    if (!empty($oldValue) && empty($newValue)) {
                        return true;
                    }

                    if (empty($oldValue) && empty($newValue)) {
                        return false;
                    }

                    $oldDateTime = new \DateTime($oldValue);
                    $newDateTime = new \DateTime('1970-01-01 ' . $newValue);

                    if ($oldDateTime->format('H:i:s') != $newDateTime->format('H:i:s')) {
                        return true;
                    }
                }else{
                    return true;
                }
            }
        }

        return false;
    }

    public function getLookupResults(string $type, string|array $filter = '', int $limit = 10, int $start = 0, array $options = []): array
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
