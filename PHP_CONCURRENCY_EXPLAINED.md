# PHP Concurrency: Multiple Workers Explained

## How PHP Handles Multiple Workers

### PHP is Single-Threaded

**Important:** PHP does **NOT** create threads. PHP is single-threaded.

### Multiple Workers = Multiple Processes

When you run multiple workers, you're creating **multiple separate PHP processes**, not threads:

```
Worker 1: php bin/console cronscheduler:process:queues (Process ID: 1234)
Worker 2: php bin/console cronscheduler:process:queues (Process ID: 1235)
Worker 3: php bin/console cronscheduler:process:queues (Process ID: 1236)
```

Each worker is:
- ✅ A **separate OS process** (not a thread)
- ✅ **Completely isolated** from other workers
- ✅ Has its own memory space
- ✅ Has its own database connection
- ✅ Can run on different servers (distributed)

### Process Isolation

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   Worker 1      │     │   Worker 2      │     │   Worker 3      │
│   (Process 1)   │     │   (Process 2)   │     │   (Process 3)   │
│                 │     │                 │     │                 │
│  Memory: 50MB   │     │  Memory: 50MB   │     │  Memory: 50MB   │
│  DB Conn: 1     │     │  DB Conn: 1     │     │  DB Conn: 1     │
│                 │     │                 │     │                 │
└────────┬────────┘     └────────┬────────┘     └────────┬────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                    ┌────────────▼────────────┐
                    │   Database/Queue        │
                    │   (Shared Resource)     │
                    └─────────────────────────┘
```

## The Race Condition Problem

### Current Implementation Issue

When multiple workers call `processJobs()` simultaneously:

**Timeline:**
```
Time 0ms: Worker 1 queries: SELECT * FROM job_queues WHERE status='pending' LIMIT 1
          → Gets Job ID 100

Time 1ms: Worker 2 queries: SELECT * FROM job_queues WHERE status='pending' LIMIT 1
          → Gets Job ID 100 (SAME JOB!)

Time 2ms: Worker 1 updates: UPDATE job_queues SET status='processing' WHERE id=100

Time 3ms: Worker 2 updates: UPDATE job_queues SET status='processing' WHERE id=100
          → Both workers process the same job! ❌
```

### The Problem

The current `pop()` method has a **race condition**:

```php
// Current code (UNSAFE)
public function pop(): ?JobQueue
{
    $jobs = $this->repository->findDueJobs(1);  // Step 1: Find job
    if (empty($jobs)) {
        return null;
    }
    
    $job = $jobs[0];
    $job->setStatus(JobQueue::STATUS_PROCESSING);  // Step 2: Update status
    $this->em->flush();  // Step 3: Save
    
    return $job;
}
```

**Problem:** Between Step 1 and Step 2, another worker can grab the same job!

## Solution: Database Locking

### Option 1: SELECT FOR UPDATE (Recommended)

Use database-level locking to prevent race conditions:

```php
public function pop(): ?JobQueue
{
    // Use SELECT FOR UPDATE to lock the row
    $qb = $this->em->createQueryBuilder();
    $qb->select('jq')
       ->from(JobQueue::class, 'jq')
       ->where('jq.status = :status')
       ->andWhere('(jq.triggerAt IS NULL OR jq.triggerAt <= :now)')
       ->setParameter('status', JobQueue::STATUS_PENDING)
       ->setParameter('now', new \DateTime('now', new \DateTimeZone('UTC')))
       ->orderBy('jq.priority', 'DESC')
       ->addOrderBy('jq.triggerAt', 'ASC')
       ->setMaxResults(1)
       ->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);  // Lock!
    
    $job = $qb->getQuery()->getOneOrNullResult();
    
    if (!$job) {
        return null;
    }
    
    // Now safe to update - row is locked
    $job->setStatus(JobQueue::STATUS_PROCESSING);
    $job->setStartedAt(new \DateTime('now', new \DateTimeZone('UTC')));
    $this->em->flush();
    
    return $job;
}
```

**How it works:**
- `SELECT FOR UPDATE` locks the row at database level
- Other workers wait until lock is released
- Only one worker can grab a job at a time
- Lock is released when transaction commits

### Option 2: Optimistic Locking

Use version field to detect concurrent modifications:

```php
// In JobQueue entity
private $version = 1;  // Add version field

// In pop()
$job = $this->repository->findDueJobs(1)[0];
$version = $job->getVersion();

// Try to update with version check
$updated = $this->em->createQueryBuilder()
    ->update(JobQueue::class, 'jq')
    ->set('jq.status', ':processing')
    ->set('jq.version', 'jq.version + 1')
    ->where('jq.id = :id')
    ->andWhere('jq.version = :version')  // Only update if version matches
    ->setParameter('processing', JobQueue::STATUS_PROCESSING)
    ->setParameter('id', $job->getId())
    ->setParameter('version', $version)
    ->getQuery()
    ->execute();

if ($updated === 0) {
    // Someone else grabbed it, try again
    return $this->pop();
}
```

## How Multiple Workers Work Together

### With Proper Locking

```
Worker 1: SELECT FOR UPDATE → Gets Job 100 (locked)
Worker 2: SELECT FOR UPDATE → Waits... (Job 100 is locked)
Worker 3: SELECT FOR UPDATE → Waits... (Job 100 is locked)

Worker 1: Updates Job 100 → Commits → Releases lock
Worker 2: SELECT FOR UPDATE → Gets Job 101 (locked)
Worker 3: SELECT FOR UPDATE → Waits... (Job 101 is locked)

Worker 2: Updates Job 101 → Commits → Releases lock
Worker 3: SELECT FOR UPDATE → Gets Job 102 (locked)
```

### Without Locking (Current Issue)

```
Worker 1: SELECT → Gets Job 100
Worker 2: SELECT → Gets Job 100 (SAME!)
Worker 3: SELECT → Gets Job 100 (SAME!)

Worker 1: Updates Job 100
Worker 2: Updates Job 100 (DUPLICATE!)
Worker 3: Updates Job 100 (DUPLICATE!)

Result: Job 100 processed 3 times! ❌
```

## Performance Considerations

### Database Queue

**With multiple workers:**
- Each worker has its own database connection
- Database handles locking automatically
- Can handle 10-100 workers easily
- Limited by database connection pool

**Example:**
```
10 workers × 100 jobs/minute = 1,000 jobs/minute
```

### Redis/SQS/RabbitMQ

**With multiple workers:**
- Queue system handles distribution automatically
- No locking needed (queue handles it)
- Can scale to 1000+ workers
- Much better performance

**Example:**
```
100 workers × 1000 jobs/minute = 100,000 jobs/minute
```

## Best Practices

1. **Use SELECT FOR UPDATE** for database queues
2. **Limit number of workers** based on database connections
3. **Use Redis/SQS/RabbitMQ** for high-volume scenarios
4. **Monitor worker processes** (Supervisor/Systemd)
5. **Handle failures gracefully** (retry logic)

## Summary

- ✅ PHP creates **separate processes** (not threads)
- ✅ Each worker is **isolated** (own memory, DB connection)
- ✅ Multiple workers can run **simultaneously**
- ⚠️ Need **database locking** to prevent race conditions
- ✅ With proper locking, workers work together safely
- ✅ Redis/SQS/RabbitMQ handle this automatically
