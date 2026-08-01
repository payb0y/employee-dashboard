<template>
  <div class="dash-header-wrap">
    <section class="dash-header" :class="{ 'dash-header--fixed': isSticky }">
      <!-- Tier 1 — who you are, and what is urgent. Never collapses: these are
           the two things that were lost on scroll when the identity strip was a
           separate panel above this one. -->
      <div class="dash-header__identity">
        <div class="dash-header__who">
          <span class="iz-identity__avatar iz-identity__avatar--sm">{{ initial }}</span>
          <div class="dash-header__who-text">
            <h2 class="dash-header__name">{{ employee.displayName || employee.uid }}</h2>
            <span class="dash-header__sub">
              <span v-if="employee.title">{{ employee.title }} · </span>
              <span v-if="organization">{{ organization.name }}</span>
            </span>
          </div>
        </div>
        <div class="dash-header__counts">
          <span
            v-if="focusNow.overdue > 0"
            class="iz-badge iz-badge--danger dash-header__count"
            @click="$emit('filter', 'Overdue')"
          >{{ focusNow.overdue }} overdue</span>
          <span
            v-if="focusNow.dueToday > 0"
            class="iz-badge iz-badge--warning dash-header__count"
            @click="$emit('filter', 'Today')"
          >{{ focusNow.dueToday }} due today</span>
          <span
            class="iz-badge iz-badge--accent dash-header__count"
            @click="$emit('filter', 'All Open')"
          >{{ workload.open }} open</span>
        </div>
      </div>

      <!-- The filter row is the only thing that folds. grid-template-rows
           1fr -> 0fr animates a height the content decides, which `height:auto`
           cannot do. -->
      <div class="dash-header__fold" :class="{ 'dash-header__fold--closed': foldClosed }">
        <div class="dash-header__fold-inner">
          <div class="dash-header__toolbar">
            <input
              v-model="tabSearch"
              type="text"
              class="iz-input dash-header__search"
              placeholder="Search projects…"
            />
            <select v-model="tabStatusFilter" class="iz-select dash-header__select">
              <option value="">All Statuses</option>
              <option value="Active">Active</option>
              <option value="Completed">Completed</option>
              <option value="Archived">Archived</option>
            </select>
            <select v-model="tabTaskDueFilter" class="iz-select dash-header__select">
              <option value="">All Task Due</option>
              <option value="overdue">Has Overdue</option>
              <option value="today">Has Due Today</option>
              <option value="nextSevenDays">Has Upcoming</option>
              <option value="nodue">Has No Due Date</option>
            </select>
            <select v-model="tabTaskStatusFilter" class="iz-select dash-header__select">
              <option value="">All Task Status</option>
              <option value="open">Has Open Tasks</option>
              <option value="done">Has Done Tasks</option>
            </select>
            <button
              v-if="hasActiveFilters"
              class="iz-btn iz-btn--sm dash-header__clear"
              @click="clearProjectFilters"
            >✕ Clear</button>
          </div>
        </div>
      </div>

      <!-- Tier 2 — the project switcher, which stays through every state. -->
      <div class="dash-header__projects">
        <span class="iz-badge iz-badge--cat-5 dash-header__project-count">{{ projects.length }}</span>
        <div class="dash-header__strip">
          <button
            v-for="p in visibleProjects"
            :key="p.id"
            class="dash-header__tab"
            :class="{ 'dash-header__tab--active': activeProjectId === p.id }"
            @click="selectProject(p)"
          >
            <span
              class="dash-header__tab-dot"
              :class="'dash-header__tab-dot--' + p.statusLabel.toLowerCase()"
            ></span>
            <span class="dash-header__tab-name">{{ p.name }}</span>
            <span v-if="p.number" class="dash-header__tab-num">{{ p.number }}</span>
          </button>
          <span v-if="visibleProjects.length === 0" class="dash-header__strip-empty">
            No projects match filters
          </span>
        </div>
        <!-- Only offered once the filters have folded away, and marked when a
             filter is active so a hidden filter cannot be forgotten. -->
        <button
          v-if="isSticky"
          class="iz-btn iz-btn--sm iz-btn--icon dash-header__filter-toggle"
          :class="{ 'dash-header__filter-toggle--on': hasActiveFilters }"
          :aria-expanded="toolbarExpanded ? 'true' : 'false'"
          :title="toolbarExpanded ? 'Hide filters' : 'Show filters'"
          @click="toolbarExpanded = !toolbarExpanded"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
          </svg>
        </button>
      </div>
    </section>
  </div>
</template>

<script>
export default {
  name: "DashboardHeader",
  props: {
    projects: { type: Array, default: function () { return []; } },
    tasks: { type: Array, default: function () { return []; } },
    activeProjectId: { type: Number, default: null },
    // Identity and counts, absorbed from what used to be a separate panel
    // above this one.
    employee: { type: Object, default: function () { return {}; } },
    organization: { type: Object, default: null },
    focusNow: { type: Object, default: function () { return { overdue: 0, dueToday: 0 }; } },
    workload: { type: Object, default: function () { return { open: 0 }; } },
  },
  data: function () {
    return {
      isSticky: false,
      toolbarExpanded: false,
      tabSearch: "",
      tabStatusFilter: "",
      tabTaskDueFilter: "",
      tabTaskStatusFilter: "",
    };
  },
  computed: {
    initial: function () {
      var name = this.employee.displayName || this.employee.uid || "?";
      return name.charAt(0).toUpperCase();
    },
    /**
     * The filter row folds once the header sticks, and only then. The gear
     * button reopens it in place via toolbarExpanded — which is why that flag
     * already existed; it just had nothing driving it.
     */
    foldClosed: function () {
      return this.isSticky && !this.toolbarExpanded;
    },
    enrichedProjects: function () {
      var statusMap = { 0: "Active", 1: "Completed", 2: "Archived" };
      var now = new Date();
      var todayStr = now.getFullYear() + "-" +
        (now.getMonth() + 1 < 10 ? "0" : "") + (now.getMonth() + 1) + "-" +
        (now.getDate() < 10 ? "0" : "") + now.getDate();
      var weekEnd = new Date(now);
      weekEnd.setDate(weekEnd.getDate() + 7);
      var weekStr = weekEnd.getFullYear() + "-" +
        (weekEnd.getMonth() + 1 < 10 ? "0" : "") + (weekEnd.getMonth() + 1) + "-" +
        (weekEnd.getDate() < 10 ? "0" : "") + weekEnd.getDate();
      var tasks = this.tasks;

      return this.projects.map(function (p) {
        var projectTasks = tasks.filter(function (t) { return t.projectId === p.id; });
        var enrichedTasks = projectTasks.map(function (t) {
          var status = t.done ? "done" : "open";
          var dueBucket = "nodue";
          if (t.duedate) {
            var due = t.duedate.substring(0, 10);
            if (due < todayStr) dueBucket = "overdue";
            else if (due === todayStr) dueBucket = "today";
            else if (due <= weekStr) dueBucket = "nextSevenDays";
            else dueBucket = "nextSevenDays";
          }
          return { dueBucket: dueBucket, status: status };
        });
        return Object.assign({}, p, {
          statusLabel: statusMap[p.status] || "Active",
          tasks: enrichedTasks,
        });
      });
    },
    hasActiveFilters: function () {
      return !!(this.tabSearch || this.tabStatusFilter || this.tabTaskDueFilter || this.tabTaskStatusFilter);
    },
    visibleProjects: function () {
      var self = this;
      var list = this.enrichedProjects;
      if (this.tabStatusFilter) {
        list = list.filter(function (p) {
          return p.statusLabel === self.tabStatusFilter;
        });
      }
      if (this.tabTaskDueFilter) {
        var dueVal = this.tabTaskDueFilter;
        list = list.filter(function (p) {
          var tasks = p.tasks || [];
          for (var i = 0; i < tasks.length; i++) {
            if (tasks[i].dueBucket === dueVal) return true;
          }
          return false;
        });
      }
      if (this.tabTaskStatusFilter) {
        var statusVal = this.tabTaskStatusFilter;
        list = list.filter(function (p) {
          var tasks = p.tasks || [];
          for (var i = 0; i < tasks.length; i++) {
            if (tasks[i].status === statusVal) return true;
          }
          return false;
        });
      }
      if (this.tabSearch) {
        var q = this.tabSearch.toLowerCase();
        list = list.filter(function (p) {
          return (
            p.name.toLowerCase().indexOf(q) !== -1 ||
            (p.number && p.number.toLowerCase().indexOf(q) !== -1)
          );
        });
      }
      return list;
    },
  },
  mounted: function () {
    var self = this;
    var scrollRoot = document.getElementById("app-content");
    if (!scrollRoot) return;

    this._onScroll = function () {
      var wrap = self.$el;
      if (!wrap) return;
      var rootRect = scrollRoot.getBoundingClientRect();
      var wrapRect = wrap.getBoundingClientRect();
      var shouldStick = wrapRect.top <= rootRect.top;
      self.isSticky = shouldStick;
      if (!shouldStick) self.toolbarExpanded = false;
    };

    this._onScroll();
    scrollRoot.addEventListener("scroll", this._onScroll, { passive: true });
  },
  beforeDestroy: function () {
    var scrollRoot = document.getElementById("app-content");
    if (scrollRoot && this._onScroll) scrollRoot.removeEventListener("scroll", this._onScroll);
  },
  methods: {
    selectProject: function (project) {
      var newId = this.activeProjectId === project.id ? null : project.id;
      this.$emit("filter-project", newId);
    },
    clearProjectFilters: function () {
      this.tabSearch = "";
      this.tabStatusFilter = "";
      this.tabTaskDueFilter = "";
      this.tabTaskStatusFilter = "";
    },
  },
};
</script>

<style scoped>
/* Wrapper stays in flow so the page does not jump when the card sticks. */
.dash-header-wrap {
  margin-bottom: var(--spacing-lg, 24px);
  position: sticky;
  top: 0;
  z-index: 100;
}

.dash-header {
  background: var(--bg-card);
  border-radius: var(--radius-card);
  box-shadow: var(--shadow-card);
  overflow: hidden;
}

/* Square off the top edge and lift the shadow once it is against the app bar,
   so it reads as attached to the chrome rather than floating over content. */
.dash-header--fixed {
  border-radius: 0 0 var(--radius-card) var(--radius-card);
  box-shadow: var(--shadow-card-hover);
}

/* ── Tier 1: identity + counts ── */
.dash-header__identity {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding: 12px var(--spacing-lg, 24px);
  border-bottom: 1px solid var(--color-border);
}

.dash-header__who {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.dash-header__who-text {
  min-width: 0;
}

/* NC core styles bare h2. */
.dash-header__name {
  margin: 0;
  padding: 0;
  border: none;
  font-size: 15px;
  font-weight: 700;
  line-height: 1.3;
  color: var(--color-text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.dash-header__sub {
  font-size: 12px;
  color: var(--color-text-secondary);
}

.dash-header__counts {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

/* These filter the task board, so they have to look pressable. */
.dash-header__count {
  cursor: pointer;
  text-transform: none;
}

/* ── The folding filter row ──
   grid-template-rows 1fr -> 0fr animates a height the content decides;
   height:auto cannot be transitioned. The inner element needs min-height:0 or
   it refuses to shrink below its content. */
.dash-header__fold {
  display: grid;
  grid-template-rows: 1fr;
  transition: grid-template-rows 0.22s ease;
}

.dash-header__fold--closed {
  grid-template-rows: 0fr;
}

.dash-header__fold-inner {
  overflow: hidden;
  min-height: 0;
}

.dash-header__toolbar {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
  padding: 10px var(--spacing-lg, 24px) 0;
}

/* Chrome from .iz-input; only this field's fixed width is local. */
.dash-header__search {
  width: 200px;
}

/* Chrome from .iz-select. width:auto because the primitive is width:100% —
   right for a stacked form, wrong for these sitting in a toolbar row. */
.dash-header__select {
  width: auto;
  cursor: pointer;
}

.dash-header__clear {
  color: var(--color-badge-danger-text);
}

/* ── Tier 2: the project switcher ── */
.dash-header__projects {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px var(--spacing-lg, 24px) 12px;
}

.dash-header__project-count {
  flex-shrink: 0;
}

.dash-header__strip {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  flex: 1;
  min-width: 0;
  padding-bottom: 2px;
  scrollbar-width: thin;
}

.dash-header__strip::-webkit-scrollbar {
  height: 4px;
}

.dash-header__strip::-webkit-scrollbar-thumb {
  background: var(--color-border-strong);
  border-radius: 2px;
}

.dash-header__tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
  padding: 5px 12px;
  border: 1.5px solid transparent;
  border-radius: var(--radius-pill);
  background: var(--bg-subtle);
  font-family: inherit;
  font-size: 12px;
  font-weight: 500;
  color: var(--color-text-secondary);
  cursor: pointer;
  white-space: nowrap;
  min-height: 0;
  transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.dash-header__tab:hover {
  background: var(--bg-inset);
}

.dash-header__tab--active {
  background: var(--accent-bg);
  color: var(--accent-on-bg);
  border-color: var(--accent);
}

.dash-header__tab-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
  background: var(--color-text-muted);
}

.dash-header__tab-dot--active { background: var(--color-success); }
.dash-header__tab-dot--completed { background: var(--chart-5); }
.dash-header__tab-dot--archived { background: var(--color-text-muted); }

.dash-header__tab-name {
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 180px;
}

.dash-header__tab-num {
  font-size: 10px;
  color: var(--color-text-muted);
  font-variant-numeric: tabular-nums;
}

.dash-header__strip-empty {
  font-size: 12px;
  color: var(--color-text-muted);
  padding: 5px 2px;
}

.dash-header__filter-toggle {
  flex-shrink: 0;
  min-height: 0;
}

/* A folded filter row can hide an active filter, which would make the project
   list look wrong for no visible reason. Mark the button when that is so. */
.dash-header__filter-toggle--on {
  color: var(--accent);
  border-color: var(--accent);
}

@media (prefers-reduced-motion: reduce) {
  .dash-header__fold,
  .dash-header__tab {
    transition: none;
  }
}

@media (max-width: 700px) {
  .dash-header__identity,
  .dash-header__toolbar,
  .dash-header__projects {
    padding-left: var(--spacing-md, 16px);
    padding-right: var(--spacing-md, 16px);
  }
  .dash-header__search {
    width: 100%;
  }
}
</style>
