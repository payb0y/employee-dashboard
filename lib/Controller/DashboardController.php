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
     */
    public function createNote(): JSONResponse {
        $user = $this->userSession->getUser();
        $uid  = $user ? $user->getUID() : '';

        $projectId  = (int)$this->request->getParam('projectId');
        $title      = (string)$this->request->getParam('title', '');
        $content    = (string)$this->request->getParam('content', '');
        $visibility = (string)$this->request->getParam('visibility', 'public');

        if ($projectId <= 0 || $title === '') {
            return new JSONResponse(['error' => 'projectId and title are required'], 400);
        }

        $note = $this->employeeService->createNote($uid, $projectId, $title, $content, $visibility);
        return new JSONResponse($note);
    }

    /**
     * @NoAdminRequired
     */
    public function updateNote(int $id): JSONResponse {
        $user = $this->userSession->getUser();
        $uid  = $user ? $user->getUID() : '';

        $title   = (string)$this->request->getParam('title', '');
        $content = (string)$this->request->getParam('content', '');

        $ok = $this->employeeService->updateNote($id, $uid, $title, $content);
        if (!$ok) {
            return new JSONResponse(['error' => 'Note not found or access denied'], 404);
        }
        return new JSONResponse(['status' => 'ok']);
    }

    /**
     * @NoAdminRequired
     */
    public function deleteNote(int $id): JSONResponse {
        $user = $this->userSession->getUser();
        $uid  = $user ? $user->getUID() : '';

        $ok = $this->employeeService->deleteNote($id, $uid);
        if (!$ok) {
            return new JSONResponse(['error' => 'Note not found or access denied'], 404);
        }
        return new JSONResponse(['status' => 'ok']);
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
