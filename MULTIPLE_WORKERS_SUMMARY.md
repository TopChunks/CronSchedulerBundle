# Multiple Workers: How PHP Handles It

## Quick Answer

**PHP does NOT create threads or multiple app instances.**

**Multiple workers = Multiple separate PHP processes**

Each worker is a completely isolated OS process with its own:
- Memory space
- Database connection  
- PHP interpreter instance

## How It Works

### Process Model

```
┌─────────────────────────────────────────────────────────┐
│                    Server/System                        │
│                                                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐ │
│  │  Worker 1    │  │  Worker 2    │  │  Worker 3    │ │
│  │  (PID: 1234) │  │  (PID: 1235) │  │  (PID: 1236) │ │
│  │              │  │              │  │              │ │
│  │  Memory:     │  │  Memory:     │  │  Memory:     │ │
│  │  50MB        │  │  50MB        │  │  50MB        │ │
│  │              │  │              │  │              │ │
│  │  DB Conn: 1  │  │  DB Conn: 1  │  │  DB Conn: 1  │ │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘ │
│         │                 │                 │         │
│         └─────────────────┼─────────────────┘         │
│                           │                           │
│              ┌────────────▼────────────┐              │
│              │   Database/Queue        │              │
│              │   (Shared Resource)      │              │
│              └──────────────────────────┘              │
└─────────────────────────────────────────────────────────┘
```

### Execution Flow

When you run 3 workers:

1. **Supervisor/Systemd starts 3 separate PHP processes**
2. **Each process runs independently**
3. **Each calls `processJobs()` simultaneously**
4. **Database locking prevents conflicts**

## Race Condition Prevention

### The Problem

Without locking, multiple workers can grab the same job:

```
Worker 1: SELECT * FROM job_queues WHERE status='pending' LIMIT 1
          → Gets Job ID 100

Worker 2: SELECT * FROM job_queues WHERE status='pending' LIMIT 1  
          → Gets Job ID 100 (SAME JOB!)

Worker 3: SELECT * FROM job_queues WHERE status='pending' LIMIT 1
          → Gets Job ID 100 (SAME JOB!)

Result: Job 100 processed 3 times! ❌
```

### The Solution: SELECT FOR UPDATE

With database locking:

```
Worker 1: SELECT ... FOR UPDATE → Gets Job 100 (locked)
Worker 2: SELECT ... FOR UPDATE → Waits... (Job 100 locked)
Worker 3: SELECT ... FOR UPDATE → Waits... (Job 100 locked)

Worker 1: Updates Job 100 → Commits → Releases lock
Worker 2: SELECT ... FOR UPDATE → Gets Job 101 (locked)
Worker 3: SELECT ... FOR UPDATE → Waits... (Job 101 locked)

Worker 2: Updates Job 101 → Commits → Releases lock
Worker 3: SELECT ... FOR UPDATE → Gets Job 102 (locked)

Result: Each job processed exactly once! ✅
```

## Implementation Details

### Current Implementation

The `DatabaseQueueManager::pop()` method uses:

1. **Transaction**: `$this->em->beginTransaction()`
2. **SELECT FOR UPDATE**: Locks the row at database level
3. **Update**: Safely updates status to PROCESSING
4. **Commit**: Releases lock

```php
// Simplified version
public function pop(): ?JobQueue
{
    $this->em->beginTransaction();
    
    // SELECT FOR UPDATE locks the row
    $job = $this->findJobWithLock();
    
    if ($job) {
        $job->setStatus(JobQueue::STATUS_PROCESSING);
        $this->em->flush();
        $this->em->commit();
    }
    
    return $job;
}
```

### How Workers Coordinate

1. **Worker 1** starts transaction, locks Job 100
2. **Worker 2** tries to lock Job 100 → **waits** (blocked)
3. **Worker 3** tries to lock Job 100 → **waits** (blocked)
4. **Worker 1** commits → releases lock
5. **Worker 2** gets lock, processes Job 100
6. **Worker 3** waits, then gets next job

## Performance

### Database Queue

- **10 workers**: ~1,000 jobs/minute
- **50 workers**: ~5,000 jobs/minute
- **Limited by**: Database connection pool, lock contention

### Redis/SQS/RabbitMQ

- **100 workers**: ~100,000 jobs/minute
- **1000 workers**: ~1,000,000 jobs/minute
- **Limited by**: Queue system capacity

## Key Points

1. ✅ **PHP is single-threaded** - no threads
2. ✅ **Multiple workers = multiple processes** - completely isolated
3. ✅ **Database locking prevents conflicts** - SELECT FOR UPDATE
4. ✅ **Each worker processes different jobs** - coordinated by database
5. ✅ **Can scale horizontally** - add more workers = more throughput

## Summary

- **No threads**: PHP doesn't create threads
- **No shared memory**: Each worker has its own memory
- **Process isolation**: Workers don't interfere with each other
- **Database coordination**: Locking ensures jobs aren't duplicated
- **Horizontal scaling**: Add more workers to process more jobs

See `PHP_CONCURRENCY_EXPLAINED.md` for detailed technical explanation.
