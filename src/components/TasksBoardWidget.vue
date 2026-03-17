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
      <button v-for="tab in tabs" :key="tab" class="tasks-board__tab" :class="{ 'tasks-board__tab--active': activeTab === tab }" @click="activeTab = tab">
        {{ tab }}
      </button>
    </div>

    <!-- Filters -->
    <div class="tasks-board__filters">
      <input v-model="search" type="text" class="tasks-board__search" placeholder="Search tasks…" />
      <!-- TODO: project filter, label filter, due filter -->
    </div>

    <!-- Task list -->
    <div v-if="tasks.length === 0" class="tasks-board__empty">
      <p>No tasks assigned yet.</p>
      <p class="tasks-board__empty-hint">Tasks will appear here once you are assigned to Deck cards.</p>
    </div>
    <div v-else class="tasks-board__list">
      <!-- TODO: render filtered/sorted task rows -->
      <div v-for="(task, idx) in tasks" :key="idx" class="tasks-board__item">
        <span class="tasks-board__item-title">{{ task.title || 'Untitled task' }}</span>
        <span class="tasks-board__item-project">{{ task.projectName || '—' }}</span>
        <span class="tasks-board__item-due">{{ task.duedate || 'No due date' }}</span>
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
      tabs: ["Overdue", "Today", "Upcoming", "All Open", "Done"],
    };
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
.tasks-board__tabs {
  display: flex;
  gap: 4px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}
.tasks-board__tab {
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
.tasks-board__list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.tasks-board__item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid #f3f4f6;
  border-radius: 8px;
  transition: background 0.15s;
}
.tasks-board__item:hover {
  background: #fafbfd;
}
.tasks-board__item-title {
  flex: 1;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-primary, #1a1a2e);
}
.tasks-board__item-project {
  font-size: 11px;
  color: var(--color-text-secondary, #6b7280);
}
.tasks-board__item-due {
  font-size: 11px;
  color: var(--color-text-muted, #9ca3af);
}
</style>
