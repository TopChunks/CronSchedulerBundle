# Job Queue Implementation Summary

## Overview

A comprehensive job queue system has been implemented for the CronSchedulerBundle that supports:
- Creating job queues from scheduled jobs with intervals
- Internal job scheduling (e.g., schedule email in next minute)
- Extensible architecture for multiple queue backends (database, Redis, SQS, RabbitMQ)

## Architecture

### Core Components

1. **JobQueue Entity** (`Entity/JobQueue.php`)
   - Stores job queue items with: id, name, command/function, trigger_at, status
   - Supports both command execution and callback functions
   - Tracks attempts, errors, and execution times
   - Links to ScheduledJob if created from one

2. **QueueManager Interface** (`Queue/QueueManagerInterface.php`)
   - Abstract interface for queue operations
   - Implementations: DatabaseQueueManager (current), Redis/SQS/RabbitMQ (future)

3. **QueueManagerFactory** (`Queue/QueueManagerFactory.php`)
   - Factory pattern to create appropriate queue manager
   - Configurable via `job_queue_type` parameter

4. **JobScheduler Service** (`Service/JobScheduler.php`)
   - Public API for scheduling jobs
   - Methods: `scheduleJob()`, `scheduleCallback()`, `createJobQueueFromScheduledJob()`

5. **JobQueueProcessor Service** (`Service/JobQueueProcessor.php`)
   - Processes queued jobs
   - Handles both command execution and callback execution
   - Implements retry logic with exponential backoff

6. **SchedulerService Updates**
   - Added `createJobQueuesFromScheduledJobs()` method
   - Can create job queues instead of executing directly

## Database Schema

New table: `job_queues`
- id, name, command, arguments
- trigger_at (when to execute)
- status (pending, processing, completed, failed, cancelled)
- priority, attempts, max_attempts
- error_message, started_at, completed_at
- queue_type, queue_id (for external queues)
- payload (JSON for callback data)
- scheduled_job_id (FK to scheduled_jobs)

## Commands

1. **cronscheduler:trigger:commands**
   - Existing command, now supports `--use-queue` flag
   - When used, creates job queues instead of executing directly

2. **cronscheduler:process:queues** (NEW)
   - Processes pending job queues
   - Options: `--limit`, `--debug`, `--tenant-id`

## Usage Flow

### Flow 1: Scheduled Jobs → Job Queues

1. Scheduled jobs are configured with intervals
2. Run: `php bin/console cronscheduler:trigger:commands --use-queue`
3. System creates JobQueue entries for due scheduled jobs
4. Run: `php bin/console cronscheduler:process:queues`
5. Jobs are executed from the queue

### Flow 2: Internal Scheduling

1. In your code (e.g., form submit handler):
   ```php
   $jobScheduler->scheduleJob(
       'Send Email',
       'mautic:emails:send',
       '--email-id=123',
       new \DateTime('+1 minute')
   );
   ```

2. Run: `php bin/console cronscheduler:process:queues`
3. Job executes at scheduled time

## Configuration

In `Config/config.php`:

```php
'parameters' => [
    'job_queue_type' => 'database', // database, redis, sqs, rabbitmq
]
```

## Future Extensibility

### Adding Redis Support

1. Create `RedisQueueManager` implementing `QueueManagerInterface`
2. Add Redis client dependency
3. Update `QueueManagerFactory` to instantiate it
4. Set `job_queue_type` to 'redis'

### Adding SQS Support

1. Create `SqsQueueManager` implementing `QueueManagerInterface`
2. Add AWS SDK dependency
3. Update `QueueManagerFactory` to instantiate it
4. Set `job_queue_type` to 'sqs'

### Adding RabbitMQ Support

1. Create `RabbitMqQueueManager` implementing `QueueManagerInterface`
2. Integrate with existing QueueBundle RabbitMQ infrastructure
3. Update `QueueManagerFactory` to instantiate it
4. Set `job_queue_type` to 'rabbitmq'

## Benefits

1. **Separation of Concerns**: Scheduling vs Execution
2. **Scalability**: Can process queues independently
3. **Reliability**: Retry logic and error handling
4. **Flexibility**: Support for commands and callbacks
5. **Extensibility**: Easy to add new queue backends
6. **Internal Scheduling**: Schedule jobs programmatically

## Migration Path

1. Existing scheduled jobs continue to work as before
2. Optionally migrate to queue-based execution using `--use-queue`
3. Gradually adopt internal scheduling for new features
4. Switch to external queues (Redis/SQS/RabbitMQ) when needed
