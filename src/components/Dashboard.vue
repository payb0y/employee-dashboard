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
      <WelcomeStrip :employee="data.employee" :organization="data.organization" :focus-now="data.focusNow" :workload="data.workload" />

      <!-- 2. Primary Focus Area -->
      <section class="emp-dashboard__focus-row">
        <FocusNowWidget :focus="data.focusNow" @filter="onFocusFilter" />
        <div class="emp-dashboard__focus-side">
          <WorkloadWidget :workload="data.workload" />
          <ScheduleWidget :schedule="data.schedule" />
        </div>
      </section>

      <!-- A. My Tasks Board -->
      <TasksBoardWidget ref="tasksBoard" :tasks="data.tasks" :projects="data.projects" :focus-filter="focusFilter" />

      <!-- B. My Week Panel -->
      <MyWeekWidget :tasks="data.tasks" :schedule="data.schedule" :timeline="data.timeline" />

      <!-- B2. Gantt Chart -->
      <GanttWidget :timeline="data.timeline" :projects="data.projects" />

      <!-- C. My Projects Workspace -->
      <ProjectsWorkspaceWidget :projects="data.projects" @select-project="onSelectProject" />

      <!-- D. Project Context Drawer -->
      <ProjectDrawerWidget :project="selectedProject" :timeline="data.timeline" :activity-events="data.activityEvents || []" :notes="data.notes || []" />

      <!-- E. Resources / Notes -->
      <ResourcesWidget :resources="data.resources" />
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
import ResourcesWidget from "./ResourcesWidget.vue";

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
    ResourcesWidget,
  },
  props: {
    data: { type: Object, required: true },
  },
  data: function () {
    return {
      focusFilter: null,
      selectedProject: null,
    };
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
