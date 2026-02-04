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
