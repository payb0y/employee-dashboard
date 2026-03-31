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
        <div
          v-for="project in projects"
          :key="project.id"
          class="projects-workspace__card"
          :class="{ 'projects-workspace__card--active-filter': activeProjectId === project.id }"
          @click="selectProject(project)"
        >
          <div class="projects-workspace__card-header">
            <span class="projects-workspace__card-name">{{ project.name || 'Untitled' }}</span>
            <span class="projects-workspace__card-status" :class="'projects-workspace__card-status--' + statusKey(project.status)">{{ statusLabel(project.status) }}</span>
          </div>
          <div v-if="project.number || project.clientName" class="projects-workspace__card-meta">
            <span v-if="project.number" class="projects-workspace__card-number">#{{ project.number }}</span>
            <span v-if="project.clientName" class="projects-workspace__card-client">{{ project.clientName }}</span>
          </div>
          <p v-if="project.description" class="projects-workspace__card-desc">{{ truncate(project.description, 100) }}</p>
          <div class="projects-workspace__card-stats">
            <span v-if="project.folderId" class="projects-workspace__card-stat projects-workspace__card-stat--link">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
              Folder
            </span>
            <span v-if="project.whiteBoardId" class="projects-workspace__card-stat projects-workspace__card-stat--link">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
              Whiteboard
            </span>
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
    activeProjectId: { type: Number, default: null },
  },
  data: function () {
    return {
      collapsed: false,
    };
  },
  methods: {
    selectProject: function (project) {
      var newId = this.activeProjectId === project.id ? null : project.id;
      this.$emit("filter-project", newId);
    },
    statusLabel: function (status) {
      var map = { 0: "Active", 1: "Completed", 2: "Archived" };
      return map[status] || "Active";
    },
    statusKey: function (status) {
      var map = { 0: "active", 1: "completed", 2: "archived" };
      return map[status] || "active";
    },
    truncate: function (str, len) {
      if (!str) return "";
      return str.length > len ? str.substring(0, len) + "\u2026" : str;
    },
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
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding-bottom: 4px;
}
.projects-workspace__card {
  min-width: 200px;
  max-width: 260px;
  flex-shrink: 0;
  border: 1px solid #f3f4f6;
  border-radius: 10px;
  padding: 14px;
  transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
  cursor: pointer;
}
.projects-workspace__card:hover {
  border-color: #e0e3e9;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}
.projects-workspace__card--active-filter {
  border-color: #4a90d9;
  box-shadow: 0 0 0 2px rgba(74, 144, 217, 0.2);
  background: #f0f7ff;
}
.projects-workspace__card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}
.projects-workspace__card-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.projects-workspace__card-status {
  font-size: 10px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 6px;
  flex-shrink: 0;
}
.projects-workspace__card-status--active {
  background: #dcfce7;
  color: #166534;
}
.projects-workspace__card-status--completed {
  background: #e0f2fe;
  color: #0369a1;
}
.projects-workspace__card-status--archived {
  background: #f3f4f6;
  color: #6b7280;
}
.projects-workspace__card-meta {
  display: flex;
  gap: 8px;
  margin-bottom: 6px;
}
.projects-workspace__card-number {
  font-size: 11px;
  font-weight: 600;
  color: #4a90d9;
}
.projects-workspace__card-client {
  font-size: 11px;
  color: var(--color-text-secondary, #6b7280);
}
.projects-workspace__card-desc {
  font-size: 12px;
  color: var(--color-text-secondary, #6b7280);
  line-height: 1.5;
  margin: 0 0 8px 0;
}
.projects-workspace__card-stats {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.projects-workspace__card-stat {
  font-size: 11px;
  color: var(--color-text-muted, #9ca3af);
  display: flex;
  align-items: center;
  gap: 3px;
}
.projects-workspace__card-stat--link svg {
  color: #4a90d9;
}
</style>
