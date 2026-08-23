<?php

namespace MauticPlugin\CronSchedulerBundle;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\PluginBundle\Bundle\PluginBundleBase;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\CronSchedulerBundle\DependencyInjection\Compiler\CommandCollectorPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CronSchedulerBundle extends PluginBundleBase
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new CommandCollectorPass());
        parent::build($container);
    }
}
