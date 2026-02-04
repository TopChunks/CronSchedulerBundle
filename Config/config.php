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
        'forms' => [
            'mautic.form.type.cron_scheduler' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Form\Type\CronSchedulerType::class,
                'arguments' => [
                    'mautic.cron_scheduler.command_provider'
                ],
            ],
        ],
        'events' => [
            'mautic.cronscheduler.button.subscriber' => [
                'class' => \MauticPlugin\CronSchedulerBundle\EventListener\ButtonSubscriber::class,
                'arguments' => [
                    'router',
                    'translator'
                ]
            ]
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
                ],
                'tag'      => 'console.command'
            ],
        ],
        'other' => [
            'mautic.cronscheduler.service.scheduler' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Service\SchedulerService::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager',
                    '@kernel',
                ],
            ],
            'mautic.cron_scheduler.command_provider' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Service\CommandProvider::class,
                'arguments' => []
            ],
        ],
    ]
];
