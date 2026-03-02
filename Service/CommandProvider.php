<?php

namespace MauticPlugin\CronSchedulerBundle\Service;

class CommandProvider
{
    /**
     * @var string[]
     */
    private array $commands;

    public function __construct(array $commands = [])
    {
        $this->commands = $commands;
    }

    public function getAvailableCommands(): array
    {
        $choices = [];

        foreach ($this->commands as $command) {
            $choices[$this->buildLabel($command)] = $command;
        }

        ksort($choices);

        return $choices;
    }

    private function buildLabel(string $command): string
    {
        $parts = explode(':', $command);

        array_shift($parts);

        $label = implode(' ', array_map('ucfirst', $parts));

        return sprintf('%s (%s)', $label, $command);
    }
}
