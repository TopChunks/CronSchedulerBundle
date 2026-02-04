<?php

namespace MauticPlugin\CronSchedulerBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Templating\Helper\ButtonHelper;
use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorInterface;

class ButtonSubscriber implements EventSubscriberInterface
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(RouterInterface $router, TranslatorInterface $translator)
    {
        $this->router     = $router;
        $this->translator = $translator;
    }

    public static function getSubscribedEvents()
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectSendMessageButtons', 0],
        ];
    }

    public function injectSendMessageButtons(CustomButtonEvent $event)
    {
        $entity = $event->getItem();

        if ($entity = $event->getItem()) {
            if ($entity instanceof ScheduledJob) {

                $triggerButton = [
                    'attr' => [
                        'class'       => 'btn btn-default btn-nospin',
                        'data-ajax'    => 'true',
                        'data-toggle'  => 'ajax',
                        'href'        => $this->router->generate('mautic_cronscheduler_action', ['objectAction' => 'trigger', 'objectId' => $entity->getId()]),
                        'data-header' => $this->translator->trans('mautic.cronscheduler.trigger'),
                    ],
                    'iconClass' => 'fa fa-play',
                    'btnText'   => $this->translator->trans('mautic.cronscheduler.trigger'),
                    'primary'   => true,
                ];

                $event->addButton(
                    $triggerButton,
                    ButtonHelper::LOCATION_PAGE_ACTIONS,
                )->addButton(
                    $triggerButton,
                    ButtonHelper::LOCATION_LIST_ACTIONS,
                );
            }
        }
    }
}
