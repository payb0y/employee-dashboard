<template>
  <div class="welcome-strip">
    <div class="welcome-strip__left">
      <span class="welcome-strip__avatar">{{ initial }}</span>
      <div class="welcome-strip__text">
        <h2 class="welcome-strip__name">{{ employee.displayName || employee.uid }}</h2>
        <span class="welcome-strip__sub">
          <span v-if="employee.title">{{ employee.title }} · </span>
          <span v-if="organization">{{ organization.name }}</span>
        </span>
      </div>
    </div>
    <div class="welcome-strip__right">
      <span v-if="focusNow.overdue > 0" class="welcome-strip__badge welcome-strip__badge--danger welcome-strip__badge--clickable" @click="$emit('filter', 'Overdue')">
        {{ focusNow.overdue }} overdue
      </span>
      <span v-if="focusNow.dueToday > 0" class="welcome-strip__badge welcome-strip__badge--warning welcome-strip__badge--clickable" @click="$emit('filter', 'Today')">
        {{ focusNow.dueToday }} due today
      </span>
      <span class="welcome-strip__badge welcome-strip__badge--info welcome-strip__badge--clickable" @click="$emit('filter', 'All Open')">
        {{ workload.open }} open
      </span>
    </div>
  </div>
</template>

<script>
export default {
  name: "WelcomeStrip",
  props: {
    employee: { type: Object, default: function () { return {}; } },
    organization: { type: Object, default: null },
    focusNow: { type: Object, default: function () { return { overdue: 0, dueToday: 0 }; } },
    workload: { type: Object, default: function () { return { open: 0 }; } },
  },
  computed: {
    initial: function () {
      var name = this.employee.displayName || this.employee.uid || "?";
      return name.charAt(0).toUpperCase();
    },
  },
};
</script>

<style scoped>
.welcome-strip {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  padding: var(--spacing-md, 16px) var(--spacing-lg, 24px);
  margin-bottom: var(--spacing-xl, 32px);
  border-left: 4px solid #4a90d9;
}
.welcome-strip__left {
  display: flex;
  align-items: center;
  gap: 14px;
}
.welcome-strip__avatar {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: linear-gradient(135deg, #4a90d9, #6cb0f0);
  color: #fff;
  font-size: 22px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.welcome-strip__text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.welcome-strip__name {
  font-size: 20px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  margin: 0;
  padding: 0;
  border: none;
  line-height: 1.2;
}
.welcome-strip__sub {
  font-size: 12px;
  color: var(--color-text-secondary, #6b7280);
}
.welcome-strip__right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.welcome-strip__badge {
  font-size: 11px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 10px;
}
.welcome-strip__badge--danger {
  background: #fde8e8;
  color: #b91c1c;
}
.welcome-strip__badge--warning {
  background: #fef3cd;
  color: #92400e;
}
.welcome-strip__badge--info {
  background: #e8f0fe;
  color: #1e4a8a;
}
.welcome-strip__badge--clickable {
  cursor: pointer;
  transition: transform 0.15s, box-shadow 0.15s;
}
.welcome-strip__badge--clickable:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
}
@media (max-width: 700px) {
  .welcome-strip {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
}
</style>
