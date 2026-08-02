# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This App Is

A Nextcloud app (`employee_dashboard`) that gives individual employees a personal work dashboard focused on "my tasks / my projects" — not org-wide admin analytics. It lives alongside the existing `adminpage` app and shares the same database schema but targets a different audience.

**App ID:** `employee_dashboard`
**Location:** `/home/payboy/src/nextcloud-docker-dev/data/apps-extra/employee_dashboard`
**Namespace:** `OCA\EmployeeDashboard`

---

## Architecture Overview

### Backend (PHP)

Single API endpoint. No separate page routes for sub-views.

```
lib/Controller/PageController.php      — serves the SPA shell (templates/index.php)
lib/Controller/DashboardController.php — GET /api/data → returns all dashboard data
lib/Service/EmployeeService.php        — all DB queries live here
appinfo/routes.php                     — page#index (GET /) + dashboard#getData (GET /api/data)
appinfo/info.xml                       — app manifest
```

`EmployeeService::getDashboardData($uid)` is the single entry point. It calls private helpers and returns one large JSON object. **All data lives in one API response** — there are no per-widget API calls.

### Frontend (Vue 2)

Single-page app mounted at `#employee-dashboard-root`.

```
src/main.js                            — fetches /api/data once on mount, renders <Dashboard>
src/components/Dashboard.vue           — orchestrator; owns focusFilter, selectedProject, activeProjectId
src/components/DashboardHeader.vue     — the sticky header: identity + counts, a
                                         folding filter row, and the project switcher
src/components/FocusNowWidget.vue      — overdue/dueToday counts, next task, oldest task, filter buttons
src/components/WorkloadWidget.vue      — open/done/completion%/active projects
src/components/ScheduleWidget.vue      — due today/week/no-date/next milestone
src/components/TasksBoardWidget.vue    — main task list (chip tabs + search + inline detail panel)
src/components/MyWeekWidget.vue        — tasks organized Mon–Sun + overdue section
src/components/GanttWidget.vue         — Gantt bars + milestones grouped by project
src/components/ProjectsMapWidget.vue   — Leaflet map of project locations
src/components/ProjectDrawerWidget.vue — detail drawer (timeline/notes/activity tabs)
```

Gone: `WelcomeStrip.vue` and `ProjectFilterWidget.vue` were merged into
`DashboardHeader.vue`; `ProjectsWorkspaceWidget.vue` and `ResourcesWidget.vue`
no longer exist.

```
```

**Data flow:** `main.js` fetches once → passes `data` prop to `Dashboard.vue` → Dashboard distributes to children as props → children emit events up. No Vuex. No per-widget fetches.

**State in Dashboard.vue:**

- `focusFilter` — drives tab selection in TasksBoardWidget (set by FocusNowWidget buttons)
- `selectedProject` — drives ProjectDrawerWidget (set by project-switcher clicks in DashboardHeader)
- `activeProjectId` — global project filter (null = all projects)

**Computed in Dashboard.vue (derived from activeProjectId):**

- `filteredTasks`, `filteredProjects`, `filteredTimeline`
- `derivedFocusNow`, `derivedWorkload`, `derivedSchedule` — re-derived from filtered tasks client-side

---

## API Response Shape

`GET /apps/employee_dashboard/api/data`

```json
{
  "employee":      { "uid", "displayName", "email", "title", "role", "orgId" },
  "organization":  { "id", "name" },
  "focusNow":      { "overdue", "dueToday", "nextTask", "oldestTask" },
  "workload":      { "open", "done", "completionPct", "activeProjects" },
  "schedule":      { "dueToday", "dueThisWeek", "noDueDate", "nextMilestone" },
  "tasks":         [{ "id", "title", "description", "duedate", "done", "projectId", "projectName", "stackTitle", "boardTitle", "labels", "comments", "attachments" }],
  "projects":      [{ "id", "name", "number", "description", "status", "boardId", "folderId", "folderPath", "whiteBoardId", "clientName", "createdAt" }],
  "timeline":      [{ "id", "projectId", "label", "itemType", "startDate", "endDate", "color" }],
  "activityEvents":[{ "id", "projectId", "eventType", "actorName", "occurredAt" }],
  "notes":         [{ "id", "projectId", "title", "content", "userId", "createdAt" }],
  "resources":     { "files", "notes", "whiteboards" }
}
```

Key field notes:

- `tasks[].projectId` — integer, cast with `(int)` in PHP; use strict `===` in JS filters
- `tasks[].done` — `null` means open; non-null means done (maps to `!!done` in JS)
- `projects[].status` — `0` = Active, `1` = Completed, `2` = Archived
- `timeline[].itemType` — `"phase"` or `"milestone"`

---

## Database Schema (Key Tables)

### Employee scoping always starts from:

```sql
oc_deck_assigned_users.participant = :uid  AND  type = 0
```

### Core join chain (tasks → projects):

```sql
oc_deck_assigned_users au
JOIN oc_deck_cards c      ON c.id = au.card_id
JOIN oc_deck_stacks s     ON s.id = c.stack_id
JOIN oc_deck_boards b     ON b.id = s.board_id
JOIN oc_custom_projects cp ON CAST(cp.board_id AS UNSIGNED) = b.id
```

### Standard active-task filters:

```sql
c.deleted_at = 0
c.archived = 0
s.deleted_at = 0
b.deleted_at = 0
```

### Key gotchas:

- `oc_custom_projects.board_id` is **varchar**, not integer — always `CAST(cp.board_id AS UNSIGNED)` when joining
- The done stack is hard-coded as `'Approved/Done'` (stack title, not a flag)
- "Done" tasks: check `c.done IS NOT NULL` — the column is nullable, not boolean
- Employee assignment = `oc_deck_assigned_users.participant`, not `oc_deck_cards.owner`
- Profile fields (email, title, org) come from `oc_accounts.data` as JSON, not simple columns

### Tables used:

| Table                       | Purpose                                                                        |
| --------------------------- | ------------------------------------------------------------------------------ |
| `oc_users`                  | Base user record, uid                                                          |
| `oc_accounts`               | Profile JSON (email, title, role)                                              |
| `oc_organizations`          | Org name, admin uid                                                            |
| `oc_organization_members`   | Employee→org link (user_uid, organization_id, role)                            |
| `oc_custom_projects`        | Project master (id, name, number, status, board_id, folder_id, white_board_id) |
| `oc_deck_boards`            | Deck boards linked to projects                                                 |
| `oc_deck_stacks`            | Board columns / workflow stages                                                |
| `oc_deck_cards`             | Tasks (title, duedate, done, description)                                      |
| `oc_deck_assigned_users`    | Employee→task assignment                                                       |
| `oc_deck_assigned_labels`   | Task→label mapping                                                             |
| `oc_deck_labels`            | Label metadata (title, color)                                                  |
| `oc_project_timeline_items` | Phases and milestones (start_date, end_date, color, system_key)                |
| `oc_filecache`              | File counts for project folders                                                |
| `oc_mimetypes`              | Mime type lookup for filecache                                                 |
| `oc_private_card_notes`     | Private notes per card per user                                                |

Tables to **never** query in this app: `oc_subscriptions`, `oc_plans`, `oc_adminpage_public_links`.

---

## Design System — the In Zicht theme

**The theme is the source of truth, not this app.** Styling lives in the In Zicht
Nextcloud theme at `/home/payboy/src/inzicht-nextcloud-theme`, in section 8 of
`themes/inzicht/core/css/server.css`. That file is shared with `adminpage` and
`superadminpage`, and it is loaded by Nextcloud *after* every app bundle, with a
`?v=` cache-buster this app's `js/` does not get.

The old local block of hex values on `.emp-dashboard` is gone. Do not re-add it.

### How tokens reach this app

`Dashboard.vue`'s root carries `iz-app`. That class is what supplies the generic
token names the widgets read — `--bg-card`, `--color-text-primary`,
`--radius-card`, `--accent`, `--chart-1`…`--chart-5`, the badge pairs, the
spacing scale. They are defined once in the theme's "App token bridge".

Two consequences worth knowing:

- **An undefined custom property does not fall back — it invalidates the whole
  declaration.** `background: var(--nope)` renders as nothing, silently. If a
  colour disappears, check the token exists before checking anything else.
- `iz-app` is also the ancestor that `.iz-input`, `.iz-select`, `.iz-close`,
  `.iz-tab` and the native form-control accent rules are scoped to. Without it
  they are inert.

### The rule: chrome from the primitive, layout stays local

Before writing a rule for surface, border, radius, shadow, type scale, hover or
focus — check whether a primitive already does it. Available: `.iz-panel`
(+ `--list`, `--flush`), `.iz-card`, `.iz-btn` (+ `--primary`, `--danger`,
`--ghost`, `--sm`, `--icon`), `.iz-input` / `.iz-select` / `.iz-textarea`,
`.iz-badge` / `.iz-pill` / `.iz-chip` (+ semantic and `--cat-N` variants),
`.iz-table-wrap` / `.iz-table`, `.iz-row` (+ `--card`, `--expandable`, with
`.iz-row__header` / `__actions` / `__chevron`), `.iz-identity__avatar`,
`.iz-tabs` / `.iz-tab` / `.iz-tab__count`, `.iz-pagination`, `.iz-empty`,
`.iz-meter`, `.iz-spinner`, `.iz-segment`, `.iz-kpi`, `.iz-figure`.

Keep local: grid tracks, widths, gaps between regions, and anything genuinely
specific to one component.

### Adding the class is only half the job — delete the local rule

Vue scoped CSS compiles to `.my-class[data-v-abc]`, which is specificity
(0,2,0) — the *same* as `.iz-app .iz-input` — and webpack injects app styles
after the theme's `<link>`. **On a tie the app wins**, so a local copy left in
place silently keeps overriding the primitive it was meant to defer to. This has
already happened twice; the class looked applied and nothing changed.

The inverse trap: rules in an **unscoped** `<style>` block get no `[data-v-…]`,
so a bare class there is (0,1,0) and *loses* to the theme. Qualify those on a
parent, e.g. `.my-widget__filters .my-widget__select`.

Audit rather than read — for every element carrying an `.iz-*` class, compare
the properties the theme rule sets against those any app rule sets on the same
element. Anything overlapping is a leftover.

### Nextcloud core fights you on bare elements

Core styles `button`, `input`, `select` and headings directly, at (0,1,1), and
some of it is `!important`. Known traps, all of which have bitten this app:

- **`min-height: 34px` on every bare button.** `min-height` beats `height`, and a
  parent cannot cap a taller child — the reset belongs on the button. `.iz-btn`
  variants already do this; a bare `<button>` does not.
- **Padding on bare buttons** (`7.5px 12px`). On a 28px icon button that leaves
  ~2px of content box and crushes the glyph. Use `.iz-btn--icon`.
- **`:focus` repaints the background** in the primary tint at (0,2,1). The theme
  counters this for `.iz-btn`; a bare button will still flash.
- **`outline: … !important` on `:focus-visible`.** A custom focus ring on a bare
  button is dead CSS.
- `.iz-input` / `.iz-select` are `width: 100%` — right for a stacked form field,
  wrong for a control in a toolbar row. Say `width: auto` there.

### Panels: pick the right variant

`.iz-panel` pads its content by `--iz-pad-panel` (20px). Use it when the panel
does the padding. Use **`.iz-panel--list`** when the component pads its own
header and body — which is the case for every collapsible widget here, because
the whole header is the click target and its hover tint has to run to the card
edge. Using the base class on those double-pads them and insets the tint.

### Colours

Never write a hex. Neutrals, text, borders, accent, semantic status and the
five-colour chart ramp all have tokens. Two blind spots to remember:

- **Colours embedded in `<template>` SVGs** — `stroke="#6b7280"`, marker fills —
  are outside every `<style>` block and have escaped several sweeps. Use
  `currentColor` for icons so they follow the button they sit in.
- Semantic colours mean status. Do not use `--color-success` because green looks
  nice; an org avatar filled with it reads as "healthy".

### Both schemes, every time

The theme has full light and dark. Check both before calling anything done. The
common failure is a token that *inverts* being used as a solid fill under white
text — `--accent-strong` and the `--color-badge-*-text` ramps all lighten on
dark. For a solid fill use `--accent`, `--color-danger`, `--color-success`, and
`--iz-accent-text` for the label on top.

Simulate dark without the theming app by setting `data-themes="dark"` and
`data-theme-dark=""` on both `<body>` and `<html>`.

### Vendored from the theme — copy, do not fork

A Nextcloud theme can only ship CSS and static assets, so anything else is
vendored and must be updated in every app in the same change:

- `ConfirmDialog.vue` — the one confirmation/notice dialog. **No `alert()` or
  `confirm()`**; they cannot be themed. The parent owns the busy flag and the
  error string, and the dialog never closes itself, so a failed action stays
  open with its reason.
- `src/lib/izChart.js` — the Chart.js bridge (canvas cannot read CSS variables).
  Not currently present here: this app draws no canvas charts. Vendor it from
  `themes/inzicht/core/js/iz-chart.js` the day it does.

### Responsive

Breakpoints in this app: `900px` (2-col → 1-col), `700px`, `600px`.

---

## Build & Dev

```bash
# One-time install
cd /home/payboy/src/nextcloud-docker-dev/data/apps-extra/employee_dashboard
npm install

# Production build (output → js/)
npm run build

# Development build
npm run dev

# Watch mode
npm run watch
```

**Always run `npm run build` after changing any file under `src/`.** The compiled bundle in `js/` is committed to the repo (not gitignored) — Nextcloud loads it directly.

Stack: Vue 2.7, `@nextcloud/axios`, `@nextcloud/router`, Webpack 5, `@nextcloud/webpack-vue-config`. Requires Node `>=20` and npm `>=10` (see `package.json` `engines`).

**No test or lint scripts are configured** — `package.json` defines only `build`, `dev`, and `watch`. There is no PHPUnit, ESLint, or stylelint setup. Don't waste a session searching for them; verify changes by rebuilding and exercising the dashboard.

## Companion Docs

Two longer reference docs live at the repo root and are not duplicated here:

- `DATABASE_REFERENCE.md` — full schema reference for every `oc_*` table this app touches, with column types and example values
- `HANDOFF.md` — original product/architecture handoff notes; useful when a request references historical design decisions

---

## Dev Environment

The live database is **PostgreSQL in `nc_pg`** — not the `nc_db` MariaDB
container an older version of this file pointed at, and not
`master-database-mysql-1`, whose `nextcloud` DB is an empty skeleton that is
easy to mistake for the real thing.

```bash
# psql shell
docker exec -it nc_pg psql -U nextcloud -d nextcloud

# one-shot query
docker exec nc_pg psql -U nextcloud -d nextcloud -c "SELECT ..."

# occ
docker exec -u www-data master-nextcloud-1 php occ <command>

# deploy the theme after changing it
cd /home/payboy/src/inzicht-nextcloud-theme && ./deploy-docker.sh master-nextcloud-1
```

Database: `nextcloud`. Table prefix: `oc_`.

**Start `nc_pg` before `master-nextcloud-1`.** If Nextcloud boots without its
database it runs `maintenance:install` and overwrites `config.php`, losing
`theme=inzicht`, the pgsql connection and the instance secrets. No data is lost,
but the instance needs rebuilding by hand.

**Verifying in the browser:** the app serves at
`http://nextcloud.local:8080/index.php/apps/employee_dashboard/`. If
`/etc/hosts` has lost its `nextcloud.local` line (WSL restarts drop it), use the
container's IP directly after adding it to `trusted_domains`. Reloading the page
does not re-fetch the bundle — `fetch(src, {cache:'reload'})` first, and do the
same for the theme's `server.css` after a deploy.

### Useful debug queries

**Check employee → org:**

```sql
SELECT * FROM oc_organization_members WHERE user_uid = 'EMPLOYEE_UID';
```

**Check task assignments:**

```sql
SELECT * FROM oc_deck_assigned_users WHERE participant = 'EMPLOYEE_UID' AND type = 0;
```

**Full task join:**

```sql
SELECT au.participant, c.id, c.title, s.title AS stack, b.id AS board_id, cp.name AS project
FROM oc_deck_assigned_users au
JOIN oc_deck_cards c ON c.id = au.card_id
JOIN oc_deck_stacks s ON s.id = c.stack_id
JOIN oc_deck_boards b ON b.id = s.board_id
JOIN oc_custom_projects cp ON CAST(cp.board_id AS UNSIGNED) = b.id
WHERE au.participant = 'EMPLOYEE_UID' AND au.type = 0;
```

---

## Adding New Features

### New widget

1. Create `src/components/MyWidget.vue` — accept data as props, no API calls inside
2. Root element: `class="iz-panel my-widget"`, or `iz-panel iz-panel--list` if the
   widget pads its own header and body (any collapsible one)
3. Reach for primitives before writing chrome — see "Design System" above. Write
   local rules only for layout, and never write a hex
4. Import and register in `Dashboard.vue`
5. Add to template, passing filtered data (`filteredTasks`, `filteredProjects`, etc.) not raw `data.*`
6. If the widget needs new data, add a method to `EmployeeService.php` and include it in `getDashboardData()`
7. `npm run build`, then check it in the browser in **both** colour schemes

### New backend data

1. Add a private method in `EmployeeService.php`
2. Call it from `getDashboardData()` and include in the returned array
3. Update the frontend prop type definitions as needed
4. Pass through `Dashboard.vue` as a prop to the relevant widget

### Adding to the global project filter

The filter in `Dashboard.vue` uses `activeProjectId`. If a new widget needs project scoping:

- Add a `filteredXxx` computed property following the existing pattern
- Pass it to the widget instead of the raw `data.xxx`

---

## What Not to Do

- Do not add per-widget API calls — all data comes from one fetch in `main.js`
- Do not use Vuex — data flows via props and events
- Do not copy layout or widgets from the `adminpage` app — the design language is shared but the layout is independent
- Do not query `oc_subscriptions`, `oc_plans`, or `oc_adminpage_public_links`
- Do not hardcode hex colours — including inside `<template>` SVGs, which every
  colour sweep so far has missed. Icons take `stroke="currentColor"`
- Do not re-add a local token block to `Dashboard.vue`. Tokens come from the
  theme's `iz-app` bridge; changing a value means changing it in the theme
- Do not add a primitive class and leave the local rule in place — scoped CSS
  ties with the theme and wins on order, so the primitive silently never applies
- Do not use `alert()` or `confirm()` — use the vendored `ConfirmDialog.vue`
- Do not edit the deployed theme inside the container; edit
  `/home/payboy/src/inzicht-nextcloud-theme` and run its `deploy-docker.sh`
- Do not bump `<version>` in `appinfo/info.xml` for frontend-only work. Bump it
  only when PHP changes (`lib/`, `appinfo/`), because the version is what makes
  Nextcloud run an upgrade cycle
- Do not change `<id>` in `appinfo/info.xml`. It must equal the directory name,
  and **production is deployed as `employee_dashboard`** — a mismatch fails
  `occ app:enable` with "appinfo file cannot be read"
- Do not use `oc_deck_cards.owner` for employee scoping — use `oc_deck_assigned_users.participant`
