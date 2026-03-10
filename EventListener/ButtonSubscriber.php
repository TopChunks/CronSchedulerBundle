<?php

namespace MauticPlugin\CronSchedulerBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ButtonSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router,
        private TranslatorInterface $translator
    ) {}

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
                'btnClass' => 'btn-nospin',
                'attr' => [
                    'href'    => 'javascript:void(0);',
                    'style'   => 'color:black',
                    'onclick' => 'Mautic.showRecentJobLogs(event)',
                    'id'      => 'recentJobLogsBtn',
                ],
                'iconClass' => 'ri-history-line ri-xl',
                'tooltip'   => [
                    'title'          => 'mautic.cron_scheduler.execution.logs',
                    'data-placement' => 'bottom',
                ],
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
                        ['objectAction' => 'run', 'objectId' => $entity->getId()]
                    ),
                    'data-ignore-formexit' => 'true',
                ],
                'iconClass' => 'ri-play-fill',
                'btnText'   => $this->translator->trans('mautic.cronscheduler.run-manually'),
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
