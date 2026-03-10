<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use MauticPlugin\CronSchedulerBundle\Service\CommandProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $excludes = array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, ['Entity', 'Service/CommandProvider.php']);
    $services->load('MauticPlugin\\CronSchedulerBundle\\', '../')
        ->exclude('../{' . implode(',', $excludes) . '}');

    $services->set('mautic.cron_scheduler.command_provider', CommandProvider::class)
        ->args([[]]);

    $services->alias(CommandProvider::class, 'mautic.cron_scheduler.command_provider');

    $services->set(MauticPlugin\CronSchedulerBundle\Entity\ScheduledJobRepository::class)
        ->tag(ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG);

    $services->alias('mautic.cronscheduler.model.cronscheduler', MauticPlugin\CronSchedulerBundle\Model\CronSchedulerModel::class);
    $services->alias('mautic.cronscheduler.service.scheduler', MauticPlugin\CronSchedulerBundle\Service\SchedulerService::class);
};
