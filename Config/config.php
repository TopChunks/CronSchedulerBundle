<?php

declare(strict_types=1);

return [
    'name'        => 'Cron Scheduler',
    'description' => 'Schedule jobs without cron file using Cron Scheduler plugin.',
    'author'      => 'Topchunks Solutions Pvt Ltd',
    'version'     => '1.0.0',
    'menu'      => [
        'admin' => [
            'items' => [
                'mautic.cron.menu.cronscheduler' => [
                    'route'     => 'mautic_cronscheduler_index',
                    'iconClass' => 'fa-sync-alt',
                    'access'    => 'cronscheduler:cronscheduler:view',
                ],
            ]
        ]
    ],
    'routes'     => [
        'main' => [
            'mautic_cronscheduler_index' => [
                'path'       => '/scheduled-jobs/{page}',
                'controller' => 'CronSchedulerBundle:CronScheduler:index',
            ],
            'mautic_cronscheduler_action' => [
                'path'       => '/scheduled-jobs/{objectAction}/{objectId}',
                'controller' => 'CronSchedulerBundle:CronScheduler:execute',
            ],
        ],
    ],
    'services' => [
        'fixtures' => [
            'mautic.plugin.cronscheduler.fixture.defaultcrons' => [
                'class' => \MauticPlugin\CronSchedulerBundle\DataFixtures\ORM\LoadDefaultCrons::class,
                'tags'  => ['doctrine.fixture.orm'],
            ],
        ],
        'forms' => [
            'mautic.form.type.cron_scheduler' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Form\Type\CronSchedulerType::class,
                'arguments' => [
                    'mautic.cron_scheduler.command_provider'
                ],
            ],
            'mautic.cronscheduler.form.type.config' => [
                'class' => \MauticPlugin\CronSchedulerBundle\Form\Type\ConfigType::class,
            ]
        ],
        'events' => [
            'mautic.cronscheduler.button.subscriber' => [
                'class' => \MauticPlugin\CronSchedulerBundle\EventListener\ButtonSubscriber::class,
                'arguments' => [
                    'router',
                    'translator'
                ]
            ],
            'mautic.cronscheduler.config.subscriber' => [
                'class' => \MauticPlugin\CronSchedulerBundle\EventListener\ConfigSubscriber::class,
            ],
            'mautic.cronscheduler.install.subscriber' => [
                'class' => \MauticPlugin\CronSchedulerBundle\EventListener\InstallSubscriber::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager'
                ],
            ],
        ],
        'models' => [
            'mautic.cronscheduler.model.cronscheduler' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Model\CronSchedulerModel::class,
                'arguments' => [
                    'mautic.cronscheduler.service.scheduler'
                ]
            ],
        ],
        'commands' => [
            'mautic.cronscheduler.command.runscheduledjobs' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Command\TriggerSchedulerJobs::class,
                'arguments' => [
                    'mautic.cronscheduler.model.cronscheduler',
                    'mautic.cronscheduler.service.scheduler',
                    'mautic.core.tenancy.runner',
                    'mautic.cronscheduler.service.job_scheduler',
                ],
                'tag'      => 'console.command'
            ],
            'mautic.cronscheduler.command.process_queues' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Command\ProcessJobQueuesCommand::class,
                'arguments' => [
                    'mautic.cronscheduler.service.job_queue_processor',
                    'mautic.core.tenancy.runner',
                ],
                'tag'      => 'console.command'
            ],
            'mautic.cronscheduler.command.delete_older_logs' => [
                'class' => \MauticPlugin\CronSchedulerBundle\Command\DeleteOlderLogsCommand::class,
                'arguments' => [
                    'mautic.cronscheduler.service.scheduler',
                    'mautic.core.tenancy.runner',
                    'mautic.helper.core_parameters'
                ]
            ]
        ],
        'other' => [
            'mautic.cronscheduler.service.scheduler' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Service\SchedulerService::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager',
                    '@kernel',
                    'mautic.cronscheduler.service.job_scheduler',
                ],
            ],
            'mautic.cron_scheduler.command_provider' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Service\CommandProvider::class,
                'arguments' => []
            ],
            'mautic.cronscheduler.queue.manager_factory' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Queue\QueueManagerFactory::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager',
                    'mautic.helper.core_parameters',
                ],
            ],
            'mautic.cronscheduler.service.job_scheduler' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Service\JobScheduler::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager',
                    'mautic.cronscheduler.queue.manager_factory',
                ],
            ],
            'mautic.cronscheduler.service.job_queue_processor' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Service\JobQueueProcessor::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager',
                    '@kernel',
                    'mautic.cronscheduler.queue.manager_factory',
                    'monolog.logger.mautic',
                    'service_container',
                    'mautic.cronscheduler.service.job_scheduler',
                    'mautic.cronscheduler.service.scheduler',
                ],
            ],
        ],
    ],
    'parameters' => [
        'log_retention_days' => 25,
        'job_queue_type' => 'database', // database, redis, sqs, rabbitmq
    ]
];
