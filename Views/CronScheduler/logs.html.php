<?php if (!empty($logs)): ?>

    <!-- Scrollable list of recent job executions -->
    <div style="max-height: 210px; overflow-y: auto; overflow-x: hidden;">
        <ul class="list-unstyled mb-0" style="margin: 0; padding: 0;">
            <?php foreach ($logs as $log):
                $job = $log->getScheduledJob();
                $url = $view['router']->path(
                    'mautic_cronscheduler_action',
                    ['objectAction' => 'view', 'objectId' => $job->getId()]
                );

                $statusClass = $log->isSuccess() ? 'text-success' : 'text-danger';

                $fullName    = $job->getName();
                $displayName = $fullName;
                $maxLen      = 30;

                if (!empty($fullName) && mb_strlen($fullName) > $maxLen) {
                    $displayName = mb_substr($fullName, 0, $maxLen - 1) . '…';
                }
            ?>

                <li class="pt-sm pb-sm pr-md pl-md bdr-b">
                    <a href="<?php echo $url; ?>" data-toggle="ajax" class="text-muted" style="display: block; text-decoration: none;">
                        <div>
                            <span title="<?php echo $view->escape($fullName); ?>">
                                <strong><?php echo $view->escape($displayName); ?></strong>
                            </span>
                            <span class="pull-right <?php echo $statusClass; ?>">
                                <i class="fa fa-eye"></i>
                            </span>
                        </div>

                        <div class="mt-xs">
                            <small class="text-muted">
                                <?php echo $log->getStartedAt()->format('Y-m-d H:i:s'); ?>
                            </small>
                        </div>
                    </a>
                </li>

            <?php endforeach; ?>
        </ul>
    </div>

    <div class="text-center pt-15 bdr-t">
        <a href="<?php echo $view['router']->path('mautic_cronscheduler_index', ['page' => 1]); ?>"
            data-toggle="ajax">
            <?php echo $view['translator']->trans('mautic.cron.logs.viewall'); ?>
        </a>
    </div>

<?php else: ?>

    <!-- Centered empty-state message within the scrollable area -->
    <div style="height: 210px; display: flex; align-items: center; justify-content: center;">
        <div class="text-center p-10 text-muted">
            <?php echo $view['translator']->trans('mautic.cron.logs.empty'); ?>
        </div>
    </div>

<?php endif; ?>