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
        @click="activeTab = tab; currentPage = 1"
      >
        {{ tab }}
        <span v-if="tabCounts[tab] !== undefined" class="tasks-board__tab-count">{{ tabCounts[tab] }}</span>
      </button>
    </div>

    <!-- Search -->
    <div class="tasks-board__filters">
      <input v-model="search" type="text" class="tasks-board__search" placeholder="Search tasks…" @input="currentPage = 1" />
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
    <template v-else>
      <div class="tasks-board__list">
        <div
          v-for="task in paginatedTasks"
          :key="task.id"
          class="tasks-board__item"
          :class="{ 'tasks-board__item--done': task.done, 'tasks-board__item--selected': selectedTask && selectedTask.id === task.id }"
          @click="selectTask(task)"
        >
          <span class="tasks-board__item-check" :class="{ 'tasks-board__item-check--done': task.done }">
            <svg v-if="task.done" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="20 6 9 17 4 12" />
            </svg>
          </span>

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

          <span class="tasks-board__item-due" :class="dueClass(task)">{{ formatDue(task.duedate) }}</span>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="tasks-board__pagination">
        <button class="tasks-board__page-btn" :disabled="currentPage <= 1" @click="currentPage--">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <span class="tasks-board__page-info">{{ currentPage }} / {{ totalPages }}</span>
        <button class="tasks-board__page-btn" :disabled="currentPage >= totalPages" @click="currentPage++">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
    </template>

    <!-- Task detail drawer -->
    <div v-if="selectedTask" class="drawer-overlay" @click.self="selectedTask = null">
      <div class="drawer">
        <div class="drawer__header">
          <h3 class="drawer__title">{{ selectedTask.title }}</h3>
          <button class="drawer__close" @click="selectedTask = null">&times;</button>
        </div>

        <div class="drawer__body">
          <!-- Status & Project -->
          <div class="drawer__meta-row">
            <span class="drawer__stack">{{ selectedTask.stackTitle }}</span>
            <span v-if="selectedTask.projectName" class="drawer__project">{{ selectedTask.projectName }}</span>
            <span v-if="selectedTask.done" class="drawer__done-badge">Completed</span>
          </div>

          <!-- Labels -->
          <div v-if="selectedTask.labels && selectedTask.labels.length" class="drawer__section">
            <span class="drawer__section-label">Labels</span>
            <div class="drawer__labels">
              <span v-for="lbl in selectedTask.labels" :key="lbl.id" class="tasks-board__label" :style="labelStyle(lbl.color)">{{ lbl.title }}</span>
            </div>
          </div>

          <!-- Due Date -->
          <div class="drawer__section">
            <span class="drawer__section-label">Due Date</span>
            <span :class="dueClass(selectedTask)" class="drawer__due-value">
              {{ selectedTask.duedate ? formatFullDate(selectedTask.duedate) + ' (' + formatDue(selectedTask.duedate) + ')' : 'No due date' }}
            </span>
          </div>

          <!-- Description -->
          <div class="drawer__section">
            <span class="drawer__section-label">Description</span>
            <div v-if="selectedTask.description" class="drawer__description">{{ selectedTask.description }}</div>
            <p v-else class="drawer__empty-text">No description.</p>
          </div>

          <!-- Comments -->
          <div class="drawer__section">
            <span class="drawer__section-label">Comments ({{ selectedTask.commentCount }})</span>
            <div v-if="selectedTask.comments && selectedTask.comments.length" class="drawer__comments">
              <div v-for="c in selectedTask.comments" :key="c.id" class="drawer__comment">
                <div class="drawer__comment-header">
                  <span class="drawer__comment-avatar">{{ c.author.charAt(0).toUpperCase() }}</span>
                  <span class="drawer__comment-author">{{ c.author }}</span>
                  <span class="drawer__comment-date">{{ formatFullDate(c.createdAt) }}</span>
                </div>
                <div class="drawer__comment-body">{{ c.message }}</div>
              </div>
            </div>
            <p v-else class="drawer__empty-text">No comments yet.</p>
          </div>

          <!-- Attachments -->
          <div class="drawer__section">
            <span class="drawer__section-label">Attachments ({{ selectedTask.attachmentCount }})</span>
            <div v-if="selectedTask.attachments && selectedTask.attachments.length" class="drawer__attachments">
              <div v-for="a in selectedTask.attachments" :key="a.id" class="drawer__attachment">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                <span class="drawer__attachment-name">{{ a.name }}</span>
                <span class="drawer__attachment-by">by {{ a.createdBy }}</span>
              </div>
            </div>
            <p v-else class="drawer__empty-text">No attachments.</p>
          </div>
        </div>
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
    focusFilter: { type: Object, default: null },
  },
  data: function () {
    return {
      activeTab: "All Open",
      search: "",
      tabs: ["All Open", "Overdue", "Today", "Upcoming", "Done"],
      selectedTask: null,
      currentPage: 1,
      pageSize: 5,
    };
  },
  watch: {
    focusFilter: function (val) {
      if (val && val.tab) {
        this.activeTab = val.tab;
        this.currentPage = 1;
        this.search = "";
      }
    },
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

      var list = this.tasks.filter(function (t) {
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
    totalPages: function () {
      return Math.max(1, Math.ceil(this.filteredTasks.length / this.pageSize));
    },
    paginatedTasks: function () {
      var start = (this.currentPage - 1) * this.pageSize;
      return this.filteredTasks.slice(start, start + this.pageSize);
    },
  },
  methods: {
    selectTask: function (task) {
      this.selectedTask = task;
    },
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
    formatFullDate: function (dateStr) {
      if (!dateStr) return "";
      var d = new Date(dateStr);
      return d.toLocaleDateString("en-US", { weekday: "short", month: "short", day: "numeric", year: "numeric" }) +
        " " + d.toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit" });
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
  margin: 0; padding: 0; border: none;
}
.tasks-board__count {
  font-size: 11px; font-weight: 600;
  background: #e8f0fe; color: #1e4a8a;
  padding: 2px 8px; border-radius: 8px;
}

/* Tabs */
.tasks-board__tabs { display: flex; gap: 4px; margin-bottom: 12px; flex-wrap: wrap; }
.tasks-board__tab {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 5px 14px; border-radius: 8px;
  border: 1px solid transparent; background: #f0f1f5;
  font-size: 12px; font-weight: 600; color: #6b7280;
  cursor: pointer; transition: all 0.15s;
}
.tasks-board__tab:hover { background: #e5e7eb; }
.tasks-board__tab--active { background: #4a90d9; color: #fff; border-color: #4a90d9; }
.tasks-board__tab-count {
  font-size: 10px; font-weight: 700; min-width: 16px; text-align: center;
  padding: 0 4px; border-radius: 6px; background: rgba(255,255,255,0.2);
}
.tasks-board__tab--active .tasks-board__tab-count { background: rgba(255,255,255,0.25); }

/* Search */
.tasks-board__filters { margin-bottom: 12px; }
.tasks-board__search {
  width: 100%; max-width: 320px; padding: 7px 12px;
  border: 1px solid #e5e7eb; border-radius: 8px;
  font-size: 12px; color: var(--color-text-primary, #1a1a2e);
  background: #fff; outline: none; transition: border-color 0.15s;
}
.tasks-board__search:focus { border-color: #4a90d9; }

/* Empty */
.tasks-board__empty { text-align: center; padding: 32px 16px; color: var(--color-text-muted, #9ca3af); }
.tasks-board__empty p { margin: 0 0 4px 0; font-size: 14px; }
.tasks-board__empty-hint { font-size: 12px; font-style: italic; }

/* Task list */
.tasks-board__list { display: flex; flex-direction: column; gap: 4px; }

/* Task row */
.tasks-board__item {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 12px; border: 1px solid #f3f4f6; border-radius: 8px;
  transition: background 0.15s, border-color 0.15s;
  cursor: pointer;
}
.tasks-board__item:hover { background: #fafbfd; border-color: #e5e7eb; }
.tasks-board__item--done { opacity: 0.6; }
.tasks-board__item--selected { border-color: #4a90d9; background: #f0f7ff; }

/* Check */
.tasks-board__item-check {
  width: 20px; height: 20px; border-radius: 50%; border: 2px solid #d1d5db;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.tasks-board__item-check--done { background: #2e7d32; border-color: #2e7d32; color: #fff; }

/* Body */
.tasks-board__item-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.tasks-board__item-row1 { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.tasks-board__item-title { font-size: 13px; font-weight: 600; color: var(--color-text-primary, #1a1a2e); }
.tasks-board__item-title--done { text-decoration: line-through; color: var(--color-text-muted, #9ca3af); }
.tasks-board__label {
  font-size: 10px; font-weight: 600; padding: 1px 7px;
  border-radius: 6px; border: 1px solid; white-space: nowrap;
}
.tasks-board__item-row2 { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.tasks-board__item-stack {
  font-size: 10px; font-weight: 600; color: #6366f1;
  background: #eef2ff; padding: 1px 8px; border-radius: 6px; white-space: nowrap;
}
.tasks-board__item-project { font-size: 11px; color: var(--color-text-secondary, #6b7280); }
.tasks-board__item-meta {
  display: inline-flex; align-items: center; gap: 3px;
  font-size: 11px; color: var(--color-text-muted, #9ca3af);
}

/* Due date */
.tasks-board__item-due {
  font-size: 11px; font-weight: 600; color: var(--color-text-muted, #9ca3af);
  white-space: nowrap; flex-shrink: 0;
}
.tasks-board__item-due--overdue { color: #dc2626; background: #fef2f2; padding: 2px 8px; border-radius: 6px; }
.tasks-board__item-due--today { color: #d97706; background: #fffbeb; padding: 2px 8px; border-radius: 6px; }

/* Pagination */
.tasks-board__pagination {
  display: flex; align-items: center; justify-content: center;
  gap: 12px; margin-top: 16px; padding-top: 12px; border-top: 1px solid #f3f4f6;
}
.tasks-board__page-btn {
  width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e5e7eb;
  background: #fff; display: flex; align-items: center; justify-content: center;
  cursor: pointer; color: #4a90d9; transition: all 0.15s;
}
.tasks-board__page-btn:hover:not(:disabled) { background: #e8f0fe; }
.tasks-board__page-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.tasks-board__page-info { font-size: 12px; font-weight: 600; color: var(--color-text-secondary, #6b7280); }

/* ── Drawer overlay ────────────────────────────────────── */
.drawer-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.35);
  z-index: 10000; display: flex; justify-content: flex-end;
  animation: fadeIn 0.15s ease;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }

.drawer {
  width: 480px; max-width: 92vw; height: 100vh;
  background: #fff; box-shadow: -4px 0 24px rgba(0,0,0,0.12);
  display: flex; flex-direction: column;
  animation: slideIn 0.2s ease;
}
.drawer__header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 20px 24px; border-bottom: 1px solid #e5e7eb; flex-shrink: 0;
}
.drawer__title {
  font-size: 17px; font-weight: 700; color: #1a1a2e;
  margin: 0; padding: 0; border: none; flex: 1; min-width: 0;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.drawer__close {
  width: 32px; height: 32px; border-radius: 8px; border: none;
  background: #f3f4f6; font-size: 20px; line-height: 1;
  color: #6b7280; cursor: pointer; display: flex;
  align-items: center; justify-content: center; flex-shrink: 0;
  transition: background 0.15s;
}
.drawer__close:hover { background: #e5e7eb; }

.drawer__body {
  flex: 1; overflow-y: auto; padding: 20px 24px;
  display: flex; flex-direction: column; gap: 20px;
}

.drawer__meta-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.drawer__stack {
  font-size: 11px; font-weight: 600; color: #6366f1;
  background: #eef2ff; padding: 3px 10px; border-radius: 6px;
}
.drawer__project { font-size: 12px; color: var(--color-text-secondary, #6b7280); }
.drawer__done-badge {
  font-size: 11px; font-weight: 600; color: #2e7d32;
  background: #e8f5e9; padding: 3px 10px; border-radius: 6px;
}

.drawer__section { display: flex; flex-direction: column; gap: 8px; }
.drawer__section-label {
  font-size: 11px; font-weight: 700; color: #6b7280;
  text-transform: uppercase; letter-spacing: 0.04em;
}
.drawer__labels { display: flex; gap: 6px; flex-wrap: wrap; }
.drawer__due-value { font-size: 13px; font-weight: 600; color: #1a1a2e; }

.drawer__description {
  font-size: 13px; line-height: 1.65; color: #374151;
  background: #f9fafb; border-radius: 8px; padding: 12px 14px;
  white-space: pre-wrap; word-break: break-word;
}
.drawer__empty-text { font-size: 12px; color: #9ca3af; margin: 0; font-style: italic; }

/* Comments */
.drawer__comments { display: flex; flex-direction: column; gap: 12px; }
.drawer__comment {
  background: #f9fafb; border-radius: 8px; padding: 12px 14px;
  display: flex; flex-direction: column; gap: 6px;
}
.drawer__comment-header { display: flex; align-items: center; gap: 8px; }
.drawer__comment-avatar {
  width: 24px; height: 24px; border-radius: 6px;
  background: linear-gradient(135deg, #4a90d9, #6cb0f0);
  color: #fff; font-size: 11px; font-weight: 700;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.drawer__comment-author { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.drawer__comment-date { font-size: 11px; color: #9ca3af; }
.drawer__comment-body { font-size: 13px; color: #374151; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }

/* Attachments */
.drawer__attachments { display: flex; flex-direction: column; gap: 6px; }
.drawer__attachment {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px; background: #f9fafb; border-radius: 8px;
  color: #6b7280;
}
.drawer__attachment-name { font-size: 13px; font-weight: 600; color: #1a1a2e; flex: 1; }
.drawer__attachment-by { font-size: 11px; color: #9ca3af; }

@media (max-width: 700px) {
  .tasks-board__item { flex-direction: column; align-items: flex-start; gap: 6px; }
  .tasks-board__item-due { align-self: flex-end; }
  .drawer { width: 100vw; max-width: 100vw; }
}
</style>
