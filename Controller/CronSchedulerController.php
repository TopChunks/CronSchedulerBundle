<?php

namespace MauticPlugin\CronSchedulerBundle\Controller;

use Mautic\CoreBundle\Controller\AbstractStandardFormController;
use Mautic\CoreBundle\Form\Type\DateRangeType;
use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;
use MauticPlugin\CronSchedulerBundle\Service\SchedulerService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormFactoryInterface;
use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\CoreBundle\Service\FlashBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\FormBundle\Helper\FormFieldHelper;

class CronSchedulerController extends AbstractStandardFormController
{
    private SchedulerService $schedulerService;

    public function __construct(
        FormFactoryInterface $formFactory,
        FormFieldHelper $fieldHelper,
        ManagerRegistry $managerRegistry,
        ModelFactory $modelFactory,
        UserHelper $userHelper,
        CoreParametersHelper $coreParametersHelper,
        EventDispatcherInterface $dispatcher,
        Translator $translator,
        FlashBag $flashBag,
        RequestStack $requestStack,
        CorePermissions $security,
        SchedulerService $schedulerService
    ) {
        parent::__construct($formFactory, $fieldHelper, $managerRegistry, $modelFactory, $userHelper, $coreParametersHelper, $dispatcher, $translator, $flashBag, $requestStack, $security);
        $this->schedulerService = $schedulerService;
    }
    /**
     * @param int $page
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request, $page = 1)
    {
        $permissions = $this->security->isGranted(
            [
                'cronscheduler:cronscheduler:viewown',
                'cronscheduler:cronscheduler:viewother',
                'cronscheduler:cronscheduler:create',
                'cronscheduler:cronscheduler:edit',
                'cronscheduler:cronscheduler:editown',
                'cronscheduler:cronscheduler:editother',
                'cronscheduler:cronscheduler:delete',
                'cronscheduler:cronscheduler:deleteown',
                'cronscheduler:cronscheduler:deleteother',
                'cronscheduler:cronscheduler:publish',
                'cronscheduler:cronscheduler:publishown',
                'cronscheduler:cronscheduler:publishother',
            ],
            'RETURN_ARRAY',
            null,
            true
        );

        if (!$this->checkActionPermission('index')) {
            return $this->accessDenied();
        }

        $this->setListFilters();

        $session = $request->getSession();
        if (empty($page)) {
            $page = $session->get('mautic.' . $this->getSessionBase() . '.page', 1);
        }

        //set limits
        $limit = $session->get('mautic.' . $this->getSessionBase() . '.limit', $this->coreParametersHelper->get('default_pagelimit'));
        $start = (1 === $page) ? 0 : (($page - 1) * $limit);
        if ($start < 0) {
            $start = 0;
        }

        $search = $request->get('search', $session->get('mautic.' . $this->getSessionBase() . '.filter', ''));
        $session->set('mautic.' . $this->getSessionBase() . '.filter', $search);

        $filter = ['string' => $search, 'force' => []];

        $model = $this->getModel($this->getModelName());
        $repo  = $model->getRepository();

        if (!$permissions[$this->getPermissionBase() . ':viewother']) {
            $filter['force'][] = ['column' => $repo->getTableAlias() . '.createdBy', 'expr' => 'eq', 'value' => $this->user->getId()];
        }

        $filter['force'][] = [
            'column' => $repo->getTableAlias() . '.systemCron',
            'expr'   => 'eq',
            'value'  => 0,
        ];

        $orderBy    = $session->get('mautic.' . $this->getSessionBase() . '.orderby', $repo->getTableAlias() . '.' . $this->getDefaultOrderColumn());
        $orderByDir = $session->get('mautic.' . $this->getSessionBase() . '.orderbydir', $this->getDefaultOrderDirection());

        [$count, $items] = $this->getIndexItems($start, $limit, $filter, $orderBy, $orderByDir);

        foreach ($items as &$item) {
            $id = $item->getId();
            $item->command = $item->getCommand($id);
        }
        unset($item);

        if ($count && $count < ($start + 1)) {
            //the number of entities are now less then the current page so redirect to the last page
            $lastPage = (1 === $count) ? 1 : (((ceil($count / $limit)) ?: 1) ?: 1);

            $session->set('mautic.' . $this->getSessionBase() . '.page', $lastPage);
            $returnUrl = $this->generateUrl($this->getIndexRoute(), ['page' => $lastPage]);

            return $this->postActionRedirect(
                $this->getPostActionRedirectArguments(
                    [
                        'returnUrl'       => $returnUrl,
                        'viewParameters'  => ['page' => $lastPage],
                        'contentTemplate' => $this->getControllerBase() . '::' . $this->getPostActionControllerAction('index') . 'Action',
                        'passthroughVars' => [
                            'mauticContent' => $this->getJsLoadMethodPrefix(),
                        ],
                    ],
                    'index'
                )
            );
        }

        $session->set('mautic.' . $this->getSessionBase() . '.page', $page);

        $viewParameters = [
            'permissionBase' => $this->getPermissionBase(),
            'mauticContent'  => $this->getJsLoadMethodPrefix(),
            'sessionVar'    => $this->getSessionBase(),
            'actionRoute'   => $this->getActionRoute(),
            'indexRoute'    => $this->getIndexRoute(),
            'tablePrefix'   => $model->getRepository()->getTableAlias(),
            'modelName'    => $this->getModelName(),
            'translationBase' => $this->getTranslationBase(),
            'searchValue'  => $search,
            'items'        => $items,
            'totalItems'   => $count,
            'page'         => $page,
            'limit'        => $limit,
            'permissions'  => $permissions,
            'tmpl'         => $request->get('tmpl', 'index'),
        ];

        return $this->delegateView(
            $this->getViewArguments([
                'viewParameters' => $viewParameters,
                'contentTemplate' => '@CronScheduler/CronScheduler/list.html.twig',
                'passthroughVars' => [
                    'activeLink'    => $this->getJsLoadMethodPrefix(),
                    'route'         => $this->generateUrl($this->getIndexRoute(), ['page' => $page]),
                ],
            ], 'index')
        );
    }

    /**
     * @param string $objectAction
     * @param int    $id
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function newAction(Request $request, $entity = null)
    {
        /**
         * @var \MauticPlugin\CronSchedulerBundle\Model\CronSchedulerModel $model
         */
        $model = $this->getModel('cronscheduler');

        if (!($entity instanceof ScheduledJob)) {
            /** @var \MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob $entity */
            $entity = $model->getEntity();
        }

        $method = $request->getMethod();
        $session = $request->getSession();

        if (!$this->security->isGranted('cronscheduler:cronscheduler:create')) {
            return $this->accessDenied();
        }

        $page = $session->get('mautic.cronscheduler.page', 1);
        $action = $this->generateUrl('mautic_cronscheduler_action', ['objectAction' => 'new']);

        $form = $model->createForm($entity, $this->formFactory, $action);

        if ('POST' === $method) {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    if ($valid) {
                        $model->saveEntity($entity);
                        $this->addFlashMessage(
                            'mautic.core.notice.created',
                            [
                                '%name%' => $entity->getName(),
                                '%menu_link%' => $this->generateUrl('mautic_cronscheduler_index'),
                                '%url%' => $this->generateUrl('mautic_cronscheduler_action', ['objectAction' => 'edit', 'objectId' => $entity->getId()]),
                            ]
                        );
                    }
                }
            }

            if ($cancelled) {
                return $this->postActionRedirect([
                    'returnUrl'       => $this->generateUrl('mautic_cronscheduler_index', ['page' => $page]),
                    'viewParameters'  => ['page' => $page],
                    'contentTemplate' => CronSchedulerController::class . '::indexAction',
                    'passthroughVars' => [
                        'activeLink'    => '#mautic_cronscheduler_index',
                        'mauticContent' => 'cronscheduler',
                    ],
                ]);
            }

            if ($valid && $form->get('buttons')->get('save')->isClicked()) {
                return $this->postActionRedirect([
                    'returnUrl'       => $this->generateUrl(
                        'mautic_cronscheduler_action',
                        ['objectAction' => 'view', 'objectId' => $entity->getId()]
                    ),
                    'viewParameters'  => [
                        'objectAction' => 'view',
                        'objectId'     => $entity->getId(),
                    ],
                    'contentTemplate' => CronSchedulerController::class . '::viewAction',
                    'passthroughVars' => [
                        'activeLink'    => '#mautic_cronscheduler_index',
                        'mauticContent' => 'cronscheduler',
                    ],
                ]);
            }
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'form'       => $form->createView(),
                    'entity'     => $entity,
                    'tmpl'       => $request->isXmlHttpRequest() ? $request->get('tmpl', 'index') : 'index',
                ],
                'contentTemplate' => '@CronScheduler/CronScheduler/form.html.twig',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_cronscheduler_index',
                    'mauticContent' => 'cronscheduler',
                    'route' => $this->generateUrl('mautic_cronscheduler_action', ['objectAction' => 'new']),
                ],
            ]
        );
    }

    public function editAction(Request $request, $objectId = null, $ignorePost = false, $forceTypeSelection = false)
    {
        /** @var \MauticPlugin\CronSchedulerBundle\Model\CronSchedulerModel $model */
        $model = $this->getModel('cronscheduler');
        $method = $request->getMethod();
        /** @var ?\MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob $entity */
        $entity = $model->getEntity($objectId);

        if ($entity && $entity->getSystemCron()) {
            return $this->accessDenied();
        }

        $session = $request->getSession();
        $page = $session->get('mautic.cronscheduler.page', 1);

        $returnUrl = $this->generateUrl('mautic_cronscheduler_index', ['page' => $page]);

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'MauticPlugin\CronSchedulerBundle\Controller\CronSchedulerController::indexAction',
            'passthroughVars' => [
                'activeLink'    => '#mautic_cronscheduler_index',
                'mauticContent' => 'cronscheduler',
            ],
        ];

        if (null  === $entity) {
            return $this->postActionRedirect(
                array_merge(
                    $postActionVars,
                    [
                        'flashes' => [
                            [
                                'type' => 'error',
                                'msg'  => 'mautic.cronscheduler.error.notfound',
                                'msgVars' => ['%id%' => $objectId],
                            ]
                        ]
                    ]
                )
            );
        } elseif (!$this->security->hasEntityAccess(
            'cronscheduler:cronscheduler:editown',
            'cronscheduler:cronscheduler:editother',
            $entity->getCreatedBy()
        )) {
            return $this->accessDenied();
        } elseif ($model->isLocked($entity)) {
            return $this->isLocked($postActionVars, $entity, 'cronscheduler');
        }
        $action = $this->generateUrl('mautic_cronscheduler_action', ['objectAction' => 'edit', 'objectId' => $objectId]);
        $form = $model->createForm($entity, $this->formFactory, $action);
        if (!$ignorePost && 'POST' === $method) {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    $model->saveEntity($entity, $form->get('buttons')->get('save')->isClicked());
                    $this->addFlashMessage(
                        'mautic.core.notice.updated',
                        [
                            '%name%' => $entity->getName(),
                            '%menu_link%' => $this->generateUrl('mautic_cronscheduler_index'),
                            '%url%' => $this->generateUrl('mautic_cronscheduler_action', ['objectAction' => 'edit', 'objectId' => $entity->getId()]),
                        ],
                    );
                }
            } else {
                $session->remove('mautic.cronscheduler.' . $entity->getId() . '.content');
                $model->unlockEntity($entity);
            }

            $passthrough = [
                'activeLink' => '#mautic_cronscheduler_index',
                'mauticContent' => 'cronscheduler',
            ];

            if ($cancelled || ($valid && $form->get('buttons')->get('save')->isClicked())) {
                $viewParameters = [
                    'objectAction' => 'view',
                    'objectId'     => $entity->getId(),
                ];

                return $this->postActionRedirect(
                    [
                        'returnUrl'    => $this->generateUrl('mautic_cronscheduler_action', $viewParameters),
                        'viewParameters'  => $viewParameters,
                        'passthroughVars'  => $passthrough,
                        'contentTemplate' => CronSchedulerController::class . '::viewAction',
                    ]
                );
            }
        } else {
            $model->lockEntity($entity);
        }
        return $this->delegateView(
            [
                'viewParameters' => [
                    'form'       => $form->createView(),
                    'entity'     => $entity,
                    'forceTypeSelection' => $forceTypeSelection,
                    'tmpl'       => $this->getCurrentRequest()->isXmlHttpRequest() ? $this->getCurrentRequest()->get('tmpl', 'index') : 'index',
                ],
                'contentTemplate' => '@CronScheduler/CronScheduler/form.html.twig',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_cronscheduler_index',
                    'mauticContent' => 'cronscheduler',
                    'route' => $this->generateUrl('mautic_cronscheduler_action', ['objectAction' => 'edit', 'objectId' => $entity->getId()]),
                ],
            ]
        );
    }

    public function viewAction(Request $request, $objectId = null)
    {
        /** @var \MauticPlugin\CronSchedulerBundle\Model\CronSchedulerModel $model */
        $model = $this->getModel('cronscheduler');

        /** @var ?\MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob $entity */
        $entity = $model->getEntity($objectId);

        if ($entity && $entity->getSystemCron()) {
            return $this->accessDenied();
        }

        $page = $request->getSession()->get('mautic.cronscheduler.page', 1);

        if (null  === $entity) {
            $returnUrl = $this->generateUrl('mautic_cronscheduler_index', ['page' => $page]);
            return $this->postActionRedirect([
                'returnUrl'    => $returnUrl,
                'contentTemplate' => 'MauticPlugin\CronSchedulerBundle\Controller\CronSchedulerController::indexAction',
                'passthrough'  => [
                    'activeLink' => '#mautic_cronscheduler_index',
                    'mauticContent' => 'cronscheduler',
                ],
                'flashes' => [
                    [
                        'type' => 'error',
                        'msg'  => 'mautic.cronscheduler.error.notfound',
                        'msgVars' => ['%id%' => $objectId],
                    ]
                ]
            ]);
        } elseif (!$this->security->hasEntityAccess(
            'cronscheduler:cronscheduler:viewown',
            'cronscheduler:cronscheduler:viewother',
            $entity->getCreatedBy()
        )) {
            return $this->accessDenied();
        }

        $dateRangeValues = $request->get('dateRange', null);
        $action = $this->generateUrl('mautic_cronscheduler_action', ['objectAction' => 'view', 'objectId' => $objectId]);
        $dateRangeFrom = $this->formFactory->create(DateRangeType::class, $dateRangeValues, ['action' => $action]);
        return $this->delegateView(
            [
                'viewParameters' => [
                    'entity'       => $entity,
                    'dateRangeForm' => $dateRangeFrom->createView(),
                    'tmpl'         => $request->isXmlHttpRequest() ? $request->get('tmpl', 'index') : 'index',
                    'isEmbedded'      => $request->get('isEmbedded') ? $request->get('isEmbedded') : false,
                    'permissions'     => $this->security->isGranted([
                        'cronscheduler:cronscheduler:viewown',
                        'cronscheduler:cronscheduler:viewother',
                        'cronscheduler:cronscheduler:create',
                        'cronscheduler:cronscheduler:editown',
                        'cronscheduler:cronscheduler:editother',
                        'cronscheduler:cronscheduler:deleteown',
                        'cronscheduler:cronscheduler:deleteother',
                        'cronscheduler:cronscheduler:publishown',
                        'cronscheduler:cronscheduler:publishother',
                    ], 'RETURN_ARRAY'),
                ],
                'contentTemplate' => '@CronScheduler/CronScheduler/details.html.twig',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_cronscheduler_index',
                    'mauticContent' => 'cronscheduler',
                ],
            ]
        );
    }

    public function deleteAction(Request $request, $objectId)
    {
        $page = $request->getSession()->get('mautic.cronscheduler.page', 1);
        $returnUrl = $this->generateUrl('mautic_cronscheduler_index', ['page' => $page]);
        $flashes = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => CronSchedulerController::class . '::indexAction',
            'passthroughVars' => [
                'activeLink'    => '#mautic_cronscheduler_index',
                'mauticContent' => 'cronscheduler',
            ],
        ];

        if ('POST' === $request->getMethod()) {
            /** @var \MauticPlugin\CronSchedulerBundle\Model\CronSchedulerModel $model */
            $model = $this->getModel('cronscheduler');
            /** @var ?\MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob $entity */
            $entity = $model->getEntity($objectId);

            if ($entity && $entity->getSystemCron()) {
                return $this->accessDenied();
            }

            if (null === $entity) {
                $flashes[] = [
                    'type' => 'error',
                    'msg'  => 'mautic.cronscheduler.error.notfound',
                    'msgVars' => ['%id%' => $objectId],
                ];
            } elseif (!$this->security->hasEntityAccess(
                'cronscheduler:cronscheduler:deleteown',
                'cronscheduler:cronscheduler:deleteother',
                $entity->getCreatedBy()
            )) {
                return $this->accessDenied();
            } elseif ($model->isLocked($entity)) {
                return $this->isLocked($postActionVars, $entity, 'cronscheduler');
            }

            $model->deleteEntity($entity);

            $flashes[] = [
                'type' => 'notice',
                'msg'  => 'mautic.core.notice.deleted',
                'msgVars' => ['%name%' => $entity->getName(), '%id%' => $objectId],
            ];
        }

        return $this->postActionRedirect(
            array_merge(
                $postActionVars,
                [
                    'flashes' => $flashes,
                ]
            )
        );
    }

    public function batchDeleteAction(Request $request)
    {
        $page = $request->getSession()->get('mautic.cronscheduler.page', 1);
        $returnUrl = $this->generateUrl('mautic_cronscheduler_index', ['page' => $page]);
        $flashes = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => CronSchedulerController::class . '::indexAction',
            'passthroughVars' => [
                'activeLink'    => '#mautic_cronscheduler_index',
                'mauticContent' => 'cronscheduler',
            ],
        ];

        if (Request::METHOD_POST === $request->getMethod()) {
            /** @var \MauticPlugin\CronSchedulerBundle\Model\CronSchedulerModel $model */
            $model = $this->getModel('cronscheduler');
            $ids   = json_decode($request->query->get('ids', '{}'));

            $deleteIds = [];

            foreach ($ids as $objectId) {
                /** @var \MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob $entity */
                $entity = $model->getEntity($objectId);

                if (null === $entity) {
                    $flashes[] = [
                        'type' => 'error',
                        'msg'  => 'mautic.cronscheduler.error.notfound',
                        'msgVars' => ['%id%' => $objectId],
                    ];
                } elseif (!$this->security->hasEntityAccess(
                    'cronscheduler:cronscheduler:viewown',
                    'cronscheduler:cronscheduler:viewother',
                    $entity->getCreatedBy()
                )) {
                    $flashes[] = $this->accessDenied(true);
                } elseif ($model->isLocked($entity)) {
                    $flashes[] = $this->isLocked($postActionVars, $entity, 'cronscheduler', true);
                } else {
                    $deleteIds[] = $objectId;
                }
            }

            if (!empty($deleteIds)) {
                $entities = $model->deleteEntities($deleteIds);

                $flashes[] = [
                    'type' => 'notice',
                    'msg' => 'mautic.cronscheduler.notice.batch_deleted',
                    'msgVars' => [
                        '%count%' => count($entities)
                    ],
                ];
            }
        }

        return $this->postActionRedirect(
            array_merge(
                $postActionVars,
                ['flashes' => $flashes]
            )
        );
    }

    public function cloneAction(Request $request, $objectId)
    {
        $model  = $this->getModel('cronscheduler');
        $entity = $model->getEntity($objectId);

        if ($entity && $entity->getSystemCron()) {
            return $this->accessDenied();
        }

        if (null != $entity) {
            if (
                !$this->security->isGranted('cronscheduler:cronscheduler:create')
                || !$this->security->hasEntityAccess(
                    'cronscheduler:cronscheduler:viewown',
                    'cronscheduler:cronscheduler:viewother',
                    $entity->getCreatedBy()
                )
            ) {
                return $this->accessDenied();
            }
        }

        return $this->newAction($entity);
    }


    public function runAction($objectId)
    {
        /** @var \MauticPlugin\CronSchedulerBundle\Model\CronSchedulerModel $model */
        $model = $this->getModel('cronscheduler');

        /** @var \MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob $entity */
        $entity = $model->getEntity($objectId);

        if (null === $entity || !$this->security->hasEntityAccess(
            'cronscheduler:cronscheduler:viewown',
            'cronscheduler:cronscheduler:viewother',
            $entity->getCreatedBy()
        )) {
            return $this->accessDenied();
        }

        $viewUrl = $this->generateUrl(
            'mautic_cronscheduler_action',
            ['objectAction' => 'view', 'objectId' => $entity->getId()]
        );

        try {
            $result = $this->schedulerService->runJobManually($entity);
        } catch (\Exception $e) {
            $this->addFlashMessage(
                'mautic.cron_scheduler.error.command.failed',
                ['%error%' => $e->getMessage()]
            );

            return $this->redirect($viewUrl);
        }

        if (!$result || empty($result['success'])) {
            $this->addFlashMessage('mautic.cron_scheduler.error.command.failed', [
                '%error%' => isset($result['message']) ? $result['message'] : 'Unknown error',
            ]);

            return $this->redirect($viewUrl);
        }

        $this->addFlashMessage(
            'mautic.cron_scheduler.success.job.executed',
            ['%name%' => $entity->getName()]
        );

        return $this->redirect($viewUrl);
    }

    public function getModelName(): string
    {
        return 'cronscheduler';
    }

    protected function getControllerBase()
    {
        return 'cronscheduler';
    }

    protected function getRouteBase()
    {
        return 'cronscheduler';
    }
}
