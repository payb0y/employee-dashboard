<template>
  <div class="schedule-widget">
    <div class="schedule-widget__header">
      <div class="schedule-widget__icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
          <line x1="16" y1="2" x2="16" y2="6" />
          <line x1="8" y1="2" x2="8" y2="6" />
          <line x1="3" y1="10" x2="21" y2="10" />
        </svg>
      </div>
      <span class="schedule-widget__title">Schedule</span>
    </div>
    <div class="schedule-widget__body">
      <div class="schedule-widget__row">
        <span class="schedule-widget__label">Due Today</span>
        <span class="schedule-widget__value">{{ schedule.dueToday }}</span>
      </div>
      <div class="schedule-widget__row">
        <span class="schedule-widget__label">This Week</span>
        <span class="schedule-widget__value">{{ schedule.dueThisWeek }}</span>
      </div>
      <div class="schedule-widget__row">
        <span class="schedule-widget__label">No Due Date</span>
        <span class="schedule-widget__value">{{ schedule.noDueDate }}</span>
      </div>
      <div class="schedule-widget__row">
        <span class="schedule-widget__label">Next Milestone</span>
        <span class="schedule-widget__value">{{ schedule.nextMilestone ? schedule.nextMilestone.label : '—' }}</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "ScheduleWidget",
  props: {
    schedule: { type: Object, default: function () { return { dueToday: 0, dueThisWeek: 0, noDueDate: 0, nextMilestone: null }; } },
  },
};
</script>

<style scoped>
.schedule-widget {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  padding: var(--spacing-md, 16px) var(--spacing-lg, 24px);
  flex: 1;
}
.schedule-widget:hover {
  box-shadow: var(--shadow-card-hover, 0 4px 12px rgba(0, 0, 0, 0.1));
}
.schedule-widget__header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}
.schedule-widget__icon {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  background: rgba(14, 165, 233, 0.1);
  color: #0ea5e9;
  display: flex;
  align-items: center;
  justify-content: center;
}
.schedule-widget__title {
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
}
.schedule-widget__body {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.schedule-widget__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 4px 0;
}
.schedule-widget__label {
  font-size: 12px;
  color: var(--color-text-secondary, #6b7280);
}
.schedule-widget__value {
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
}
</style>
