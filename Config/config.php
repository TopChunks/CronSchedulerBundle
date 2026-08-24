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
                    'iconClass' => 'fa-clock-o',
                    'access'    => 'cronscheduler:cronscheduler:view',
                ],
            ],
        ],
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
                    'mautic.cron_scheduler.command_provider',
                ],
            ],
            'mautic.cronscheduler.form.type.config' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Form\Type\ConfigType::class,
                'arguments' => [
                    'mautic.sms.transport_chain',
                ],
            ],
        ],
        'events' => [
            'mautic.cronscheduler.button.subscriber' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\EventListener\ButtonSubscriber::class,
                'arguments' => [
                    'router',
                    'translator',
                ],
            ],
            'mautic.cronscheduler.config.subscriber' => [
                'class' => \MauticPlugin\CronSchedulerBundle\EventListener\ConfigSubscriber::class,
            ],
            'mautic.cronscheduler.install.subscriber' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\EventListener\InstallSubscriber::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager',
                ],
            ],
            'mautic.cronscheduler.webhook.subscriber' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\EventListener\WebhookSubscriber::class,
                'arguments' => [
                    'mautic.webhook.model.webhook',
                ],
            ],
        ],
        'models' => [
            'mautic.cronscheduler.model.cronscheduler' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Model\CronSchedulerModel::class,
                'arguments' => [
                    'mautic.cronscheduler.service.scheduler',
                ],
            ],
        ],
        'commands' => [
            'mautic.cronscheduler.command.runscheduledjobs' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Command\TriggerSchedulerJobs::class,
                'arguments' => [
                    'mautic.cronscheduler.model.cronscheduler',
                    'mautic.cronscheduler.service.scheduler',
                ],
                'tag'      => 'console.command',
            ],
            'mautic.cronscheduler.command.delete_older_logs' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Command\DeleteOlderLogsCommand::class,
                'arguments' => [
                    'mautic.cronscheduler.service.scheduler',
                    'mautic.helper.core_parameters',
                ],
            ],
        ],
        'other' => [
            'mautic.cronscheduler.service.scheduler' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Service\SchedulerService::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager',
                    '@kernel',
                    'mautic.cronscheduler.service.failure_alert',
                ],
            ],
            'mautic.cronscheduler.service.failure_alert' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Service\FailureAlertService::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                    'mautic.helper.mailer',
                    'mautic.email.model.email',
                    'event_dispatcher',
                    'monolog.logger.mautic',
                    'router',
                    'mautic.sms.model.sms',
                    'mautic.sms.transport_chain',
                    'service_container',
                ],
            ],
            'mautic.cron_scheduler.command_provider' => [
                'class'     => \MauticPlugin\CronSchedulerBundle\Service\CommandProvider::class,
                'arguments' => [],
            ],
        ],
    ],
    'parameters' => [
        'log_retention_days'                 => 25,
        'failure_alert_channel'              => 'email',
        'failure_alert_email_template'       => null,
        'failure_alert_sms_template'         => null,
        'failure_alert_whatsapp_template'    => null,
        'failure_alert_emails'               => '',
        'failure_alert_phone_numbers'        => '',
    ],
];
