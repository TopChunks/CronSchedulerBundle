<?php if (!empty($logs)): ?>

    <li class="dropdown-header">
        <strong>Recent Job Executions</strong>
    </li>

    <!-- Scrollable container for logs -->
    <li style="max-height: 400px; overflow-y: auto; overflow-x: hidden;">
        <ul class="list-unstyled" style="margin: 0; padding: 0;">
            <?php foreach ($logs as $log):
                $job = $log->getScheduledJob();
                $url = $view['router']->path(
                    'mautic_cronscheduler_action',
                    ['objectAction' => 'view', 'objectId' => $job->getId()]
                );

                $statusClass = $log->isSuccess() ? 'text-success' : 'text-danger';

                // Determine an icon based on the command context (segment, campaign, etc.)
                $command   = $job->getCommand();
                $iconClass = 'fa-cog';
                if (false !== strpos($command, 'segments')) {
                    $iconClass = 'fa-filter';
                } elseif (false !== strpos($command, 'campaign')) {
                    $iconClass = 'fa-sitemap';
                }
            ?>

                <li style="list-style: none;">
                    <a href="<?= $url ?>" data-toggle="ajax" style="display: block; padding: 10px 20px; text-decoration: none; color: inherit;">
                        <div>
                            <span><strong>#<?= (int) $job->getId() ?></strong></span>
                            <span class="pull-right <?= $statusClass ?>">
                                <i class="fa <?= $iconClass ?>"></i>
                            </span>
                        </div>

                        <small class="text-muted">
                            <?= $log->getStartedAt()->format('Y-m-d H:i:s') ?>
                        </small>
                    </a>
                </li>

            <?php endforeach; ?>
        </ul>
    </li>

    <li class="divider"></li>

    <li class="text-center">
        <a href="<?= $view['router']->path('mautic_cronscheduler_index', ['page' => 1]) ?>"
            data-toggle="ajax">
            View All Jobs
        </a>
    </li>

<?php else: ?>

    <li class="text-center p-10 text-muted">
        No job executions yet
    </li>

<?php endif; ?>