<?php

declare(strict_types=1);

namespace MauticPlugin\CronSchedulerBundle\Service;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\SmsBundle\Model\SmsModel;
use Mautic\SmsBundle\Sms\TransportChain as SmsTransportChain;
use MauticPlugin\CronSchedulerBundle\CronSchedulerEvents;
use MauticPlugin\CronSchedulerBundle\Entity\JobExecutionLog;
use MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob;
use MauticPlugin\CronSchedulerBundle\Event\JobFailedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class FailureAlertService
{
    /**
     * @var CoreParametersHelper
     */
    private $coreParametersHelper;

    /**
     * @var MailHelper
     */
    private $mailHelper;

    /**
     * @var EmailModel
     */
    private $emailModel;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var SmsModel
     */
    private $smsModel;

    /**
     * @var SmsTransportChain
     */
    private $smsTransport;

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(
        CoreParametersHelper $coreParametersHelper,
        MailHelper $mailHelper,
        EmailModel $emailModel,
        EventDispatcherInterface $dispatcher,
        LoggerInterface $logger,
        RouterInterface $router,
        SmsModel $smsModel,
        SmsTransportChain $smsTransport,
        ContainerInterface $container
    ) {
        $this->coreParametersHelper = $coreParametersHelper;
        $this->mailHelper           = $mailHelper;
        $this->emailModel           = $emailModel;
        $this->dispatcher           = $dispatcher;
        $this->logger               = $logger;
        $this->router               = $router;
        $this->smsModel             = $smsModel;
        $this->smsTransport         = $smsTransport;
        $this->container            = $container;
    }

    /**
     * @param array<string, mixed> $fallback
     */
    public function send(ScheduledJob $job, ?JobExecutionLog $log = null, array $fallback = []): void
    {
        if (!$job->getSendFailureAlert()) {
            return;
        }

        try {
            $tokens  = $this->buildTokens($job, $log, $fallback);
            $payload = $this->buildPayload($job, $tokens, $log);

            try {
                $this->dispatcher->dispatch(
                    CronSchedulerEvents::JOB_FAILED,
                    new JobFailedEvent($job, $payload)
                );
            } catch (\Throwable $e) {
                $this->logger->error(
                    'CronScheduler failure webhook failed for job '.$job->getId().': '.$e->getMessage(),
                    ['exception' => $e]
                );
            }

            $channel = (string) $this->coreParametersHelper->get('failure_alert_channel', 'email');

            switch ($channel) {
                case 'sms':
                    $this->sendSms($tokens);
                    break;
                case 'whatsapp':
                    $this->sendWhatsapp($tokens);
                    break;
                case 'email':
                default:
                    $this->sendEmail($tokens);
                    break;
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                'CronScheduler failure alert failed for job '.$job->getId().': '.$e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    /**
     * @param array<string, mixed> $fallback
     *
     * @return array<string, string>
     */
    private function buildTokens(ScheduledJob $job, ?JobExecutionLog $log, array $fallback): array
    {
        $executedAt = $fallback['executedAt'] ?? null;
        if ($executedAt instanceof \DateTimeInterface) {
            $executedAt = $executedAt->format('Y-m-d H:i:s');
        } elseif ($log && $log->getStartedAt()) {
            $executedAt = $log->getStartedAt()->format('Y-m-d H:i:s');
        } else {
            $executedAt = (new \DateTime())->format('Y-m-d H:i:s');
        }

        $duration = $fallback['duration'] ?? ($log ? $log->getDuration() : null);
        if (null !== $duration && '' !== $duration) {
            $duration = number_format((float) $duration, 2).'s';
        } else {
            $duration = '';
        }

        $exitCode = $fallback['exitCode'] ?? ($log ? $log->getExitCode() : null);
        $reason   = $fallback['failReason'] ?? '';
        if ('' === $reason && $log) {
            $reason = $log->getErrorMessage() ?: $log->getOutput() ?: 'Command failed';
        }
        if ('' === $reason) {
            $reason = 'Command failed';
        }

        $command   = trim((string) $job->getCommand());
        $arguments = trim((string) $job->getArguments());

        return [
            '{job_id}'           => (string) $job->getId(),
            '{job_name}'         => (string) $job->getName(),
            '{job_command}'      => trim($command.' '.$arguments),
            '{job_arguments}'    => $arguments,
            '{job_executed_at}'  => (string) $executedAt,
            '{job_duration}'     => (string) $duration,
            '{job_exit_code}'    => null !== $exitCode ? (string) $exitCode : '',
            '{job_fail_reason}'  => $this->truncate((string) $reason, 1500),
            '{job_log_url}'      => $this->buildJobUrl($job),
        ];
    }

    /**
     * @param array<string, string> $tokens
     */
    private function buildPayload(ScheduledJob $job, array $tokens, ?JobExecutionLog $log): array
    {
        return [
            'job' => [
                'id'        => $job->getId(),
                'name'      => $job->getName(),
                'command'   => $job->getCommand(),
                'arguments' => $job->getArguments(),
            ],
            'execution' => [
                'log_id'      => $log ? $log->getId() : null,
                'executed_at' => $tokens['{job_executed_at}'],
                'duration'    => $tokens['{job_duration}'],
                'exit_code'   => $tokens['{job_exit_code}'],
                'fail_reason' => $tokens['{job_fail_reason}'],
                'log_url'     => $tokens['{job_log_url}'],
            ],
        ];
    }

    /**
     * @param array<string, string> $tokens
     */
    private function sendEmail(array $tokens): void
    {
        $templateId = $this->normalizeId(
            $this->coreParametersHelper->get('failure_alert_email_template')
            ?? $this->coreParametersHelper->get('failure_alert_email_template_id')
        );
        $emails     = $this->splitList((string) $this->coreParametersHelper->get('failure_alert_emails', ''));

        if (!$templateId || empty($emails)) {
            $this->logger->warning('CronScheduler email failure alert skipped: template or recipients are missing.');

            return;
        }

        $email = $this->emailModel->getEntity($templateId);
        if (!$email) {
            $this->logger->warning('CronScheduler email failure alert skipped: template '.$templateId.' was not found.');

            return;
        }

        $mailer = $this->mailHelper->getMailer();
        $mailer->reset(true);
        $mailer->setEmail($email, false, [], [], true);
        $mailer->addTokens($tokens);
        $mailer->setTo($emails);
        $mailer->send(false);
    }

    /**
     * @param array<string, string> $tokens
     */
    private function sendSms(array $tokens): void
    {
        $templateId = $this->normalizeId(
            $this->coreParametersHelper->get('failure_alert_sms_template')
            ?? $this->coreParametersHelper->get('failure_alert_sms_template_id')
        );
        $numbers    = $this->splitList((string) $this->coreParametersHelper->get('failure_alert_phone_numbers', ''));

        if (!$templateId || empty($numbers)) {
            $this->logger->warning('CronScheduler SMS failure alert skipped: template or recipients are missing.');

            return;
        }

        $sms = $this->smsModel->getEntity($templateId);
        if (!$sms) {
            $this->logger->warning('CronScheduler SMS failure alert skipped: template '.$templateId.' was not found.');

            return;
        }

        $content = $this->replaceTokens((string) $sms->getMessage(), $tokens);

        foreach ($numbers as $number) {
            $lead = new Lead();
            $lead->setMobile($number);
            $result = $this->smsTransport->sendSms($lead, $content);
            if (true !== $result) {
                $this->logger->warning(
                    'CronScheduler SMS failure alert failed for '.$number.': '.(is_string($result) ? $result : 'unknown error')
                );
            }
        }
    }

    /**
     * @param array<string, string> $tokens
     */
    private function sendWhatsapp(array $tokens): void
    {
        if (
            !$this->container->has('mautic.whatsappmessage.model.whatsappmessage')
            || !$this->container->has('mautic.whatsappbundle.transport_chain')
        ) {
            $this->logger->warning('CronScheduler WhatsApp failure alert skipped: WhatsApp plugin is not available.');

            return;
        }

        $templateId = $this->normalizeId(
            $this->coreParametersHelper->get('failure_alert_whatsapp_template')
            ?? $this->coreParametersHelper->get('failure_alert_whatsapp_template_id')
        );
        $numbers    = $this->splitList((string) $this->coreParametersHelper->get('failure_alert_phone_numbers', ''));

        if (!$templateId || empty($numbers)) {
            $this->logger->warning('CronScheduler WhatsApp failure alert skipped: template or recipients are missing.');

            return;
        }

        $model     = $this->container->get('mautic.whatsappmessage.model.whatsappmessage');
        $transport = $this->container->get('mautic.whatsappbundle.transport_chain');
        $message   = $model->getEntity($templateId);

        if (!$message) {
            $this->logger->warning('CronScheduler WhatsApp failure alert skipped: template '.$templateId.' was not found.');

            return;
        }

        $personalized = clone $message;
        $customParams = $personalized->getCustomParams();
        foreach ($customParams as $key => $value) {
            $customParams[$key] = $this->replaceTokens((string) $value, $tokens);
        }
        $personalized->setCustomParams($customParams);

        $statClass = \MauticPlugin\WhatsappBundle\Entity\Stat::class;

        foreach ($numbers as $number) {
            $lead = new Lead();
            $lead->setMobile($number);

            $stat = new $statClass();
            $stat->setWhatsappMessage($personalized);
            $stat->setLead($lead);
            $stat->setDateSent(new \DateTime());

            $result = $transport->sendMessage($lead, $personalized, $stat);
            if (true !== $result) {
                $this->logger->warning(
                    'CronScheduler WhatsApp failure alert failed for '.$number.': '.(is_string($result) ? $result : 'unknown error')
                );
            }
        }
    }

    private function buildJobUrl(ScheduledJob $job): string
    {
        $path = $this->router->generate(
            'mautic_cronscheduler_action',
            [
                'objectAction' => 'view',
                'objectId'     => $job->getId(),
            ]
        );

        $siteUrl = rtrim((string) $this->coreParametersHelper->get('site_url'), '/');

        return $siteUrl.$path;
    }

    /**
     * @param array<string, string> $tokens
     */
    private function replaceTokens(string $content, array $tokens): string
    {
        return str_replace(array_keys($tokens), array_values($tokens), $content);
    }

    /**
     * @return string[]
     */
    private function splitList(string $value): array
    {
        if ('' === trim($value)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * @param mixed $value
     */
    private function normalizeId($value): ?int
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        if (null === $value || '' === $value) {
            return null;
        }

        return (int) $value;
    }

    private function truncate(string $value, int $limit): string
    {
        if (function_exists('mb_strlen') && mb_strlen($value) > $limit) {
            return mb_substr($value, 0, $limit).'...';
        }

        if (strlen($value) > $limit) {
            return substr($value, 0, $limit).'...';
        }

        return $value;
    }
}
