<template>
  <section class="gantt-widget">
    <div class="gantt-widget__header" @click="collapsed = !collapsed">
      <h3 class="gantt-widget__title">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="4" y1="9" x2="20" y2="9" /><line x1="4" y1="15" x2="20" y2="15" />
          <line x1="10" y1="3" x2="8" y2="21" /><line x1="16" y1="3" x2="14" y2="21" />
        </svg>
        Project Timeline
        <span v-if="timeline.length" class="gantt-widget__count">{{ timeline.length }} items</span>
      </h3>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="gantt-widget__chevron" :class="{ 'gantt-widget__chevron--rotated': collapsed }">
        <polyline points="18 15 12 9 6 15" />
      </svg>
    </div>

    <div v-show="!collapsed" class="gantt-widget__body">
      <div v-if="!timeline.length" class="gantt-widget__empty">No timeline items found.</div>

      <template v-else>
        <!-- Date axis -->
        <div class="gantt-chart">
          <div class="gantt-chart__axis">
            <div class="gantt-chart__label-col"></div>
            <div class="gantt-chart__bar-col">
              <div class="gantt-chart__ticks">
                <span v-for="(tick, i) in dateTicks" :key="i" class="gantt-chart__tick" :style="{ left: tick.pct + '%' }">
                  {{ tick.label }}
                </span>
              </div>
            </div>
          </div>

          <!-- Today marker -->
          <div v-if="todayPct !== null" class="gantt-chart__today-wrap">
            <div class="gantt-chart__label-col"></div>
            <div class="gantt-chart__bar-col" style="position: relative;">
              <div class="gantt-chart__today" :style="{ left: todayPct + '%' }">
                <span class="gantt-chart__today-label">Today</span>
              </div>
            </div>
          </div>

          <!-- Grouped by project -->
          <template v-for="group in groupedItems">
            <div :key="'g-' + group.projectName" class="gantt-chart__group-header">
              <div class="gantt-chart__label-col">
                <span class="gantt-chart__group-name">{{ group.projectName }}</span>
              </div>
              <div class="gantt-chart__bar-col"></div>
            </div>

            <div v-for="item in group.items" :key="item.id" class="gantt-chart__row">
              <div class="gantt-chart__label-col">
                <span class="gantt-chart__item-label" :title="item.label">{{ item.label }}</span>
                <span class="gantt-chart__item-type" :class="'gantt-chart__item-type--' + item.itemType">{{ item.itemType }}</span>
              </div>
              <div class="gantt-chart__bar-col">
                <!-- Phase bar -->
                <div v-if="item.itemType === 'phase'" class="gantt-chart__bar" :style="barStyle(item)" :title="item.label + ': ' + formatRange(item)">
                  <span class="gantt-chart__bar-text">{{ formatRange(item) }}</span>
                </div>
                <!-- Milestone diamond -->
                <div v-else class="gantt-chart__milestone" :style="milestoneStyle(item)" :title="item.label + ': ' + shortDate(item.startDate || item.endDate)">
                  <svg width="14" height="14" viewBox="0 0 14 14"><polygon points="7,0 14,7 7,14 0,7" :fill="item.color || '#f59e0b'" /></svg>
                </div>
              </div>
            </div>
          </template>
        </div>
      </template>
    </div>
  </section>
</template>

<script>
export default {
  name: "GanttWidget",
  props: {
    timeline: { type: Array, default: function () { return []; } },
    projects: { type: Array, default: function () { return []; } },
  },
  data: function () {
    return { collapsed: false };
  },
  computed: {
    dateRange: function () {
      var min = null;
      var max = null;
      this.timeline.forEach(function (item) {
        var dates = [item.startDate, item.endDate].filter(Boolean);
        dates.forEach(function (d) {
          var ts = new Date(d + "T00:00:00").getTime();
          if (min === null || ts < min) min = ts;
          if (max === null || ts > max) max = ts;
        });
      });
      if (min === null) return { min: 0, max: 0, span: 0 };
      var pad = 2 * 86400000;
      min -= pad;
      max += pad;
      return { min: min, max: max, span: max - min };
    },
    todayPct: function () {
      if (!this.dateRange.span) return null;
      var today = new Date();
      today.setHours(0, 0, 0, 0);
      var ts = today.getTime();
      if (ts < this.dateRange.min || ts > this.dateRange.max) return null;
      return ((ts - this.dateRange.min) / this.dateRange.span) * 100;
    },
    dateTicks: function () {
      var range = this.dateRange;
      if (!range.span) return [];
      var ticks = [];
      var count = 6;
      for (var i = 0; i <= count; i++) {
        var ts = range.min + (range.span * i / count);
        var d = new Date(ts);
        var months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
        ticks.push({
          pct: (i / count) * 100,
          label: months[d.getMonth()] + " " + d.getDate(),
        });
      }
      return ticks;
    },
    projectNameMap: function () {
      var map = {};
      this.projects.forEach(function (p) {
        map[p.id] = p.name || "Project " + p.id;
      });
      return map;
    },
    groupedItems: function () {
      var self = this;
      var groups = {};
      var order = [];
      this.timeline.forEach(function (item) {
        var pid = item.projectId;
        if (!groups[pid]) {
          groups[pid] = { projectName: self.projectNameMap[pid] || "Project " + pid, items: [] };
          order.push(pid);
        }
        groups[pid].items.push(item);
      });
      return order.map(function (pid) { return groups[pid]; });
    },
  },
  methods: {
    datePct: function (isoDate) {
      if (!isoDate || !this.dateRange.span) return 0;
      var ts = new Date(isoDate + "T00:00:00").getTime();
      return ((ts - this.dateRange.min) / this.dateRange.span) * 100;
    },
    barStyle: function (item) {
      var left = this.datePct(item.startDate || item.endDate);
      var right = this.datePct(item.endDate || item.startDate);
      var width = Math.max(right - left, 1);
      return {
        left: left + "%",
        width: width + "%",
        background: item.color || "#3b82f6",
      };
    },
    milestoneStyle: function (item) {
      var pct = this.datePct(item.startDate || item.endDate);
      return { left: "calc(" + pct + "% - 7px)" };
    },
    formatRange: function (item) {
      var s = item.startDate ? this.shortDate(item.startDate) : "";
      var e = item.endDate ? this.shortDate(item.endDate) : "";
      if (s && e && s !== e) return s + " \u2013 " + e;
      return s || e || "";
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
.gantt-widget {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  margin-bottom: var(--spacing-xl, 32px);
  overflow: hidden;
}
.gantt-widget__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-md, 16px) var(--spacing-lg, 24px);
  cursor: pointer;
  user-select: none;
  transition: background 0.15s;
}
.gantt-widget__header:hover { background: #fafbfd; }
.gantt-widget__title {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  margin: 0; padding: 0; border: none;
  display: flex;
  align-items: center;
  gap: 8px;
}
.gantt-widget__title svg { color: #6366f1; }
.gantt-widget__count {
  font-size: 11px;
  font-weight: 600;
  background: #eef2ff;
  color: #4338ca;
  padding: 2px 8px;
  border-radius: 8px;
}
.gantt-widget__chevron {
  color: var(--color-text-muted, #9ca3af);
  transition: transform 0.3s;
}
.gantt-widget__chevron--rotated { transform: rotate(180deg); }
.gantt-widget__body {
  padding: 0 var(--spacing-lg, 24px) var(--spacing-lg, 24px);
}
.gantt-widget__empty {
  text-align: center;
  padding: 20px 0;
  font-size: 13px;
  color: var(--color-text-muted, #9ca3af);
}

/* Chart */
.gantt-chart {
  overflow-x: auto;
  min-width: 0;
}
.gantt-chart__axis,
.gantt-chart__today-wrap,
.gantt-chart__group-header,
.gantt-chart__row {
  display: flex;
  align-items: center;
  min-height: 28px;
}
.gantt-chart__label-col {
  width: 160px;
  min-width: 160px;
  flex-shrink: 0;
  padding-right: 10px;
  display: flex;
  align-items: center;
  gap: 6px;
}
.gantt-chart__bar-col {
  flex: 1;
  position: relative;
  min-width: 300px;
  height: 100%;
}

/* Ticks */
.gantt-chart__ticks {
  position: relative;
  height: 22px;
  border-bottom: 1px solid #e5e7eb;
}
.gantt-chart__tick {
  position: absolute;
  top: 0;
  transform: translateX(-50%);
  font-size: 10px;
  color: var(--color-text-muted, #9ca3af);
  white-space: nowrap;
}

/* Today */
.gantt-chart__today {
  position: absolute;
  top: -2px;
  bottom: -2px;
  width: 2px;
  background: #ef4444;
  z-index: 2;
}
.gantt-chart__today-label {
  position: absolute;
  top: -14px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 9px;
  font-weight: 700;
  color: #ef4444;
  white-space: nowrap;
}

/* Group header */
.gantt-chart__group-header {
  margin-top: 8px;
  border-bottom: 1px solid #f3f4f6;
}
.gantt-chart__group-name {
  font-size: 11px;
  font-weight: 700;
  color: var(--color-text-secondary, #6b7280);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

/* Row */
.gantt-chart__row {
  min-height: 32px;
  padding: 2px 0;
}
.gantt-chart__row:hover {
  background: #fafbfd;
}
.gantt-chart__item-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--color-text-primary, #1a1a2e);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 110px;
}
.gantt-chart__item-type {
  font-size: 9px;
  font-weight: 600;
  padding: 1px 5px;
  border-radius: 4px;
  text-transform: capitalize;
  flex-shrink: 0;
}
.gantt-chart__item-type--phase { background: #e0f2fe; color: #0369a1; }
.gantt-chart__item-type--milestone { background: #fef3c7; color: #92400e; }

/* Phase bar */
.gantt-chart__bar {
  position: absolute;
  top: 4px;
  height: 22px;
  border-radius: 6px;
  min-width: 4px;
  display: flex;
  align-items: center;
  padding: 0 6px;
  overflow: hidden;
  transition: opacity 0.15s;
  opacity: 0.85;
}
.gantt-chart__row:hover .gantt-chart__bar { opacity: 1; }
.gantt-chart__bar-text {
  font-size: 10px;
  font-weight: 600;
  color: #fff;
  white-space: nowrap;
  text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

/* Milestone */
.gantt-chart__milestone {
  position: absolute;
  top: 6px;
}

@media (max-width: 600px) {
  .gantt-chart__label-col {
    width: 100px;
    min-width: 100px;
  }
  .gantt-chart__item-label {
    max-width: 60px;
  }
}
</style>
