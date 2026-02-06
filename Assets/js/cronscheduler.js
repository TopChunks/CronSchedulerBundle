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

Mautic.showLogs = function (e) {

    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }

    const DROPDOWN_ID = 'cronLogsDropdown';
    let $dropdown = mQuery('#' + DROPDOWN_ID);

    // Create dropdown
    if (!$dropdown.length) {
        $dropdown = mQuery(`
            <ul id="${DROPDOWN_ID}"
                class="dropdown-menu dropdown-menu-right dropdown-menu-lg"
                style="width:360px; position:absolute; top:50px; right:15px; z-index:1000;">
                <li class="text-center p-10 text-muted">Loading…</li>
            </ul>
        `);

        mQuery('body').append($dropdown);
    }

    $dropdown.toggle();

    if (!$dropdown.data('loaded')) {
        mQuery.ajax({
            url: mauticAjaxUrl,
            type: 'POST',
            data: {
                action: 'plugin:cronScheduler:logs'
            },
            dataType: 'json',
            success: function (response) {
                if (response.html) {
                    $dropdown.html(response.html);
                    $dropdown.data('loaded', true);
                } else {
                    $dropdown.html('<li class="text-center p-10 text-muted">No logs available</li>');
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                $dropdown.html('<li class="text-danger p-10 text-center">Failed to load logs</li>');
            }
        });
    }

    $dropdown.off('click').on('click', function (e) {
        e.stopPropagation();
    });
};

mQuery(document).on('click', function (e) {
    if (!mQuery(e.target).closest('#cronLogsDropdown, #cronLogsBtn').length) {
        mQuery('#cronLogsDropdown').hide();
    }
});
