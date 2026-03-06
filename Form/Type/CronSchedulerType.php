<?php

namespace MauticPlugin\CronSchedulerBundle\Form\Type;

use Mautic\CategoryBundle\Form\Type\CategoryListType;
use Mautic\CoreBundle\Form\Type\ButtonGroupType;
use Mautic\CoreBundle\Form\Type\FormButtonsType;
use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use MauticPlugin\CronSchedulerBundle\Service\CommandProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Cron\CronExpression;

class CronSchedulerType extends AbstractType
{
    private CommandProvider $commandProvider;

    public function __construct(CommandProvider $commandProvider)
    {
        $this->commandProvider = $commandProvider;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'name',
            TextType::class,
            [
                'label'      => 'mautic.cron_scheduler.form.name',
                'label_attr' => ['class' => 'control-label'],
                'required'   => true,
                'attr'       => [
                    'class' => 'form-control',
                ],
            ]
        );

        $builder->add(
            'command',
            ChoiceType::class,
            [
                'label'      => 'mautic.cron_scheduler.form.command',
                'label_attr' => ['class' => 'control-label'],
                'required'   => true,
                'choices'    => $this->commandProvider->getAvailableCommands(),
                'attr'       => [
                    'class' => 'form-control',
                ],
            ]
        );

        $builder->add(
            'arguments',
            TextType::class,
            [
                'label'      => 'mautic.cron_scheduler.form.arguments',
                'label_attr' => ['class' => 'control-label'],
                'required'   => false,
                'attr'       => [
                    'class' => 'form-control',
                ],
            ]
        );

        $builder->add(
            'priority',
            NumberType::class,
            [
                'label' => 'mautic.cron_scheduler.form.priority',
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'mautic.cron_scheduler.form.priority.tooltip',
                ],
                'label_attr' => ['class' => 'control-label'],
                'required' => false,
            ]
        );

        $builder->add(
            'category',
            CategoryListType::class,
            [
                'bundle' => 'CronSchedulerBundle',
            ],
        );

        $builder->add('isPublished', YesNoButtonGroupType::class);

        $builder->add(
            'publishUp',
            DateTimeType::class,
            [
                'widget'     => 'single_text',
                'label'      => 'mautic.core.form.publishup',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'       => 'form-control',
                    'data-toggle' => 'datetime',
                ],
                'format'   => 'yyyy-MM-dd HH:mm',
                'required' => false,
            ]
        );

        $builder->add(
            'publishDown',
            DateTimeType::class,
            [
                'widget'     => 'single_text',
                'label'      => 'mautic.core.form.publishdown',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'       => 'form-control',
                    'data-toggle' => 'datetime',
                ],
                'format'   => 'yyyy-MM-dd HH:mm',
                'required' => false,
            ]
        );

        $choices = [
            'interval'  => 'mautic.cronscheduler.trigger.for.every',
            'date'      => 'mautic.campaign.form.type.date',
            'cron'      => 'mautic.cron_scheduler.cron.notation'
        ];

        $default = key($choices);
        $entity = $options['data'];
        $triggerMode = $entity instanceof \MauticPlugin\CronSchedulerBundle\Entity\ScheduledJob
            ? ($entity->getTriggerMode() ?? $default)
            : $default;

        $builder->add('triggerMode', ButtonGroupType::class, [
            'choices' => array_flip($choices),
            'expanded' => true,
            'multiple' => false,
            'label_attr'        => ['class' => 'control-label'],
            'placeholder'       => false,
            'required'          => false,
            'data'     => $triggerMode,
            'attr' => [
                'onchange' => 'Mautic.cronSchedulerToggleTimeframes();',
            ],
        ]);

        $builder->add(
            'triggerDate',
            DateTimeType::class,
            [
                'label'  => false,
                'attr'   => [
                    'class'       => 'form-control',
                    'preaddon'    => 'fa fa-calendar',
                    'data-toggle' => 'datetime',
                ],
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd HH:mm',
                'constraints' => [
                    new Callback([
                        'callback' => function ($value, ExecutionContextInterface $context) {
                            $form = $context->getRoot();
                            $triggerMode = $form->get('triggerMode')->getData();
                            if ($triggerMode === 'date') {
                                if (empty($value)) {
                                    $context->buildViolation('mautic.cronscheduler.triggerDate.required')
                                        ->addViolation();
                                    return;
                                }
                            }
                        }
                    ])
                ],
            ]
        );

        $builder->add(
            'triggerInterval',
            NumberType::class,
            [
                'label' => false,
                'attr'  => [
                    'class'    => 'form-control',
                    'preaddon' => 'symbol-hashtag',
                ],
                'constraints' => [
                    new Callback([
                        'callback' => function ($value, ExecutionContextInterface $context) {
                            $form = $context->getRoot();
                            $triggerMode = $form->get('triggerMode')->getData();
                            if ($triggerMode === 'interval') {
                                if (empty($value)) {
                                    $context->buildViolation('mautic.cronscheduler.interval.required')
                                        ->addViolation();
                                    return;
                                }
                            }
                        }
                    ])
                ],
            ]
        );

        $builder->add('triggerIntervalUnit', ChoiceType::class, [
            'label' => false,
            'choices' => [
                'mautic.campaign.event.intervalunit.choice.i' => 'i',
                'mautic.campaign.event.intervalunit.choice.h' => 'h',
                'mautic.campaign.event.intervalunit.choice.d' => 'd',
                'mautic.campaign.event.intervalunit.choice.m' => 'm',
                'mautic.campaign.event.intervalunit.choice.y' => 'y',
            ],
            'attr' => [
                'class' => 'form-control',
            ],
            'constraints' => [
                new Callback([
                    'callback' => function ($value, ExecutionContextInterface $context) {
                        $form = $context->getRoot();
                        $triggerMode = $form->get('triggerMode')->getData();
                        if ($triggerMode === 'interval') {
                            if (empty($value)) {
                                $context->buildViolation('mautic.cronscheduler.triggerIntervalUnit.required')
                                    ->addViolation();
                                return;
                            }
                        }
                    }
                ])
            ]
        ]);

        $data = $this->getTimeValue($options['data'], 'triggerHour');
        $builder->add(
            'triggerHour',
            TextType::class,
            [
                'label' => false,
                'attr'  => [
                    'class'        => 'form-control',
                    'data-toggle'  => 'time',
                    'data-format'  => 'H:i',
                    'autocomplete' => 'off',
                ],
                'required' => false,
                'data'  => ($data) ? $data->format('H:i') : $data,
              ],
        );

        $builder->add(
            'triggerRestrictedDaysOfWeek',
            ChoiceType::class,
            [
                'label'    => 'mautic.cron_scheduler.days_of_week',
                'attr'     => [
                    'class' => 'form-control',
                ],
                'choices'  => [
                    'mautic.report.schedule.day.monday'     => 1,
                    'mautic.report.schedule.day.tuesday'    => 2,
                    'mautic.report.schedule.day.wednesday'  => 3,
                    'mautic.report.schedule.day.thursday'   => 4,
                    'mautic.report.schedule.day.friday'     => 5,
                    'mautic.report.schedule.day.saturday'   => 6,
                    'mautic.report.schedule.day.sunday'     => 0,
                    'mautic.report.schedule.day.week_days'  => -1,
                ],
                'expanded'          => true,
                'multiple'          => true,
                'required'          => false,
            ]
        );

        $builder->add(
            'cronNotation',
            TextType::class,
            [
                'label'      => 'mautic.cron_scheduler.cron.notation',
                'label_attr' => ['class' => 'control-label'],
                'required'   => false,
                'attr'       => [
                    'class' => 'form-control',
                    'tooltip' => 'mautic.cronscheduler.notation.tooltip',
                    'placeholder' => '* * * * *'
                ],
                'constraints' => [
                    new Callback([
                        'callback' => function ($value, ExecutionContextInterface $context) {
                            // Get the form data
                            $form = $context->getRoot();
                            $triggerMode = $form->get('triggerMode')->getData();

                            // Only validate if cron mode is selected
                            if ($triggerMode === 'cron') {
                                if (empty($value)) {
                                    $context->buildViolation('mautic.cronscheduler.notation.required')
                                        ->addViolation();
                                    return;
                                }

                                // Validate cron expression using vendor package
                                try {
                                    new CronExpression($value);
                                } catch (\Exception $e) {
                                    $context->buildViolation('mautic.cron_scheduler.cron_notation.invalid')
                                        ->addViolation();
                                }
                            }
                        }
                    ])
                ],
            ]
        );

        $builder->add('buttons', FormButtonsType::class);
    }

    private function getTimeValue($data, string $field): ?\DateTime
    {
        if (!$data) {
            return null;
        }

        $getter = 'get' . ucfirst($field);

        if (is_object($data) && method_exists($data, $getter)) {
            $value = $data->$getter();

            if ($value instanceof \DateTime) {
                return $value;
            }

            if (is_string($value)) {
                try {
                    return new \DateTime($value);
                } catch (\Exception $e) {
                    return null;
                }
            }
        }

        return null;
    }

    public function getBlockPrefix()
    {
        return 'cronscheduler';
    }
}
