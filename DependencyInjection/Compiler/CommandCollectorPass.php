<?php

namespace MauticPlugin\CronSchedulerBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CommandCollectorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $serviceId = 'mautic.cron_scheduler.command_provider';

        if (!$container->has($serviceId)) {
            return;
        }

        $commandNames = [];

        // Find all services tagged as console commands
        foreach ($container->findTaggedServiceIds('console.command') as $id => $tags) {
            try {
                $definition = $container->getDefinition($id);
                $class = $definition->getClass();

                if ($class && strpos($class, '%') === 0) {
                    $class = $container->getParameter(trim($class, '%'));
                }

                if (!$class || !class_exists($class)) {
                    continue;
                }

                $reflectionClass = new \ReflectionClass($class);

                $fileName = $reflectionClass->getFileName();

                if (!$fileName) {
                    continue;
                }

                $normalizedPath = str_replace('\\', '/', $fileName);

                if (
                    strpos($normalizedPath, '/app/bundles/') === false &&
                    strpos($normalizedPath, '/plugins/') === false
                ) {
                    continue;
                }

                if ($reflectionClass->hasConstant('COMMAND_LABEL')) {
                    $commandName = $reflectionClass->getConstant('COMMAND_LABEL');
                    if ($commandName && is_string($commandName)) {
                        $commandNames[] = $commandName;
                        continue;
                    }
                } elseif ($reflectionClass->hasConstant('COMMAND_NAME')) {
                    $commandName = $reflectionClass->getConstant('COMMAND_NAME');
                    if ($commandName && is_string($commandName)) {
                        $commandNames[] = $commandName;
                        continue;
                    }
                }

                if ($reflectionClass->hasMethod('configure')) {
                    $configureMethod = $reflectionClass->getMethod('configure');
                    $fileName = $configureMethod->getFileName();
                    $startLine = $configureMethod->getStartLine();
                    $endLine = $configureMethod->getEndLine();

                    if ($fileName && $startLine && $endLine) {
                        $source = file($fileName);
                        $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));

                        if (preg_match('/->setName\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $methodSource, $matches)) {
                            $commandNames[] = $matches[1];
                            continue;
                        }

                        if (preg_match('/->setName\s*\(\s*self::([A-Z_]+)\s*\)/', $methodSource, $matches)) {
                            $constantName = $matches[1];
                            if ($reflectionClass->hasConstant($constantName)) {
                                $commandNames[] = $reflectionClass->getConstant($constantName);
                                continue;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Skip commands that fail
                continue;
            }
        }

        $commandNames = array_unique($commandNames);
        sort($commandNames);

        $container
            ->getDefinition($serviceId)
            ->setArgument(0, $commandNames);
    }
}
