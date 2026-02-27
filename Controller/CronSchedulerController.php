<?php

namespace MauticPlugin\CronSchedulerBundle\Controller;

use Mautic\CoreBundle\Controller\AbstractStandardFormController;
use Mautic\CoreBundle\Form\Type\DateRangeType;
use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;
use Symfony\Component\HttpFoundation\JsonResponse;

class CronSchedulerController extends AbstractStandardFormController
{
    /**
     * @param int $page
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function indexAction($page = 1)
    {
        $permissions = $this->get('mautic.security')->isGranted(
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

        $session = $this->get('session');
        if (empty($page)) {
            $page = $session->get('mautic.' . $this->getSessionBase() . '.page', 1);
        }

        //set limits
        $limit = $session->get('mautic.' . $this->getSessionBase() . '.limit', $this->coreParametersHelper->get('default_pagelimit'));
        $start = (1 === $page) ? 0 : (($page - 1) * $limit);
        if ($start < 0) {
            $start = 0;
        }

        $search = $this->request->get('search', $session->get('mautic.' . $this->getSessionBase() . '.filter', ''));
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
                        'contentTemplate' => $this->getControllerBase() . ':' . $this->getPostActionControllerAction('index'),
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
            'tmpl'         => $this->request->get('tmpl', 'index'),
        ];

        return $this->delegateView(
            $this->getViewArguments([
                'viewParameters' => $viewParameters,
                'contentTemplate' => $this->getTemplateName('CronScheduler:list.html.php'),
                'passthroughVars' => [
                    'activeLink'    => $this->getJsLoadMethodPrefix(),
                    'route'         => $this->generateUrl($this->getIndexRoute(), ['page' => $page]),
                ],
            ], 'index')
        );
    }

    public function getTemplateName($file)
    {
        return 'CronSchedulerBundle:' . $file;
    }

    /**
     * @param string $objectAction
     * @param int    $id
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function newAction($entity = null)
    {
        /**
         * @var \MauticPlugin\CronSchedulerBundle\Model\CronSchedulerModel $model
         */
        $model = $this->getModel('cronscheduler');

        if (!($entity instanceof ScheduledJob)) {
            /** @var \MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob $entity */
            $entity = $model->getEntity();
        }

        $method = $this->request->getMethod();
        $session = $this->get('session');

        if (!$this->get('mautic.security')->isGranted('cronscheduler:cronscheduler:create')) {
            return $this->accessDenied();
        }

        $page = $session->get('mautic.cronscheduler.page', 1);
        $action = $this->generateUrl('mautic_cronscheduler_action', ['objectAction' => 'new']);

        $form = $model->createForm($entity, $this->get('form.factory'), $action);

        if ('POST' === $method) {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    if ($valid) {
                        $model->saveEntity($entity);
                        $this->addFlash(
                            'mautic.core.notice.created',
                            [
                                '%name%' => $entity->getName(),
                                '%menu_link%' => $this->generateUrl('mautic_cronscheduler_index'),
                                '%url%' => $this->generateUrl('mautic_cronscheduler_action', ['objectAction' => 'edit', 'objectId' => $entity->getId()]),
                            ]
                        );
                    }
                }
            } else {
                $session->remove('mautic.cronscheduler.' . $entity->getId() . '.content');
            }

            if ($cancelled) {
                return $this->postActionRedirect([
                    'returnUrl'       => $this->generateUrl('mautic_cronscheduler_index', ['page' => $page]),
                    'viewParameters'  => ['page' => $page],
                    'contentTemplate' => 'CronSchedulerBundle:CronScheduler:index',
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
                    'contentTemplate' => 'CronSchedulerBundle:CronScheduler:view',
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
                    'form'       => $this->setFormTheme($form, 'CronSchedulerBundle:CronScheduler:form.html.php'),
                    'entity'     => $entity,
                    'tmpl'       => $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index',
                ],
                'contentTemplate' => 'CronSchedulerBundle:CronScheduler:form.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_cronscheduler_index',
                    'mauticContent' => 'cronscheduler',
                    'route' => $this->generateUrl('mautic_cronscheduler_action', ['objectAction' => 'new']),
                ],
            ]
        );
    }

    public function editAction($objectId = null, $ignorePost = false, $forceTypeSelection = false)
    {
        /** @var \MauticPlugin\CronSchedulerBundle\Model\CronSchedulerModel $model */
        $model = $this->getModel('cronscheduler');
        $method = $this->request->getMethod();
        /** @var \MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob $entity */
        $entity = $model->getEntity($objectId);

        if ($entity && $entity->getSystemCron()) {
            return $this->accessDenied();
        }

        $session = $this->get('session');
        $page = $session->get('mautic.cronscheduler.page', 1);

        $returnUrl = $this->generateUrl('mautic_cronscheduler_index', ['page' => $page]);

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'CronSchedulerBundle:CronScheduler:index',
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
                                'msg'  => 'mautic.cron.error.notfound',
                                'msgVars' => ['%id%' => $objectId],
                            ]
                        ]
                    ]
                )
            );
        } elseif (!$this->get('mautic.security')->hasEntityAccess(
            'cronscheduler:cronscheduler:editown',
            'cronscheduler:cronscheduler:editother',
            $entity->getCreatedBy()
        )) {
            return $this->accessDenied();
        } elseif ($model->isLocked($entity)) {
            return $this->isLocked($postActionVars, $entity, 'cronscheduler');
        }
        $action = $this->generateUrl('mautic_cronscheduler_action', ['objectAction' => 'edit', 'objectId' => $objectId]);
        $form = $model->createForm($entity, $this->get('form.factory'), $action);
        if (!$ignorePost && 'POST' === $method) {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    $model->saveEntity($entity, $form->get('buttons')->get('save')->isClicked());
                    $this->addFlash(
                        'mautic.core.notice.updated',
                        [
                            '%name%' => $entity->getName(),
                            '%menu_link%' => $this->generateUrl('mautic_cronscheduler_index'),
                            '%url%' => $this->generateUrl('mautic_cronscheduler_action', ['objectAction' => 'edit', 'objectId' => $entity->getId()]),
                        ],
                        'warning'
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
                        'contentTemplate' => 'CronSchedulerBundle:CronScheduler:view',
                    ]
                );
            }
        } else {
            $model->lockEntity($entity);
        }
        return $this->delegateView(
            [
                'viewParameters' => [
                    'form'       => $this->setFormTheme($form, 'CronSchedulerBundle:CronScheduler:form.html.php'),
                    'entity'     => $entity,
                    'forceTypeSelection' => $forceTypeSelection,
                    'tmpl'       => $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index',
                ],
                'contentTemplate' => 'CronSchedulerBundle:CronScheduler:form.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_cronscheduler_index',
                    'mauticContent' => 'cronscheduler',
                    'route' => $this->generateUrl('mautic_cronscheduler_action', ['objectAction' => 'edit', 'objectId' => $entity->getId()]),
                ],
            ]
        );
    }

    public function viewAction($objectId = null)
    {
        /** @var \MauticPlugin\CronSchedulerBundle\Model\CronSchedulerModel $model */
        $model = $this->getModel('cronscheduler');

        $security = $this->get('mautic.security');

        /** @var \MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob $entity */
        $entity = $model->getEntity($objectId);

        if ($entity && $entity->getSystemCron()) {
            return $this->accessDenied();
        }

        $page = $this->get('session')->get('mautic.cronscheduler.page', 1);

        if (null  === $entity) {
            $returnUrl = $this->generateUrl('mautic_cronscheduler_index', ['page' => $page]);
            return $this->postActionRedirect([
                'returnUrl'    => $returnUrl,
                'contentTemplate' => 'CronSchedulerBundle:CronScheduler:index',
                'passthrough'  => [
                    'activeLink' => '#mautic_cronscheduler_index',
                    'mauticContent' => 'cronscheduler',
                ],
                'flashes' => [
                    [
                        'type' => 'error',
                        'msg'  => 'mautic.cron.error.notfound',
                        'msgVars' => ['%id%' => $objectId],
                    ]
                ]
            ]);
        } elseif (!$security->hasEntityAccess(
            'cronscheduler:cronscheduler:viewown',
            'cronscheduler:cronscheduler:viewother',
            $entity->getCreatedBy()
        )) {
            return $this->accessDenied();
        }

        $session = $this->get('session');

        $logPage = (int) $this->request->get('logPage', $session->get('mautic.cronscheduler.logs.page', 1));
        $logLimit = (int) $session->get(
            'mautic.cronscheduler.logs.limit',
            $this->coreParametersHelper->get('default_pagelimit')
        );

        $logStart = ($logPage - 1) * $logLimit;
        if ($logStart < 0) {
            $logStart = 0;
        }

        $session->set('mautic.cronscheduler.logs.page', $logPage);

        $logs = $this->getModel('core.auditlog')->getLogForObject('cronscheduler.scheduledjob', $entity->getId(), $entity->getDateAdded());
        $dateRangeValues = $this->request->get('dateRange', null);
        $action = $this->generateUrl('mautic_cronscheduler_action', ['objectAction' => 'view', 'objectId' => $objectId]);
        $dateRangeFrom = $this->get('form.factory')->create(DateRangeType::class, $dateRangeValues, ['action' => $action]);
        return $this->delegateView(
            [
                'viewParameters' => [
                    'entity'       => $entity,
                    'logs'         => $logs,
                    'dateRangeForm' => $dateRangeFrom->createView(),
                    'tmpl'         => $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index',
                    'isEmbedded'      => $this->request->get('isEmbedded') ? $this->request->get('isEmbedded') : false,
                    'permissions'     => $security->isGranted([
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
                'contentTemplate' => 'CronSchedulerBundle:CronScheduler:details.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_cronscheduler_index',
                    'mauticContent' => 'cronscheduler',
                ],
            ]
        );
    }

    public function deleteAction($objectId)
    {
        $page = $this->get('session')->get('mautic.cronscheduler.page', 1);
        $returnUrl = $this->generateUrl('mautic_cronscheduler_index', ['page' => $page]);
        $flashes = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'CronSchedulerBundle:CronScheduler:index',
            'passthroughVars' => [
                'activeLink'    => '#mautic_cronscheduler_index',
                'mauticContent' => 'cronscheduler',
            ],
        ];

        if ('POST' === $this->request->getMethod()) {
            /** @var \MauticPlugin\CronSchedulerBundle\Model\CronSchedulerModel $model */
            $model = $this->getModel('cronscheduler');
            /** @var \MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob $entity */
            $entity = $model->getEntity($objectId);

            if ($entity && $entity->getSystemCron()) {
                return $this->accessDenied();
            }

            if (null === $entity) {
                $flashes[] = [
                    'type' => 'error',
                    'msg'  => 'mautic.cron.error.notfound',
                    'msgVars' => ['%id%' => $objectId],
                ];
            } elseif (!$this->get('mautic.security')->hasEntityAccess(
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

    public function batchDeleteAction()
    {
        $page = $this->get('session')->get('mautic.cronscheduler.page', 1);
        $returnUrl = $this->generateUrl('mautic_cronscheduler_index', ['page' => $page]);
        $flashes = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'CronSchedulerBundle:CronScheduler:index',
            'passthroughVars' => [
                'activeLink'    => '#mautic_cronscheduler_index',
                'mauticContent' => 'cronscheduler',
            ],
        ];

        if ('POST' === $this->request->getMethod()) {
            /** @var \MauticPlugin\CronSchedulerBundle\Model\CronSchedulerModel $model */
            $model = $this->getModel('cronscheduler');
            $ids   = json_decode($this->request->get('ids', '{}'), true);

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
                } elseif (!$this->get('mautic.security')->hasEntityAccess(
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

    public function cloneAction($objectId)
    {
        $model  = $this->getModel('cronscheduler');
        $entity = $model->getEntity($objectId);

        if ($entity && $entity->getSystemCron()) {
            return $this->accessDenied();
        }

        if (null != $entity) {
            if (
                !$this->get('mautic.security')->isGranted('cronscheduler:cronscheduler:create')
                || !$this->get('mautic.security')->hasEntityAccess(
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

    public function triggerAction($objectId)
    {
        /** @var \MauticPlugin\CronSchedulerBundle\Model\CronSchedulerModel $model */
        $model = $this->getModel('cronscheduler');

        /** @var \MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob $entity */
        $entity = $model->getEntity($objectId);

        if (null === $entity || (!$this->get('mautic.security')->hasEntityAccess(
            'cronscheduler:cronscheduler:viewown',
            'cronscheduler:cronscheduler:viewother',
            $entity->getCreatedBy()
        ))) {
            return $this->accessDenied();
        }

        $result = $this->get('mautic.cronscheduler.service.scheduler')->triggerJob($entity);

        if (!$result || empty($result['success'])) {
            return $this->addFlash('mautic.cronscheduler.error');
        }

        $this->addFlash('mautic.cronscheduler.triggered.successfully');
        return $this->redirect($this->request->headers->get('referer'));
    }

    // public function logsAction()
    // {
    //     if (!$this->get('mautic.security')->isGranted('cronscheduler:cronscheduler:viewown')) {
    //         return $this->accessDenied();
    //     }

    //     /** @var \MauticPlugin\CronSchedulerBundle\Model\CronSchedulerModel $model */
    //     $model = $this->getModel('cronscheduler');

    //     $logs = $model->getLogsRepository()->getLatestLogs();

    //     $viewParameters = [
    //         'logs' => $logs,
    //         'tmpl' => $this->request->get('tmpl', 'index'),
    //     ];

    //     return $this->delegateView([
    //         'viewParameters'  => $viewParameters,
    //         'contentTemplate' => 'CronSchedulerBundle:CronScheduler:logs.html.php',
    //     ]);
    // }

    public function getModelName()
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
