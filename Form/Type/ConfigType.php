<?php

namespace MauticPlugin\CronSchedulerBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;

class ConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('log_retention_days', NumberType::class, [
            'label' => 'mautic.cronscheduler.log.retention.days',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'class' => 'form-control',
                'tooltip' => 'mautic.cronscheduler.log.retention.days.tooltip'
            ]
        ]);
    }
}
