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
            CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectTriggerScheduledJobButton', 0],
        ];
    }

    public function injectTriggerScheduledJobButton(CustomButtonEvent $event)
    {
        $entity = $event->getItem();
        $location = $event->getLocation();

        if ($location === ButtonHelper::LOCATION_NAVBAR) {

            $navbarLogsButton = [
                'attr' => [
                    'class' => 'btn btn-default btn-nospin dropdown-toggle',
                    'style' => 'background-color:primary;position:relative;font-weight:bold;z-index:1050;',
                    'href'  => 'javascript:void(0);',
                    'onclick' => 'Mautic.showLogs(event)',
                    'id' => 'cronLogsBtn',
                    'title' => $this->translator->trans('mautic.cron_scheduler.execution.logs'),
                    'data-toggle' => 'tooltip',
                    'data-placement' => 'bottom',
                ],
                'iconClass' => 'fa fa-history text-primary',
            ];

            $event->addButton($navbarLogsButton, ButtonHelper::LOCATION_NAVBAR);

            return;
        }

        if ($entity instanceof ScheduledJob) {

            $triggerButton = [
                'attr' => [
                    'class'       => 'btn btn-default btn-nospin',
                    'href'        => $this->router->generate(
                        'mautic_cronscheduler_action',
                        ['objectAction' => 'trigger', 'objectId' => $entity->getId()]
                    ),
                    'data-ignore-formexit' => 'true',
                ],
                'iconClass' => 'fa fa-play',
                'btnText'   => $this->translator->trans('mautic.cronscheduler.trigger'),
                'primary'   => true,
            ];

            $event
                ->addButton(
                    $triggerButton,
                    ButtonHelper::LOCATION_PAGE_ACTIONS
                )
                ->addButton(
                    $triggerButton,
                    ButtonHelper::LOCATION_LIST_ACTIONS
                );
        }
    }
}
