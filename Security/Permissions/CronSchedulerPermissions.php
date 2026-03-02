<?php

namespace MauticPlugin\CronSchedulerBundle\Security\Permissions;

use Mautic\CoreBundle\Security\Permissions\AbstractPermissions;
use Symfony\Component\Form\FormBuilderInterface;

class CronSchedulerPermissions extends AbstractPermissions
{
    public function __construct($params)
    {
        parent::__construct($params);
        $this->addExtendedPermissions('cronscheduler');
    }

    public function getName()
    {
        return 'cronscheduler';
    }

    public function buildForm(FormBuilderInterface &$builder, array $options, array $data)
    {
        $this->addExtendedFormFields('cronscheduler', 'cronscheduler', $builder, $data);
    }
}
