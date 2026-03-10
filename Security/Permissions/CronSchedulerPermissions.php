<?php

namespace MauticPlugin\CronSchedulerBundle\Security\Permissions;

use Mautic\CoreBundle\Security\Permissions\AbstractPermissions;
use Symfony\Component\Form\FormBuilderInterface;

class CronSchedulerPermissions extends AbstractPermissions
{
    public function __construct(array $params)
    {
        parent::__construct($params);
        $this->addExtendedPermissions('cronscheduler');
    }

    public function getName(): string
    {
        return 'cronscheduler';
    }

    public function buildForm(FormBuilderInterface &$builder, array $options, array $data): void
    {
        $this->addExtendedFormFields('cronscheduler', 'cronscheduler', $builder, $data);
    }
}
