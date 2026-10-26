<?php

declare(strict_types=1);

namespace OCA\EmployeeDashboard\Service;

use OCP\App\IAppManager;
use OCP\IDBConnection;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * projectcreatoraio's timeline summary for each of an employee's projects, for
 * the dashboard-wide Project Timeline widget.
 *
 * The drawer reads one project's summary from projectcreatoraio's own endpoint.
 * The widget needs every project at once, and that endpoint, read once per
 * project, would also sync each project's calculated card dates back to Deck
 * on every dashboard load. So the summaries are built here, from the same
 * services, for the projects the dashboard already lists for this employee
 * (EmployeeService::getUserProjects) — and read-only: the phase hierarchy
 * seeds missing default phases and card dependencies, so each project's build
 * runs in a transaction that is always rolled back.
 *
 * Phases go out without their cards: the widget draws the System Planning row
 * and the phases, and card titles stay in the drawer, behind projectcreatoraio's
 * own access check.
 *
 * projectcreatoraio's classes are looked up at call time, not injected, so this
 * app still boots when that app is disabled; the list is then empty.
 */
class ProjectTimelinesService {

    private const PROVIDER_APP = 'projectcreatoraio';
    private const PROJECT_MAPPER = 'OCA\\ProjectCreatorAIO\\Db\\ProjectMapper';
    private const PHASE_SERVICE = 'OCA\\ProjectCreatorAIO\\Service\\TimelinePhaseService';
    private const PLANNING_SERVICE = 'OCA\\ProjectCreatorAIO\\Service\\TimelinePlanningService';
    private const IMPACT_SERVICE = 'OCA\\ProjectCreatorAIO\\Service\\TimelineImpactService';

    private EmployeeService $employeeService;
    private IAppManager $appManager;
    private ContainerInterface $container;
    private IDBConnection $db;
    private LoggerInterface $logger;

    public function __construct(
        EmployeeService $employeeService,
        IAppManager $appManager,
        ContainerInterface $container,
        IDBConnection $db,
        LoggerInterface $logger
    ) {
        $this->employeeService = $employeeService;
        $this->appManager = $appManager;
        $this->container = $container;
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * @return list<array{id: int, name: string, summary: array<string, mixed>}>
     */
    public function getForUser(string $uid): array {
        if ($uid === ''
            || !$this->appManager->isEnabledForAnyone(self::PROVIDER_APP)
            || !class_exists(self::PROJECT_MAPPER)) {
            return [];
        }

        $mapper = $this->container->get(self::PROJECT_MAPPER);
        $phaseService = $this->container->get(self::PHASE_SERVICE);
        $planningService = $this->container->get(self::PLANNING_SERVICE);
        $impactService = $this->container->get(self::IMPACT_SERVICE);

        $timelines = [];
        foreach ($this->employeeService->getUserProjects($uid) as $row) {
            $project = $mapper->find((int)$row['id']);
            if ($project === null) {
                continue;
            }
            // One rolled-back transaction per project: on PostgreSQL a failed
            // statement poisons the rest of its transaction, so one project's
            // broken plan must not take the others with it.
            $this->db->beginTransaction();
            try {
                $phases = $phaseService->getProjectPhaseHierarchy($project)['phases'];
                $summary = $planningService->buildSummary($project, $phases);
                $summary['delayAnalysis'] = ['hasActiveDelays' => $impactService->analyzeProjectDelays($project, $phases)['hasActiveDelays']];
                $summary['phases'] = array_map([$this, 'withoutCards'], $phases);
            } catch (\Throwable $e) {
                $this->logger->warning('employee_dashboard: timeline for project ' . $row['id'] . ' failed: ' . $e->getMessage(), [
                    'app' => 'employee_dashboard',
                    'exception' => $e,
                ]);
                continue;
            } finally {
                $this->db->rollBack();
            }
            $timelines[] = ['id' => (int)$row['id'], 'name' => (string)$row['name'], 'summary' => $summary];
        }

        return $timelines;
    }

    /**
     * @param array<string, mixed> $phase
     * @return array<string, mixed>
     */
    private function withoutCards(array $phase): array {
        $tasks = $phase['tasks'] ?? [];
        unset($phase['tasks']);
        $phase['taskCount'] = count($tasks);
        $phase['doneCount'] = count(array_filter($tasks, static fn ($t) => !empty($t['isDone'])));
        return $phase;
    }
}
