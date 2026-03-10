<?php

namespace MauticPlugin\CronSchedulerBundle\EventListener;

use Mautic\ConfigBundle\ConfigEvents;
use Mautic\ConfigBundle\Event\ConfigBuilderEvent;
use MauticPlugin\CronSchedulerBundle\Form\Type\ConfigType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConfigSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ConfigEvents::CONFIG_ON_GENERATE => ['onConfigGenerate', 0],
        ];
    }

    public function onConfigGenerate(ConfigBuilderEvent $event): void
    {
        $event->addForm([
            'bundle' => 'CronSchedulerBundle',
            'formAlias' => 'cronschedulerconfig',
            'formType' => ConfigType::class,
            'formTheme' => '@CronScheduler/FormTheme/Config/_config_cronschedulerconfig_widget.html.twig',
            'parameters' => $event->getParametersFromConfig('CronSchedulerBundle')
        ]);
    }
}
