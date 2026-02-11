<?php

namespace MauticPlugin\CronSchedulerBundle\Examples;

use MauticPlugin\CronSchedulerBundle\Service\JobScheduler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Examples of how to schedule function-based jobs
 * 
 * These examples show how to schedule service methods to run at specific times
 */
class FunctionBasedJobsExample
{
    /**
     * Example 1: Schedule email sending on form submit
     * 
     * In your FormSubscriber or FormController:
     */
    public function scheduleEmailOnFormSubmit(ContainerInterface $container, $formId, $emailId)
    {
        $jobScheduler = $container->get('mautic.cronscheduler.service.job_scheduler');
        
        // Schedule email to be sent in 1 minute
        $triggerAt = new \DateTime('+1 minute', new \DateTimeZone('UTC'));
        
        $jobScheduler->scheduleCallback(
            'Send Form Email',
            'mautic.email.model.email:sendEmail', // service:method format
            [$emailId, $formId], // arguments to pass to sendEmail method
            $triggerAt,
            0 // priority
        );
    }

    /**
     * Example 2: Schedule a custom service method
     * 
     * Create your own service method and schedule it:
     */
    public function scheduleCustomServiceMethod(ContainerInterface $container)
    {
        $jobScheduler = $container->get('mautic.cronscheduler.service.job_scheduler');
        
        // Schedule to run in 5 minutes
        $triggerAt = new \DateTime('+5 minutes', new \DateTimeZone('UTC'));
        
        $jobScheduler->scheduleCallback(
            'Process Data Export',
            'mautic.report.model.report:exportData', // Your service:method
            ['reportId' => 123, 'format' => 'csv'], // Arguments
            $triggerAt,
            10 // Higher priority
        );
    }

    /**
     * Example 3: Schedule multiple jobs with different priorities
     */
    public function scheduleMultipleJobs(ContainerInterface $container)
    {
        $jobScheduler = $container->get('mautic.cronscheduler.service.job_scheduler');
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        
        // High priority job - runs first
        $jobScheduler->scheduleCallback(
            'Critical Email',
            'mautic.email.model.email:sendEmail',
            [1],
            $now,
            100 // High priority
        );
        
        // Low priority job - runs later
        $jobScheduler->scheduleCallback(
            'Background Cleanup',
            'mautic.core.model.cleanup:cleanOldData',
            [],
            $now,
            0 // Low priority
        );
    }

    /**
     * Example 4: Schedule job with complex arguments
     */
    public function scheduleJobWithComplexArgs(ContainerInterface $container)
    {
        $jobScheduler = $container->get('mautic.cronscheduler.service.job_scheduler');
        
        $triggerAt = new \DateTime('+10 minutes', new \DateTimeZone('UTC'));
        
        // Pass complex data structures
        $args = [
            'emailId' => 123,
            'recipients' => ['user1@example.com', 'user2@example.com'],
            'options' => [
                'trackOpens' => true,
                'trackClicks' => true,
            ],
        ];
        
        $jobScheduler->scheduleCallback(
            'Send Bulk Email',
            'mautic.email.model.email:sendBulkEmail',
            $args,
            $triggerAt
        );
    }

    /**
     * Example 5: Schedule job in EventListener
     * 
     * In your EventListener class:
     */
    public function onLeadCreated(ContainerInterface $container, $leadId)
    {
        $jobScheduler = $container->get('mautic.cronscheduler.service.job_scheduler');
        
        // Schedule welcome email 2 minutes after lead creation
        $triggerAt = new \DateTime('+2 minutes', new \DateTimeZone('UTC'));
        
        $jobScheduler->scheduleCallback(
            'Send Welcome Email',
            'mautic.email.model.email:sendWelcomeEmail',
            [$leadId],
            $triggerAt
        );
    }
}

/**
 * Example Service Method Signature
 * 
 * Your service method should accept the arguments you pass:
 * 
 * class EmailModel
 * {
 *     public function sendEmail($emailId, $formId)
 *     {
 *         // Your implementation
 *     }
 *     
 *     public function sendBulkEmail($emailId, $recipients, $options)
 *     {
 *         // Your implementation
 *     }
 * }
 */
