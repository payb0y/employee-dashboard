<template>
  <div class="workload-widget">
    <div class="workload-widget__header">
      <div class="workload-widget__icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
        </svg>
      </div>
      <span class="workload-widget__title">Workload</span>
    </div>
    <div class="workload-widget__body">
      <div class="workload-widget__row">
        <span class="workload-widget__label">Open</span>
        <span class="workload-widget__value">{{ workload.open }}</span>
      </div>
      <div class="workload-widget__row">
        <span class="workload-widget__label">Done</span>
        <span class="workload-widget__value">{{ workload.done }}</span>
      </div>
      <div class="workload-widget__row">
        <span class="workload-widget__label">Completion</span>
        <span class="workload-widget__value">{{ workload.completionPct }}%</span>
      </div>
      <div class="workload-widget__row">
        <span class="workload-widget__label">Projects</span>
        <span class="workload-widget__value">{{ workload.activeProjects }}</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "WorkloadWidget",
  props: {
    workload: { type: Object, default: function () { return { open: 0, done: 0, completionPct: 0, activeProjects: 0 }; } },
  },
};
</script>

<style scoped>
.workload-widget {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  padding: var(--spacing-md, 16px) var(--spacing-lg, 24px);
  flex: 1;
}
.workload-widget:hover {
  box-shadow: var(--shadow-card-hover, 0 4px 12px rgba(0, 0, 0, 0.1));
}
.workload-widget__header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}
.workload-widget__icon {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  background: rgba(74, 144, 217, 0.1);
  color: #4a90d9;
  display: flex;
  align-items: center;
  justify-content: center;
}
.workload-widget__title {
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
}
.workload-widget__body {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.workload-widget__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 4px 0;
}
.workload-widget__label {
  font-size: 12px;
  color: var(--color-text-secondary, #6b7280);
}
.workload-widget__value {
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
}
</style>
