<?php

/*
 * @copyright   2025 TopChunks Pvt Ltd. All rights reserved
 * @author      TopChunks
 *
 * @link        http://topchunks.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
if ('index' == $tmpl) {
    $view->extend('CronSchedulerBundle:CronScheduler:index.html.php');
}

$generateLabel = function (string $command): string {
    $parts = explode(':', $command);
    array_shift($parts);

    return implode(' ', array_map('ucfirst', $parts));
};

if (count($items)):

?>
    <div class="table-responsive">
        <table class="table table-condensed cronscheduler-list">
            <thead>
                <tr>
                    <?php
                    echo $view->render(
                        'MauticCoreBundle:Helper:tableheader.html.php',
                        [
                            'checkall'        => 'true',
                            'routeBase'       => 'cronscheduler',
                            'templateButtons' => [
                                'delete' => $permissions['cronscheduler:cronscheduler:deleteown'] || $permissions['cronscheduler:cronscheduler:deleteother'],
                            ],
                        ]
                    );

                    echo $view->render(
                        'MauticCoreBundle:Helper:tableheader.html.php',
                        [
                            'sessionVar' => 'cronscheduler',
                            'orderBy'    => 'sj.name',
                            'text'       => 'mautic.core.name',
                            'class'      => 'col-cron-name',
                            'default'    => true,
                        ]
                    );

                    echo $view->render(
                        'MauticCoreBundle:Helper:tableheader.html.php',
                        [
                            'sessionVar' => 'cronscheduler',
                            'orderBy'    => 'c.title',
                            'text'       => 'mautic.core.category',
                            'class'      => 'visible-md visible-lg col-cron-category',
                        ]
                    );
                    ?>

                    <?php echo $view->render(
                        'MauticCoreBundle:Helper:tableheader.html.php',
                        [
                            'sessionVar' => 'cronscheduler',
                            'orderBy'    => 'sj.command',
                            'text'       => 'mautic.core.command',
                            'class'      => 'visible-md visible-lg col-cron-command',
                        ]
                    );
                    ?>

                    <?php
                    echo $view->render(
                        'MauticCoreBundle:Helper:tableheader.html.php',
                        [
                            'sessionVar' => 'cronscheduler',
                            'orderBy'    => 'sj.id',
                            'text'       => 'mautic.core.id',
                            'class'      => 'visible-md visible-lg col-cron-id',
                        ]
                    );
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                /** @var \MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob $item */
                foreach ($items as $item):

                ?>
                    <tr>
                        <td>
                            <?php
                            $edit = $view['security']->hasEntityAccess(
                                $permissions['cronscheduler:cronscheduler:editown'],
                                $permissions['cronscheduler:cronscheduler:editother'],
                                $item->getCreatedBy()
                            );
                            echo $view->render(
                                'MauticCoreBundle:Helper:list_actions.html.php',
                                [
                                    'item'            => $item,
                                    'templateButtons' => [
                                        'edit' => $view['security']->hasEntityAccess(
                                            $permissions['cronscheduler:cronscheduler:editown'],
                                            $permissions['cronscheduler:cronscheduler:editother'],
                                            $item->getCreatedBy()
                                        ),
                                        'clone' => (
                                            $permissions['cronscheduler:cronscheduler:create']
                                            && $view['security']->hasEntityAccess(
                                                $permissions['cronscheduler:cronscheduler:viewown'],
                                                $permissions['cronscheduler:cronscheduler:viewother'],
                                                $item->getCreatedBy()
                                            )
                                        ),
                                        'delete' => $view['security']->hasEntityAccess(
                                            $permissions['cronscheduler:cronscheduler:deleteown'],
                                            $permissions['cronscheduler:cronscheduler:deleteother'],
                                            $item->getCreatedBy()
                                        ),
                                    ],
                                    'routeBase'     => 'cronscheduler',
                                ]
                            );
                            ?>
                        </td>
                        <td>
                            <div>
                                <?php echo $view->render(
                                    'MauticCoreBundle:Helper:publishstatus_icon.html.php',
                                    ['item' => $item, 'model' => 'cronscheduler']
                                ); ?>
                                <a href="<?php echo $view['router']->path(
                                                'mautic_cronscheduler_action',
                                                ['objectAction' => 'view', 'objectId' => $item->getId()]
                                            ); ?>" data-toggle="ajax">
                                    <?php echo $item->getName(); ?>
                                </a>
                            </div>
                        </td>
                        <td class="visible-md visible-lg">
                            <?php $category = $item->getCategory(); ?>
                            <?php $catName  = ($category) ? $category->getTitle() : $view['translator']->trans('mautic.core.form.uncategorized'); ?>
                            <?php $color    = ($category) ? '#' . $category->getColor() : 'inherit'; ?>
                            <span style="white-space: nowrap;"><span class="label label-default pa-4" style="border: 1px solid #d5d5d5; background: <?php echo $color; ?>;"> </span> <span><?php echo $catName; ?></span></span>
                        </td>
                        <td class="visible-md visible-lg">
                            <?php echo $generateLabel($item->getCommand()); ?>
                        </td>

                        <td class="visible-md visible-lg"><?php echo $item->getId(); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <?php echo $view->render(
            'MauticCoreBundle:Helper:pagination.html.php',
            [
                'totalItems' => $totalItems,
                'page'       => $page,
                'limit'      => $limit,
                'baseUrl'    => $view['router']->path('mautic_cronscheduler_index'),
                'sessionVar' => 'cronscheduler',
            ]
        ); ?>
    </div>
<?php else: ?>
    <?php echo $view->render('MauticCoreBundle:Helper:noresults.html.php', ['tip' => 'mautic.core.noresults.tip']); ?>
<?php endif; ?>