<template>
  <div class="proj-filter-wrap">
    <section
      class="proj-filter"
      :class="{ 'proj-filter--fixed': isSticky }"
    >
      <div v-show="!isSticky" class="proj-filter__header" @click="collapsed = !collapsed">
        <h3 class="proj-filter__title">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path d="M3 3h18v2.5L14 12.46V19l-4 2V12.46L3 5.5V3z" fill="#8b5cf6" stroke="none"/></svg>
          My Projects
          <span class="proj-filter__badge">{{ projects.length }}</span>
        </h3>
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" class="proj-filter__chevron" :class="{ 'proj-filter__chevron--rotated': collapsed }"><path d="M18 15l-6-6-6 6" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
      <div v-show="!collapsed || isSticky" class="proj-filter__body" :class="{ 'proj-filter__body--compact': isSticky }">
        <div class="proj-filter__tabs">
          <div v-show="!isSticky || toolbarExpanded" class="proj-filter__tabs-toolbar">
            <input
              v-model="tabSearch"
              type="text"
              class="proj-filter__tabs-search-input"
              placeholder="Search projects…"
            />
            <select
              v-model="tabStatusFilter"
              class="proj-filter__tabs-status-select"
            >
              <option value="">All Statuses</option>
              <option value="Active">Active</option>
              <option value="Completed">Completed</option>
              <option value="Archived">Archived</option>
            </select>
            <select
              v-model="tabTaskDueFilter"
              class="proj-filter__tabs-status-select"
            >
              <option value="">All Task Due</option>
              <option value="overdue">Has Overdue</option>
              <option value="today">Has Due Today</option>
              <option value="nextSevenDays">Has Upcoming</option>
              <option value="nodue">Has No Due Date</option>
            </select>
            <select
              v-model="tabTaskStatusFilter"
              class="proj-filter__tabs-status-select"
            >
              <option value="">All Task Status</option>
              <option value="open">Has Open Tasks</option>
              <option value="done">Has Done Tasks</option>
            </select>
            <button
              v-if="tabSearch || tabStatusFilter || tabTaskDueFilter || tabTaskStatusFilter"
              class="proj-filter__tabs-clear"
              @click="clearProjectFilters"
            >
              ✕ Clear
            </button>
          </div>
          <div class="proj-filter__tabs-strip">
            <button
              v-if="isSticky && !toolbarExpanded"
              class="proj-filter__sticky-toggle"
              title="Show filters"
              @click="toolbarExpanded = true"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"><path d="M3 3h18v2.5L14 12.46V19l-4 2V12.46L3 5.5V3z" fill="#6b7280" stroke="none"/></svg>
              <span v-if="hasActiveFilters" class="proj-filter__filter-dot"></span>
            </button>
            <button
              v-if="isSticky && toolbarExpanded"
              class="proj-filter__sticky-toggle"
              title="Hide filters"
              @click="toolbarExpanded = false"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"><path d="M18 15l-6-6-6 6" fill="none" stroke="#6b7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button
              v-for="p in visibleProjects"
              :key="p.id"
              class="proj-filter__tab"
              :class="{ 'proj-filter__tab--active': activeProjectId === p.id }"
              @click="selectProject(p)"
            >
              <span
                class="proj-filter__tab-dot"
                :class="'proj-filter__tab-dot--' + p.statusLabel.toLowerCase()"
              ></span>
              <span class="proj-filter__tab-name">{{ p.name }}</span>
              <span v-if="p.number" class="proj-filter__tab-num">{{ p.number }}</span>
            </button>
            <span v-if="visibleProjects.length === 0" class="proj-filter__tabs-empty">
              No projects match filters
            </span>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script>
export default {
  name: "ProjectFilterWidget",
  props: {
    projects: { type: Array, default: function () { return []; } },
    tasks: { type: Array, default: function () { return []; } },
    activeProjectId: { type: Number, default: null },
  },
  data: function () {
    return {
      collapsed: false,
      isSticky: false,
      toolbarExpanded: false,
      tabSearch: "",
      tabStatusFilter: "",
      tabTaskDueFilter: "",
      tabTaskStatusFilter: "",
    };
  },
  computed: {
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
/* ── Wrapper (always in flow) ── */
.proj-filter-wrap {
  margin-bottom: var(--spacing-lg, 24px);
  position: sticky;
  top: 0;
  z-index: 100;
}

/* ── Card ── */
.proj-filter {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
}

/* ── Fixed state ── */
.proj-filter--fixed {
  border-radius: 0 0 var(--radius-card, 12px) var(--radius-card, 12px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
}

.proj-filter__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-md, 16px) var(--spacing-lg, 24px);
  cursor: pointer;
  user-select: none;
  transition: background 0.15s;
}
.proj-filter__header:hover { background: #fafbfd; }
.proj-filter__title {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  margin: 0; padding: 0; border: none;
  display: flex;
  align-items: center;
  gap: 8px;
}
.proj-filter__badge {
  font-size: 11px;
  font-weight: 600;
  background: #f3e8ff;
  color: #7c3aed;
  padding: 2px 8px;
  border-radius: 8px;
}
.proj-filter__chevron {
  color: var(--color-text-muted, #9ca3af);
  transition: transform 0.3s;
}
.proj-filter__chevron--rotated { transform: rotate(180deg); }
.proj-filter__body {
  padding: 0 var(--spacing-lg, 24px) var(--spacing-lg, 24px);
}
.proj-filter__body--compact {
  padding: 8px var(--spacing-lg, 24px) !important;
}

/* ── Tabs container ── */
.proj-filter__tabs {
  margin-bottom: 0;
}

/* ── Toolbar ── */
.proj-filter__tabs-toolbar {
  display: flex;
  gap: 8px;
  margin-bottom: 8px;
  align-items: center;
  flex-wrap: wrap;
}

.proj-filter__tabs-search-input {
  padding: 6px 12px;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 8px;
  font-size: 13px;
  color: var(--color-text-primary, #1a1a2e);
  background: #fff;
  outline: none;
  width: 200px;
  transition: border-color 0.15s;
}
.proj-filter__tabs-search-input:focus {
  border-color: #4a90d9;
}

.proj-filter__tabs-status-select {
  padding: 6px 10px;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 8px;
  font-size: 13px;
  color: var(--color-text-primary, #1a1a2e);
  background: #fff;
  outline: none;
  transition: border-color 0.15s;
  cursor: pointer;
}
.proj-filter__tabs-status-select:focus {
  border-color: #4a90d9;
}

.proj-filter__tabs-clear {
  padding: 5px 12px;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 8px;
  background: #fff;
  font-size: 12px;
  font-weight: 500;
  color: var(--color-text-muted, #9ca3af);
  cursor: pointer;
  transition: all 0.15s;
}
.proj-filter__tabs-clear:hover {
  background: #fef2f2;
  border-color: #ef4444;
  color: #ef4444;
}

/* ── Tab strip ── */
.proj-filter__tabs-strip {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 4px;
  scrollbar-width: thin;
}
.proj-filter__tabs-strip::-webkit-scrollbar {
  height: 4px;
}
.proj-filter__tabs-strip::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 2px;
}

/* ── Tab pill ── */
.proj-filter__tab {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 20px;
  background: #fff;
  font-size: 12px;
  font-weight: 500;
  color: var(--color-text-primary, #1a1a2e);
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
  transition: all 0.15s;
}
.proj-filter__tab:hover {
  background: #f8fafc;
  border-color: #c878c8;
}
.proj-filter__tab--active {
  background: linear-gradient(135deg, #c878c8, #d494d4);
  color: #fff;
  border-color: #c878c8;
}

/* ── Status dot ── */
.proj-filter__tab-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}
.proj-filter__tab--active .proj-filter__tab-dot {
  box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.4);
}
.proj-filter__tab-dot--active {
  background: #22c55e;
}
.proj-filter__tab-dot--completed {
  background: #6366f1;
}
.proj-filter__tab-dot--archived {
  background: #6b7280;
}

/* ── Tab text ── */
.proj-filter__tab-name {
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
}
.proj-filter__tab-num {
  font-size: 10px;
  opacity: 0.7;
}

.proj-filter__tabs-empty {
  font-size: 13px;
  color: var(--color-text-muted, #9ca3af);
  padding: 8px 0;
}

/* ── Toggle button ── */
.proj-filter__sticky-toggle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  background: none;
  border: 1px solid var(--color-border, #e5e7eb);
  color: var(--color-text-secondary, #6b7280);
  cursor: pointer;
  flex-shrink: 0;
  position: relative;
  transition: background 0.15s;
}
.proj-filter__sticky-toggle:hover {
  background: #f3f4f6;
}

/* ── Active filter dot ── */
.proj-filter__filter-dot {
  position: absolute;
  top: 2px;
  right: 2px;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #c878c8;
}

@media (max-width: 700px) {
  .proj-filter__tabs-search-input {
    width: 100%;
  }
}
</style>
