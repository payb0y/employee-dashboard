<template>
  <CardPanel title="My Projects" :total="projects.length" empty="No projects assigned">
    <template #icon>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z" />
      </svg>
    </template>

    <button
      v-for="p in projects"
      :key="p.id"
      class="projects-panel__row"
      @click="$emit('select', p.id)"
    >
      <span class="projects-panel__name">{{ p.name }}</span>
      <span class="projects-panel__meta">{{ p.number }}</span>
      <span class="iz-badge" :class="statusClass(p.status)">{{ statusLabel(p.status) }}</span>
    </button>
  </CardPanel>
</template>

<script>
import CardPanel from "./CardPanel.vue";

export default {
  name: "ProjectsPanel",
  components: { CardPanel },
  props: {
    projects: { type: Array, default: function () { return []; } },
  },
  methods: {
    statusLabel: function (status) {
      if (status === 1) return "Completed";
      if (status === 2) return "Archived";
      return "Active";
    },
    statusClass: function (status) {
      if (status === 1) return "iz-badge--accent";
      if (status === 2) return "iz-badge--warning";
      return "iz-badge--cat-5";
    },
  },
};
</script>

<style scoped>
.projects-panel__row {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 3px;
  width: 100%;
  text-align: left;
  padding: 6px;
  margin: 0 -6px;
  border-radius: var(--radius-sm);
  cursor: pointer;
}
.projects-panel__row:hover { background: var(--bg-subtle); }
.projects-panel__name {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-primary);
}
.projects-panel__meta {
  font-size: 11px;
  color: var(--color-text-secondary);
}
</style>
