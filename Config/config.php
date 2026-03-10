<?php

declare(strict_types=1);

return [
    'name'        => 'Scheduled Jobs',
    'description' => 'Schedules mautic commands to run at specified intervals. No need to define mutiple commands in cron file. Plugin is fully compatible with system cron.',
    'author'      => 'Topchunks Solutions Pvt Ltd',
    'version'     => '1.0.0',
    'menu'        => [
        'admin' => [
            'items' => [
                'mautic.cron.menu.cronscheduler' => [
                    'route'     => 'mautic_cronscheduler_index',
                    'iconClass' => 'ri-time-line',
                    'access'    => 'cronscheduler:cronscheduler:view',
                ],
            ],
        ],
    ],
    'routes'     => [
        'main' => [
            'mautic_cronscheduler_index' => [
                'path'       => '/scheduled-jobs/{page}',
                'controller' => 'MauticPlugin\CronSchedulerBundle\Controller\CronSchedulerController::indexAction',
            ],
            'mautic_cronscheduler_action' => [
                'path'       => '/scheduled-jobs/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\CronSchedulerBundle\Controller\CronSchedulerController::executeAction',
            ],
        ],
    ],
    'parameters' => [
        'log_retention_days' => 25,
    ],
];
