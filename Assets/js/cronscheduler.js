Mautic.cronschedulerOnLoad = function (container, response) {

    Mautic.cronSchedulerToggleTimeframes = function () {
        var mode = mQuery('input[name="cronscheduler[triggerMode]"]:checked').val();

        mQuery('#triggerInterval, #triggerDate, #cron-notation').addClass('hide');

        if (mode === 'interval') {
            mQuery('#triggerInterval').removeClass('hide');
        }

        if (mode === 'date') {
            mQuery('#triggerDate').removeClass('hide');
        }

        if (mode === 'cron') {
            mQuery('#cron-notation').removeClass('hide');
        }
    };

    Mautic.cronSchedulerInit = function () {
        Mautic.cronSchedulerToggleTimeframes();

        if (!mQuery('#cronscheduler_triggerHour').length) {
            return;
        }

        Mautic.cronSchedulerShowHideIntervalSettings();

        mQuery('#cronscheduler_triggerIntervalUnit').on(
            'change',
            Mautic.cronSchedulerShowHideIntervalSettings
        );

        mQuery('[id^="cronscheduler_triggerRestrictedDaysOfWeek_"]').on(
            'change',
            Mautic.cronSchedulerSelectDOW
        );
    };

    Mautic.cronSchedulerShowHideIntervalSettings = function () {
        var unit = mQuery('#cronscheduler_triggerIntervalUnit').val();

        if (unit === 'i' || unit === 'h') {
            mQuery('#interval_settings,#interval_settings_days').addClass('hide');
        } else {
            mQuery('#interval_settings,#interval_settings_days').removeClass('hide');
        }
    };

    Mautic.cronSchedulerSelectDOW = function () {
        if (mQuery('#cronscheduler_triggerRestrictedDaysOfWeek_7').prop('checked')) {
            mQuery(
                '#cronscheduler_triggerRestrictedDaysOfWeek_0,' +
                '#cronscheduler_triggerRestrictedDaysOfWeek_1,' +
                '#cronscheduler_triggerRestrictedDaysOfWeek_2,' +
                '#cronscheduler_triggerRestrictedDaysOfWeek_3,' +
                '#cronscheduler_triggerRestrictedDaysOfWeek_4'
            ).prop('checked', true);
        }

        mQuery('#cronscheduler_triggerRestrictedDaysOfWeek_7').prop('checked', false);
    };

    mQuery(document).on(
        'change',
        'input[name="cronscheduler[triggerMode]"]',
        function () {
            Mautic.cronSchedulerToggleTimeframes();
        }
    );
    Mautic.cronSchedulerInit();
};

Mautic.showRecentJobLogs = function (e) {

    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }

    const DROPDOWN_ID = 'cronLogsDropdown';
    let $dropdown = mQuery('#' + DROPDOWN_ID);

    // Create dropdown container (panel with fixed scroll area) on first use.
    if (!$dropdown.length) {
        const title = (typeof mauticLang !== 'undefined' && mauticLang['mautic.cron.logs.title'])
            ? mauticLang['mautic.cron.logs.title']
            : 'Job execution logs';
        
        const failedTranslation = (typeof mauticLang !== 'undefined' && mauticLang['mautic.cron.logs.failed'])
            ? mauticLang['mautic.cron.logs.failed']
            : 'Failed to load logs';

        $dropdown = mQuery(
            '<ul id="' + DROPDOWN_ID + '"' +
                ' class="dropdown-menu dropdown-menu-right dropdown-menu-lg"' +
                ' style="width:360px; position:fixed; top:60px; right:15px; z-index:1000;">' +
                '<li>' +
                    '<div class="panel panel-default mb-0">' +
                        '<div class="panel-heading">' +
                            '<div class="panel-title">' +
                                '<h6 class="fw-sb">' + title +
                                    '<a href="javascript:void(0);" class="btn btn-default btn-xs btn-nospin pull-right text-danger"' +
                                       ' onclick="mQuery(\'#' + DROPDOWN_ID + '\').hide();">' +
                                        '<i class="fa fa-times"></i>' +
                                    '</a>' +
                                '</h6>' +
                            '</div>' +
                        '</div>' +
                        '<div class="pt-0 pb-xs pl-0 pr-0">' +
                            '<div class="scroll-content slimscroll" id="cronLogsContainer" style="height:250px;">' +
                                '<div class="spinner text-center"><i class="fa fa-spinner fa-spin"></i></div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</li>' +
            '</ul>'
        );

        mQuery(e.target).append($dropdown);
    }

    // Always refresh logs when opening the dropdown so new executions appear immediately.
    $dropdown.toggle();

    mQuery.ajax({
        url: mauticAjaxUrl,
        type: 'POST',
        data: {
            action: 'plugin:cronScheduler:logs'
        },
        dataType: 'json',
        success: function (response) {
            const html = (response && response.html)
                ? response.html
                : '<div class="text-center p-10 text-muted">No logs available</div>';

            const $container = $dropdown.find('#cronLogsContainer');
            $container.html(html);

            // Activate Mautic's AJAX/link behavior on newly injected links.
            if (typeof Mautic.makeLinksAlive === 'function') {
                Mautic.makeLinksAlive($container.find('a[data-toggle="ajax"]'));
            }
            if (typeof Mautic.makeModalsAlive === 'function') {
                Mautic.makeModalsAlive($container.find('*[data-toggle="ajaxmodal"]'));
            }
        },
        error: function (xhr, status, error) {
            $dropdown.find('#cronLogsContainer').html(
                '<div class="text-danger p-10 text-center">' + failedTranslation + '</div>'
            );
        }
    });

    $dropdown.off('click').on('click', function (e) {
        e.stopPropagation();
    });
};

mQuery(document).on('click', function (e) {
    if (!mQuery(e.target).closest('#cronLogsDropdown, #cronLogsBtn').length) {
        mQuery('#cronLogsDropdown').hide();
    }
});
