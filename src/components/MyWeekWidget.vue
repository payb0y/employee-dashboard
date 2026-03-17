<template>
  <section class="my-week">
    <div class="my-week__header" @click="collapsed = !collapsed">
      <h3 class="my-week__title">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
          <line x1="16" y1="2" x2="16" y2="6" />
          <line x1="8" y1="2" x2="8" y2="6" />
          <line x1="3" y1="10" x2="21" y2="10" />
        </svg>
        My Week
      </h3>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="my-week__chevron" :class="{ 'my-week__chevron--rotated': collapsed }">
        <polyline points="18 15 12 9 6 15" />
      </svg>
    </div>
    <div v-show="!collapsed" class="my-week__body">
      <div class="my-week__section">
        <span class="my-week__section-label">Due Today</span>
        <span class="my-week__section-value">{{ schedule.dueToday }}</span>
      </div>
      <div class="my-week__section">
        <span class="my-week__section-label">Due Next 7 Days</span>
        <span class="my-week__section-value">{{ schedule.dueThisWeek }}</span>
      </div>
      <div class="my-week__section">
        <span class="my-week__section-label">Recently Updated</span>
        <span class="my-week__section-value"><!-- TODO -->&mdash;</span>
      </div>
      <div class="my-week__section">
        <span class="my-week__section-label">Next Milestone</span>
        <span class="my-week__section-value">{{ nextMilestoneLabel }}</span>
      </div>
    </div>
  </section>
</template>

<script>
export default {
  name: "MyWeekWidget",
  props: {
    tasks: { type: Array, default: function () { return []; } },
    schedule: { type: Object, default: function () { return { dueToday: 0, dueThisWeek: 0 }; } },
    timeline: { type: Array, default: function () { return []; } },
  },
  data: function () {
    return { collapsed: false };
  },
  computed: {
    nextMilestoneLabel: function () {
      if (this.timeline.length === 0) return "—";
      return this.timeline[0].label || "—";
    },
  },
};
</script>

<style scoped>
.my-week {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  margin-bottom: var(--spacing-xl, 32px);
  overflow: hidden;
}
.my-week__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-md, 16px) var(--spacing-lg, 24px);
  cursor: pointer;
  user-select: none;
  transition: background 0.15s;
}
.my-week__header:hover {
  background: #fafbfd;
}
.my-week__title {
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
.my-week__title svg {
  color: #0ea5e9;
}
.my-week__chevron {
  color: var(--color-text-muted, #9ca3af);
  transition: transform 0.3s;
}
.my-week__chevron--rotated {
  transform: rotate(180deg);
}
.my-week__body {
  padding: 0 var(--spacing-lg, 24px) var(--spacing-lg, 24px);
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.my-week__section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  background: #f9fafb;
  border-radius: 8px;
}
.my-week__section-label {
  font-size: 12px;
  color: var(--color-text-secondary, #6b7280);
  font-weight: 500;
}
.my-week__section-value {
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
}
</style>
