# Changes Summary

## Single Command Implementation

### Before
- Two commands: `cronscheduler:trigger:commands` and `cronscheduler:process:queues`
- Had to run both commands separately
- `--use-queue` flag needed to create queues

### After
- **Single command**: `cronscheduler:process:queues`
- Automatically creates job queues from scheduled jobs
- Then processes all pending job queues
- One command does everything!

## Command Behavior

The `cronscheduler:process:queues` command now:

1. **Creates job queues** from scheduled jobs that are due (if `--skip-create` is not used)
2. **Processes pending job queues** up to the limit

### Usage

```bash
# Create queues from scheduled jobs AND process them (default behavior)
php bin/console cronscheduler:process:queues --limit=100

# Only process existing queues (skip creating new ones)
php bin/console cronscheduler:process:queues --skip-create --limit=100

# With debug output
php bin/console cronscheduler:process:queues --limit=100 --debug
```

### Recommended Cron Setup

```bash
# Run every minute
* * * * * php /path/to/bin/console cronscheduler:process:queues --limit=100
```

## Function-Based Jobs Example

See `Examples/FunctionBasedJobsExample.php` for complete examples.

**Quick Example:**
```php
$jobScheduler = $container->get('mautic.cronscheduler.service.job_scheduler');

// Schedule a service method to run in 1 minute
$jobScheduler->scheduleCallback(
    'Send Email',
    'mautic.email.model.email:sendEmail', // service:method
    [$emailId, $formId], // arguments
    new \DateTime('+1 minute')
);
```

## Redis/SQS/RabbitMQ Questions Answered

### Do I need cron for Redis/SQS/RabbitMQ?

**Short answer:** No, cron is optional.

**Long answer:**
- **Database queue**: Cron is required (runs command periodically)
- **Redis/SQS/RabbitMQ**: Cron is optional
  - Workers can run continuously (better performance)
  - Or use cron for batch processing
  - Multiple workers can process in parallel

### Can Redis/SQS/RabbitMQ handle 10,000 jobs at once?

**Yes, easily!**

- **Redis**: Can handle 10,000+ jobs/second
- **SQS**: Unlimited scale, auto-scales workers
- **RabbitMQ**: Can handle 10,000+ jobs/second

**Recommendations:**
- Use multiple workers/consumers
- Run workers continuously (not just cron)
- Jobs are automatically distributed across workers

See `QUEUE_BACKENDS.md` for detailed information.

## Migration Notes

1. **Old command still works**: `cronscheduler:trigger:commands` still exists but is deprecated
2. **Use new command**: `cronscheduler:process:queues` does everything
3. **No code changes needed**: Existing scheduled jobs continue to work
4. **Gradual migration**: Switch to queue-based processing when ready
