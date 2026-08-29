<template>
  <div class="dash-header-root">
    <!-- Sticky trigger. See the comment on the observer in mounted(): this has
         to sit ABOVE the sticky wrapper, because anything inside it moves when
         the header folds. -->
    <div ref="sentinel" class="dash-header__sentinel" aria-hidden="true"></div>
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

        <!-- View switcher. Lives in the identity tier, which never collapses,
             so it stays reachable however far the page is scrolled. -->
        <div class="iz-tabs dash-header__views">
          <button
            class="iz-tab"
            :class="{ 'iz-tab--active': activeView === 'overview' }"
            @click="$emit('switch-view', 'overview')"
          >Overview</button>
          <button
            class="iz-tab"
            :class="{ 'iz-tab--active': activeView === 'cards' }"
            @click="$emit('switch-view', 'cards')"
          >Cards</button>
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

            <!-- Completion bands. Same quartiles as adminpage's
                 ProjectPerformancePanel, but the rate is MY completion in the
                 project, and each band carries how many projects it holds — the
                 strip scrolls, so its far end is often out of sight. -->
            <div class="dash-header__bands" role="group" aria-label="Completion rate">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
              </svg>
              <button
                v-for="b in completionBands"
                :key="b.key"
                type="button"
                class="iz-chip"
                :class="[b.tone, { 'iz-chip--active': tabCompletionFilter === b.key }]"
                :disabled="b.key !== '' && b.count === 0"
                :aria-pressed="tabCompletionFilter === b.key ? 'true' : 'false'"
                :title="b.title"
                @click="tabCompletionFilter = b.key"
              >{{ b.label }} <span class="dash-header__band-n">{{ b.count }}</span></button>
            </div>

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
            <span
              class="dash-header__tab-pct"
              :class="'dash-header__tab-pct--' + bandOf(p)"
              :title="p.progressPct === null
                ? 'No tasks assigned to you here'
                : p.doneCount + ' of ' + p.taskCount + ' of your tasks done'"
            >{{ p.progressPct === null ? "—" : p.progressPct + "%" }}</span>
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
    activeView: { type: String, default: "overview" },
  },
  data: function () {
    return {
      isSticky: false,
      toolbarExpanded: false,
      tabSearch: "",
      tabStatusFilter: "",
      tabTaskDueFilter: "",
      tabTaskStatusFilter: "",
      tabCompletionFilter: "",
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
        var doneCount = 0;
        projectTasks.forEach(function (t) { if (t.done) doneCount++; });
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
          taskCount: projectTasks.length,
          doneCount: doneCount,
          // null, not 0. No tasks assigned to me here means there is no
          // completion rate — not a rate of zero, which would drop the project
          // into the 0–25% band and read as "nothing done".
          progressPct: projectTasks.length
            ? Math.round((doneCount / projectTasks.length) * 100)
            : null,
        });
      });
    },
    hasActiveFilters: function () {
      return !!(this.tabSearch || this.tabStatusFilter || this.tabTaskDueFilter ||
        this.tabTaskStatusFilter || this.tabCompletionFilter);
    },
    /**
     * Every filter EXCEPT the completion band. The band chips count off this
     * list, so their numbers describe what is currently in view and do not
     * shift as you move between bands.
     */
    projectsPreBand: function () {
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
    completionBands: function () {
      var self = this;
      var defs = [
        { key: "", label: "All", tone: "", title: "All projects" },
        { key: "0-25", label: "0\u201325%", tone: "iz-chip--danger", title: "0\u201325% of your tasks done" },
        { key: "25-50", label: "25\u201350%", tone: "iz-chip--warning", title: "25\u201350% of your tasks done" },
        { key: "50-75", label: "50\u201375%", tone: "iz-chip--accent", title: "50\u201375% of your tasks done" },
        { key: "75-100", label: "75\u2013100%", tone: "iz-chip--success", title: "75\u2013100% of your tasks done" },
      ];
      var pre = this.projectsPreBand;
      return defs.map(function (d) {
        return Object.assign({}, d, {
          count: pre.filter(function (p) { return self.inBand(p, d.key); }).length,
        });
      });
    },
    visibleProjects: function () {
      var self = this;
      return this.projectsPreBand.filter(function (p) {
        return self.inBand(p, self.tabCompletionFilter);
      });
    },
  },
  mounted: function () {
    var self = this;
    var scrollRoot = document.getElementById("app-content");
    var sentinel = this.$refs.sentinel;
    if (!scrollRoot || !sentinel) return;

    /*
     * The stuck state is read from a sentinel above the header, not from the
     * header's own position.
     *
     * It used to compare the header's own top against the scroll root's, which
     * feeds back on itself: crossing the line folds the filter row, the header
     * loses ~48px, the content below shifts, the scroll position is adjusted to
     * compensate, and the header crosses the line again in the other direction.
     * Scrolling slowly up through the threshold made it flip repeatedly and
     * yanked scrollTop with it — asking for scrollTop 10 landed at 28.
     *
     * The sentinel is a zero-height element BEFORE the sticky wrapper, so
     * nothing the header does to its own height can move it. Stuck simply means
     * the sentinel has left the top of the scroll root.
     */
    this._observer = new IntersectionObserver(
      function (entries) {
        var shouldStick = !entries[0].isIntersecting;
        if (shouldStick === self.isSticky) return;
        self.isSticky = shouldStick;
        if (!shouldStick) self.toolbarExpanded = false;
      },
      { root: scrollRoot, threshold: 0 }
    );
    this._observer.observe(sentinel);
  },
  beforeDestroy: function () {
    if (this._observer) this._observer.disconnect();
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
      this.tabCompletionFilter = "";
    },
    /**
     * Boundaries match adminpage's passesCompletionFilter exactly — [0,25]
     * (25,50] (50,75] (75,100] — so the same range gives the same answer in
     * both apps. A project with no tasks of mine matches no band.
     */
    inBand: function (p, band) {
      if (!band) return true;
      if (p.progressPct === null) return false;
      var v = p.progressPct;
      if (band === "0-25") return v >= 0 && v <= 25;
      if (band === "25-50") return v > 25 && v <= 50;
      if (band === "50-75") return v > 50 && v <= 75;
      if (band === "75-100") return v > 75 && v <= 100;
      return true;
    },
    bandOf: function (p) {
      if (p.progressPct === null) return "none";
      if (p.progressPct <= 25) return "danger";
      if (p.progressPct <= 50) return "warning";
      if (p.progressPct <= 75) return "accent";
      return "success";
    },
  },
};
</script>

<style scoped>
/* Vue 2 needs a single root, but a real box here would break the sticky child:
   an element can only travel within its containing block, and a plain wrapper
   is exactly as tall as its only visible child — zero room to move, so the
   header scrolled away instead of pinning. display:contents makes this element
   generate no box at all, so the sentinel and the sticky wrapper below are laid
   out as children of the dashboard, which is what they were before the sentinel
   was introduced. */
.dash-header-root {
  display: contents;
}

/* Zero-height trigger. Not display:none — an unrendered element never
   intersects, so the observer would report "stuck" from the start. */
.dash-header__sentinel {
  height: 0;
  margin: 0;
  padding: 0;
}

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

/* Bar, tab, active underline and hover all come from .iz-tabs / .iz-tab —
   only the placement belongs here. */
.dash-header__views {
  flex-basis: 100%;
  margin-top: 8px;
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

/* Chrome for the chips themselves is .iz-chip — including the tone tints,
   which by design only appear once a chip is active, so an untouched row
   stays quiet. Only the group's own box is local. */
.dash-header__bands {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 6px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-el);
  background: var(--bg-card);
  transition: border-color 0.15s ease;
}

.dash-header__bands:hover {
  border-color: var(--accent);
}

.dash-header__bands > svg {
  color: var(--accent);
  flex-shrink: 0;
  margin: 0 3px 0 2px;
}

.dash-header__bands .iz-chip[disabled] {
  opacity: 0.38;
  cursor: default;
}

.dash-header__band-n {
  font-size: 10px;
  font-weight: 600;
  opacity: 0.72;
  font-variant-numeric: tabular-nums;
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

/* Without this the band filter is a black box: chips vanish and you take the
   range on faith. With it the strip doubles as a completion overview. */
.dash-header__tab-pct {
  font-family: var(--iz-font-mono);
  font-size: 10.5px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  padding-left: 6px;
  border-left: 1px solid var(--color-border-dark);
}

.dash-header__tab-pct--danger { color: var(--color-badge-danger-text); }
.dash-header__tab-pct--warning { color: var(--color-badge-warning-text); }
.dash-header__tab-pct--accent { color: var(--accent-on-bg); }
.dash-header__tab-pct--success { color: var(--color-badge-success-text); }
.dash-header__tab-pct--none {
  color: var(--color-text-muted);
  font-weight: 500;
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
