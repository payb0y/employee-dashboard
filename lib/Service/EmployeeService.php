<?php

declare(strict_types=1);

namespace OCA\EmployeeDashboard\Service;

use OCP\App\IAppManager;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class EmployeeService {

    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';
    private const NOMINATIM_TIMEOUT_SECONDS = 10;

    private IDBConnection $db;
    private IConfig $config;
    private IClientService $clientService;
    private IAppManager $appManager;
    private LoggerInterface $logger;

    public function __construct(
        IDBConnection $db,
        IConfig $config,
        IClientService $clientService,
        IAppManager $appManager,
        LoggerInterface $logger
    ) {
        $this->db            = $db;
        $this->config        = $config;
        $this->clientService = $clientService;
        $this->appManager    = $appManager;
        $this->logger        = $logger;
    }

    public function getDashboardData(string $uid): array {
        $orgId    = $this->resolveOrgId($uid);
        $cards    = $this->fetchAssignedCards($uid);
        $projects = $this->fetchUserProjects($uid);

        $projectIds = array_map(function ($p) {
            return (int)$p['id'];
        }, $projects);

        $upcoming = $this->fetchUpcomingEvents($uid);

        $focusNow = $this->computeFocusNow($cards);
        $focusNow['remainingToday'] = $upcoming['remainingToday'];

        $projectLocations = $this->fetchProjectLocations($projects);

        return [
            'employee'     => $this->getEmployeeProfile($uid, $orgId),
            'organization' => $this->getOrganization($orgId),
            'focusNow'     => $focusNow,
            'workload'     => $this->computeWorkload($cards, $projects),
            'schedule'     => $this->computeSchedule($cards, $projectIds),
            'tasks'        => $this->buildTaskList($cards, $projects),
            'projects'     => $this->formatProjects($projects),
            'timeline'     => $this->fetchTimeline($projectIds),
            'resources'       => $this->computeResources($projects, $projectIds),
            'activityEvents'  => $this->fetchActivityEvents($projectIds),
            'notes'           => $this->fetchNotes($projectIds),
            'upcomingEvents'  => $upcoming['events'],
            'projectLocations' => $projectLocations,
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
                       p.client_name, p.created_at,
                       p.loc_street, p.loc_city, p.loc_zip
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

    // ── Upcoming calendar events ─────────────────────────────────────

    private function fetchUpcomingEvents(string $uid): array {
        $tzName = $this->config->getUserValue($uid, 'core', 'timezone', '') ?: 'UTC';
        try {
            $tz = new \DateTimeZone($tzName);
        } catch (\Exception $e) {
            $tz = new \DateTimeZone('UTC');
        }

        $now       = new \DateTime('now', $tz);
        $windowEnd = (clone $now)->modify('+7 days');
        $todayEnd  = (clone $now)->setTime(23, 59, 59);

        $sql = "SELECT co.id, co.uid AS event_uid, co.calendardata,
                       co.firstoccurence, co.lastoccurence,
                       cal.id AS calendar_id, cal.calendarcolor
                FROM *PREFIX*calendars cal
                JOIN *PREFIX*calendarobjects co ON co.calendarid = cal.id
                WHERE cal.principaluri = ?
                  AND cal.deleted_at IS NULL
                  AND co.componenttype = 'VEVENT'
                  AND co.deleted_at IS NULL
                  AND co.lastoccurence >= ?
                  AND co.firstoccurence <= ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'principals/users/' . $uid,
            $now->getTimestamp(),
            $windowEnd->getTimestamp(),
        ]);

        $events         = [];
        $remainingToday = 0;

        while ($row = $stmt->fetch()) {
            $occurrence = $this->extractNextOccurrence(
                $row['calendardata'],
                $now,
                $windowEnd,
                $tz
            );
            if ($occurrence === null) {
                continue;
            }

            $startsAt = $occurrence['start'];

            if ($startsAt <= $todayEnd) {
                $remainingToday++;
            }

            $events[] = [
                'uid'        => (string)$row['event_uid'],
                'title'      => $occurrence['title'],
                'startsAt'   => $startsAt->format(\DateTime::ATOM),
                'allDay'     => $occurrence['allDay'],
                'color'      => $row['calendarcolor'] ?: null,
                'calendarId' => (int)$row['calendar_id'],
            ];
        }

        usort($events, function ($a, $b) {
            return strcmp($a['startsAt'], $b['startsAt']);
        });

        return [
            'events'         => array_slice($events, 0, 5),
            'remainingToday' => $remainingToday,
        ];
    }

    private function extractNextOccurrence(
        $calendarData,
        \DateTime $now,
        \DateTime $windowEnd,
        \DateTimeZone $tz
    ): ?array {
        if (!is_string($calendarData) && is_resource($calendarData)) {
            $calendarData = stream_get_contents($calendarData);
        }
        if (!is_string($calendarData) || $calendarData === '') {
            return null;
        }

        try {
            $vCalendar = \Sabre\VObject\Reader::read($calendarData);
            $expanded  = $vCalendar->expand($now, $windowEnd);
        } catch (\Exception $e) {
            return null;
        }

        $bestStart = null;
        $bestEvent = null;

        foreach ($expanded->VEVENT ?? [] as $vEvent) {
            $dtStart = $vEvent->DTSTART;
            if ($dtStart === null) {
                continue;
            }
            $start = $dtStart->getDateTime($tz);
            if ($start <= $now || $start > $windowEnd) {
                continue;
            }
            if ($bestStart === null || $start < $bestStart) {
                $bestStart = $start;
                $bestEvent = $vEvent;
            }
        }

        if ($bestEvent === null) {
            return null;
        }

        $summary = isset($bestEvent->SUMMARY) ? trim((string)$bestEvent->SUMMARY) : '';
        if ($summary === '') {
            $summary = '(untitled event)';
        }

        $allDay  = false;
        $dtStart = $bestEvent->DTSTART;
        if ($dtStart !== null) {
            $valueParam = $dtStart['VALUE'];
            if ($valueParam !== null && strtoupper((string)$valueParam) === 'DATE') {
                $allDay = true;
            }
        }

        return [
            'start'  => $bestStart,
            'title'  => $summary,
            'allDay' => $allDay,
        ];
    }

    // ── Project geocoding ────────────────────────────────────────────

    private function fetchProjectLocations(array $projects): array {
        $out = [];
        foreach ($projects as $project) {
            $projectId = (int)$project['id'];
            $street = trim((string)($project['loc_street'] ?? ''));
            $city   = trim((string)($project['loc_city']   ?? ''));
            $zip    = trim((string)($project['loc_zip']    ?? ''));

            if ($street === '' && $city === '' && $zip === '') {
                continue;
            }

            $hit = $this->geocodeAddress($street, $city, $zip);
            if ($hit === null) {
                continue;
            }

            $out[$projectId] = [
                'lat'         => $hit['lat'],
                'lng'         => $hit['lng'],
                'displayName' => $hit['displayName'],
            ];
        }
        return $out;
    }

    private function geocodeAddress(string $street, string $city, string $zip): ?array {
        $normalized = strtolower($street) . '|' . strtolower($city) . '|' . strtolower($zip);
        $addrHash   = hash('sha256', $normalized);

        $cached = $this->lookupGeocodeCache($addrHash);
        if ($cached !== null) {
            if ($cached['lat'] === null || $cached['lng'] === null) {
                return null;
            }
            return [
                'lat'         => (float)$cached['lat'],
                'lng'         => (float)$cached['lng'],
                'displayName' => $cached['display_name'],
            ];
        }

        $parts = array_filter([$street, $zip, $city], static function ($p) { return $p !== ''; });
        $query = implode(', ', $parts);

        $userAgent = sprintf(
            'Nextcloud-EmployeeDashboard/%s (%s)',
            $this->appManager->getAppVersion('employee_dashboard'),
            $this->resolveInstanceHost()
        );

        try {
            $client = $this->clientService->newClient();
            $response = $client->get(self::NOMINATIM_URL, [
                'query'   => [
                    'format' => 'jsonv2',
                    'limit'  => 1,
                    'q'      => $query,
                ],
                'headers' => [
                    'User-Agent' => $userAgent,
                    'Accept'     => 'application/json',
                ],
                'timeout' => self::NOMINATIM_TIMEOUT_SECONDS,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Nominatim request failed', [
                'app'       => 'employee_dashboard',
                'exception' => $e,
            ]);
            usleep(1_000_000);
            return null;
        }

        if ($response->getStatusCode() !== 200) {
            $this->logger->warning('Nominatim non-200', [
                'app'    => 'employee_dashboard',
                'status' => $response->getStatusCode(),
            ]);
            usleep(1_000_000);
            return null;
        }

        $body    = (string)$response->getBody();
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            usleep(1_000_000);
            return null;
        }
        if (empty($decoded)) {
            $this->insertGeocodeCache($addrHash, null, null, null, 'nominatim');
            usleep(1_000_000);
            return null;
        }

        $first = $decoded[0];
        if (!isset($first['lat'], $first['lon'])) {
            $this->insertGeocodeCache($addrHash, null, null, null, 'nominatim');
            usleep(1_000_000);
            return null;
        }

        $lat         = (float)$first['lat'];
        $lng         = (float)$first['lon'];
        $displayName = isset($first['display_name']) ? (string)$first['display_name'] : null;

        $this->insertGeocodeCache($addrHash, $lat, $lng, $displayName, 'nominatim');
        usleep(1_000_000);

        return [
            'lat'         => $lat,
            'lng'         => $lng,
            'displayName' => $displayName,
        ];
    }

    private function lookupGeocodeCache(string $addrHash): ?array {
        $stmt = $this->db->prepare(
            "SELECT lat, lng, display_name, source
             FROM *PREFIX*adminpage_geocode_cache
             WHERE addr_hash = ? LIMIT 1"
        );
        $stmt->bindValue(1, $addrHash, \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function insertGeocodeCache(
        string $addrHash,
        ?float $lat,
        ?float $lng,
        ?string $displayName,
        string $source
    ): void {
        $stmt = $this->db->prepare(
            "INSERT INTO *PREFIX*adminpage_geocode_cache
             (addr_hash, lat, lng, display_name, source, created_at)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bindValue(1, $addrHash, \PDO::PARAM_STR);
        if ($lat === null) {
            $stmt->bindValue(2, null, \PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(2, number_format($lat, 7, '.', ''), \PDO::PARAM_STR);
        }
        if ($lng === null) {
            $stmt->bindValue(3, null, \PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(3, number_format($lng, 7, '.', ''), \PDO::PARAM_STR);
        }
        if ($displayName === null) {
            $stmt->bindValue(4, null, \PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(4, mb_substr($displayName, 0, 255), \PDO::PARAM_STR);
        }
        $stmt->bindValue(5, $source, \PDO::PARAM_STR);
        $stmt->bindValue(6, time(), \PDO::PARAM_INT);
        $stmt->execute();
    }

    private function resolveInstanceHost(): string {
        $cli = (string)$this->config->getSystemValue('overwrite.cli.url', '');
        if ($cli !== '') {
            $host = parse_url($cli, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                return $host;
            }
        }
        $trusted = $this->config->getSystemValue('trusted_domains', []);
        if (is_array($trusted) && isset($trusted[0]) && is_string($trusted[0]) && $trusted[0] !== '') {
            return $trusted[0];
        }
        return 'unknown-host';
    }
}
