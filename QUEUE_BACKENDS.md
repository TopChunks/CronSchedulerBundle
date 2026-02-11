# Queue Backends: Redis, SQS, RabbitMQ

## Overview

The job queue system supports multiple queue backends. This document explains how each backend works and answers common questions.

## Queue Types

### Database Queue (Current Implementation)

- **Storage**: Jobs stored in `job_queues` database table
- **Processing**: Requires `cronscheduler:process:queues` command to run periodically
- **Cron Required**: Yes, you need to run the command in cron
- **Capacity**: Limited by database performance
- **Use Case**: Small to medium workloads (< 1000 jobs/minute)

**Cron Setup:**
```bash
# Run every minute
* * * * * php /path/to/bin/console cronscheduler:process:queues --limit=100
```

### Redis Queue (Future Implementation)

- **Storage**: Jobs stored in Redis lists/sorted sets
- **Processing**: Can use Redis workers OR the command
- **Cron Required**: Optional - Redis workers can process continuously
- **Capacity**: Very high (10,000+ jobs/second)
- **Use Case**: High-volume workloads, distributed processing

**How it works:**
- Jobs are pushed to Redis queues
- Multiple workers can consume from the same queue
- Redis handles job distribution automatically
- No need for cron if using Redis workers

**For 10,000 jobs:**
- Redis can easily handle 10,000 jobs triggering at once
- Jobs are distributed across available workers
- No blocking - Redis queues are very fast

**Setup Options:**

Option 1: Use cron (periodic execution):
```bash
# Runs every minute, processes jobs, then exits
* * * * * php /path/to/bin/console cronscheduler:process:queues --limit=1000
```

Option 2: Use workers with Supervisor (continuous execution):
```bash
# Workers run continuously, checking for jobs constantly
# See WORKER_MODE_EXPLAINED.md for setup instructions
supervisorctl start cronscheduler-worker:*
```

**Note:** The `--daemon` flag is not yet implemented. Use Supervisor or Systemd to run workers continuously.

### Amazon SQS (Future Implementation)

- **Storage**: Jobs stored in AWS SQS queues
- **Processing**: SQS workers OR the command
- **Cron Required**: Optional - SQS workers can process continuously
- **Capacity**: Very high (unlimited scale)
- **Use Case**: Cloud deployments, auto-scaling

**How it works:**
- Jobs are pushed to SQS queues
- SQS automatically distributes jobs to workers
- Built-in retry and dead-letter queue support
- Auto-scaling based on queue depth

**For 10,000 jobs:**
- SQS can handle millions of jobs
- Automatically scales workers based on queue depth
- No blocking - jobs are distributed instantly

**Setup Options:**

Option 1: Use cron (periodic execution):
```bash
* * * * * php /path/to/bin/console cronscheduler:process:queues --limit=1000
```

Option 2: Use workers with Supervisor (continuous execution):
```bash
# Workers run continuously, auto-scale by running multiple instances
# See WORKER_MODE_EXPLAINED.md for setup instructions
supervisorctl start cronscheduler-worker:*
```

### RabbitMQ (Future Implementation)

- **Storage**: Jobs stored in RabbitMQ queues
- **Processing**: RabbitMQ consumers OR the command
- **Cron Required**: Optional - RabbitMQ consumers run continuously
- **Capacity**: Very high (10,000+ jobs/second)
- **Use Case**: Enterprise deployments, complex routing

**How it works:**
- Jobs are published to RabbitMQ exchanges/queues
- Multiple consumers can process jobs in parallel
- RabbitMQ handles load balancing
- Can integrate with existing QueueBundle infrastructure

**For 10,000 jobs:**
- RabbitMQ can easily handle 10,000 jobs at once
- Jobs are distributed across consumers
- No blocking - RabbitMQ is designed for high throughput

**Setup Options:**

Option 1: Use cron (periodic execution):
```bash
* * * * * php /path/to/bin/console cronscheduler:process:queues --limit=1000
```

Option 2: Use workers with Supervisor (continuous execution):
```bash
# Workers run continuously, multiple consumers process in parallel
# See WORKER_MODE_EXPLAINED.md for setup instructions
supervisorctl start cronscheduler-worker:*
```

## Handling 10,000 Jobs at Once

### Database Queue
- **Can handle**: Yes, but slower
- **Recommendation**: Process in batches (limit=1000 per run)
- **Cron frequency**: Run every minute or more frequently
- **Example**: `* * * * * php bin/console cronscheduler:process:queues --limit=1000`

### Redis Queue
- **Can handle**: Yes, easily
- **Recommendation**: Use multiple workers or increase limit
- **Cron frequency**: Optional - workers can run continuously
- **Example**: Run 5 workers: `php bin/console cronscheduler:process:queues --limit=2000` (5 instances)

### SQS Queue
- **Can handle**: Yes, unlimited scale
- **Recommendation**: Let SQS auto-scale workers
- **Cron frequency**: Not needed - workers auto-scale
- **Example**: Deploy workers that auto-scale based on queue depth

### RabbitMQ Queue
- **Can handle**: Yes, easily
- **Recommendation**: Use multiple consumers
- **Cron frequency**: Not needed - consumers run continuously
- **Example**: Run 10 consumers: `php bin/console cronscheduler:process:queues --limit=1000` (10 instances)

## Do You Need Cron?

### Database Queue
- **Yes, cron is recommended**
- Command runs periodically (every minute)
- Processes jobs, then exits
- Cron automatically restarts it

### Redis/SQS/RabbitMQ
- **Cron is optional**
- **Option 1:** Use cron (simpler, works fine for low-medium volume)
- **Option 2:** Use workers with Supervisor/Systemd (better for high volume)
  - Workers run continuously (don't exit)
  - Check for jobs constantly (not just once per minute)
  - Better performance and immediate processing
  - Still need to **start** workers (via Supervisor/Systemd)

## Best Practices

1. **For high volume (10,000+ jobs)**:
   - Use Redis, SQS, or RabbitMQ
   - Run multiple workers/consumers
   - Don't rely on cron - use continuous workers

2. **For low volume (< 1000 jobs/minute)**:
   - Database queue is fine
   - Run command in cron every minute

3. **For distributed systems**:
   - Use Redis, SQS, or RabbitMQ
   - Multiple servers can process from same queue
   - Automatic load balancing

4. **For cloud deployments**:
   - Use SQS for auto-scaling
   - Workers scale automatically based on queue depth

## Migration Path

1. Start with database queue (current)
2. Monitor job volume and processing time
3. When volume increases, migrate to Redis/SQS/RabbitMQ
4. Switch queue type in config: `job_queue_type: redis`
5. Deploy workers (no code changes needed)
