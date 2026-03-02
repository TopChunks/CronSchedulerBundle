# CronSchedulerBundle

CronSchedulerBundle is a Mautic plugin that lets you define, schedule, and monitor Symfony console commands directly from the Mautic UI.  
It provides:

- A **Scheduled Jobs** UI (list and detail views) under Mautic.
- Multiple **scheduling strategies**: single-run date, fixed interval, and cron expression–based schedules.
- A **manual trigger** button to execute a job on demand from the UI.
- Persistent **execution logs** with start/end time, duration, exit code, output, and error messages.
- A **navbar “executed logs” dropdown** showing the most recent job runs.

> **Important:** CronSchedulerBundle relies on the `dragonmantank/cron-expression` composer package for parsing and evaluating cron expressions.  
> You **must** install this dependency in your Mautic project (see [Requirements](#requirements)).

---

## Requirements

- **Mautic 4.x** (tested with a standard Mautic 4 installation).
- **PHP CLI** available in the environment that runs your cron jobs.
- **Composer dependency**:

  ```bash
  composer require dragonmantank/cron-expression
  ```

  This package is used by `CronSchedulerBundle\Service\SchedulerService` to:

  - Parse the cron notation configured on a job.
  - Calculate the next valid run date for `cron`–mode jobs.
  - Skip invalid or unsatisfiable cron strings gracefully.

Make sure you run `composer install` (or `composer update`) and clear the Mautic cache after adding the bundle and this dependency.

---

## High-level architecture

At a high level, the bundle introduces:

- An **entity** `ScheduledJob` that defines what to run and when.
- An **entity** `JobExecutionLog` that records each execution attempt.
- A **model** `CronSchedulerModel` responsible for CRUD and permission checks.
- A **service** `SchedulerService` responsible for:
  - Finding due jobs.
  - Locking them.
  - Executing their console commands.
  - Recording success/failure and calculating the next run.
- A **console command** `TriggerSchedulerJobs` (service `mautic.cronscheduler.command.runscheduledjobs`) that:
  - Scans for due jobs.
  - Delegates execution to `SchedulerService`.
- A set of **controllers and views**:
  - `CronSchedulerController` for list, detail, form, and manual triggering.
  - PHP templates for list view, detail view, execution logs, and the navbar dropdown.
- An **event subscriber** `ButtonSubscriber` that:
  - Injects a “Cron logs” button in the navbar.
  - Injects a “Trigger” button in the Cron Scheduler job list and details views.

### Entities

#### `ScheduledJob`

Represents a scheduled job definition. Key fields include:

- `name` – Human‑friendly job name.
- `command` – Console command to execute (e.g. `mautic:segments:update`).
- `arguments` – Optional arguments appended to the command string.
- `triggerMode` – One of:
  - `date` – Run once at a specific date/time.
  - `interval` – Run at a fixed interval (minutes/hours/days).
  - `cron` – Run according to a cron expression.
- `cronNotation` – Cron string (e.g. `0 * * * *`) when `triggerMode = cron`.
- `triggerInterval` / `triggerIntervalUnit` – Interval + unit when `triggerMode = interval`.
- `triggerHour` – Optional time-of-day constraint for interval/cron modes.
- `triggerRestrictedDaysOfWeek` – Optional day‑of‑week restrictions.
- `lastRunAt` / `nextRunAt` – Timestamps for last and next execution.
- `lockedAt` – Timestamp used to prevent concurrent execution by `SchedulerService::acquireLock()`.
- `runOnRecovery` – Flag to prioritize jobs that were skipped while the server was down or when cron did not run.

#### `JobExecutionLog`

Represents a single execution (or attempt) of a `ScheduledJob`. Key fields include:

- `scheduledJob` – The parent job.
- `startedAt` / `completedAt` – Actual runtime window.
- `duration` – Execution duration in seconds.
- `exitCode` – Exit code from the CLI command.
- `isSuccess` – Boolean success/failure flag.
- `output` – Captured standard output (stdout).
- `errorMessage` – Exception message when an error occurs.

These logs are used both in the detailed view and in the navbar dropdown “Executed logs”.

---

## Scheduling modes

CronSchedulerBundle supports three scheduling modes on a job:

### 1. Date (one-time)

- Run the job **once at a specific date and time**.
- Internally:
  - `triggerMode = date`
  - `triggerDate` is used to set `nextRunAt`.
  - After successful execution, `nextRunAt` becomes `null` (one-shot).

### 2. Interval

- Run the job at a **fixed interval**, e.g. every 15 minutes or every 2 hours.
- Configuration:
  - `triggerMode = interval`
  - `triggerInterval` – numeric value.
  - `triggerIntervalUnit` – unit (`i` for minutes, `h` for hours, `d` for days).
  - Optional `triggerHour` and `triggerRestrictedDaysOfWeek` for more precise control.
- `SchedulerService::calculateNextIntervalRun()` uses the above values to compute `nextRunAt` after each run.

### 3. Cron expression

- Run the job according to a **cron expression**, e.g.:
  - `0 0 * * *` – every day at midnight.
  - `*/5 * * * *` – every 5 minutes.
- Configuration:
  - `triggerMode = cron`
  - `cronNotation` contains the cron expression.
- Internals:
  - `SchedulerService::calculateNextCronRun()` uses `dragonmantank/cron-expression` to parse and compute the next run date.
  - Additional `meetsTimeRestrictions()` and `meetsDayRestrictions()` checks are applied if you configure time-of-day and day-of-week constraints.

---

## How execution works

### 1. Automatic execution (via system cron)

You wire Mautic’s CronScheduler command into your system’s cron (or other scheduler), for example:

```bash
# Every minute (example)
* * * * * /usr/bin/php /var/www/mautic-4/bin/console mautic:jobs:trigger
```

The flow is:

1. System cron calls `mautic:jobs:trigger`.
2. `TriggerSchedulerJobs`:
   - Uses `CronSchedulerModel` + repositories to find due jobs (`nextRunAt <= now`, not locked, active, and within publish window).
   - For each due job, calls `SchedulerService::triggerJob($job)`.
3. `SchedulerService::triggerJob()`:
   - Acquires a lock (`lockedAt`) to prevent concurrent runs.
   - Creates a `JobExecutionLog` (unless `systemCron` is flagged).
   - Builds the command string (`{command} {arguments}`).
   - Uses a Symfony `Application` + `StringInput` + `BufferedOutput` to run the command.
   - Captures `exitCode`, `output`, and any exception.
   - Updates `lastRunAt` and `nextRunAt` using the active schedule mode.
   - Persists both the job and its execution log, including duration and error details.
   - Clears the lock in a `finally` block.

### 2. Manual execution (Trigger button in UI)

- In Cron Scheduler list and detail views, `ButtonSubscriber` injects a **Trigger** button:

  ```php
  'attr' => [
      'class' => 'btn btn-default btn-nospin',
      'href'  => $this->router->generate(
          'mautic_cronscheduler_action',
          ['objectAction' => 'trigger', 'objectId' => $entity->getId()]
      ),
      'data-ignore-formexit' => 'true',
  ],
  ```

- Clicking the button calls `CronSchedulerController::triggerAction()`:
  - Validates permissions (`viewown` / `viewother`).
  - Calls `SchedulerService::triggerJob($entity)` inside a `try/catch`.
  - On **success**:
    - Adds a flash message using `mautic.cron_scheduler.success.job.executed` with `%name%`.
    - Redirects to the **detail view** of the same job so you stay on the page you were viewing.
  - On **failure**:
    - Uses `mautic.cron_scheduler.error.command.failed` with `%error%` from the exception or result.
    - Redirects back to the job’s **detail view**, letting you re-check the configuration.

This preserves all functionality while keeping the user experience consistent—no unexpected jumps back to the list view, and clear flash messages when something is wrong.

---

## UI overview

### List view (`list.html.php`)

- Located under the Cron Scheduler menu.
- Uses standard Mautic list components:
  - Bulk action checkbox column with list actions (edit/clone/delete).
  - Columns: Name, Category, Command (humanised), ID.
  - Pagination footer (`panel-footer`) using the core `pagination.html.php` helper.
- Each row features:
  - Publish status icon (active/inactive).
  - Link to the job’s **detail view**.

### Detail view (`details.html.php`)

The detail view is structured similarly to other bundles (e.g. SMS):

- Extends `MauticCoreBundle:Default:content.html.php`.
- Sets slots:
  - `mauticContent = 'cronscheduler'`
  - `headerTitle = $entity->getName()`
  - `actions` – using `page_actions.html.php` with edit/clone/delete/close.
  - `publishStatus` – using `publishstatus_badge.html.php`.
- The main information panel shows:
  - Command (humanised from `command`).
  - Arguments.
  - Cron notation.
  - Last run time / Next run time with friendly fallbacks (`never`, `pending`).

Below that, if there are execution logs:

- A **panel** titled `Execution Logs`:

  ```php
  <div class="panel panel-default mb-0">
      <div class="panel-heading">
          <h4 class="panel-title mb-0">
              <?php echo $view['translator']->trans('mautic.cron_scheduler.execution.logs'); ?>
          </h4>
      </div>
      <div class="panel-body">
          <!-- table with logs -->
      </div>
  </div>
  ```

- The logs table shows:
  - Start time.
  - End time (or a “Running” label if not completed).
  - Duration.
  - Status badge (Success/Error/-).
  - A **Details** button for each row that opens a modal with:
    - Error message (if any).
    - Command output (if any).

### Navbar “Executed logs” dropdown

- `ButtonSubscriber` registers a navbar button:

  ```php
  'attr' => [
      'class' => 'btn btn-default btn-nospin dropdown-toggle',
      'style' => 'background-color:transparent;position:relative;font-weight:bold;z-index:1050;',
      'href'  => 'javascript:void(0);',
      'onclick' => 'Mautic.showLogs(event)',
      'id' => 'cronLogsBtn',
      'title' => $this->translator->trans('mautic.cron_scheduler.execution.logs'),
      'data-toggle' => 'tooltip',
      'data-placement' => 'bottom',
  ],
  'iconClass' => 'fa fa-history text-primary',
  ```

- The corresponding JS (`cronscheduler.js`) implements `Mautic.showLogs(e)`:
  - Dynamically creates a dropdown `<ul id="cronLogsDropdown" class="dropdown-menu dropdown-menu-right dropdown-menu-lg">` containing:
    - A `panel panel-default` with header/title and a **close (X)** button.
    - A fixed-height scrollable container (`#cronLogsContainer`, height ~250px).
  - On every open, performs an AJAX call:

    ```js
    mQuery.ajax({
        url: mauticAjaxUrl,
        type: 'POST',
        data: { action: 'plugin:cronScheduler:logs' },
        ...
    });
    ```

  - The AJAX response HTML is rendered by `Views/CronScheduler/logs.html.php`, which:
    - Lists the most recent job executions (job name, status icon, timestamp).
    - Truncates long names but shows the full name in a tooltip (`title`).
    - Provides a footer link **View All Jobs** that opens the Cron Scheduler list.
  - After injecting the HTML, the JS re-activates Mautic’s AJAX behavior:

    ```js
    if (typeof Mautic.makeLinksAlive === 'function') {
        Mautic.makeLinksAlive($container.find('a[data-toggle="ajax"]'));
    }
    if (typeof Mautic.makeModalsAlive === 'function') {
        Mautic.makeModalsAlive($container.find('*[data-toggle="ajaxmodal"]'));
    }
    ```

This makes the dropdown behave like core notification dropdowns: consistent panel style, scrollable content, and AJAX navigation for links.

---

## Installation and setup

1. **Clone or install the bundle** into `plugins/CronSchedulerBundle` of your Mautic project.
2. Add the composer dependency:

   ```bash
   composer require dragonmantank/cron-expression
   ```

3. Clear and warm up the Mautic cache:

   ```bash
   php bin/console cache:clear --env=prod
   ```

4. Log in to Mautic and ensure the plugin is **installed and enabled** in the Plugins section (if you’re using the plugin manager).
5. Configure system cron to run:

   ```bash
   php bin/console mautic:jobs:trigger
   ```

6. Navigate to **Cron Scheduler → Scheduled Jobs** in the Mautic UI:
   - Create a new job with name, command, schedule, and optional constraints.
   - Use the Trigger button or wait for cron to execute the job.
   - Monitor job history via the detail page and the navbar logs dropdown.

---

## Troubleshooting

- **No jobs are running:**
  - Confirm your system cron is correctly calling `mautic:jobs:trigger`.
  - Check `lastRunAt` / `nextRunAt` on the job detail page.
  - Ensure the job is **published** and within `publishUp`/`publishDown` dates.

- **Cron expression is not respected:**
  - Verify that `dragonmantank/cron-expression` is installed and autoloaded.
  - Check that `cronNotation` is a valid expression (`* * * * *`, `0 0 * * *`, etc.).
  - For complex cron + day/time restrictions, inspect `nextRunAt` on the job and adjust constraints as needed.

- **Clicking Trigger causes a 500 error:**
  - The controller now catches exceptions from `triggerJob()` and shows a flash:  
    `mautic.cron_scheduler.error.command.failed` with the underlying error message.
  - Check the flash notification and adjust your command or arguments.
  - Also check Mautic’s logs under `var/logs` for stack traces if needed.

If you run into a scenario not covered here, inspecting `JobExecutionLog` entries in the detail view (and the command output in the modal) is usually the fastest way to understand what’s going wrong.

