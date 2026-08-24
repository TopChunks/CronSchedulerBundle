<?php

namespace MauticPlugin\CronSchedulerBundle\EventListener;

use Mautic\ConfigBundle\ConfigEvents;
use Mautic\ConfigBundle\Event\ConfigBuilderEvent;
use Mautic\ConfigBundle\Event\ConfigEvent;
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
            ConfigEvents::CONFIG_PRE_SAVE    => ['onConfigPreSave', 0],
        ];
    }

    public function onConfigGenerate(ConfigBuilderEvent $event)
    {
        $event->addForm([
            'bundle'     => 'CronSchedulerBundle',
            'formAlias'  => 'cronschedulerconfig',
            'formType'   => ConfigType::class,
            'formTheme'  => 'CronSchedulerBundle:FormTheme\Config',
            'parameters' => $event->getParametersFromConfig('CronSchedulerBundle'),
        ]);
    }

    /**
     * Store lookup values as scalar IDs so they reload as the selected template.
     */
    public function onConfigPreSave(ConfigEvent $event)
    {
        $config = $event->getConfig('cronschedulerconfig');
        if (empty($config)) {
            return;
        }

        foreach (['failure_alert_email_template', 'failure_alert_sms_template', 'failure_alert_whatsapp_template'] as $key) {
            if (!array_key_exists($key, $config)) {
                continue;
            }

            $config[$key] = $this->normalizeLookupId($config[$key]);
        }

        if (isset($config['failure_alert_channel'])) {
            $config['failure_alert_channel'] = (string) $config['failure_alert_channel'];
        }

        $event->setConfig($config, 'cronschedulerconfig');
    }

    /**
     * @param mixed $value
     */
    private function normalizeLookupId($value): ?int
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        if (null === $value || '' === $value) {
            return null;
        }

        return (int) $value;
    }
}
