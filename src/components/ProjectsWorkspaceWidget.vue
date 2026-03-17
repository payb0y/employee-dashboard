<template>
  <section class="projects-workspace">
    <div class="projects-workspace__header" @click="collapsed = !collapsed">
      <h3 class="projects-workspace__title">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" />
        </svg>
        My Projects
        <span class="projects-workspace__count">{{ projects.length }}</span>
      </h3>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="projects-workspace__chevron" :class="{ 'projects-workspace__chevron--rotated': collapsed }">
        <polyline points="18 15 12 9 6 15" />
      </svg>
    </div>
    <div v-show="!collapsed" class="projects-workspace__body">
      <div v-if="projects.length === 0" class="projects-workspace__empty">
        No projects linked to your assigned tasks yet.
      </div>
      <div v-else class="projects-workspace__grid">
        <div v-for="(project, idx) in projects" :key="idx" class="projects-workspace__card">
          <div class="projects-workspace__card-header">
            <span class="projects-workspace__card-name">{{ project.name || 'Untitled' }}</span>
            <span class="projects-workspace__card-status"><!-- TODO: status badge --></span>
          </div>
          <div class="projects-workspace__card-stats">
            <span class="projects-workspace__card-stat"><!-- TODO --> — tasks</span>
            <span class="projects-workspace__card-stat"><!-- TODO --> — overdue</span>
            <span class="projects-workspace__card-stat"><!-- TODO --> — completion</span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
export default {
  name: "ProjectsWorkspaceWidget",
  props: {
    projects: { type: Array, default: function () { return []; } },
  },
  data: function () {
    return { collapsed: false };
  },
};
</script>

<style scoped>
.projects-workspace {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  margin-bottom: var(--spacing-xl, 32px);
  overflow: hidden;
}
.projects-workspace__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-md, 16px) var(--spacing-lg, 24px);
  cursor: pointer;
  user-select: none;
  transition: background 0.15s;
}
.projects-workspace__header:hover {
  background: #fafbfd;
}
.projects-workspace__title {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  margin: 0;
  padding: 0;
  border: none;
  display: flex;
  align-items: center;
  gap: 8px;
}
.projects-workspace__title svg {
  color: #4a90d9;
}
.projects-workspace__count {
  font-size: 11px;
  font-weight: 600;
  background: #e8f0fe;
  color: #1e4a8a;
  padding: 2px 8px;
  border-radius: 8px;
}
.projects-workspace__chevron {
  color: var(--color-text-muted, #9ca3af);
  transition: transform 0.3s;
}
.projects-workspace__chevron--rotated {
  transform: rotate(180deg);
}
.projects-workspace__body {
  padding: 0 var(--spacing-lg, 24px) var(--spacing-lg, 24px);
}
.projects-workspace__empty {
  text-align: center;
  padding: 24px 0;
  font-size: 13px;
  color: var(--color-text-muted, #9ca3af);
}
.projects-workspace__grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}
.projects-workspace__card {
  border: 1px solid #f3f4f6;
  border-radius: 10px;
  padding: 14px;
  transition: border-color 0.15s, box-shadow 0.15s;
  cursor: pointer;
}
.projects-workspace__card:hover {
  border-color: #e0e3e9;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}
.projects-workspace__card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}
.projects-workspace__card-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
}
.projects-workspace__card-stats {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.projects-workspace__card-stat {
  font-size: 11px;
  color: var(--color-text-muted, #9ca3af);
}
@media (max-width: 700px) {
  .projects-workspace__grid {
    grid-template-columns: 1fr;
  }
}
</style>
