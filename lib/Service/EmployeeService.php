<?php

declare(strict_types=1);

namespace OCA\EmployeeDashboard\Service;

use OCP\IDBConnection;

class EmployeeService {

    private IDBConnection $db;

    public function __construct(IDBConnection $db) {
        $this->db = $db;
    }

    /**
     * Build the full employee dashboard payload.
     *
     * TODO: Each section currently returns placeholder/stub data.
     * Wire up real queries as widgets are implemented.
     */
    public function getDashboardData(string $uid): array {
        $orgId = $this->resolveOrgId($uid);

        return [
            'employee'     => $this->getEmployeeProfile($uid, $orgId),
            'organization' => $this->getOrganization($orgId),
            'focusNow'     => $this->getFocusNow($uid),
            'workload'     => $this->getWorkloadSummary($uid),
            'schedule'     => $this->getScheduleSnapshot($uid),
            'tasks'        => $this->getAssignedTasks($uid),
            'projects'     => $this->getMyProjects($uid),
            'timeline'     => $this->getTimeline($uid),
            'resources'    => $this->getResources($uid),
        ];
    }

    public function resolveOrgId(string $uid): ?int {
        $sql = "SELECT id FROM *PREFIX*organizations WHERE admin_uid = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if ($row) {
            return (int)$row['id'];
        }

        $sql2 = "SELECT organization_id FROM *PREFIX*organization_members WHERE user_uid = ? LIMIT 1";
        $stmt2 = $this->db->prepare($sql2);
        $stmt2->execute([$uid]);
        $row2 = $stmt2->fetch();
        if ($row2) {
            return (int)$row2['organization_id'];
        }

        return null;
    }

    private function getEmployeeProfile(string $uid, ?int $orgId): array {
        // TODO: fetch from oc_users + oc_accounts + oc_organization_members
        return [
            'uid' => $uid,
            'displayName' => $uid,
            'email' => '',
            'title' => '',
            'role' => '',
            'orgId' => $orgId,
        ];
    }

    private function getOrganization(?int $orgId): ?array {
        if ($orgId === null) {
            return null;
        }
        // TODO: fetch from oc_organizations
        return [
            'id' => $orgId,
            'name' => '',
        ];
    }

    private function getFocusNow(string $uid): array {
        // TODO: overdue + due today + next upcoming + oldest unresolved
        return [
            'overdue' => 0,
            'dueToday' => 0,
            'nextTask' => null,
            'oldestTask' => null,
        ];
    }

    private function getWorkloadSummary(string $uid): array {
        // TODO: open, done, completion %, active projects
        return [
            'open' => 0,
            'done' => 0,
            'completionPct' => 0,
            'activeProjects' => 0,
        ];
    }

    private function getScheduleSnapshot(string $uid): array {
        // TODO: due today, due this week, no due date, next milestone
        return [
            'dueToday' => 0,
            'dueThisWeek' => 0,
            'noDueDate' => 0,
            'nextMilestone' => null,
        ];
    }

    private function getAssignedTasks(string $uid): array {
        // TODO: full task list from oc_deck_assigned_users join chain
        return [];
    }

    private function getMyProjects(string $uid): array {
        // TODO: projects linked through assigned cards
        return [];
    }

    private function getTimeline(string $uid): array {
        // TODO: oc_project_timeline_items for assigned projects
        return [];
    }

    private function getResources(string $uid): array {
        // TODO: files, notes, whiteboards for assigned projects
        return [
            'files' => 0,
            'notes' => 0,
            'whiteboards' => 0,
        ];
    }
}
