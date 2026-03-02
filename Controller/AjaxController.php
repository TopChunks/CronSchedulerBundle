<?php

namespace MauticPlugin\CronSchedulerBundle\Controller;

use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Controller\AjaxLookupControllerTrait;
use Symfony\Component\HttpFoundation\JsonResponse;

class AjaxController extends CommonAjaxController
{
    use AjaxLookupControllerTrait;

    /**
     * AJAX endpoint for dropdown logs
     */
    protected function logsAction()
    {
        if (!$this->get('mautic.security')->isGranted('cronscheduler:cronscheduler:viewown')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        /** @var \MauticPlugin\CronSchedulerBundle\Model\CronSchedulerModel $model */
        $model = $this->getModel('cronscheduler');
        $logs = $model->getLogsRepository()->getLatestLogs();

        $html = $this->renderView(
            'CronSchedulerBundle:CronScheduler:logs.html.php',
            [
                'logs' => $logs,
                'tmpl' => 'dropdown',
            ]
        );

        return new JsonResponse([
            'success' => true,
            'html' => $html
        ]);
    }
}
