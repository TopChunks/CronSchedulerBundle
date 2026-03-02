<?php
$scheduledjob = $form->vars['data'];
$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'cronscheduler');

$isExisting = $scheduledjob->getId();
$header = $isExisting
    ? $view['translator']->trans('mautic.cron.header.edit', ['%name%' => $scheduledjob->getName()])
    : $view['translator']->trans('mautic.cron.header.new');

$view['slots']->set('headerTitle', $header);
$hideTriggerMode = false;
?>


<div class="page-content">
    <?php echo $view['form']->start($form); ?>

    <div class="box-layout content-area">
        <div class="row">

            <div class="col-md-9 bdr-r">

                <ul class="nav nav-tabs pr-md pl-md">
                    <li class="active">
                        <a href="#details-container" data-toggle="tab">
                            <?php echo $view['translator']->trans('mautic.core.details'); ?>
                        </a>
                    </li>
                </ul>

                <div class="tab-content pa-md">
                    <div class="tab-pane fade in active" id="details-container">

                        <div class="row">
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['name']); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['command']); ?>
                            </div>
                        </div>

                        <?php if (isset($form['triggerMode'])): ?>

                            <?php echo $view['form']->row($form['triggerMode']); ?>

                            <div id="triggerDate" class="<?php echo ('date' !== $form['triggerMode']->vars['data']) ? 'hide' : ''; ?>">
                                <?php echo $view['form']->row($form['triggerDate']); ?>
                            </div>

                            <div<?php echo ('interval' != $form['triggerMode']->vars['data']) ? ' class="hide"' : ''; ?> id="triggerInterval">
                                <div class="row">
                                    <div class="col-sm-2">
                                        <?php echo $view['form']->row($form['triggerInterval']); ?>
                                    </div>
                                    <div class="col-sm-6">
                                        <?php echo $view['form']->row($form['triggerIntervalUnit']); ?>
                                    </div>
                                    <div class="col-sm-4 hide" id="interval_settings_days">
                                        <div style="display:inline-block; font-weight: 600;"><?php echo $view['translator']->trans('mautic.cronscheduler.form.type.interval_trigger_at'); ?> </div>
                                        <div style="width: 75px; display:inline-block; margin:0 10px 0 10px;"><?php echo $view['form']->widget($form['triggerHour']); ?></div>
                                    </div>
                                </div>

                                <div id="interval_settings" class="hide">
                                    <div class="row mt-5">
                                        <div class="col-sm-12" style="font-weight: 600;"><?php echo $view['translator']->trans('mautic.campaign.form.type.interval_trigger_restricted_dow'); ?></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-sm-3">
                                            <div class="checkbox">
                                                <label><?php echo $view['form']->widget($form['triggerRestrictedDaysOfWeek'][0]); ?> <?php echo $view['translator']->trans($form['triggerRestrictedDaysOfWeek'][0]->vars['label']); ?></label>
                                            </div>
                                            <div class="checkbox">
                                                <label><?php echo $view['form']->widget($form['triggerRestrictedDaysOfWeek'][1]); ?> <?php echo $view['translator']->trans($form['triggerRestrictedDaysOfWeek'][1]->vars['label']); ?></label>
                                            </div>
                                        </div>
                                        <div class="col-sm-3">
                                            <div class="checkbox">
                                                <label><?php echo $view['form']->widget($form['triggerRestrictedDaysOfWeek'][2]); ?> <?php echo $view['translator']->trans($form['triggerRestrictedDaysOfWeek'][2]->vars['label']); ?></label>
                                            </div>
                                            <div class="checkbox">
                                                <label><?php echo $view['form']->widget($form['triggerRestrictedDaysOfWeek'][3]); ?> <?php echo $view['translator']->trans($form['triggerRestrictedDaysOfWeek'][3]->vars['label']); ?></label>
                                            </div>
                                        </div>
                                        <div class="col-sm-3">
                                            <div class="checkbox">
                                                <label><?php echo $view['form']->widget($form['triggerRestrictedDaysOfWeek'][4]); ?> <?php echo $view['translator']->trans($form['triggerRestrictedDaysOfWeek'][4]->vars['label']); ?></label>
                                            </div>
                                            <div class="checkbox">
                                                <label><?php echo $view['form']->widget($form['triggerRestrictedDaysOfWeek'][5]); ?> <?php echo $view['translator']->trans($form['triggerRestrictedDaysOfWeek'][5]->vars['label']); ?></label>
                                            </div>
                                        </div>
                                        <div class="col-sm-3">
                                            <div class="checkbox">
                                                <label><?php echo $view['form']->widget($form['triggerRestrictedDaysOfWeek'][6]); ?> <?php echo $view['translator']->trans($form['triggerRestrictedDaysOfWeek'][6]->vars['label']); ?></label>
                                            </div>
                                            <div class="checkbox">
                                                <label><?php echo $view['form']->widget($form['triggerRestrictedDaysOfWeek'][7]); ?> <?php echo $view['translator']->trans($form['triggerRestrictedDaysOfWeek'][7]->vars['label']); ?></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                    </div>

                    <div id="cron-notation" class="<?php echo ('notation' !== $form['triggerMode']->vars['data']) ? 'hide' : ''; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['cronNotation']); ?>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <?php echo $view['form']->row($form['arguments']); ?>
                    </div>
                    <div class="col-md-6">
                        <?php echo $view['form']->errors($form); ?>
                    </div>
                </div>

                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="pr-lg pl-lg pt-md pb-md">
                <?php echo $view['form']->row($form['runOnRecovery']); ?>
                <?php echo $view['form']->row($form['isPublished']); ?>
                <?php echo $view['form']->row($form['category']); ?>
                <?php echo $view['form']->row($form['publishUp']); ?>
                <?php echo $view['form']->row($form['publishDown']); ?>
            </div>
        </div>

    </div>
</div>

<div class="hide">
    <?php echo $view['form']->rest($form); ?>
</div>

<?php echo $view['form']->end($form); ?>
</div>