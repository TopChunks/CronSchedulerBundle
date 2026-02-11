<?php

declare(strict_types=1);

namespace MauticPlugin\CronSchedulerBundle\DependencyInjection\Compiler;

use MauticPlugin\CronSchedulerBundle\Integration\ScheduledSend\ScheduledSendRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects all services tagged cronscheduler.scheduled_send_handler and
 * injects them into ScheduledSendRegistry.
 */
class ScheduledSendHandlerPass implements CompilerPassInterface
{
    public const TAG = 'cronscheduler.scheduled_send_handler';
    public const REGISTRY_SERVICE = 'mautic.cronscheduler.scheduled_send.registry';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(self::REGISTRY_SERVICE)) {
            return;
        }

        $tagged = $container->findTaggedServiceIds(self::TAG, true);
        $refs   = [];
        foreach (array_keys($tagged) as $id) {
            $refs[] = new Reference($id);
        }

        $container
            ->getDefinition(self::REGISTRY_SERVICE)
            ->setArgument(0, $refs);
    }
}
