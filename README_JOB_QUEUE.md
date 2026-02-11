# Job Queue System

This document explains how to use the job queue system in CronSchedulerBundle.

## Overview

The job queue system allows you to:
1. Schedule jobs from scheduled jobs (with intervals)
2. Schedule jobs internally (e.g., on form submit, schedule email in next minute)
3. Support multiple queue backends (database, Redis, SQS, RabbitMQ)

## Architecture

### Components

1. **JobQueue Entity**: Stores job queue items with id, name, command/function, trigger_at, status
2. **QueueManager**: Interface for different queue implementations (database, Redis, SQS, RabbitMQ)
3. **JobScheduler**: Service to schedule jobs (commands or callbacks)
4. **JobQueueProcessor**: Service to process queued jobs

## Usage

### 1. Scheduling a Command Job

```php
use MauticPlugin\CronSchedulerBundle\Service\JobScheduler;

// Inject JobScheduler service
$jobScheduler = $container->get('mautic.cronscheduler.service.job_scheduler');

// Schedule a command to run in 1 minute
$triggerAt = new \DateTime('+1 minute', new \DateTimeZone('UTC'));
$jobQueue = $jobScheduler->scheduleJob(
    'Send Email',
    'mautic:emails:send',
    '--email-id=123',
    $triggerAt,
    0 // priority
);
```

### 2. Scheduling a Callback Function

```php
use MauticPlugin\CronSchedulerBundle\Service\JobScheduler;

// Inject JobScheduler service
$jobScheduler = $container->get('mautic.cronscheduler.service.job_scheduler');

// Schedule a callback function to run in 1 minute
// Format: 'service:method' (e.g., 'mautic.email.model.email:sendEmail')
$triggerAt = new \DateTime('+1 minute', new \DateTimeZone('UTC'));
$jobQueue = $jobScheduler->scheduleCallback(
    'Send Email Callback',
    'mautic.email.model.email:sendEmail', // service:method format
    [123, 456], // arguments to pass to the method
    $triggerAt,
    0 // priority
);
```

**Complete Example - Schedule Email on Form Submit:**

```php
use MauticPlugin\CronSchedulerBundle\Service\JobScheduler;

class FormSubscriber extends CommonSubscriber
{
    private $jobScheduler;

    public function __construct(JobScheduler $jobScheduler)
    {
        $this->jobScheduler = $jobScheduler;
    }

    public function onFormSubmit(FormEvent $event)
    {
        $form = $event->getForm();
        $emailId = 123; // Your email ID
        
        // Schedule email to be sent in 1 minute
        $triggerAt = new \DateTime('+1 minute', new \DateTimeZone('UTC'));
        
        $this->jobScheduler->scheduleCallback(
            'Send Form Email',
            'mautic.email.model.email:sendEmail', // Your service:method
            [$emailId, $form->getId()], // Arguments
            $triggerAt
        );
    }
}
```

**Note:** Your service method must exist and accept the arguments you pass:
```php
class EmailModel
{
    public function sendEmail($emailId, $formId)
    {
        // Your implementation here
    }
}
```

### 3. Example: Schedule Email on Form Submit

```php
use MauticPlugin\CronSchedulerBundle\Service\JobScheduler;

class FormSubscriber extends CommonSubscriber
{
    private $jobScheduler;

    public function __construct(JobScheduler $jobScheduler)
    {
        $this->jobScheduler = $jobScheduler;
    }

    public function onFormSubmit(FormEvent $event)
    {
        $form = $event->getForm();
        
        // Schedule email to be sent in 1 minute
        $triggerAt = new \DateTime('+1 minute', new \DateTimeZone('UTC'));
        $this->jobScheduler->scheduleJob(
            'Send Form Email',
            'mautic:emails:send',
            '--email-id=' . $form->getId(),
            $triggerAt
        );
    }
}
```

### 4. Processing Job Queues

Run the command to process job queues:

```bash
# Process up to 10 jobs
php bin/console cronscheduler:process:queues --limit=10

# Process with debug output
php bin/console cronscheduler:process:queues --limit=10 --debug
```

### 5. Creating Job Queues from Scheduled Jobs

The `cronscheduler:process:queues` command automatically creates job queues from scheduled jobs before processing them. No separate command needed!

## Queue Types

Currently supported:
- **database**: Stores jobs in database (default)

Future support (to be implemented):
- **redis**: Uses Redis for queue storage
- **sqs**: Uses Amazon SQS
- **rabbitmq**: Uses RabbitMQ (can integrate with existing QueueBundle)

To change queue type, set the parameter in config:

```php
'parameters' => [
    'job_queue_type' => 'database', // or 'redis', 'sqs', 'rabbitmq'
]
```

## Job Queue Statuses

- `pending`: Job is waiting to be processed
- `processing`: Job is currently being processed
- `completed`: Job completed successfully
- `failed`: Job failed after max attempts
- `cancelled`: Job was cancelled

## Retry Logic

Jobs automatically retry on failure with exponential backoff:
- 1st retry: 2 minutes
- 2nd retry: 4 minutes
- 3rd retry: 8 minutes
- etc.

Maximum attempts can be configured per job (default: 3).

## Database Schema

The `job_queues` table includes:
- id, name, command, arguments
- trigger_at (when to execute)
- status (pending, processing, completed, failed, cancelled)
- priority (higher priority jobs run first)
- attempts, max_attempts
- error_message
- started_at, completed_at
- queue_type, queue_id (for external queues)
- payload (for callback functions)
- scheduled_job_id (link to ScheduledJob if created from one)
