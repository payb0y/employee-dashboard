<template>
  <section class="resources-widget">
    <div class="resources-widget__header" @click="collapsed = !collapsed">
      <h3 class="resources-widget__title">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z" />
          <polyline points="13 2 13 9 20 9" />
        </svg>
        Resources &amp; Notes
      </h3>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="resources-widget__chevron" :class="{ 'resources-widget__chevron--rotated': collapsed }">
        <polyline points="18 15 12 9 6 15" />
      </svg>
    </div>
    <div v-show="!collapsed" class="resources-widget__body">
      <div class="resources-widget__grid">
        <div class="resources-widget__item">
          <span class="resources-widget__item-value">{{ resources.files }}</span>
          <span class="resources-widget__item-label">Files</span>
        </div>
        <div class="resources-widget__item">
          <span class="resources-widget__item-value">{{ resources.notes }}</span>
          <span class="resources-widget__item-label">Notes</span>
        </div>
        <div class="resources-widget__item">
          <span class="resources-widget__item-value">{{ resources.whiteboards }}</span>
          <span class="resources-widget__item-label">Whiteboards</span>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
export default {
  name: "ResourcesWidget",
  props: {
    resources: { type: Object, default: function () { return { files: 0, notes: 0, whiteboards: 0 }; } },
  },
  data: function () {
    return { collapsed: true };
  },
};
</script>

<style scoped>
.resources-widget {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  margin-bottom: var(--spacing-xl, 32px);
  overflow: hidden;
}
.resources-widget__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-md, 16px) var(--spacing-lg, 24px);
  cursor: pointer;
  user-select: none;
  transition: background 0.15s;
}
.resources-widget__header:hover {
  background: #fafbfd;
}
.resources-widget__title {
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
.resources-widget__title svg {
  color: #8b5cf6;
}
.resources-widget__chevron {
  color: var(--color-text-muted, #9ca3af);
  transition: transform 0.3s;
}
.resources-widget__chevron--rotated {
  transform: rotate(180deg);
}
.resources-widget__body {
  padding: 0 var(--spacing-lg, 24px) var(--spacing-lg, 24px);
}
.resources-widget__grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}
.resources-widget__item {
  text-align: center;
  padding: 16px 8px;
  background: #f9fafb;
  border-radius: 10px;
}
.resources-widget__item-value {
  display: block;
  font-size: 24px;
  font-weight: 800;
  color: var(--color-text-primary, #1a1a2e);
  margin-bottom: 2px;
}
.resources-widget__item-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
@media (max-width: 500px) {
  .resources-widget__grid {
    grid-template-columns: 1fr;
  }
}
</style>
