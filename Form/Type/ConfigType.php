<?php

declare(strict_types=1);

namespace MauticPlugin\CronSchedulerBundle\Form\Type;

use Mautic\EmailBundle\Form\Type\EmailListType;
use Mautic\SmsBundle\Form\Type\SmsListType;
use Mautic\SmsBundle\Sms\TransportChain as SmsTransportChain;
use MauticPlugin\WhatsappBundle\Form\Type\WhatsappMessageListType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ConfigType extends AbstractType
{
    /**
     * @var SmsTransportChain
     */
    private $smsTransportChain;

    public function __construct(SmsTransportChain $smsTransportChain)
    {
        $this->smsTransportChain = $smsTransportChain;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('log_retention_days', NumberType::class, [
            'label'      => 'mautic.cronscheduler.log.retention.days',
            'label_attr' => ['class' => 'control-label'],
            'attr'       => [
                'class'   => 'form-control',
                'tooltip' => 'mautic.cronscheduler.log.retention.days.tooltip',
            ],
        ]);

        $channelChoices = [
            'mautic.cron_scheduler.alert.channel.email' => 'email',
        ];

        if (count($this->smsTransportChain->getEnabledTransports()) > 0) {
            $channelChoices['mautic.cron_scheduler.alert.channel.sms'] = 'sms';
        }

        $whatsappAvailable = class_exists(WhatsappMessageListType::class);
        if ($whatsappAvailable) {
            $channelChoices['mautic.cron_scheduler.alert.channel.whatsapp'] = 'whatsapp';
        }

        $currentChannel = $options['data']['failure_alert_channel'] ?? 'email';
        if ($currentChannel && !in_array($currentChannel, $channelChoices, true)) {
            $channelChoices['mautic.cron_scheduler.alert.channel.'.$currentChannel] = $currentChannel;
        }

        $builder->add('failure_alert_channel', ChoiceType::class, [
            'label'       => 'mautic.cron_scheduler.alert.channel',
            'label_attr'  => ['class' => 'control-label'],
            'choices'     => $channelChoices,
            'required'    => false,
            'placeholder' => false,
            'attr'        => [
                'class'   => 'form-control',
                'tooltip' => 'mautic.cron_scheduler.alert.channel.tooltip',
            ],
        ]);

        $builder->add('failure_alert_email_template', EmailListType::class, [
            'label'      => 'mautic.cron_scheduler.alert.email_template',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'multiple'   => false,
            'attr'       => [
                'class'        => 'form-control',
                'tooltip'      => 'mautic.cron_scheduler.alert.email_template.tooltip',
                'data-show-on' => '{"config_cronschedulerconfig_failure_alert_channel":["email"]}',
            ],
        ]);

        $builder->add('failure_alert_sms_template', SmsListType::class, [
            'label'      => 'mautic.cron_scheduler.alert.sms_template',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'multiple'   => false,
            'attr'       => [
                'class'        => 'form-control',
                'tooltip'      => 'mautic.cron_scheduler.alert.sms_template.tooltip',
                'data-show-on' => '{"config_cronschedulerconfig_failure_alert_channel":["sms"]}',
            ],
        ]);

        if ($whatsappAvailable) {
            $builder->add('failure_alert_whatsapp_template', WhatsappMessageListType::class, [
                'label'      => 'mautic.cron_scheduler.alert.whatsapp_template',
                'label_attr' => ['class' => 'control-label'],
                'required'   => false,
                'multiple'   => false,
                'attr'       => [
                    'class'        => 'form-control',
                    'tooltip'      => 'mautic.cron_scheduler.alert.whatsapp_template.tooltip',
                    'data-show-on' => '{"config_cronschedulerconfig_failure_alert_channel":["whatsapp"]}',
                ],
            ]);
        }

        $builder->add('failure_alert_emails', TextType::class, [
            'label'      => 'mautic.cron_scheduler.alert.emails',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'attr'       => [
                'class'        => 'form-control',
                'placeholder'  => 'ops@example.com, oncall@example.com',
                'tooltip'      => 'mautic.cron_scheduler.alert.emails.tooltip',
                'data-show-on' => '{"config_cronschedulerconfig_failure_alert_channel":["email"]}',
            ],
            'constraints' => [
                new Callback([
                    'callback' => function ($value, ExecutionContextInterface $context) {
                        if (empty($value)) {
                            return;
                        }

                        $emails = array_filter(array_map('trim', explode(',', (string) $value)));
                        $emailConstraint = new Email(['message' => 'mautic.cron_scheduler.alert.emails.invalid']);

                        foreach ($emails as $email) {
                            $violations = $context->getValidator()->validate($email, $emailConstraint);
                            if (count($violations) > 0) {
                                $context->buildViolation('mautic.cron_scheduler.alert.emails.invalid')
                                    ->setParameter('%email%', $email)
                                    ->addViolation();

                                return;
                            }
                        }
                    },
                ]),
            ],
        ]);

        $builder->add('failure_alert_phone_numbers', TextType::class, [
            'label'      => 'mautic.cron_scheduler.alert.phone_numbers',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'attr'       => [
                'class'        => 'form-control',
                'placeholder'  => '+919876543210, +14155550123',
                'tooltip'      => 'mautic.cron_scheduler.alert.phone_numbers.tooltip',
                'data-show-on' => '{"config_cronschedulerconfig_failure_alert_channel":["sms","whatsapp"]}',
            ],
            'constraints' => [
                new Callback([
                    'callback' => function ($value, ExecutionContextInterface $context) {
                        if (empty($value)) {
                            return;
                        }

                        $numbers = array_filter(array_map('trim', explode(',', (string) $value)));
                        foreach ($numbers as $number) {
                            if (!preg_match('/^\+?[0-9]{7,15}$/', $number)) {
                                $context->buildViolation('mautic.cron_scheduler.alert.phone_numbers.invalid')
                                    ->setParameter('%number%', $number)
                                    ->addViolation();

                                return;
                            }
                        }
                    },
                ]),
            ],
        ]);
    }

    public function getBlockPrefix()
    {
        return 'cronschedulerconfig';
    }
}
