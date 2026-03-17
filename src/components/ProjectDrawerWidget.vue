<template>
  <section class="project-drawer">
    <div class="project-drawer__header" @click="collapsed = !collapsed">
      <h3 class="project-drawer__title">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        Project Detail
      </h3>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="project-drawer__chevron" :class="{ 'project-drawer__chevron--rotated': collapsed }">
        <polyline points="18 15 12 9 6 15" />
      </svg>
    </div>
    <div v-show="!collapsed" class="project-drawer__body">
      <p class="project-drawer__placeholder">
        Select a project from My Projects to view details here.
      </p>
      <!-- TODO: project summary, client info, stack breakdown, timeline, resources -->
    </div>
  </section>
</template>

<script>
export default {
  name: "ProjectDrawerWidget",
  data: function () {
    return { collapsed: true };
  },
};
</script>

<style scoped>
.project-drawer {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  margin-bottom: var(--spacing-xl, 32px);
  overflow: hidden;
}
.project-drawer__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-md, 16px) var(--spacing-lg, 24px);
  cursor: pointer;
  user-select: none;
  transition: background 0.15s;
}
.project-drawer__header:hover {
  background: #fafbfd;
}
.project-drawer__title {
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
.project-drawer__title svg {
  color: #c878c8;
}
.project-drawer__chevron {
  color: var(--color-text-muted, #9ca3af);
  transition: transform 0.3s;
}
.project-drawer__chevron--rotated {
  transform: rotate(180deg);
}
.project-drawer__body {
  padding: 0 var(--spacing-lg, 24px) var(--spacing-lg, 24px);
}
.project-drawer__placeholder {
  text-align: center;
  padding: 20px 0;
  font-size: 13px;
  color: var(--color-text-muted, #9ca3af);
  font-style: italic;
}
</style>
