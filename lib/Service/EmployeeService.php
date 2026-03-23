<?php

declare(strict_types=1);

namespace OCA\EmployeeDashboard\Service;

use OCP\IDBConnection;

class EmployeeService {

    private IDBConnection $db;

    public function __construct(IDBConnection $db) {
        $this->db = $db;
    }

    public function getDashboardData(string $uid): array {
        $orgId = $this->resolveOrgId($uid);
        $cards = $this->fetchAssignedCards($uid);
        $projects = $this->fetchUserProjects($uid);
        $projectIds = array_map(function ($p) {
            return (int)$p['id'];
        }, $projects);

        return [
            'employee'     => $this->getEmployeeProfile($uid, $orgId),
            'organization' => $this->getOrganization($orgId),
            'focusNow'     => $this->computeFocusNow($cards),
            'workload'     => $this->computeWorkload($cards, $projects),
            'schedule'     => $this->computeSchedule($cards, $projectIds),
            'tasks'        => [],
            'projects'     => [],
            'timeline'     => [],
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

    /**
     * Fetch all non-deleted, non-archived cards assigned to the user,
     * joined with stack and board info.
     */
    private function fetchAssignedCards(string $uid): array {
        $sql = "SELECT c.id, c.title, c.duedate, c.done, c.stack_id,
                       c.created_at, c.last_modified,
                       s.title AS stack_title, s.board_id,
                       b.title AS board_title
                FROM *PREFIX*deck_assigned_users au
                JOIN *PREFIX*deck_cards c ON c.id = au.card_id
                JOIN *PREFIX*deck_stacks s ON s.id = c.stack_id
                JOIN *PREFIX*deck_boards b ON b.id = s.board_id
                WHERE au.participant = ?
                  AND c.deleted_at = 0
                  AND s.deleted_at = 0
                  AND b.deleted_at = 0
                  AND c.archived = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$uid]);
        return $stmt->fetchAll();
    }

    /**
     * Fetch projects linked to the user through their assigned cards' boards.
     */
    private function fetchUserProjects(string $uid): array {
        $sql = "SELECT DISTINCT p.id, p.name, p.board_id, p.status
                FROM *PREFIX*deck_assigned_users au
                JOIN *PREFIX*deck_cards c ON c.id = au.card_id
                JOIN *PREFIX*deck_stacks s ON s.id = c.stack_id
                JOIN *PREFIX*custom_projects p ON p.board_id = s.board_id
                WHERE au.participant = ?
                  AND c.deleted_at = 0
                  AND s.deleted_at = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$uid]);
        return $stmt->fetchAll();
    }

    private function getEmployeeProfile(string $uid, ?int $orgId): array {
        $sql = "SELECT data FROM *PREFIX*accounts WHERE uid = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$uid]);
        $row = $stmt->fetch();

        $displayName = $uid;
        $email = '';
        $role = '';

        if ($row && !empty($row['data'])) {
            $acct = json_decode($row['data'], true);
            if (!empty($acct['displayname']['value'])) {
                $displayName = $acct['displayname']['value'];
            }
            $email = $acct['email']['value'] ?? '';
            $role  = $acct['role']['value'] ?? '';
        }

        $memberRole = '';
        if ($orgId !== null) {
            $sql2 = "SELECT role FROM *PREFIX*organization_members
                     WHERE user_uid = ? AND organization_id = ? LIMIT 1";
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->execute([$uid, $orgId]);
            $row2 = $stmt2->fetch();
            if ($row2) {
                $memberRole = $row2['role'];
            }
        }

        return [
            'uid'         => $uid,
            'displayName' => $displayName,
            'email'       => $email ?: '',
            'title'       => $memberRole,
            'role'        => $role,
            'orgId'       => $orgId,
        ];
    }

    private function getOrganization(?int $orgId): ?array {
        if ($orgId === null) {
            return null;
        }
        $sql = "SELECT id, name FROM *PREFIX*organizations WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$orgId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return [
            'id'   => (int)$row['id'],
            'name' => $row['name'],
        ];
    }

    private function computeFocusNow(array $cards): array {
        $now        = new \DateTime();
        $todayStart = (clone $now)->setTime(0, 0, 0);
        $todayEnd   = (clone $now)->setTime(23, 59, 59);

        $overdue     = 0;
        $dueToday    = 0;
        $nextTask    = null;
        $nextTaskDue = null;
        $oldestTask      = null;
        $oldestCreatedAt = null;

        foreach ($cards as $card) {
            if ($card['done'] !== null) {
                continue;
            }

            if ($card['duedate'] !== null) {
                $due = new \DateTime($card['duedate']);
                if ($due < $todayStart) {
                    $overdue++;
                } elseif ($due <= $todayEnd) {
                    $dueToday++;
                }
                if ($due > $now && ($nextTaskDue === null || $due < $nextTaskDue)) {
                    $nextTask = [
                        'title'   => $card['title'],
                        'id'      => (int)$card['id'],
                        'duedate' => $card['duedate'],
                    ];
                    $nextTaskDue = $due;
                }
            }

            $created = (int)$card['created_at'];
            if ($oldestCreatedAt === null || $created < $oldestCreatedAt) {
                $oldestTask = [
                    'title' => $card['title'],
                    'id'    => (int)$card['id'],
                ];
                $oldestCreatedAt = $created;
            }
        }

        return [
            'overdue'    => $overdue,
            'dueToday'   => $dueToday,
            'nextTask'   => $nextTask,
            'oldestTask' => $oldestTask,
        ];
    }

    private function computeWorkload(array $cards, array $projects): array {
        $open = 0;
        $done = 0;

        foreach ($cards as $card) {
            if ($card['done'] !== null) {
                $done++;
            } else {
                $open++;
            }
        }

        $total = $open + $done;
        $pct   = $total > 0 ? (int)round(($done / $total) * 100) : 0;

        return [
            'open'           => $open,
            'done'           => $done,
            'completionPct'  => $pct,
            'activeProjects' => count($projects),
        ];
    }

    private function computeSchedule(array $cards, array $projectIds): array {
        $now        = new \DateTime();
        $todayStart = (clone $now)->setTime(0, 0, 0);
        $todayEnd   = (clone $now)->setTime(23, 59, 59);

        $daysUntilSunday = 7 - (int)$now->format('N');
        $weekEnd = (clone $now)->modify("+{$daysUntilSunday} days")->setTime(23, 59, 59);

        $dueToday    = 0;
        $dueThisWeek = 0;
        $noDueDate   = 0;

        foreach ($cards as $card) {
            if ($card['done'] !== null) {
                continue;
            }

            if ($card['duedate'] === null) {
                $noDueDate++;
            } else {
                $due = new \DateTime($card['duedate']);
                if ($due >= $todayStart && $due <= $todayEnd) {
                    $dueToday++;
                }
                if ($due >= $todayStart && $due <= $weekEnd) {
                    $dueThisWeek++;
                }
            }
        }

        $nextMilestone = null;
        if (!empty($projectIds)) {
            $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
            $sql = "SELECT label, end_date
                    FROM *PREFIX*project_timeline_items
                    WHERE project_id IN ({$placeholders})
                      AND item_type = 'milestone'
                      AND end_date >= ?
                    ORDER BY end_date ASC
                    LIMIT 1";
            $params = array_merge($projectIds, [$todayStart->format('Y-m-d')]);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch();
            if ($row) {
                $nextMilestone = [
                    'label' => $row['label'],
                    'date'  => $row['end_date'],
                ];
            }
        }

        return [
            'dueToday'      => $dueToday,
            'dueThisWeek'   => $dueThisWeek,
            'noDueDate'     => $noDueDate,
            'nextMilestone' => $nextMilestone,
        ];
    }

    private function getResources(string $uid): array {
        // TODO: wire to real file/note/whiteboard counts
        return [
            'files'       => 0,
            'notes'       => 0,
            'whiteboards' => 0,
        ];
    }
}
