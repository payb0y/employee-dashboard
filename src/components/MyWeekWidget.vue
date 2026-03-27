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
      <!-- Summary row -->
      <div class="my-week__summary">
        <div class="my-week__section">
          <span class="my-week__section-label">Due Today</span>
          <span class="my-week__section-value">{{ schedule.dueToday }}</span>
        </div>
        <div class="my-week__section">
          <span class="my-week__section-label">Due Next 7 Days</span>
          <span class="my-week__section-value">{{ schedule.dueThisWeek }}</span>
        </div>
        <div class="my-week__section">
          <span class="my-week__section-label">No Due Date</span>
          <span class="my-week__section-value">{{ schedule.noDueDate || 0 }}</span>
        </div>
        <div class="my-week__section">
          <span class="my-week__section-label">Next Milestone</span>
          <span class="my-week__section-value">{{ nextMilestoneLabel }}</span>
        </div>
      </div>

      <!-- Timeline items -->
      <div v-if="timeline.length > 0" class="my-week__timeline">
        <h4 class="my-week__sub-title">Project Timeline</h4>
        <div v-for="item in sortedTimeline" :key="item.id" class="my-week__tl-item">
          <span class="my-week__tl-dot" :style="{ background: item.color || '#94a3b8' }"></span>
          <div class="my-week__tl-content">
            <div class="my-week__tl-row">
              <span class="my-week__tl-label">{{ item.label }}</span>
              <span class="my-week__tl-badge" :class="'my-week__tl-badge--' + item.itemType">{{ item.itemType }}</span>
            </div>
            <span class="my-week__tl-date">{{ formatRange(item) }}</span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
export default {
  name: "MyWeekWidget",
  props: {
    tasks: { type: Array, default: function () { return []; } },
    schedule: { type: Object, default: function () { return { dueToday: 0, dueThisWeek: 0, noDueDate: 0 }; } },
    timeline: { type: Array, default: function () { return []; } },
  },
  data: function () {
    return { collapsed: false };
  },
  computed: {
    nextMilestoneLabel: function () {
      var milestones = this.timeline.filter(function (t) { return t.itemType === "milestone"; });
      if (milestones.length === 0) return "\u2014";
      var now = new Date();
      now.setHours(0, 0, 0, 0);
      var upcoming = milestones.filter(function (m) {
        var d = m.endDate || m.startDate;
        return d && new Date(d) >= now;
      }).sort(function (a, b) {
        return new Date(a.endDate || a.startDate) - new Date(b.endDate || b.startDate);
      });
      if (upcoming.length === 0) return "\u2014";
      return upcoming[0].label;
    },
    sortedTimeline: function () {
      return this.timeline.slice().sort(function (a, b) {
        var da = a.startDate || a.endDate || "";
        var db = b.startDate || b.endDate || "";
        return da.localeCompare(db);
      });
    },
  },
  methods: {
    formatRange: function (item) {
      var s = item.startDate ? this.shortDate(item.startDate) : "";
      var e = item.endDate ? this.shortDate(item.endDate) : "";
      if (item.itemType === "milestone") return s || e || "\u2014";
      if (s && e && s !== e) return s + " \u2013 " + e;
      return s || e || "\u2014";
    },
    shortDate: function (iso) {
      if (!iso) return "";
      var d = new Date(iso + "T00:00:00");
      var months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
      return months[d.getMonth()] + " " + d.getDate();
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
}
.my-week__summary {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 16px;
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

/* Timeline */
.my-week__sub-title {
  font-size: 12px;
  font-weight: 700;
  color: var(--color-text-secondary, #6b7280);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin: 0 0 10px 0;
  padding: 0;
  border: none;
}
.my-week__timeline {
  border-top: 1px solid #f3f4f6;
  padding-top: 14px;
}
.my-week__tl-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 8px 0;
  position: relative;
}
.my-week__tl-item + .my-week__tl-item {
  border-top: 1px solid #f9fafb;
}
.my-week__tl-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 3px;
}
.my-week__tl-content {
  flex: 1;
  min-width: 0;
}
.my-week__tl-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 2px;
}
.my-week__tl-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-primary, #1a1a2e);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.my-week__tl-badge {
  font-size: 10px;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 6px;
  text-transform: capitalize;
  flex-shrink: 0;
}
.my-week__tl-badge--phase {
  background: #e0f2fe;
  color: #0369a1;
}
.my-week__tl-badge--milestone {
  background: #fef3c7;
  color: #92400e;
}
.my-week__tl-date {
  font-size: 11px;
  color: var(--color-text-muted, #9ca3af);
}
</style>
