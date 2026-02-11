<?php

namespace MauticPlugin\CronSchedulerBundle\Service;

use Doctrine\ORM\EntityManager;
use MauticPlugin\CronSchedulerBundle\Entity\JobQueue;
use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;
use MauticPlugin\CronSchedulerBundle\Service\JobScheduler;
use MauticPlugin\CronSchedulerBundle\Service\SchedulerService;
use MauticPlugin\CronSchedulerBundle\Queue\QueueManagerFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Mautic\CoreBundle\Tenancy\TenantContext;
use Psr\Log\LoggerInterface;

class JobQueueProcessor
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var QueueManagerFactory
     */
    private $queueManagerFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var JobScheduler
     */
    private $jobScheduler;

    /**
     * @var SchedulerService
     */
    private $schedulerService;

    /**
     * @var Application|null
     */
    private $application = null;

    public function __construct(
        EntityManager $em,
        KernelInterface $kernel,
        QueueManagerFactory $queueManagerFactory,
        LoggerInterface $logger,
        ContainerInterface $container,
        JobScheduler $jobScheduler,
        SchedulerService $schedulerService
    ) {
        $this->em = $em;
        $this->kernel = $kernel;
        $this->queueManagerFactory = $queueManagerFactory;
        $this->logger = $logger;
        $this->container = $container;
        $this->jobScheduler = $jobScheduler;
        $this->schedulerService = $schedulerService;
    }

    /**
     * Process a single job from the queue
     *
     * @param JobQueue|null $jobQueue
     * @return array
     */
    public function processJob(JobQueue $jobQueue = null): array
    {
        if (!$jobQueue) {
            $queueManager = $this->queueManagerFactory->getManager();
            $jobQueue = $queueManager->pop();
        }

        if (!$jobQueue) {
            return [
                'success' => false,
                'message' => 'No jobs available',
            ];
        }

        $startTime = microtime(true);
        $jobQueue->incrementAttempts();

        try {
            $result = $this->executeJob($jobQueue);
            
            $duration = microtime(true) - $startTime;
            $jobQueue->setStatus(JobQueue::STATUS_COMPLETED);
            $jobQueue->setCompletedAt(new \DateTime('now', new \DateTimeZone('UTC')));

            $this->em->persist($jobQueue);
            $this->em->flush();

            return [
                'success' => true,
                'jobId' => $jobQueue->getId(),
                'exitCode' => $result['exitCode'] ?? 0,
                'output' => $result['output'] ?? '',
                'duration' => $duration,
            ];
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            $jobQueue->setErrorMessage($e->getMessage());
            
            if ($jobQueue->canRetry()) {
                $jobQueue->setStatus(JobQueue::STATUS_PENDING);
                // Exponential backoff: retry after 2^attempts minutes
                $retryDelay = pow(2, $jobQueue->getAttempts()) * 60;
                $retryAt = new \DateTime("+{$retryDelay} seconds", new \DateTimeZone('UTC'));
                $jobQueue->setTriggerAt($retryAt);
            } else {
                $jobQueue->setStatus(JobQueue::STATUS_FAILED);
            }

            $jobQueue->setCompletedAt(new \DateTime('now', new \DateTimeZone('UTC')));
            $this->em->persist($jobQueue);
            $this->em->flush();

            $this->logger->error('Job queue processing failed', [
                'jobId' => $jobQueue->getId(),
                'error' => $e->getMessage(),
                'attempts' => $jobQueue->getAttempts(),
            ]);

            return [
                'success' => false,
                'jobId' => $jobQueue->getId(),
                'error' => $e->getMessage(),
                'duration' => $duration,
            ];
        }
    }

    /**
     * Create job queues from scheduled jobs that are due
     *
     * @return int Number of job queues created
     */
    public function createJobQueuesFromScheduledJobs(): int
    {
        /** @var \MauticPlugin\CronSchedulerBundle\Entity\ScheduledJobRepository $repo */
        $repo = $this->em->getRepository(ScheduledJob::class);
        
        // Get all published scheduled jobs
        $scheduledJobs = $repo->findBy(['isPublished' => true]);
        
        if (empty($scheduledJobs)) {
            return 0;
        }

        $created = 0;
        foreach ($scheduledJobs as $job) {
            // Check if job is due and doesn't already have a pending queue entry
            if ($this->schedulerService->isDue($job)) {
                // Check if there's already a pending queue entry for this scheduled job
                $existingQueue = $this->em->getRepository(JobQueue::class)
                    ->findOneBy([
                        'scheduledJob' => $job,
                        'status' => JobQueue::STATUS_PENDING,
                    ]);

                if (!$existingQueue) {
                    $jobQueue = $this->jobScheduler->createJobQueueFromScheduledJob($job);
                    if ($jobQueue) {
                        $created++;
                    }
                }
            }
        }

        return $created;
    }

    /**
     * Process multiple jobs from the queue
     *
     * @param int $limit
     * @return array
     */
    public function processJobs(int $limit = 10): array
    {
        $results = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
        ];

        for ($i = 0; $i < $limit; $i++) {
            $result = $this->processJob();
            
            if ($result['success']) {
                $results['succeeded']++;
            } else {
                if (isset($result['jobId'])) {
                    $results['failed']++;
                } else {
                    // No more jobs available
                    break;
                }
            }
            
            $results['processed']++;
        }

        return $results;
    }

    /**
     * Execute a job queue item
     *
     * @param JobQueue $jobQueue
     * @return array
     * @throws \Exception
     */
    private function executeJob(JobQueue $jobQueue): array
    {
        // Check if it's a command or a callback function
        if ($jobQueue->getCommand()) {
            return $this->executeCommand($jobQueue);
        } elseif ($jobQueue->getPayload() && isset($jobQueue->getPayload()['callback'])) {
            return $this->executeCallback($jobQueue);
        }

        throw new \Exception('Job queue has neither command nor callback');
    }

    /**
     * Execute a command
     *
     * @param JobQueue $jobQueue
     * @return array
     * @throws \Exception
     */
    private function executeCommand(JobQueue $jobQueue): array
    {
        if (null === $this->application) {
            $this->application = new Application($this->kernel);
            $this->application->setAutoExit(false);
            $this->application->setCatchExceptions(true);
        }

        $commandString = trim($jobQueue->getCommand() . ' ' . ($jobQueue->getArguments() ?? ''));
        
        // Add tenant ID if available
        if (TenantContext::hasTenant()) {
            $commandString .= ' --tenant-id=' . TenantContext::getTenantId();
        }

        $input = new StringInput($commandString);
        $output = new BufferedOutput();

        $exitCode = $this->application->run($input, $output);
        $outputString = $output->fetch();

        return [
            'exitCode' => $exitCode,
            'output' => $outputString,
        ];
    }

    /**
     * Execute a callback function
     * Callback format: 'service:method' (e.g., 'mautic.email.model.email:sendEmail')
     *
     * @param JobQueue $jobQueue
     * @return array
     * @throws \Exception
     */
    private function executeCallback(JobQueue $jobQueue): array
    {
        $payload = $jobQueue->getPayload();
        $callbackString = $payload['callback'] ?? null;

        if (!$callbackString || !is_string($callbackString)) {
            throw new \Exception('Invalid callback in job queue payload');
        }

        // Parse service:method format
        if (strpos($callbackString, ':') === false) {
            throw new \Exception('Callback must be in format "service:method"');
        }

        [$serviceName, $method] = explode(':', $callbackString, 2);
        
        if (!$this->container->has($serviceName)) {
            throw new \Exception("Service '{$serviceName}' not found");
        }

        $service = $this->container->get($serviceName);
        
        if (!method_exists($service, $method)) {
            throw new \Exception("Method '{$method}' not found in service '{$serviceName}'");
        }

        $args = $payload['args'] ?? [];

        try {
            $result = call_user_func_array([$service, $method], $args);
            
            return [
                'exitCode' => 0,
                'output' => is_string($result) ? $result : json_encode($result),
            ];
        } catch (\Exception $e) {
            throw new \Exception('Callback execution failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
