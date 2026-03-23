<template>
  <section class="tasks-board">
    <div class="tasks-board__header">
      <div class="tasks-board__icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 11l3 3L22 4" />
          <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
        </svg>
      </div>
      <h3 class="tasks-board__title">My Tasks</h3>
      <span class="tasks-board__count">{{ tasks.length }}</span>
    </div>

    <!-- Tabs -->
    <div class="tasks-board__tabs">
      <button
        v-for="tab in tabs"
        :key="tab"
        class="tasks-board__tab"
        :class="{ 'tasks-board__tab--active': activeTab === tab }"
        @click="activeTab = tab"
      >
        {{ tab }}
        <span v-if="tabCounts[tab] !== undefined" class="tasks-board__tab-count">{{ tabCounts[tab] }}</span>
      </button>
    </div>

    <!-- Search -->
    <div class="tasks-board__filters">
      <input v-model="search" type="text" class="tasks-board__search" placeholder="Search tasks…" />
    </div>

    <!-- Empty state -->
    <div v-if="tasks.length === 0" class="tasks-board__empty">
      <p>No tasks assigned yet.</p>
      <p class="tasks-board__empty-hint">Tasks will appear here once you are assigned to Deck cards.</p>
    </div>

    <!-- No results for filter -->
    <div v-else-if="filteredTasks.length === 0" class="tasks-board__empty">
      <p>No tasks match the current filter.</p>
    </div>

    <!-- Task list -->
    <div v-else class="tasks-board__list">
      <div
        v-for="task in filteredTasks"
        :key="task.id"
        class="tasks-board__item"
        :class="{ 'tasks-board__item--done': task.done }"
      >
        <!-- Done indicator -->
        <span class="tasks-board__item-check" :class="{ 'tasks-board__item-check--done': task.done }">
          <svg v-if="task.done" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12" />
          </svg>
        </span>

        <!-- Main content -->
        <div class="tasks-board__item-body">
          <div class="tasks-board__item-row1">
            <span class="tasks-board__item-title" :class="{ 'tasks-board__item-title--done': task.done }">{{ task.title }}</span>
            <span v-for="lbl in task.labels" :key="lbl.id" class="tasks-board__label" :style="labelStyle(lbl.color)">{{ lbl.title }}</span>
          </div>
          <div class="tasks-board__item-row2">
            <span class="tasks-board__item-stack">{{ task.stackTitle }}</span>
            <span v-if="task.projectName" class="tasks-board__item-project">{{ task.projectName }}</span>
            <span v-if="task.commentCount > 0" class="tasks-board__item-meta">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              {{ task.commentCount }}
            </span>
            <span v-if="task.attachmentCount > 0" class="tasks-board__item-meta">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
              {{ task.attachmentCount }}
            </span>
            <span v-if="task.description" class="tasks-board__item-meta" title="Has description">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg>
            </span>
          </div>
        </div>

        <!-- Due date -->
        <span class="tasks-board__item-due" :class="dueClass(task)">{{ formatDue(task.duedate) }}</span>
      </div>
    </div>
  </section>
</template>

<script>
export default {
  name: "TasksBoardWidget",
  props: {
    tasks: { type: Array, default: function () { return []; } },
    projects: { type: Array, default: function () { return []; } },
  },
  data: function () {
    return {
      activeTab: "All Open",
      search: "",
      tabs: ["All Open", "Overdue", "Today", "Upcoming", "Done"],
    };
  },
  computed: {
    tabCounts: function () {
      var now = new Date();
      var todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
      var todayEnd = new Date(todayStart.getTime() + 86400000);
      var counts = { "All Open": 0, "Overdue": 0, "Today": 0, "Upcoming": 0, "Done": 0 };

      for (var i = 0; i < this.tasks.length; i++) {
        var t = this.tasks[i];
        if (t.done) {
          counts["Done"]++;
          continue;
        }
        counts["All Open"]++;
        if (t.duedate) {
          var d = new Date(t.duedate);
          if (d < todayStart) counts["Overdue"]++;
          else if (d < todayEnd) counts["Today"]++;
          else counts["Upcoming"]++;
        }
      }
      return counts;
    },
    filteredTasks: function () {
      var now = new Date();
      var todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
      var todayEnd = new Date(todayStart.getTime() + 86400000);
      var tab = this.activeTab;
      var q = this.search.trim().toLowerCase();

      var list = this.tasks;

      list = list.filter(function (t) {
        switch (tab) {
          case "Overdue":
            return !t.done && t.duedate && new Date(t.duedate) < todayStart;
          case "Today":
            return !t.done && t.duedate && new Date(t.duedate) >= todayStart && new Date(t.duedate) < todayEnd;
          case "Upcoming":
            return !t.done && t.duedate && new Date(t.duedate) >= todayEnd;
          case "All Open":
            return !t.done;
          case "Done":
            return !!t.done;
          default:
            return true;
        }
      });

      if (q) {
        list = list.filter(function (t) {
          return (
            t.title.toLowerCase().indexOf(q) !== -1 ||
            (t.projectName && t.projectName.toLowerCase().indexOf(q) !== -1) ||
            (t.description && t.description.toLowerCase().indexOf(q) !== -1) ||
            (t.stackTitle && t.stackTitle.toLowerCase().indexOf(q) !== -1)
          );
        });
      }

      return list;
    },
  },
  methods: {
    formatDue: function (duedate) {
      if (!duedate) return "No due date";
      var d = new Date(duedate);
      var now = new Date();
      var todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
      var diff = Math.ceil((d.getTime() - todayStart.getTime()) / 86400000);

      if (diff < 0) return Math.abs(diff) + "d overdue";
      if (diff === 0) return "Due today";
      if (diff === 1) return "Tomorrow";
      if (diff <= 7) return "In " + diff + " days";
      return d.toLocaleDateString("en-US", { month: "short", day: "numeric" });
    },
    dueClass: function (task) {
      if (task.done || !task.duedate) return "";
      var d = new Date(task.duedate);
      var now = new Date();
      var todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
      var todayEnd = new Date(todayStart.getTime() + 86400000);

      if (d < todayStart) return "tasks-board__item-due--overdue";
      if (d < todayEnd) return "tasks-board__item-due--today";
      return "";
    },
    labelStyle: function (hexColor) {
      var c = hexColor || "999999";
      return {
        backgroundColor: "#" + c + "18",
        color: "#" + c,
        borderColor: "#" + c + "40",
      };
    },
  },
};
</script>

<style scoped>
.tasks-board {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  padding: var(--spacing-lg, 24px);
  margin-bottom: var(--spacing-xl, 32px);
}
.tasks-board__header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}
.tasks-board__icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: rgba(200, 120, 200, 0.1);
  color: #c878c8;
  display: flex;
  align-items: center;
  justify-content: center;
}
.tasks-board__title {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  margin: 0;
  padding: 0;
  border: none;
}
.tasks-board__count {
  font-size: 11px;
  font-weight: 600;
  background: #e8f0fe;
  color: #1e4a8a;
  padding: 2px 8px;
  border-radius: 8px;
}

/* Tabs */
.tasks-board__tabs {
  display: flex;
  gap: 4px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}
.tasks-board__tab {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 14px;
  border-radius: 8px;
  border: 1px solid transparent;
  background: #f0f1f5;
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.15s;
}
.tasks-board__tab:hover {
  background: #e5e7eb;
}
.tasks-board__tab--active {
  background: #4a90d9;
  color: #fff;
  border-color: #4a90d9;
}
.tasks-board__tab-count {
  font-size: 10px;
  font-weight: 700;
  min-width: 16px;
  text-align: center;
  padding: 0 4px;
  border-radius: 6px;
  background: rgba(255, 255, 255, 0.2);
}
.tasks-board__tab--active .tasks-board__tab-count {
  background: rgba(255, 255, 255, 0.25);
}

/* Search */
.tasks-board__filters {
  margin-bottom: 12px;
}
.tasks-board__search {
  width: 100%;
  max-width: 320px;
  padding: 7px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 12px;
  color: var(--color-text-primary, #1a1a2e);
  background: #fff;
  outline: none;
  transition: border-color 0.15s;
}
.tasks-board__search:focus {
  border-color: #4a90d9;
}

/* Empty */
.tasks-board__empty {
  text-align: center;
  padding: 32px 16px;
  color: var(--color-text-muted, #9ca3af);
}
.tasks-board__empty p {
  margin: 0 0 4px 0;
  font-size: 14px;
}
.tasks-board__empty-hint {
  font-size: 12px;
  font-style: italic;
}

/* Task list */
.tasks-board__list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

/* Task row */
.tasks-board__item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid #f3f4f6;
  border-radius: 8px;
  transition: background 0.15s, border-color 0.15s;
}
.tasks-board__item:hover {
  background: #fafbfd;
  border-color: #e5e7eb;
}
.tasks-board__item--done {
  opacity: 0.6;
}

/* Check circle */
.tasks-board__item-check {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: 2px solid #d1d5db;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.tasks-board__item-check--done {
  background: #2e7d32;
  border-color: #2e7d32;
  color: #fff;
}

/* Body */
.tasks-board__item-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.tasks-board__item-row1 {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.tasks-board__item-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-primary, #1a1a2e);
}
.tasks-board__item-title--done {
  text-decoration: line-through;
  color: var(--color-text-muted, #9ca3af);
}

/* Labels */
.tasks-board__label {
  font-size: 10px;
  font-weight: 600;
  padding: 1px 7px;
  border-radius: 6px;
  border: 1px solid;
  white-space: nowrap;
}

/* Row 2: stack, project, meta icons */
.tasks-board__item-row2 {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.tasks-board__item-stack {
  font-size: 10px;
  font-weight: 600;
  color: #6366f1;
  background: #eef2ff;
  padding: 1px 8px;
  border-radius: 6px;
  white-space: nowrap;
}
.tasks-board__item-project {
  font-size: 11px;
  color: var(--color-text-secondary, #6b7280);
}
.tasks-board__item-meta {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 11px;
  color: var(--color-text-muted, #9ca3af);
}

/* Due date */
.tasks-board__item-due {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-muted, #9ca3af);
  white-space: nowrap;
  flex-shrink: 0;
}
.tasks-board__item-due--overdue {
  color: #dc2626;
  background: #fef2f2;
  padding: 2px 8px;
  border-radius: 6px;
}
.tasks-board__item-due--today {
  color: #d97706;
  background: #fffbeb;
  padding: 2px 8px;
  border-radius: 6px;
}

@media (max-width: 700px) {
  .tasks-board__item {
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
  }
  .tasks-board__item-due {
    align-self: flex-end;
  }
}
</style>
