<?php

declare(strict_types=1);

namespace OCA\EmployeeDashboard\Controller;

use OCA\EmployeeDashboard\Service\EmployeeService;
use OCA\EmployeeDashboard\Service\ProjectTimelinesService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

class DashboardController extends Controller {

    private EmployeeService $employeeService;
    private ProjectTimelinesService $projectTimelinesService;
    private IUserSession $userSession;

    public function __construct(
        string $appName,
        IRequest $request,
        EmployeeService $employeeService,
        ProjectTimelinesService $projectTimelinesService,
        IUserSession $userSession
    ) {
        parent::__construct($appName, $request);
        $this->employeeService = $employeeService;
        $this->projectTimelinesService = $projectTimelinesService;
        $this->userSession = $userSession;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getData(): JSONResponse {
        $user = $this->userSession->getUser();
        $uid  = $user ? $user->getUID() : '';

        $data = $this->employeeService->getDashboardData($uid);

        return new JSONResponse($data);
    }

    /**
     * Each of this employee's projects with projectcreatoraio's timeline
     * summary, for the dashboard-wide Project Timeline widget. Read-only.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getProjectTimelines(): JSONResponse {
        $user = $this->userSession->getUser();
        $uid  = $user ? $user->getUID() : '';

        return new JSONResponse(['projects' => $this->projectTimelinesService->getForUser($uid)]);
    }
}
