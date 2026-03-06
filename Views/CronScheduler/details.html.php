<?php

use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;

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

function getWeekDaysHumanReadableLabel($view, array $weekDays): string
{
    return implode(', ', array_map(function ($day) use ($view) {
        switch ($day) {
            case 1:
                return $view['translator']->trans('mautic.report.schedule.day.monday');
            case 2:
                return $view['translator']->trans('mautic.report.schedule.day.tuesday');
            case 3:
                return $view['translator']->trans('mautic.report.schedule.day.wednesday');
            case 4:
                return $view['translator']->trans('mautic.report.schedule.day.thursday');
            case 5:
                return $view['translator']->trans('mautic.report.schedule.day.friday');
            case 6:
                return $view['translator']->trans('mautic.report.schedule.day.saturday');
            case 0:
                return $view['translator']->trans('mautic.report.schedule.day.sunday');
            case -1:
                return $view['translator']->trans('mautic.report.schedule.day.week_days');
        }
    }, $weekDays));
}

function getTriggerIntervalHumanReadableLabel($view, ScheduledJob $entity): string
{

    if ($entity->getTriggerMode() !== 'interval') {
        return '';
    }

    $unit = $entity->getTriggerIntervalUnit();
    $value = $entity->getTriggerInterval();

    if (!$unit || !$value) {
        return '';
    }

    $label = '';
    $unitLabel = '';

    switch ($unit) {
        case 'i':
            $unitLabel = $view['translator']->trans('mautic.campaign.event.intervalunit.choice.i');
            break;
        case 'h':
            $unitLabel = $view['translator']->trans('mautic.campaign.event.intervalunit.choice.h');
            break;
        case 'd':
            $unitLabel = $view['translator']->trans('mautic.campaign.event.intervalunit.choice.d');
            break;
        case 'm':
            $unitLabel = $view['translator']->trans('mautic.campaign.event.intervalunit.choice.m');
            break;
        case 'y':
            $unitLabel = $view['translator']->trans('mautic.campaign.event.intervalunit.choice.y');
            break;
    }

    $label = $view['translator']->trans('mautic.cron_scheduler.interval.trigger.label', [
        '%value%' => $value,
        '%unit%' => $unitLabel,
    ]);

    if(in_array($unit, ['d', 'm', 'y'])) {

        if ($entity->getTriggerHour()) {
            $label .= ' ' . $view['translator']->trans('mautic.cron_scheduler.interval.trigger.specific.hour', [
                '%hour%' => $entity->getTriggerHour()->format('h:i A'),
            ]);
        }

        if ($entity->getTriggerRestrictedDaysOfWeek()) {
            $label .= ' ' . $view['translator']->trans('mautic.cron_scheduler.interval.trigger.specific.days', [
                '%days%' => getWeekDaysHumanReadableLabel($view, $entity->getTriggerRestrictedDaysOfWeek()),
            ]);
        }
    }

    return $label;

}


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

                    <dt><?php echo $view['translator']->trans('mautic.cron_scheduler.form.priority'); ?>:</dt>
                    <dd><?php echo $view->escape($entity->getPriority()); ?></dd>

                    <?php if ($entity->getTriggerMode() === 'cron'): ?>
                        <dt><?php echo $view['translator']->trans('mautic.cron_scheduler.cron.notation'); ?>:</dt>
                        <dd><?php echo $view->escape($entity->getCronNotation()); ?></dd>
                    <?php endif; ?>
                    <?php if ($entity->getTriggerMode() === 'interval'): ?>
                        <dt><?php echo $view['translator']->trans('mautic.cron_scheduler.interval'); ?>:</dt>
                        <dd>
                            <?php echo getTriggerIntervalHumanReadableLabel($view, $entity); ?>
                        </dd>
                    <?php endif; ?>
                    <?php if ($entity->getTriggerMode() === 'date'): ?>
                        <dt><?php echo $view['translator']->trans('mautic.cronscheduler.form.type.interval_trigger_at'); ?>:</dt>
                        <dd>
                            <?php echo $view['date']->toFull($entity->getTriggerDate()); ?>
                        </dd>
                    <?php endif; ?>

                    <dt><?php echo $view['translator']->trans('mautic.cron_scheduler.form.lastruntime'); ?>:</dt>
                    <dd>
                        <?php if ($entity->getLastRunAt()): ?>
                            <?php echo $view['date']->toFull($entity->getLastRunAt()); ?>
                        <?php else: ?>
                            <em><?php echo $view['translator']->trans('mautic.cron.details.never'); ?></em>
                        <?php endif; ?>
                    </dd>

                    <dt><?php echo $view['translator']->trans('mautic.cron_scheduler.form.nextruntime'); ?>:</dt>
                    <dd>
                        <?php if ($entity->getNextRunAt()): ?>
                            <?php echo $view['date']->toFull($entity->getNextRunAt()); ?>
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
                    <div class="panel panel-default mb-0">
                        <div class="panel-heading">
                            <h4 class="panel-title mb-0">
                                <?php echo $view['translator']->trans('mautic.cron_scheduler.execution.logs'); ?>
                            </h4>
                        </div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered mb-0">
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
                                                <td><?php echo $view['date']->toFull($log->getStartedAt()); ?></td>
                                                <td>
                                                    <?php if ($log->getCompletedAt()): ?>
                                                        <?php echo $view['date']->toFull($log->getCompletedAt()); ?>
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
                                                    <button type="button" class="btn btn-xs btn-primary" data-toggle="modal" data-target="#outputModal<?php echo $log->getId(); ?>">
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
                                                                    <button type="button" class="btn btn-primary" data-dismiss="modal"><?php echo $view['translator']->trans('mautic.core.close'); ?></button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
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
        </div>
    </div>
<?php endif; ?>
