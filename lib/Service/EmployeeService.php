<?php

declare(strict_types=1);

namespace OCA\EmployeeDashboard\Service;

use OCP\IDBConnection;
use OCP\Files\IRootFolder;

class EmployeeService {

    private IDBConnection $db;
    private IRootFolder $rootFolder;

    public function __construct(IDBConnection $db, IRootFolder $rootFolder) {
        $this->db = $db;
        $this->rootFolder = $rootFolder;
    }

    public function getDashboardData(string $uid): array {
        $orgId    = $this->resolveOrgId($uid);
        $cards    = $this->fetchAssignedCards($uid);
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
            'tasks'        => $this->buildTaskList($cards, $projects),
            'projects'     => $this->formatProjects($projects),
            'timeline'     => $this->fetchTimeline($projectIds),
            'resources'       => $this->computeResources($projects, $projectIds),
            'activityEvents'  => $this->fetchActivityEvents($projectIds),
            'notes'           => $this->fetchNotes($projectIds),
        ];
    }

    // ── Org resolution ───────────────────────────────────────────────

    public function resolveOrgId(string $uid): ?int {
        $sql  = "SELECT id FROM *PREFIX*organizations WHERE admin_uid = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if ($row) {
            return (int)$row['id'];
        }

        $sql2  = "SELECT organization_id FROM *PREFIX*organization_members WHERE user_uid = ? LIMIT 1";
        $stmt2 = $this->db->prepare($sql2);
        $stmt2->execute([$uid]);
        $row2 = $stmt2->fetch();
        if ($row2) {
            return (int)$row2['organization_id'];
        }

        return null;
    }

    // ── Core data fetchers ───────────────────────────────────────────

    private function fetchAssignedCards(string $uid): array {
        $sql = "SELECT c.id, c.title, c.description, c.duedate, c.done,
                       c.stack_id, c.created_at, c.last_modified,
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

    private function fetchUserProjects(string $uid): array {
        $sql = "SELECT DISTINCT p.id, p.name, p.number, p.description,
                       p.board_id, p.status, p.organization_id,
                       p.folder_id, p.folder_path, p.white_board_id,
                       p.client_name, p.created_at
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

    // ── Task list builder ────────────────────────────────────────────

    private function buildTaskList(array $cards, array $projects): array {
        if (empty($cards)) {
            return [];
        }

        $cardIds = array_map(function ($c) { return (int)$c['id']; }, $cards);

        $projectByBoard = [];
        foreach ($projects as $p) {
            $projectByBoard[$p['board_id']] = $p;
        }

        $labelsByCard      = $this->fetchLabelsForCards($cardIds);
        $commentsByCard    = $this->fetchCommentsForCards($cardIds);
        $attachmentsByCard = $this->fetchAttachmentsForCards($cardIds);

        $tasks = [];
        foreach ($cards as $card) {
            $cid  = (int)$card['id'];
            $proj = $projectByBoard[$card['board_id']] ?? null;
            $comments    = $commentsByCard[$cid] ?? [];
            $attachments = $attachmentsByCard[$cid] ?? [];

            $tasks[] = [
                'id'              => $cid,
                'title'           => $card['title'],
                'description'     => $card['description'] ?: '',
                'duedate'         => $card['duedate'],
                'done'            => $card['done'],
                'stackTitle'      => $card['stack_title'],
                'boardTitle'      => $card['board_title'],
                'projectName'     => $proj ? $proj['name'] : '',
                'projectId'       => $proj ? (int)$proj['id'] : null,
                'labels'          => $labelsByCard[$cid] ?? [],
                'comments'        => $comments,
                'attachments'     => $attachments,
                'commentCount'    => count($comments),
                'attachmentCount' => count($attachments),
            ];
        }

        return $tasks;
    }

    private function fetchLabelsForCards(array $cardIds): array {
        if (empty($cardIds)) {
            return [];
        }
        $ph  = implode(',', array_fill(0, count($cardIds), '?'));
        $sql = "SELECT al.card_id, l.id, l.title, l.color
                FROM *PREFIX*deck_assigned_labels al
                JOIN *PREFIX*deck_labels l ON l.id = al.label_id
                WHERE al.card_id IN ({$ph})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($cardIds);

        $result = [];
        while ($row = $stmt->fetch()) {
            $cid = (int)$row['card_id'];
            $result[$cid][] = [
                'id'    => (int)$row['id'],
                'title' => $row['title'],
                'color' => $row['color'],
            ];
        }
        return $result;
    }

    private function fetchCommentsForCards(array $cardIds): array {
        if (empty($cardIds)) {
            return [];
        }
        $ph  = implode(',', array_fill(0, count($cardIds), '?'));
        $sql = "SELECT id, object_id AS card_id, actor_id, message, creation_timestamp
                FROM *PREFIX*comments
                WHERE object_type = 'deckCard'
                  AND object_id IN ({$ph})
                ORDER BY creation_timestamp ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_map('strval', $cardIds));

        $result = [];
        while ($row = $stmt->fetch()) {
            $cid = (int)$row['card_id'];
            $result[$cid][] = [
                'id'        => (int)$row['id'],
                'author'    => $row['actor_id'],
                'message'   => $row['message'],
                'createdAt' => $row['creation_timestamp'],
            ];
        }
        return $result;
    }

    private function fetchAttachmentsForCards(array $cardIds): array {
        if (empty($cardIds)) {
            return [];
        }
        $ph  = implode(',', array_fill(0, count($cardIds), '?'));
        $sql = "SELECT id, card_id, type, data, created_by, created_at
                FROM *PREFIX*deck_attachment
                WHERE card_id IN ({$ph})
                  AND deleted_at = 0
                ORDER BY created_at ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($cardIds);

        $result = [];
        while ($row = $stmt->fetch()) {
            $cid = (int)$row['card_id'];
            $result[$cid][] = [
                'id'        => (int)$row['id'],
                'type'      => $row['type'],
                'name'      => $row['data'],
                'createdBy' => $row['created_by'],
                'createdAt' => date('Y-m-d H:i:s', (int)$row['created_at']),
            ];
        }
        return $result;
    }

    // ── Projects formatter ───────────────────────────────────────────

    private function formatProjects(array $projects): array {
        return array_map(function ($p) {
            return [
                'id'           => (int)$p['id'],
                'name'         => $p['name'],
                'number'       => $p['number'] ?? '',
                'description'  => $p['description'] ?? '',
                'boardId'      => $p['board_id'],
                'status'       => (int)($p['status'] ?? 0),
                'folderId'     => $p['folder_id'] ? (int)$p['folder_id'] : null,
                'folderPath'   => $p['folder_path'] ?? '',
                'whiteBoardId' => $p['white_board_id'] ?? null,
                'clientName'   => $p['client_name'] ?? '',
                'createdAt'    => $p['created_at'],
            ];
        }, $projects);
    }

    // ── Timeline ─────────────────────────────────────────────────────

    private function fetchTimeline(array $projectIds): array {
        if (empty($projectIds)) {
            return [];
        }
        $ph  = implode(',', array_fill(0, count($projectIds), '?'));
        $sql = "SELECT id, project_id, label, start_date, end_date,
                       color, order_index, item_type, system_key
                FROM *PREFIX*project_timeline_items
                WHERE project_id IN ({$ph})
                ORDER BY order_index ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($projectIds);

        $items = [];
        while ($row = $stmt->fetch()) {
            $items[] = [
                'id'         => (int)$row['id'],
                'projectId'  => (int)$row['project_id'],
                'label'      => $row['label'],
                'startDate'  => $row['start_date'],
                'endDate'    => $row['end_date'],
                'color'      => $row['color'],
                'orderIndex' => (int)$row['order_index'],
                'itemType'   => $row['item_type'],
                'systemKey'  => $row['system_key'],
            ];
        }
        return $items;
    }

    // ── Resources ────────────────────────────────────────────────────

    private function computeResources(array $projects, array $projectIds): array {
        $folders     = 0;
        $whiteboards = 0;
        foreach ($projects as $p) {
            if (!empty($p['folder_id'])) {
                $folders++;
            }
            if (!empty($p['white_board_id'])) {
                $whiteboards++;
            }
        }

        $notes = 0;
        if (!empty($projectIds)) {
            $ph   = implode(',', array_fill(0, count($projectIds), '?'));
            $sql  = "SELECT COUNT(*) AS cnt FROM *PREFIX*project_notes WHERE project_id IN ({$ph})";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($projectIds);
            $row = $stmt->fetch();
            if ($row) {
                $notes = (int)$row['cnt'];
            }
        }

        return [
            'files'       => $folders,
            'notes'       => $notes,
            'whiteboards' => $whiteboards,
        ];
    }

    // ── Profile & Org ────────────────────────────────────────────────

    private function getEmployeeProfile(string $uid, ?int $orgId): array {
        $sql  = "SELECT data FROM *PREFIX*accounts WHERE uid = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$uid]);
        $row = $stmt->fetch();

        $displayName = $uid;
        $email = '';
        $role  = '';

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
            $sql2  = "SELECT role FROM *PREFIX*organization_members
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
        $sql  = "SELECT id, name FROM *PREFIX*organizations WHERE id = ?";
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

    // ── Stats computations (unchanged) ───────────────────────────────

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
            $ph  = implode(',', array_fill(0, count($projectIds), '?'));
            $sql = "SELECT label, end_date
                    FROM *PREFIX*project_timeline_items
                    WHERE project_id IN ({$ph})
                      AND item_type = 'milestone'
                      AND end_date >= ?
                    ORDER BY end_date ASC
                    LIMIT 1";
            $params = array_merge($projectIds, [$todayStart->format('Y-m-d')]);
            $stmt   = $this->db->prepare($sql);
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

    // ── Notes ─────────────────────────────────────────────────────

    private function fetchNotes(array $projectIds): array {
        if (empty($projectIds)) {
            return [];
        }
        $ph  = implode(',', array_fill(0, count($projectIds), '?'));
        $sql = "SELECT id, project_id, user_id, title, content, visibility,
                       created_at, updated_at
                FROM *PREFIX*project_notes
                WHERE project_id IN ({$ph})
                ORDER BY updated_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($projectIds);

        $items = [];
        while ($row = $stmt->fetch()) {
            $items[] = [
                'id'         => (int)$row['id'],
                'projectId'  => (int)$row['project_id'],
                'userId'     => $row['user_id'],
                'title'      => $row['title'],
                'content'    => $row['content'],
                'visibility' => $row['visibility'],
                'createdAt'  => $row['created_at'],
                'updatedAt'  => $row['updated_at'],
            ];
        }
        return $items;
    }

    public function createNote(string $uid, int $projectId, string $title, string $content, string $visibility = 'public'): array {
        $now = (new \DateTime())->format('Y-m-d H:i:s');
        $sql = "INSERT INTO *PREFIX*project_notes (project_id, user_id, title, content, visibility, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$projectId, $uid, $title, $content, $visibility, $now, $now]);
        $id = (int)$this->db->lastInsertId('*PREFIX*project_notes');

        return [
            'id'         => $id,
            'projectId'  => $projectId,
            'userId'     => $uid,
            'title'      => $title,
            'content'    => $content,
            'visibility' => $visibility,
            'createdAt'  => $now,
            'updatedAt'  => $now,
        ];
    }

    public function updateNote(int $noteId, string $uid, string $title, string $content): bool {
        $now = (new \DateTime())->format('Y-m-d H:i:s');
        $sql = "UPDATE *PREFIX*project_notes SET title = ?, content = ?, updated_at = ?
                WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$title, $content, $now, $noteId, $uid]);
        return $stmt->rowCount() > 0;
    }

    public function deleteNote(int $noteId, string $uid): bool {
        $sql = "DELETE FROM *PREFIX*project_notes WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$noteId, $uid]);
        return $stmt->rowCount() > 0;
    }

    // ── File listing ─────────────────────────────────────────────

    public function listFolderContents(string $uid, string $folderPath): array {
        try {
            $userFolder = $this->rootFolder->getUserFolder($uid);
        } catch (\Exception $e) {
            return [];
        }

        $cleanPath = trim($folderPath, '/');
        if ($cleanPath === '') {
            return [];
        }

        try {
            $folder = $userFolder->get($cleanPath);
        } catch (\OCP\Files\NotFoundException $e) {
            return [];
        }

        if (!($folder instanceof \OCP\Files\Folder)) {
            return [];
        }

        $items = [];
        foreach ($folder->getDirectoryListing() as $node) {
            $isFolder = $node instanceof \OCP\Files\Folder;
            $items[] = [
                'name'     => $node->getName(),
                'size'     => $node->getSize(),
                'type'     => $isFolder ? 'folder' : 'file',
                'mtime'    => $node->getMTime(),
                'mimetype' => $isFolder ? 'httpd/unix-directory' : ($node instanceof \OCP\Files\File ? $node->getMimeType() : ''),
                'path'     => $cleanPath . '/' . $node->getName(),
            ];
        }

        usort($items, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'folder' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return $items;
    }

    // ── Activity Events ──────────────────────────────────────────

    private function fetchActivityEvents(array $projectIds): array {
        if (empty($projectIds)) {
            return [];
        }
        $ph  = implode(',', array_fill(0, count($projectIds), '?'));
        $sql = "SELECT id, project_id, actor_uid, actor_display_name,
                       event_type, payload_json, occurred_at
                FROM *PREFIX*project_activity_events
                WHERE project_id IN ({$ph})
                ORDER BY occurred_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($projectIds);

        $items = [];
        while ($row = $stmt->fetch()) {
            $items[] = [
                'id'          => (int)$row['id'],
                'projectId'   => (int)$row['project_id'],
                'actorUid'    => $row['actor_uid'],
                'actorName'   => $row['actor_display_name'],
                'eventType'   => $row['event_type'],
                'payload'     => json_decode($row['payload_json'] ?? '{}', true),
                'occurredAt'  => $row['occurred_at'],
            ];
        }
        return $items;
    }
}
