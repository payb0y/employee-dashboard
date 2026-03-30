<template>
  <div class="emp-dashboard">
    <!-- No Organization State -->
    <div v-if="!data.organization" class="emp-dashboard__empty">
      <div class="emp-dashboard__empty-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
          <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </svg>
      </div>
      <h2 class="emp-dashboard__empty-title">No Organization Found</h2>
      <p class="emp-dashboard__empty-text">
        Your account is not associated with any organization.<br />
        Please contact your administrator.
      </p>
    </div>

    <template v-else>
      <!-- 1. Welcome Strip -->
      <WelcomeStrip :employee="data.employee" :organization="data.organization" :focus-now="derivedFocusNow" :workload="derivedWorkload" />

      <!-- Project Filter -->
      <div class="emp-dashboard__project-filter">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
        </svg>
        <label class="emp-dashboard__filter-label" for="project-filter-select">Project</label>
        <select
          id="project-filter-select"
          class="emp-dashboard__filter-select"
          :value="activeProjectId === null ? '' : String(activeProjectId)"
          @change="onProjectFilter($event.target.value === '' ? null : parseInt($event.target.value, 10))"
        >
          <option value="">All Projects</option>
          <option v-for="p in data.projects" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
        </select>
        <button
          v-if="activeProjectId !== null"
          class="emp-dashboard__filter-clear"
          title="Clear filter"
          @click="onProjectFilter(null)"
        >&times;</button>
      </div>

      <!-- 2. Primary Focus Area -->
      <section class="emp-dashboard__focus-row">
        <FocusNowWidget :focus="derivedFocusNow" @filter="onFocusFilter" />
        <div class="emp-dashboard__focus-side">
          <WorkloadWidget :workload="derivedWorkload" />
          <ScheduleWidget :schedule="derivedSchedule" />
        </div>
      </section>

      <!-- A. My Week Panel -->
      <MyWeekWidget :tasks="filteredTasks" />

      <!-- B. My Tasks Board -->
      <TasksBoardWidget ref="tasksBoard" :tasks="filteredTasks" :projects="filteredProjects" :focus-filter="focusFilter" />

      <!-- B2. Gantt Chart -->
      <GanttWidget v-if="activeProjectId === null" :timeline="filteredTimeline" :projects="filteredProjects" />

      <!-- C. My Projects Workspace -->
      <ProjectsWorkspaceWidget v-if="activeProjectId === null" :projects="filteredProjects" @select-project="onSelectProject" />

      <!-- D. Project Context Drawer -->
      <ProjectDrawerWidget :project="selectedProject" :timeline="data.timeline" :activity-events="data.activityEvents || []" :notes="data.notes || []" />
    </template>
  </div>
</template>

<script>
import WelcomeStrip from "./WelcomeStrip.vue";
import FocusNowWidget from "./FocusNowWidget.vue";
import WorkloadWidget from "./WorkloadWidget.vue";
import ScheduleWidget from "./ScheduleWidget.vue";
import TasksBoardWidget from "./TasksBoardWidget.vue";
import MyWeekWidget from "./MyWeekWidget.vue";
import GanttWidget from "./GanttWidget.vue";
import ProjectsWorkspaceWidget from "./ProjectsWorkspaceWidget.vue";
import ProjectDrawerWidget from "./ProjectDrawerWidget.vue";

export default {
  name: "Dashboard",
  components: {
    WelcomeStrip,
    FocusNowWidget,
    WorkloadWidget,
    ScheduleWidget,
    TasksBoardWidget,
    MyWeekWidget,
    GanttWidget,
    ProjectsWorkspaceWidget,
    ProjectDrawerWidget,
  },
  props: {
    data: { type: Object, required: true },
  },
  data: function () {
    return {
      focusFilter: null,
      selectedProject: null,
      activeProjectId: null,
    };
  },
  computed: {
    filteredTasks: function () {
      var pid = this.activeProjectId;
      if (pid === null) return this.data.tasks;
      return this.data.tasks.filter(function (t) { return t.projectId === pid; });
    },
    filteredProjects: function () {
      var pid = this.activeProjectId;
      if (pid === null) return this.data.projects;
      return this.data.projects.filter(function (p) { return p.id === pid; });
    },
    filteredTimeline: function () {
      var pid = this.activeProjectId;
      if (pid === null) return this.data.timeline;
      return this.data.timeline.filter(function (i) { return i.projectId === pid; });
    },
    derivedFocusNow: function () {
      var now = new Date();
      var todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
      var todayEnd = new Date(todayStart.getTime() + 86400000 - 1);
      var overdue = 0, dueToday = 0, nextTask = null, nextTaskDue = null, oldestTask = null, oldestId = Infinity;
      this.filteredTasks.forEach(function (t) {
        if (t.done) return;
        if (t.duedate) {
          var d = new Date(t.duedate);
          if (d < todayStart) overdue++;
          else if (d <= todayEnd) dueToday++;
          if (d > now && (nextTaskDue === null || d < nextTaskDue)) {
            nextTask = { id: t.id, title: t.title, duedate: t.duedate };
            nextTaskDue = d;
          }
        }
        if (t.id < oldestId) { oldestId = t.id; oldestTask = { id: t.id, title: t.title }; }
      });
      return { overdue: overdue, dueToday: dueToday, nextTask: nextTask, oldestTask: oldestTask };
    },
    derivedWorkload: function () {
      var open = 0, done = 0;
      this.filteredTasks.forEach(function (t) { if (t.done) done++; else open++; });
      var total = open + done;
      return {
        open: open, done: done,
        completionPct: total > 0 ? Math.round((done / total) * 100) : 0,
        activeProjects: this.filteredProjects.filter(function (p) { return p.status === 0; }).length,
      };
    },
    derivedSchedule: function () {
      var now = new Date();
      var todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
      var todayEnd = new Date(todayStart.getTime() + 86400000 - 1);
      var day = now.getDay();
      var weekEnd = new Date(todayStart.getTime() + ((day === 0 ? 0 : 7 - day) + 1) * 86400000 - 1);
      var dueToday = 0, dueThisWeek = 0, noDueDate = 0;
      this.filteredTasks.forEach(function (t) {
        if (t.done) return;
        if (!t.duedate) { noDueDate++; return; }
        var d = new Date(t.duedate);
        if (d >= todayStart && d <= todayEnd) dueToday++;
        if (d >= todayStart && d <= weekEnd) dueThisWeek++;
      });
      var todayStr = todayStart.toISOString().slice(0, 10);
      var nextMilestone = null;
      this.filteredTimeline
        .filter(function (i) { return i.itemType === 'milestone' && i.endDate >= todayStr; })
        .sort(function (a, b) { return a.endDate < b.endDate ? -1 : 1; })
        .slice(0, 1)
        .forEach(function (i) { nextMilestone = { label: i.label, date: i.endDate }; });
      return { dueToday: dueToday, dueThisWeek: dueThisWeek, noDueDate: noDueDate, nextMilestone: nextMilestone };
    },
  },
  methods: {
    onFocusFilter: function (tab) {
      var self = this;
      this.focusFilter = { tab: tab, ts: Date.now() };
      this.$nextTick(function () {
        var el = self.$refs.tasksBoard;
        if (el && el.$el) {
          el.$el.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      });
    },
    onSelectProject: function (project) {
      this.selectedProject = project;
    },
    onProjectFilter: function (projectId) {
      this.activeProjectId = projectId;
      if (projectId !== null) {
        var match = this.data.projects.find(function (p) { return p.id === projectId; });
        this.selectedProject = match || null;
      } else {
        this.selectedProject = null;
      }
    },
  },
};
</script>

<style>
/* ── Nextcloud style isolation ──────────────────────────────── */
#app-content:has(.emp-dashboard) {
  background-color: #f0f1f5 !important;
  padding: 0 !important;
}
#employee-dashboard-root {
  background-color: #f0f1f5 !important;
  min-height: 100vh;
  color: #1a1a2e !important;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen,
    Ubuntu, Cantarell, "Fira Sans", "Droid Sans", "Helvetica Neue", Arial,
    sans-serif !important;
}

/* Reset headings — Nextcloud adds borders, padding, and themed colors */
#employee-dashboard-root h1,
#employee-dashboard-root h2,
#employee-dashboard-root h3,
#employee-dashboard-root h4,
#employee-dashboard-root h5,
#employee-dashboard-root h6 {
  border: none !important;
  border-bottom: none !important;
  padding: 0 !important;
  margin: 0 !important;
  color: #1a1a2e !important;
  font-family: inherit !important;
  line-height: 1.4 !important;
  background: none !important;
}

/* Reset links */
#employee-dashboard-root a {
  color: inherit;
  text-decoration: none;
}

/* Reset buttons — Nextcloud styles all buttons with themed colors */
#employee-dashboard-root button {
  font-family: inherit;
  color: inherit;
  background: none;
  border: none;
  padding: 0;
  margin: 0;
  cursor: pointer;
  font-size: inherit;
  line-height: inherit;
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
}

/* Reset inputs/textareas */
#employee-dashboard-root input,
#employee-dashboard-root textarea {
  font-family: inherit;
  color: #1a1a2e;
  background: #fff;
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
}

/* Reset paragraphs */
#employee-dashboard-root p {
  margin: 0;
  padding: 0;
  color: inherit;
}

/* Reset lists */
#employee-dashboard-root ul,
#employee-dashboard-root ol {
  margin: 0;
  padding: 0;
  list-style: none;
}

/* Prevent Nextcloud dark mode / theming from overriding our colors */
#employee-dashboard-root * {
  --color-main-text: #1a1a2e;
  --color-text-maxcontrast: #6b7280;
}

/* Reset SVG colors — Nextcloud can theme these via fill/stroke */
#employee-dashboard-root svg {
  color: inherit;
}

/* Reset section/div backgrounds that Nextcloud might theme */
#employee-dashboard-root section {
  background: transparent;
}
</style>

<style scoped>
.emp-dashboard {
  --bg-page: #f0f1f5;
  --bg-card: #ffffff;
  --shadow-card: 0 1px 3px rgba(0, 0, 0, 0.08);
  --shadow-card-hover: 0 4px 12px rgba(0, 0, 0, 0.1);
  --radius-card: 12px;
  --color-text-primary: #1a1a2e;
  --color-text-secondary: #6b7280;
  --color-text-muted: #9ca3af;
  --color-blue: #4a90d9;
  --color-purple: #c878c8;
  --color-success: #2e7d32;
  --color-warning: #f59e0b;
  --color-danger: #d94040;
  --color-border: #e5e7eb;
  --spacing-xs: 4px;
  --spacing-sm: 8px;
  --spacing-md: 16px;
  --spacing-lg: 24px;
  --spacing-xl: 32px;
  --spacing-2xl: 40px;

  background-color: var(--bg-page);
  max-width: 1200px;
  margin: 0 auto;
  padding: var(--spacing-lg);
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen,
    Ubuntu, Cantarell, "Fira Sans", "Droid Sans", "Helvetica Neue", Arial,
    sans-serif;
  color: var(--color-text-primary);
}

.emp-dashboard__focus-row {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: var(--spacing-md);
  margin-bottom: var(--spacing-xl);
}

.emp-dashboard__focus-side {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
}

/* Empty state */
.emp-dashboard__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 50vh;
  text-align: center;
  padding: var(--spacing-2xl);
}
.emp-dashboard__empty-icon {
  width: 80px;
  height: 80px;
  border-radius: 20px;
  background: #e8f0fe;
  color: #4a90d9;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: var(--spacing-lg);
}
.emp-dashboard__empty-title {
  font-size: 22px;
  font-weight: 700;
  color: var(--color-text-primary);
  margin: 0 0 8px 0;
  padding: 0;
  border: none;
}
.emp-dashboard__empty-text {
  font-size: 14px;
  color: var(--color-text-secondary);
  line-height: 1.5;
  margin: 0;
}

.emp-dashboard__project-filter {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
  margin-bottom: var(--spacing-lg);
  padding: var(--spacing-sm) var(--spacing-md);
  background: var(--bg-card);
  border-radius: var(--radius-card);
  box-shadow: var(--shadow-card);
  border-left: 4px solid var(--color-purple);
  color: var(--color-text-secondary);
}
.emp-dashboard__filter-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary);
  white-space: nowrap;
}
.emp-dashboard__filter-select {
  flex: 1;
  max-width: 260px;
  padding: 6px 10px;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  color: var(--color-text-primary);
  background: #fff;
  cursor: pointer;
}
.emp-dashboard__filter-select:focus {
  outline: none;
  border-color: var(--color-blue);
}
.emp-dashboard__filter-clear {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #e5e7eb;
  color: var(--color-text-secondary);
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.15s;
}
.emp-dashboard__filter-clear:hover {
  background: var(--color-danger);
  color: #fff;
}

@media (max-width: 900px) {
  .emp-dashboard__focus-row {
    grid-template-columns: 1fr;
  }
  .emp-dashboard__focus-side {
    flex-direction: row;
  }
}
@media (max-width: 600px) {
  .emp-dashboard__focus-side {
    flex-direction: column;
  }
}
</style>
