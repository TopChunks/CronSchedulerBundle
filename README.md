# Scheduled Jobs for Mautic

**Scheduled Jobs** (bundle name: `CronSchedulerBundle`) is an open-source Mautic plugin that lets you manage Mautic console commands from the admin UI instead of maintaining a long system crontab.

You keep **one** system cron entry. Everything else — segments, campaigns, emails, custom plugin commands, intervals, cron expressions, logs, and failure alerts — is configured inside Mautic.

Works with **Mautic 4, 5, 6, and 7** (dedicated git branch per version). Requires the Composer package `dragonmantank/cron-expression`.

Built and maintained by [Topchunks Solutions Pvt Ltd](https://github.com/TopChunks).

---

## Why this plugin exists

A typical Mautic install needs many recurring commands, for example:

```bash
mautic:segments:update
mautic:campaigns:update
mautic:campaigns:trigger
mautic:emails:send
mautic:webhooks:process
```

Without this plugin, each command is another line in crontab, on every server, with no history, no UI, and no easy way to pause or re-run a job.

Scheduled Jobs gives you:

- One system cron that ticks every minute
- A UI to create, edit, publish, and prioritize jobs
- Execution history with output, duration, and exit code
- Alerts when a job fails (email, SMS, or WhatsApp)
- Webhooks so monitoring tools can subscribe to failures

It is intended for marketing teams, agencies, and operators running Mautic anywhere in the world.

---

## Features

- **Scheduled Jobs UI** under Mautic Settings / admin menu
- **Command picker** populated from installed Mautic and plugin console commands
- **Three schedule types:** interval, one-time date, and cron expression
- **Optional day-of-week and time-of-day** limits on interval jobs
- **Priority** so important jobs run first in the same minute
- **Publish up / publish down** to start or stop a job automatically
- **Manual run** from the list or detail page
- **Locking** so the same job cannot run twice at once
- **Execution logs** with success/failure, duration, exit code, and command output
- **Navbar recent-logs** dropdown
- **Log retention** with an automatic cleanup job
- **Failure alerts** (opt-in per job) over a single channel: email, SMS, or WhatsApp
- **Webhook event** `Scheduled Job Failed` for third-party integrations
- **Argument validation** so unknown flags (including Symfony globals such as `--env`) fail the job instead of being silently ignored
- **Roles and permissions** using Mautic’s standard own/other access model
- **Default jobs** created on plugin install (segments, campaigns, log cleanup)

---

## Requirements

| Item | Version / notes |
|---|---|
| Mautic | **4, 5, 6, or 7** (use the matching plugin branch) |
| PHP | Same PHP version as your Mautic install |
| Composer | Required — see the package below |
| Database | MySQL / MariaDB used by Mautic |
| CLI | `php bin/console` must work on the server that runs cron |
| Cron | Ability to add one system crontab entry |

**Composer package (required):**

```bash
composer require dragonmantank/cron-expression
```

This package is used to parse and evaluate cron expressions on jobs that use cron notation. Install it in the **Mautic project root** (the directory that contains `composer.json` and `bin/console`), then clear the cache. Do not skip this step.

| Mautic | Plugin git branch |
|---|---|
| 4.x | `v4` |
| 5.x | `v5` |
| 6.x | `v6` |
| 7.x | `v7` |

Optional, only if you want those alert channels:

- **SMS** — a working Mautic SMS transport
- **WhatsApp** — a WhatsApp plugin that exposes approved templates (for example Topchunks WhatsApp)

---

## Installation

1. Copy this bundle into your Mautic plugins directory, using the branch that matches your Mautic version:

   ```text
   /path/to/mautic/plugins/CronSchedulerBundle
   ```

   Or clone it:

   ```bash
   cd /path/to/mautic/plugins
   git clone -b v4 https://github.com/TopChunks/CronSchedulerBundle.git CronSchedulerBundle
   ```

   Replace `v4` with `v5`, `v6`, or `v7` as needed.

2. From the **Mautic root**, install the Composer dependency:

   ```bash
   composer require dragonmantank/cron-expression
   ```

3. Clear the Mautic cache:

   ```bash
   php bin/console cache:clear --env=prod
   ```

4. In Mautic, go to **Settings → Plugins**, find **Scheduled Jobs**, and click **Install**.

5. Add **one** system cron entry (every minute is recommended):

   ```bash
   * * * * * www-data php /path/to/mautic/bin/console mautic:jobs:trigger --env=prod
   ```

   Adjust the PHP binary, path, and user (`www-data`, `nginx`, `apache`, etc.) for your server.

6. Assign **Scheduled Jobs** permissions to the roles that should manage jobs (**Settings → Roles**).

After install, the plugin creates starter jobs (segment rebuild, campaign update, campaign trigger, and log cleanup). Review them, set the right schedule for your volume, and unpublish anything you do not need.

> If you previously listed those Mautic commands in crontab, **remove the old lines**. Leaving both the system crontab and Scheduled Jobs will run the same command twice.

---

## Quick start

1. Open **Scheduled Jobs** in the Mautic admin menu.
2. Click **New**.
3. Give the job a name, pick a console command, and add arguments if the command needs them.
4. Choose a schedule (see below).
5. Publish the job.
6. Optionally enable **Send Failure Alerts** for jobs you care about.
7. Wait for the next minute, or click **Run Manually** to test immediately.
8. Open the job and check **Execution Logs**.

---

## Creating a job

| Field | Purpose |
|---|---|
| **Job name** | Label shown in the list and in alerts |
| **Console command** | Command to run (dropdown of available Mautic/plugin commands) |
| **Command arguments** | Extra flags for *that* command only, for example `--batch-limit=300` |
| **Priority** | Higher numbers run first when several jobs are due in the same tick |
| **Category** | Optional grouping |
| **Published** | Only published jobs are triggered |
| **Send Failure Alerts** | Off by default. When on, a failure uses the global alert settings |
| **Publish up / down** | Optional window during which the job is allowed to run |

### Command arguments

Put only flags the selected command defines.

```text
--batch-limit=300 --max-leads=1000
```

Do **not** copy flags from a full shell line such as:

```bash
php bin/console mautic:segments:update --env=prod --no-debug
```

`--env`, `--no-debug`, `--quiet`, `--no-interaction`, and similar flags belong to Symfony’s console application, not to the Mautic command. The plugin rejects them and marks the run as failed. The environment is already set by the `bin/console` cron line.

Invalid or unknown arguments fail the job. That is intentional: a typo should not look like a successful run.

---

## Schedule types

### Interval (`Trigger for Every`)

Run every *n* minutes, hours, days, months, or years.

Optionally:

- **Trigger at** a specific time of day (useful for daily/monthly jobs)
- **Allowed days** — only certain weekdays, or weekdays only

Examples:

- Every 15 minutes
- Every 1 hour
- Every 1 day at 02:30
- Every 1 day, Monday–Friday

### Date (one-time)

Run once at a chosen date and time. After a successful run it will not fire again.

Useful for a one-off rebuild or a deferred maintenance command.

### Cron notation

Standard five-field cron expression:

```text
* * * * *
│ │ │ │ │
│ │ │ │ └── day of week (0–7, Sunday is 0 or 7)
│ │ │ └──── month (1–12)
│ │ └────── day of month (1–31)
│ └──────── hour (0–23)
└────────── minute (0–59)
```

Examples:

| Expression | Meaning |
|---|---|
| `*/5 * * * *` | Every 5 minutes |
| `0 * * * *` | Every hour, on the hour |
| `0,15,30,45 * * * *` | Four times an hour |
| `0 2 * * *` | Every day at 02:00 |
| `0 3 * * 0` | Sundays at 03:00 |

Use spaces between fields. Invalid expressions are rejected when you save the job.

---

## How jobs are executed

```text
System cron (every minute)
        │
        ▼
mautic:jobs:trigger
        │
        ├── skip unpublished jobs
        ├── skip jobs that are not due
        ├── skip jobs still locked by another process
        ├── run due jobs by priority (highest first)
        ├── record execution log (unless it is a system job)
        └── on failure, send alert + webhook if enabled
```

- Jobs are executed **inside the Mautic process** (Symfony console `Application`), not by shelling out to a new PHP process.
- A job is **due** when its schedule matches the current local Mautic timezone.
- A **lock** is taken for the duration of the run. If the process dies, the lock expires after **30 minutes** so the job can run again.
- **Success** means the command returned exit code `0` *and* the arguments were valid for that command.
- Non-zero exit codes, invalid arguments, and thrown exceptions are **failures**.
- A job that is skipped because it is locked is **not** treated as a failure and does not send an alert.

### System jobs

A small number of internal jobs are flagged as system jobs (for example log cleanup). They still run on a schedule but:

- they are hidden from the Scheduled Jobs list
- they do not write execution logs

You normally do not need to manage these by hand.

---

## Manual runs

On the job list or detail page, use **Run Manually**.

- The command runs immediately, even if it is not due
- The result is shown as a flash message
- If **Send Failure Alerts** is on, a failed manual run still alerts

Use this to test a new job or to recover after a missed window.

---

## Execution logs

Each non-system run stores:

- start and end time
- duration
- exit code
- success or failure
- command output or error message

Open a job to see its history. The navbar history icon shows recent runs across jobs.

### Log retention

In **Settings → Configuration → Scheduled Jobs Settings**, set **Log Retention Days** (default **25**).

The plugin installs a cleanup job that runs `mautic:delete:joblogs` (by default around 03:00). You can also run it yourself:

```bash
php bin/console mautic:delete:joblogs --env=prod
```

---

## Failure alerts

Alerts are **opt-in per job** and use **one global channel**.

### 1. Enable the job

On the job form, set **Send Failure Alerts** to Yes.

### 2. Configure the channel

Go to **Settings → Configuration → Scheduled Jobs Settings**.

| Setting | When it is used |
|---|---|
| **Alert Channel** | `email` (always). `sms` if an SMS transport is enabled. `whatsapp` if a WhatsApp plugin is present |
| **Email / SMS / WhatsApp template** | Shown for the selected channel |
| **Alert email addresses** | Comma-separated, email channel |
| **Alert phone numbers** | International format, SMS or WhatsApp, for example `+14155550123, +447911123456` |

Only the matching template and recipient field are used. Switching from email to WhatsApp does not send email.

If the chosen channel cannot send (missing template, missing recipients, WhatsApp plugin absent), the failure is still logged. The plugin will not break job processing because an alert failed.

### Template tokens

Use these in email/SMS bodies, email subjects, or WhatsApp **variable mappings** (not in an already-approved WhatsApp body text):

| Token | Meaning |
|---|---|
| `{job_id}` | Job ID |
| `{job_name}` | Job name |
| `{job_command}` | Full command line (command + arguments) |
| `{job_arguments}` | Arguments only |
| `{job_executed_at}` | When the run started (Mautic local time) |
| `{job_duration}` | Duration, for example `12.40s` |
| `{job_exit_code}` | Process exit code |
| `{job_fail_reason}` | Error message or command output (truncated) |
| `{job_log_url}` | Link to the job in Mautic |

#### Email

Create a **template** email (not a campaign-only email). Put the tokens in the subject and body. MJML example:

**Subject:** `Scheduled job failed: {job_name}`

```mjml
<mjml>
  <mj-body background-color="#f3f4f6">
    <mj-section background-color="#b42318" padding="24px">
      <mj-column>
        <mj-text align="center" color="#ffffff" font-size="20px" font-weight="700">
          Scheduled job failed
        </mj-text>
        <mj-text align="center" color="#fecaca">{job_name}</mj-text>
      </mj-column>
    </mj-section>
    <mj-section background-color="#ffffff" padding="24px">
      <mj-column>
        <mj-text>
          <p><strong>Job ID:</strong> {job_id}</p>
          <p><strong>Command:</strong> {job_command}</p>
          <p><strong>Arguments:</strong> {job_arguments}</p>
          <p><strong>Executed at:</strong> {job_executed_at}</p>
          <p><strong>Duration:</strong> {job_duration}</p>
          <p><strong>Exit code:</strong> {job_exit_code}</p>
          <p><strong>Reason:</strong><br>{job_fail_reason}</p>
        </mj-text>
        <mj-button href="{job_log_url}" background-color="#b42318" color="#ffffff">
          Open job
        </mj-button>
      </mj-column>
    </mj-section>
  </mj-body>
</mjml>
```

Then select that email under **Email Template** in Scheduled Jobs Settings.

#### SMS

Create an SMS template that includes the tokens you need, for example:

```text
Job failed: {job_name}
Command: {job_command}
Reason: {job_fail_reason}
```

#### WhatsApp

Do **not** change a Meta-approved template body. Keep `{job_*}` tokens in the Mautic WhatsApp message **variable mappings** (`body_1`, `body_2`, …).

Example mapping:

| Variable | Value |
|---|---|
| `body_1` | `{job_name}` |
| `body_2` | `{job_executed_at}` |
| `body_3` | `{job_fail_reason}` |
| `body_4` | `{job_log_url}` |

Meta may reject very long variable values. `{job_fail_reason}` is truncated, but keep mappings short where possible.

---

## Webhooks

When a job fails **and** Send Failure Alerts is enabled, Mautic also queues any webhook subscribed to **Scheduled Job Failed**.

1. Go to **Settings → Webhooks**
2. Create or edit a webhook
3. Enable **Scheduled Job Failed**
4. Point it at your monitoring, ticketing, or chat endpoint

Event name: `mautic.cronscheduler_job_failed`

Example payload:

```json
{
  "job": {
    "id": 6,
    "name": "Segment Rebuild",
    "command": "mautic:segments:update",
    "arguments": "--batch-limit=300"
  },
  "execution": {
    "log_id": 304563,
    "executed_at": "2026-08-23 12:14:54",
    "duration": "8.21s",
    "exit_code": "1",
    "fail_reason": "Invalid job arguments: The \"--env\" option does not exist.",
    "log_url": "https://your-mautic.example/s/scheduled-jobs/view/6"
  }
}
```

Locked (skipped) jobs do not fire this event. Alert send failures are logged and do not stop the scheduler.

---

## Permissions

Under **Settings → Roles → [role] → Scheduled Jobs**:

- View own / view other
- Create
- Edit own / edit other
- Delete own / delete other
- Publish own / publish other

Grant the minimum your operators need. Running commands can be expensive; treat publish and edit as privileged.

---

## Console commands provided by the plugin

| Command | Purpose |
|---|---|
| `mautic:jobs:trigger` | Run every due published job. This is the command to put in system cron. |
| `mautic:delete:joblogs` | Delete execution logs older than the configured retention. |

Useful options for the trigger command:

```bash
php bin/console mautic:jobs:trigger --env=prod
php bin/console mautic:jobs:trigger --env=prod --force
php bin/console mautic:jobs:trigger --env=prod --debug
```

- `--force` — run all published jobs, even if they are not due (use with care)
- `--debug` — print schedule details for each job

`mautic:jobs:trigger` is excluded from the job command picker so a job cannot schedule the scheduler itself.

---

## Recommended production setup

1. **One** crontab line for `mautic:jobs:trigger` every minute.
2. Move all recurring Mautic commands into Scheduled Jobs.
3. Stagger heavy jobs (segments, campaigns, email send) so they do not all start in the same minute. The default install already uses offset cron minutes (`0,15,30,45`, `5,20,35,50`, `10,25,40,55`).
4. Set **priority** so rebuilds you care about run before cleanup.
5. Enable failure alerts on jobs that would hurt marketing if they silently stop (segments, campaigns, broadcasts).
6. Keep log retention long enough to debug, short enough to protect the database (25 days is a reasonable default).
7. Monitor `var/logs/mautic_prod-*.php` as well as the job logs in the UI.

---

## Troubleshooting

**Cron expression is not respected / plugin errors about `CronExpression`**

- From the Mautic root run `composer require dragonmantank/cron-expression`, then `php bin/console cache:clear --env=prod`.
- Confirm you cloned the plugin branch that matches your Mautic version (`v4`–`v7`).

**Jobs never run**

- Confirm system cron is calling `mautic:jobs:trigger` as a user that can read the Mautic files.
- Confirm the job is **published**.
- Check publish up/down dates.
- Open the job and look at **Next run**.
- Run `php bin/console mautic:jobs:trigger --debug --env=prod` by hand and read the output.

**Job shows Success but I expected it to fail**

- Older versions treated Symfony global flags such as `--env` as valid. Current versions reject flags the command does not define. Update the plugin and remove `--env` / `--no-debug` from job arguments.

**Job shows Failed with “The `--env` option does not exist”**

- Remove `--env` from **Command arguments**. Set the environment on the crontab `bin/console` line instead.

**Job is skipped (locked)**

- A previous run is still in progress, or a crash left a lock younger than 30 minutes. Wait, or check for a stuck PHP process.

**Failure email/SMS/WhatsApp not received**

- **Send Failure Alerts** must be Yes on that job.
- Channel, template, and recipients must be set in Scheduled Jobs Settings.
- For email, use a *template* email and include the `{job_*}` tokens.
- For WhatsApp, map tokens on variables; do not edit the approved body.
- Check `var/logs` for `CronScheduler failure alert` messages. Alert errors are swallowed so the scheduler keeps running.

**Timezone looks wrong**

- Times use Mautic’s configured local timezone, not UTC unless you set UTC in Mautic.

---

## Upgrading

Stay on the git branch that matches your Mautic version (`v4`, `v5`, `v6`, or `v7`). Do not mix a Mautic 5 install with the `v4` plugin branch.

1. Replace the `plugins/CronSchedulerBundle` directory with the new version (or `git pull` on the correct branch).
2. Confirm the Composer package is still present: `composer require dragonmantank/cron-expression`
3. Clear cache: `php bin/console cache:clear --env=prod`
4. Open **Settings → Plugins** and click **Install/Upgrade** if the plugin reports a schema or version update.

Schema changes (for example new columns) are applied by the plugin’s install/update subscriber. If a page errors about a missing column after an upgrade, run the plugin update step and reload.

---

## Development

Bundle namespace: `MauticPlugin\CronSchedulerBundle`

| Area | Location |
|---|---|
| Job entity | `Entity/ScheduledJob.php` |
| Execution log | `Entity/JobExecutionLog.php` |
| Due-date + run + lock | `Service/SchedulerService.php` |
| Alerts + webhook payload | `Service/FailureAlertService.php` |
| Command list for the form | `Service/CommandProvider.php` |
| Trigger CLI | `Command/TriggerSchedulerJobs.php` |
| Permissions | `Security/Permissions/CronSchedulerPermissions.php` |
| Webhook registration | `EventListener/WebhookSubscriber.php` |

This repository ships separate branches for Mautic 4, 5, 6, and 7. Open pull requests against the branch for the Mautic version you are changing. Do not weaken argument validation or locking.

---

## Security notes

- Anyone who can create or edit jobs can run console commands available in the picker. Restrict permissions accordingly.
- Destructive core commands (install, updates, marketplace, maintenance cleanup, and the scheduler itself) are excluded from the picker.
- Failure alerts may contain command output. Treat email/SMS/WhatsApp recipients and webhook URLs as sensitive.
- Do not put secrets in job arguments; they will appear in logs, alerts, and webhooks.

---

## License

This plugin is open source and released under the **GNU General Public License v3.0**, the same family of license as Mautic.

You may use, study, share, and improve it, including in commercial Mautic deployments, as long as you follow GPL-3.0.

---

## Support and contributing

- Issues and pull requests: [github.com/TopChunks/CronSchedulerBundle](https://github.com/TopChunks/CronSchedulerBundle)
- Author: [Topchunks Solutions Pvt Ltd](https://github.com/TopChunks)

When reporting a bug, include:

- Mautic version (4, 5, 6, or 7) and PHP version
- Plugin version
- Job command + arguments (redact secrets)
- Schedule type
- Relevant lines from `var/logs` and the job execution log

Translations live in `Translations/en_US/`. Additional locales can be added as `Translations/<locale>/messages.ini` following Mautic’s usual plugin translation layout.

---

## Changelog (high level)

### 1.0.x

- Schedule Mautic commands from the UI (interval, date, cron)
- Execution logs, locking, priority, manual run
- Failure alerts (email / SMS / WhatsApp) with per-job opt-in
- Webhook event for failed jobs
- Strict validation of job arguments against the command definition
- Log retention and default starter jobs
