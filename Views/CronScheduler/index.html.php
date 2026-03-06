<?php

/*
 * @copyright   2025 TopChunks Pvt Ltd. All rights reserved
 * @author      TopChunks
 *
 * @link        http://topchunks.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'cronscheduler');
$view['slots']->set('headerTitle', $view['translator']->trans('mautic.cronscheduler.scheduled.jobs'));

$view['slots']->set(
    'actions',
    $view->render(
        'MauticCoreBundle:Helper:page_actions.html.php',
        [
            'templateButtons' => [
                'new' => $permissions['cronscheduler:cronscheduler:create'],
            ],
            'routeBase' => 'cronscheduler',
        ]
    )
);

?>

<div class="panel panel-default bdr-t-wdh-0">
    <?php echo $view->render(
        'MauticCoreBundle:Helper:list_toolbar.html.php',
        [
            'searchValue' => $searchValue,
            'action'      => $actionRoute,
        ]
    ); ?>
    <div class="page-list">
        <?php $view['slots']->output('_content'); ?>
    </div>
</div>
