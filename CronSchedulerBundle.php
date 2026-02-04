<?php

namespace MauticPlugin\CronSchedulerBundle;

use Mautic\PluginBundle\Bundle\PluginBundleBase;
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
