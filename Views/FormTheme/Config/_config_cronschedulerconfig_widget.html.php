<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
?>

<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.config.tab.cronscheduler.logs'); ?></h3>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form['log_retention_days']); ?>
            </div>
        </div>
    </div>
</div>

<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.cron_scheduler.alert.config.header'); ?></h3>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form['failure_alert_channel']); ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form['failure_alert_email_template']); ?>
            </div>
            <div class="col-md-6">
                <?php echo $view['form']->row($form['failure_alert_sms_template']); ?>
            </div>
            <?php if (isset($form['failure_alert_whatsapp_template'])): ?>
                <div class="col-md-6">
                    <?php echo $view['form']->row($form['failure_alert_whatsapp_template']); ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="row">
                    <div class="col-xs-12">
                        <?php echo $view['form']->row($form['failure_alert_emails']); ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-12">
                        <?php echo $view['form']->row($form['failure_alert_phone_numbers']); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="alert alert-info mb-0">
            <?php echo $view['translator']->trans('mautic.cron_scheduler.alert.tokens.help'); ?>
            <code>{job_id}</code>,
            <code>{job_name}</code>,
            <code>{job_command}</code>,
            <code>{job_arguments}</code>,
            <code>{job_executed_at}</code>,
            <code>{job_duration}</code>,
            <code>{job_exit_code}</code>,
            <code>{job_fail_reason}</code>,
            <code>{job_log_url}</code>
            <br>
        </div>
    </div>
</div>
