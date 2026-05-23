<template>
  <div class="focus-widget">
    <div class="focus-widget__header">
      <div class="focus-widget__icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10" />
          <polyline points="12 6 12 12 16 14" />
        </svg>
      </div>
      <span class="focus-widget__title">Focus Now</span>
    </div>
    <div class="focus-widget__body">
      <div class="focus-widget__stats">
        <div class="focus-widget__stat focus-widget__stat--danger focus-widget__stat--clickable" @click="$emit('filter', 'Overdue')">
          <span class="focus-widget__stat-value">{{ focus.overdue }}</span>
          <span class="focus-widget__stat-label">Overdue</span>
        </div>
        <div class="focus-widget__stat focus-widget__stat--warning focus-widget__stat--clickable" @click="$emit('filter', 'Today')">
          <span class="focus-widget__stat-value">{{ focus.dueToday }}</span>
          <span class="focus-widget__stat-label">Due Today</span>
        </div>
      </div>
      <div class="focus-widget__next" :class="{ 'focus-widget__row--clickable': focus.nextTask }" @click="focus.nextTask && $emit('select-task', focus.nextTask.id)">
        <span class="focus-widget__next-label">Next Task</span>
        <span class="focus-widget__next-value">
          {{ focus.nextTask ? focus.nextTask.title : '— none —' }}
        </span>
      </div>
      <div class="focus-widget__oldest" :class="{ 'focus-widget__row--clickable': focus.oldestTask }" @click="focus.oldestTask && $emit('select-task', focus.oldestTask.id)">
        <span class="focus-widget__oldest-label">Oldest Open</span>
        <span class="focus-widget__oldest-value">
          {{ focus.oldestTask ? focus.oldestTask.title : '— none —' }}
        </span>
      </div>
      <div class="focus-widget__actions">
        <button class="focus-widget__btn" @click="$emit('filter', 'Overdue')">Show overdue</button>
        <button class="focus-widget__btn" @click="$emit('filter', 'Today')">Show today</button>
        <button class="focus-widget__btn" @click="$emit('filter', 'All Open')">Show all open</button>
      </div>

      <div v-if="showEventsSection" class="focus-widget__events">
        <div class="focus-widget__events-header">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
          </svg>
          <span>Upcoming events</span>
        </div>

        <div v-if="(focus.remainingToday || 0) === 0" class="focus-widget__events-empty">
          No more events today
        </div>

        <ul class="focus-widget__events-list">
          <li
            v-for="ev in events"
            :key="ev.uid"
            class="focus-widget__event focus-widget__row--clickable"
            @click="openCalendar"
          >
            <span class="focus-widget__event-dot" :style="{ background: ev.color || '#4a90d9' }"></span>
            <span class="focus-widget__event-title">{{ ev.title }}</span>
            <span class="focus-widget__event-when">{{ formatRelative(ev.startsAt, ev.allDay) }}</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "FocusNowWidget",
  props: {
    focus: {
      type: Object,
      default: function () {
        return { overdue: 0, dueToday: 0, nextTask: null, oldestTask: null, remainingToday: 0 };
      },
    },
    events: {
      type: Array,
      default: function () { return []; },
    },
  },
  computed: {
    showEventsSection: function () {
      var hasEvents = Array.isArray(this.events) && this.events.length > 0;
      var nothingToday = (this.focus && this.focus.remainingToday === 0);
      return hasEvents || nothingToday;
    },
  },
  methods: {
    formatRelative: function (isoString, allDay) {
      if (!isoString) { return ""; }
      var start = new Date(isoString);
      if (isNaN(start.getTime())) { return ""; }

      var now = new Date();
      var startMid = new Date(start.getFullYear(), start.getMonth(), start.getDate());
      var nowMid   = new Date(now.getFullYear(), now.getMonth(), now.getDate());
      var diffDays = Math.round((startMid - nowMid) / 86400000);

      if (diffDays <= 0) {
        if (allDay) { return "today"; }
        var hh = String(start.getHours()).padStart(2, "0");
        var mm = String(start.getMinutes()).padStart(2, "0");
        return "today " + hh + ":" + mm;
      }
      if (diffDays === 1) { return "tomorrow"; }
      return "in " + diffDays + " days";
    },
    openCalendar: function () {
      window.open("/index.php/apps/calendar", "_blank", "noopener");
    },
  },
};
</script>

<style scoped>
.focus-widget {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  padding: var(--spacing-lg, 24px);
  min-height: 200px;
}
.focus-widget:hover {
  box-shadow: var(--shadow-card-hover, 0 4px 12px rgba(0, 0, 0, 0.1));
}
.focus-widget__header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}
.focus-widget__icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  display: flex;
  align-items: center;
  justify-content: center;
}
.focus-widget__title {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
}
.focus-widget__body {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.focus-widget__stats {
  display: flex;
  gap: 12px;
}
.focus-widget__stat {
  flex: 1;
  padding: 12px;
  border-radius: 10px;
  text-align: center;
}
.focus-widget__stat--danger {
  background: #fef3f2;
}
.focus-widget__stat--warning {
  background: #fffbeb;
}
.focus-widget__stat--clickable {
  cursor: pointer;
  transition: transform 0.15s, box-shadow 0.15s;
}
.focus-widget__stat--clickable:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
.focus-widget__row--clickable {
  cursor: pointer;
  transition: background 0.15s;
}
.focus-widget__row--clickable:hover {
  background: #eef2ff;
}
.focus-widget__stat-value {
  display: block;
  font-size: 28px;
  font-weight: 800;
  color: var(--color-text-primary, #1a1a2e);
}
.focus-widget__stat-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.focus-widget__next,
.focus-widget__oldest {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  background: #f9fafb;
  border-radius: 8px;
}
.focus-widget__next-label,
.focus-widget__oldest-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
}
.focus-widget__next-value,
.focus-widget__oldest-value {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-primary, #1a1a2e);
}
.focus-widget__actions {
  display: flex;
  gap: 8px;
}
.focus-widget__btn {
  flex: 1;
  padding: 6px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  font-size: 11px;
  font-weight: 600;
  color: #4a90d9;
  cursor: pointer;
  transition: background 0.15s;
}
.focus-widget__btn:hover:not(:disabled) {
  background: #e8f0fe;
}
.focus-widget__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.focus-widget__events {
  margin-top: 4px;
  padding-top: 14px;
  border-top: 1px solid var(--color-border, #e5e7eb);
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.focus-widget__events-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-primary, #1a1a2e);
}
.focus-widget__events-empty {
  font-size: 12px;
  color: var(--color-text-secondary, #6b7280);
  padding: 4px 0;
}
.focus-widget__events-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.focus-widget__event {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 8px;
  border-radius: 8px;
  font-size: 13px;
}
.focus-widget__event-dot {
  flex: 0 0 10px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--color-blue, #4a90d9);
}
.focus-widget__event-title {
  flex: 1;
  min-width: 0;
  font-weight: 600;
  color: var(--color-text-primary, #1a1a2e);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.focus-widget__event-when {
  flex: 0 0 auto;
  font-size: 12px;
  color: var(--color-text-secondary, #6b7280);
}
</style>
