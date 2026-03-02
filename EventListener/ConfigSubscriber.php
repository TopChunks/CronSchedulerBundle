<?php

namespace MauticPlugin\CronSchedulerBundle\EventListener;

use Mautic\ConfigBundle\ConfigEvents;
use Mautic\ConfigBundle\Event\ConfigBuilderEvent;
use MauticPlugin\CronSchedulerBundle\Form\Type\ConfigType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConfigSubscriber implements EventSubscriberInterface
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            ConfigEvents::CONFIG_ON_GENERATE => ['onConfigGenerate', 0],
        ];
    }

    public function onConfigGenerate(ConfigBuilderEvent $event)
    {
        $event->addForm([
            'bundle' => 'CronSchedulerBundle',
            'formAlias' => 'cronschedulerconfig',
            'formType' => ConfigType::class,
            'formTheme' => 'CronSchedulerBundle:FormTheme\Config',
            'parameters' => $event->getParametersFromConfig('CronSchedulerBundle')
        ]);
    }
}
