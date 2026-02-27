<?php

if (!$isEmbedded) {
    $view->extend('MauticCoreBundle:Default:content.html.php');
    $view['slots']->set('mauticContent', 'cronscheduler');
    $view['slots']->set('headerTitle', $entity->getName());
}

if (!$isEmbedded) {
    $view['slots']->set(
        'actions',
        $view->render(
            'MauticCoreBundle:Helper:page_actions.html.php',
            [
                'item'            => $entity,
                'templateButtons' => [
                    'edit' => $view['security']->hasEntityAccess(
                        $permissions['cronscheduler:cronscheduler:editown'],
                        $permissions['cronscheduler:cronscheduler:editother'],
                        $entity->getCreatedBy()
                    ),
                    'clone'  => $permissions['cronscheduler:cronscheduler:create'],
                    'delete' => $view['security']->hasEntityAccess(
                        $permissions['cronscheduler:cronscheduler:deleteown'],
                        $permissions['cronscheduler:cronscheduler:deleteother'],
                        $entity->getCreatedBy()
                    ),
                    'close' => $view['security']->hasEntityAccess(
                        $permissions['cronscheduler:cronscheduler:viewown'],
                        $permissions['cronscheduler:cronscheduler:viewother'],
                        $entity->getCreatedBy()
                    ),
                ],
                'routeBase' => 'cronscheduler',
            ]
        )
    );

    $view['slots']->set(
        'publishStatus',
        $view->render('MauticCoreBundle:Helper:publishstatus_badge.html.php', ['entity' => $entity])
    );
}

$generateLabel = function (string $command): string {
    $parts = explode(':', $command);
    array_shift($parts);

    return implode(' ', array_map('ucfirst', $parts));
};
?>
<div class="page-content">
    <div class="detail-view">
        <div class="col-md-12 np">
            <div class="bg-white pa-15 shadow-card">
                <dl class="dl-horizontal">
                    <dt><?php echo $view['translator']->trans('mautic.cron_scheduler.form.command'); ?>:</dt>
                    <dd><?php echo $view->escape($generateLabel($entity->getCommand())); ?></dd>

                    <dt><?php echo $view['translator']->trans('mautic.cron_scheduler.form.arguments'); ?>:</dt>
                    <dd><?php echo $view->escape($entity->getArguments()); ?></dd>

                    <dt><?php echo $view['translator']->trans('mautic.cron_scheduler.cron.notation'); ?>:</dt>
                    <dd><?php echo $view->escape($entity->getCronNotation()); ?></dd>

                    <dt><?php echo $view['translator']->trans('mautic.cron_scheduler.form.lastruntime'); ?>:</dt>
                    <dd>
                        <?php if ($entity->getLastRunAt()): ?>
                            <?php echo $entity->getLastRunAt()->format('Y-m-d H:i:s'); ?>
                        <?php else: ?>
                            <em><?php echo $view['translator']->trans('mautic.cron.details.never'); ?></em>
                        <?php endif; ?>
                    </dd>

                    <dt><?php echo $view['translator']->trans('mautic.cron_scheduler.form.nextruntime'); ?>:</dt>
                    <dd>
                        <?php if ($entity->getNextRunAt()): ?>
                            <?php echo $entity->getNextRunAt()->format('Y-m-d H:i:s'); ?>
                        <?php else: ?>
                            <em><?php echo $view['translator']->trans('mautic.cron.details.pending'); ?></em>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<?php if ($entity->getExecutionLogs() && count($entity->getExecutionLogs()) > 0): ?>
    <div class="page-content">
        <div class="detail-view">
            <div class="col-md-12 np">
                <div class="bg-white pa-15 shadow-card mb-15">
                    <div class="table-responsive">
                        <h4 class="mb-md">
                            <?php echo $view['translator']->trans('mautic.cron_scheduler.execution.logs'); ?>
                        </h4>
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th><?php echo $view['translator']->trans('mautic.cron.logs.starttime'); ?></th>
                                    <th><?php echo $view['translator']->trans('mautic.cron.logs.endtime'); ?></th>
                                    <th><?php echo $view['translator']->trans('mautic.cron.logs.duration'); ?></th>
                                    <th><?php echo $view['translator']->trans('mautic.cron.logs.status'); ?></th>
                                    <th><?php echo $view['translator']->trans('mautic.cron.logs.details'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entity->getExecutionLogs()->slice(0, 50) as $log): ?>
                                    <tr>
                                        <td><?php echo $log->getStartedAt()->format('Y-m-d H:i:s'); ?></td>
                                        <td>
                                            <?php if ($log->getCompletedAt()): ?>
                                                <?php echo $log->getCompletedAt()->format('Y-m-d H:i:s'); ?>
                                            <?php else: ?>
                                                <span class="label label-warning"><?php echo $view['translator']->trans('mautic.cron.logs.running'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($log->getDuration()): ?>
                                                <?php echo number_format($log->getDuration(), 2); ?>s
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($log->isSuccess()): ?>
                                                <span class="label label-success"><?php echo $view['translator']->trans('mautic.cron.logs.success'); ?></span>
                                            <?php elseif ($log->isSuccess() === false): ?>
                                                <span class="label label-danger"><?php echo $view['translator']->trans('mautic.cron.logs.error'); ?></span>
                                            <?php else: ?>
                                                <span class="label label-default">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($log->getOutput() || $log->getErrorMessage()): ?>
                                                <button class="label label-primary" data-toggle="modal" data-target="#outputModal<?php echo $log->getId(); ?>">
                                                    <?php echo $view['translator']->trans('mautic.core.details'); ?>
                                                </button>
                                                <div class="modal fade" id="outputModal<?php echo $log->getId(); ?>" tabindex="-1" role="dialog">
                                                    <div class="modal-dialog modal-lg">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title"><?php echo $view['translator']->trans('mautic.cron.logs.details'); ?></h5>
                                                                <button type="button" class="close" data-dismiss="modal" style="margin-top: -20px;">
                                                                    <span>&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <?php if ($log->getErrorMessage()): ?>
                                                                    <h6><?php echo $view['translator']->trans('mautic.cron.logs.error'); ?>:</h6>
                                                                    <pre class="bg-danger" style="padding: 10px; border-radius: 4px;"><?php echo htmlspecialchars($log->getErrorMessage()); ?></pre>
                                                                <?php endif; ?>
                                                                <?php if ($log->getOutput()): ?>
                                                                    <h6><?php echo $view['translator']->trans('mautic.cron.logs.commandoutput'); ?>:</h6>
                                                                    <br>
                                                                    <pre class="bg-light" style="padding: 10px; border-radius: 4px; max-height: 400px; overflow-y: auto;"><?php echo htmlspecialchars($log->getOutput()); ?></pre>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $view['translator']->trans('mautic.core.close'); ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>