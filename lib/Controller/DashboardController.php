<?php

declare(strict_types=1);

namespace OCA\EmployeeDashboard\Controller;

use OCA\EmployeeDashboard\Service\EmployeeService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

class DashboardController extends Controller {

    private EmployeeService $employeeService;
    private IUserSession $userSession;

    public function __construct(
        string $appName,
        IRequest $request,
        EmployeeService $employeeService,
        IUserSession $userSession
    ) {
        parent::__construct($appName, $request);
        $this->employeeService = $employeeService;
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
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function listFiles(): JSONResponse {
        $user = $this->userSession->getUser();
        $uid  = $user ? $user->getUID() : '';

        $path = (string)$this->request->getParam('path', '');
        if ($path === '') {
            return new JSONResponse(['error' => 'path is required'], 400);
        }

        $files = $this->employeeService->listFolderContents($uid, $path);
        return new JSONResponse($files);
    }
}
