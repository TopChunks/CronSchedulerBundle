<?php

namespace MauticPlugin\CronSchedulerBundle\Service;

class CommandProvider
{
    public function __construct(
        private array $commands = []
    ) {}

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
