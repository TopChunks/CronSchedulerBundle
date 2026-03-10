<?php

namespace MauticPlugin\CronSchedulerBundle\Controller;

use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Controller\AjaxLookupControllerTrait;
use Symfony\Component\HttpFoundation\JsonResponse;

class AjaxController extends CommonAjaxController
{
    use AjaxLookupControllerTrait;

    /**
     * AJAX endpoint for dropdown logs.
     */
    public function logsAction(): JsonResponse
    {
        $permissions = $this->security->isGranted(
            [
                'cronscheduler:cronscheduler:viewown',
                'cronscheduler:cronscheduler:viewother',
            ],
            'RETURN_ARRAY'
        );

        if (empty($permissions['cronscheduler:cronscheduler:viewown']) && empty($permissions['cronscheduler:cronscheduler:viewother'])) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $model = $this->getModel('cronscheduler');
        $logs  = $model->getLogsRepository()->getLatestLogs();

        $html = $this->renderView('@CronScheduler/CronScheduler/logs.html.twig', [
            'logs' => $logs,
            'tmpl' => 'dropdown',
        ]);

        return $this->sendJsonResponse([
            'success' => 1,
            'html'    => $html,
        ]);
    }
}
