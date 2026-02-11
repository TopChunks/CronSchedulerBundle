<?php

declare(strict_types=1);

namespace MauticPlugin\CronSchedulerBundle\Integration\ScheduledSend;

use Mautic\CoreBundle\Integration\ScheduledSend\ScheduledSendHandlerInterface as CoreScheduledSendHandlerInterface;

/**
 * Holds all registered scheduled-send handlers (email, whatsapp, etc.).
 * Populated via compiler pass from services tagged cronscheduler.scheduled_send_handler.
 */
class ScheduledSendRegistry
{
    /**
     * @var CoreScheduledSendHandlerInterface[]
     */
    private $handlers = [];

    public function __construct(iterable $handlers = [])
    {
        foreach ($handlers as $handler) {
            if ($handler instanceof CoreScheduledSendHandlerInterface) {
                $this->handlers[] = $handler;
            }
        }
    }

    /**
     * @return CoreScheduledSendHandlerInterface[]
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    public function addHandler(CoreScheduledSendHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }
}
