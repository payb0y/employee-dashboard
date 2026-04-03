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
        <span class="my-week__badge">{{ totalThisWeek }} tasks</span>
      </h3>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="my-week__chevron" :class="{ 'my-week__chevron--rotated': collapsed }">
        <polyline points="18 15 12 9 6 15" />
      </svg>
    </div>
    <div v-show="!collapsed" class="my-week__body">

      <!-- Week navigation -->
      <div class="my-week__nav">
        <button class="my-week__nav-btn" title="Previous week" @click.stop="prevWeek">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" fill="none" stroke="#6b7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <span class="my-week__nav-label">{{ weekLabel }}</span>
        <button class="my-week__nav-btn" title="Next week" @click.stop="nextWeek">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6" fill="none" stroke="#6b7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <button v-if="!isCurrentWeek" class="my-week__nav-today" @click.stop="goToCurrentWeek">Today</button>
      </div>

      <!-- Overdue -->
      <div v-if="overdueTasks.length > 0" class="my-week__day my-week__day--overdue">
        <div class="my-week__day-header">
          <span class="my-week__day-label">Overdue</span>
          <span class="my-week__day-count">{{ overdueTasks.length }}</span>
        </div>
        <div v-for="task in overdueTasks" :key="'o-' + task.id" class="my-week__task my-week__task--clickable" :class="{ 'my-week__task--done': task.done }" @click="$emit('select-task', task.id)">
          <span class="my-week__task-dot my-week__task-dot--overdue"></span>
          <span class="my-week__task-title">{{ task.title }}</span>
          <span v-if="task.projectName" class="my-week__task-project my-week__task-project--clickable" @click.stop="$emit('filter-project', task.projectId)">{{ task.projectName }}</span>
          <span v-for="label in (task.labels || [])" :key="label.id" class="my-week__task-label" :style="{ background: '#' + label.color }" :title="label.title"></span>
          <span class="my-week__task-due">{{ shortDate(task.duedate) }}</span>
        </div>
      </div>

      <!-- Week days -->
      <div v-for="day in weekDays" :key="day.date" class="my-week__day" :class="{ 'my-week__day--today': day.isToday }">
        <div class="my-week__day-header">
          <span class="my-week__day-label">
            {{ day.fullLabel }}, {{ day.dateLabel }}
            <span v-if="day.isToday" class="my-week__today-tag">Today</span>
          </span>
          <span v-if="dayTasks(day.date).length" class="my-week__day-count">{{ dayTasks(day.date).length }}</span>
        </div>
        <div v-if="dayTasks(day.date).length === 0" class="my-week__empty-day">No tasks</div>
        <div v-for="task in dayTasks(day.date)" :key="'d-' + task.id" class="my-week__task my-week__task--clickable" :class="{ 'my-week__task--done': task.done }" @click="$emit('select-task', task.id)">
          <span class="my-week__task-dot" :class="{ 'my-week__task-dot--done': task.done }"></span>
          <span class="my-week__task-title">{{ task.title }}</span>
          <span v-if="task.projectName" class="my-week__task-project my-week__task-project--clickable" @click.stop="$emit('filter-project', task.projectId)">{{ task.projectName }}</span>
          <span v-for="label in (task.labels || [])" :key="label.id" class="my-week__task-label" :style="{ background: '#' + label.color }" :title="label.title"></span>
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
  },
  data: function () {
    return { collapsed: false, weekOffset: 0 };
  },
  computed: {
    today: function () {
      var d = new Date();
      return this.toIso(d);
    },
    mondayDate: function () {
      var d = new Date();
      var day = d.getDay();
      var diff = day === 0 ? -6 : 1 - day;
      var mon = new Date(d);
      mon.setDate(mon.getDate() + diff + (this.weekOffset * 7));
      mon.setHours(0, 0, 0, 0);
      return mon;
    },
    weekLabel: function () {
      var mon = this.mondayDate;
      var sun = new Date(mon);
      sun.setDate(sun.getDate() + 6);
      var months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
      var start = months[mon.getMonth()] + " " + mon.getDate();
      var end = months[sun.getMonth()] + " " + sun.getDate() + ", " + sun.getFullYear();
      return start + " – " + end;
    },
    isCurrentWeek: function () {
      return this.weekOffset === 0;
    },
    weekDays: function () {
      var days = [];
      var dayNames = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
      var months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
      for (var i = 0; i < 7; i++) {
        var d = new Date(this.mondayDate);
        d.setDate(d.getDate() + i);
        var iso = this.toIso(d);
        days.push({
          fullLabel: dayNames[i],
          dateLabel: months[d.getMonth()] + " " + d.getDate(),
          date: iso,
          isToday: iso === this.today,
        });
      }
      return days;
    },
    tasksByDate: function () {
      var map = {};
      this.tasks.forEach(function (task) {
        if (!task.duedate) return;
        var iso = task.duedate.substring(0, 10);
        if (!map[iso]) map[iso] = [];
        map[iso].push(task);
      });
      return map;
    },
    overdueTasks: function () {
      var today = this.today;
      var monday = this.weekDays.length ? this.weekDays[0].date : today;
      return this.tasks.filter(function (t) {
        if (!t.duedate || t.done) return false;
        var iso = t.duedate.substring(0, 10);
        return iso < monday;
      });
    },
    sundayDate: function () {
      return this.weekDays.length ? this.weekDays[6].date : this.today;
    },
    totalThisWeek: function () {
      var self = this;
      var monday = this.weekDays.length ? this.weekDays[0].date : this.today;
      var sunday = this.sundayDate;
      var count = 0;
      this.tasks.forEach(function (t) {
        if (!t.duedate) return;
        var iso = t.duedate.substring(0, 10);
        if (iso >= monday && iso <= sunday) count++;
      });
      return count + this.overdueTasks.length;
    },
  },
  methods: {
    dayTasks: function (dateIso) {
      return this.tasksByDate[dateIso] || [];
    },
    toIso: function (d) {
      var y = d.getFullYear();
      var m = d.getMonth() + 1;
      var day = d.getDate();
      return y + "-" + (m < 10 ? "0" + m : m) + "-" + (day < 10 ? "0" + day : day);
    },
    prevWeek: function () { this.weekOffset--; },
    nextWeek: function () { this.weekOffset++; },
    goToCurrentWeek: function () { this.weekOffset = 0; },
    shortDate: function (iso) {
      if (!iso) return "";
      var d = new Date(iso.substring(0, 10) + "T00:00:00");
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
.my-week__header:hover { background: #fafbfd; }
.my-week__title {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  margin: 0; padding: 0; border: none;
  display: flex;
  align-items: center;
  gap: 8px;
}
.my-week__title svg { color: #0ea5e9; }
.my-week__badge {
  font-size: 11px;
  font-weight: 600;
  background: #e0f2fe;
  color: #0369a1;
  padding: 2px 8px;
  border-radius: 8px;
}
.my-week__chevron {
  color: var(--color-text-muted, #9ca3af);
  transition: transform 0.3s;
}
.my-week__chevron--rotated { transform: rotate(180deg); }
.my-week__body {
  padding: 0 var(--spacing-lg, 24px) var(--spacing-lg, 24px);
}

/* Week navigation */
.my-week__nav {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 0 0 12px;
  margin-bottom: 4px;
  border-bottom: 1px solid var(--color-border, #e5e7eb);
}
.my-week__nav-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  background: none;
  border: 1px solid var(--color-border, #e5e7eb);
  color: var(--color-text-secondary, #6b7280);
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.my-week__nav-btn:hover {
  background: #f3f4f6;
  color: var(--color-text-primary, #1a1a2e);
}
.my-week__nav-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-primary, #1a1a2e);
}
.my-week__nav-today {
  margin-left: auto;
  font-size: 11px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 6px;
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
  cursor: pointer;
  transition: background 0.15s;
}
.my-week__nav-today:hover {
  background: #dbeafe;
}

/* Day group */
.my-week__day {
  border-radius: 10px;
  margin-bottom: 6px;
  overflow: hidden;
}
.my-week__day-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  background: #f9fafb;
  border-radius: 8px 8px 0 0;
}
.my-week__day:last-child { margin-bottom: 0; }
.my-week__day-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
  display: flex;
  align-items: center;
  gap: 6px;
}
.my-week__day-count {
  font-size: 11px;
  font-weight: 700;
  background: #e5e7eb;
  color: #374151;
  padding: 1px 7px;
  border-radius: 8px;
  min-width: 16px;
  text-align: center;
}

/* Today highlight */
.my-week__day--today .my-week__day-header {
  background: #eff6ff;
}
.my-week__day--today .my-week__day-label {
  color: #1d4ed8;
  font-weight: 700;
}
.my-week__day--today .my-week__day-count {
  background: #3b82f6;
  color: #fff;
}
.my-week__today-tag {
  font-size: 10px;
  font-weight: 700;
  background: #3b82f6;
  color: #fff;
  padding: 1px 6px;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

/* Overdue group */
.my-week__day--overdue .my-week__day-header {
  background: #fef2f2;
}
.my-week__day--overdue .my-week__day-label {
  color: #b91c1c;
  font-weight: 700;
}
.my-week__day--overdue .my-week__day-count {
  background: #ef4444;
  color: #fff;
}

/* Empty day */
.my-week__empty-day {
  padding: 6px 12px 8px;
  font-size: 11px;
  color: var(--color-text-muted, #9ca3af);
  font-style: italic;
}

/* Task row */
.my-week__task {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  transition: background 0.1s;
}
.my-week__task:hover { background: #f9fafb; }
.my-week__task--clickable { cursor: pointer; }
.my-week__task--done { opacity: 0.5; }
.my-week__task--done .my-week__task-title {
  text-decoration: line-through;
}

.my-week__task-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #3b82f6;
  flex-shrink: 0;
}
.my-week__task-dot--done { background: #22c55e; }
.my-week__task-dot--overdue { background: #ef4444; }

.my-week__task-title {
  font-size: 13px;
  font-weight: 500;
  color: var(--color-text-primary, #1a1a2e);
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 0;
}
.my-week__task-project {
  font-size: 10px;
  font-weight: 600;
  background: #f3f4f6;
  color: #6b7280;
  padding: 1px 6px;
  border-radius: 4px;
  white-space: nowrap;
  flex-shrink: 0;
}
.my-week__task-project--clickable {
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.my-week__task-project--clickable:hover {
  background: #e8f0fe;
  color: #1e4a8a;
}
.my-week__task-label {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.my-week__task-due {
  font-size: 10px;
  color: #ef4444;
  font-weight: 600;
  white-space: nowrap;
  flex-shrink: 0;
}
</style>
