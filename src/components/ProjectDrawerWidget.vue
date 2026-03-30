<template>
  <section class="project-drawer">
    <div class="project-drawer__header" @click="collapsed = !collapsed">
      <h3 class="project-drawer__title">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        Project Detail
      </h3>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="project-drawer__chevron" :class="{ 'project-drawer__chevron--rotated': collapsed }">
        <polyline points="18 15 12 9 6 15" />
      </svg>
    </div>
    <div v-show="!collapsed" class="project-drawer__body">
      <p v-if="!project" class="project-drawer__placeholder">
        Select a project from My Projects to view details here.
      </p>

      <template v-else>
        <!-- Project header -->
        <div class="project-drawer__project-header">
          <h4 class="project-drawer__project-name">{{ project.name }}</h4>
          <span class="project-drawer__project-status" :class="'project-drawer__project-status--' + statusKey(project.status)">{{ statusLabel(project.status) }}</span>
        </div>

        <!-- Meta grid -->
        <div class="project-drawer__meta-grid">
          <div v-if="project.number" class="project-drawer__meta-item">
            <span class="project-drawer__meta-label">Project Number</span>
            <span class="project-drawer__meta-value">#{{ project.number }}</span>
          </div>
          <div v-if="project.clientName" class="project-drawer__meta-item">
            <span class="project-drawer__meta-label">Client</span>
            <span class="project-drawer__meta-value">{{ project.clientName }}</span>
          </div>
          <div v-if="project.createdAt" class="project-drawer__meta-item">
            <span class="project-drawer__meta-label">Created</span>
            <span class="project-drawer__meta-value">{{ formatDate(project.createdAt) }}</span>
          </div>
        </div>

        <!-- Description -->
        <div v-if="project.description" class="project-drawer__section">
          <h5 class="project-drawer__section-title">Description</h5>
          <p class="project-drawer__description">{{ project.description }}</p>
        </div>

        <!-- Links -->
        <div class="project-drawer__links">
          <a v-if="project.folderId" :href="folderUrl" class="project-drawer__link" target="_blank" rel="noopener">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            Open Shared Folder
          </a>
          <a v-if="project.whiteBoardId" :href="whiteboardUrl" class="project-drawer__link" target="_blank" rel="noopener">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
            Open Whiteboard
          </a>
        </div>

        <!-- Tab navigation -->
        <div class="project-drawer__tabs">
          <button v-for="tab in tabs" :key="tab.key"
            class="project-drawer__tab"
            :class="{ 'project-drawer__tab--active': activeTab === tab.key }"
            @click="activeTab = tab.key">
            {{ tab.label }}
            <span v-if="tab.count !== undefined" class="project-drawer__tab-count">{{ tab.count }}</span>
          </button>
        </div>

        <!-- Tab: Timeline -->
        <div v-if="activeTab === 'timeline'" class="project-drawer__tab-body">
          <div v-if="projectTimeline.length === 0" class="project-drawer__empty-tab">No timeline items.</div>
          <template v-else>
            <div class="pd-gantt">
              <!-- Date axis -->
              <div class="pd-gantt__axis">
                <div class="pd-gantt__label-col"></div>
                <div class="pd-gantt__bar-col">
                  <div class="pd-gantt__ticks">
                    <span v-for="(tick, i) in drawerDateTicks" :key="i" class="pd-gantt__tick" :style="{ left: tick.pct + '%' }">{{ tick.label }}</span>
                  </div>
                </div>
              </div>
              <!-- Today line (full-height) -->
              <div v-if="drawerTodayPct !== null" class="pd-gantt__today-line" :style="{ left: 'calc(120px + ' + (drawerTodayPct / 100).toFixed(4) + ' * (100% - 120px))' }">
                <span class="pd-gantt__today-pill">Today</span>
              </div>
              <!-- Rows -->
              <div v-for="item in projectTimeline" :key="item.id" class="pd-gantt__row">
                <div class="pd-gantt__label-col">
                  <span class="pd-gantt__item-label" :title="item.label">{{ item.label }}</span>
                  <span class="pd-gantt__item-type" :class="'pd-gantt__item-type--' + item.itemType">{{ item.itemType }}</span>
                </div>
                <div class="pd-gantt__bar-col">
                  <div v-if="item.itemType === 'phase'" class="pd-gantt__bar" :style="drawerBarStyle(item)" :title="item.label + ': ' + formatRange(item)">
                    <span class="pd-gantt__bar-text">{{ formatRange(item) }}</span>
                  </div>
                  <div v-else class="pd-gantt__milestone" :style="drawerMilestoneStyle(item)" :title="item.label + ': ' + shortDate(item.startDate || item.endDate)">
                    <svg width="14" height="14" viewBox="0 0 14 14"><polygon points="7,0 14,7 7,14 0,7" :fill="item.color || '#f59e0b'" /></svg>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- Tab: Notes -->
        <div v-if="activeTab === 'notes'" class="project-drawer__tab-body">
          <div v-if="projectNotes.length === 0" class="project-drawer__empty-tab">No notes for this project.</div>

          <div v-for="note in projectNotes" :key="note.id" class="project-drawer__note-card">
            <div class="project-drawer__note-header">
              <strong class="project-drawer__note-title">{{ note.title }}</strong>
            </div>
            <p v-if="note.content" class="project-drawer__note-content">{{ note.content }}</p>
            <span class="project-drawer__note-meta">{{ note.userId }} &middot; {{ timeAgo(note.updatedAt || note.createdAt) }}</span>
          </div>
        </div>

        <!-- Tab: Activity -->
        <div v-if="activeTab === 'activity'" class="project-drawer__tab-body">
          <div v-if="projectEvents.length === 0" class="project-drawer__empty-tab">No activity recorded.</div>
          <div class="project-drawer__activity">
            <div v-for="event in projectEvents" :key="event.id" class="project-drawer__event">
              <div class="project-drawer__event-dot"></div>
              <div class="project-drawer__event-body">
                <span class="project-drawer__event-text">
                  <strong>{{ event.actorName || event.actorUid }}</strong>
                  {{ eventDescription(event) }}
                </span>
                <span class="project-drawer__event-time">{{ timeAgo(event.occurredAt) }}</span>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </section>
</template>

<script>
export default {
  name: "ProjectDrawerWidget",
  props: {
    project: { type: Object, default: null },
    timeline: { type: Array, default: function () { return []; } },
    activityEvents: { type: Array, default: function () { return []; } },
    notes: { type: Array, default: function () { return []; } },
  },
  data: function () {
    return {
      collapsed: false,
      activeTab: "timeline",
    };
  },
  computed: {
    folderUrl: function () {
      if (!this.project || !this.project.folderPath) return "#";
      return OC.generateUrl("/apps/files/?dir=" + encodeURIComponent(this.project.folderPath));
    },
    whiteboardUrl: function () {
      if (!this.project || !this.project.whiteBoardId) return "#";
      return OC.generateUrl("/apps/whiteboard/" + this.project.whiteBoardId);
    },
    projectTimeline: function () {
      if (!this.project) return [];
      var pid = this.project.id;
      return this.timeline.filter(function (t) { return t.projectId === pid; });
    },
    projectEvents: function () {
      if (!this.project) return [];
      var pid = this.project.id;
      return this.activityEvents.filter(function (e) { return e.projectId === pid; });
    },
    projectNotes: function () {
      if (!this.project) return [];
      var pid = this.project.id;
      return this.notes.filter(function (n) { return n.projectId === pid; });
    },
    tabs: function () {
      return [
        { key: "timeline", label: "Timeline", count: this.projectTimeline.length },
        { key: "notes", label: "Notes", count: this.projectNotes.length },
        { key: "activity", label: "Activity", count: this.projectEvents.length },
      ];
    },
    drawerDateRange: function () {
      var min = null;
      var max = null;
      this.projectTimeline.forEach(function (item) {
        var dates = [item.startDate, item.endDate].filter(Boolean);
        dates.forEach(function (d) {
          var ts = new Date(d + "T00:00:00").getTime();
          if (min === null || ts < min) min = ts;
          if (max === null || ts > max) max = ts;
        });
      });
      if (min === null) return { min: 0, max: 0, span: 0 };
      var pad = 4 * 86400000;
      min -= pad;
      max += pad;
      return { min: min, max: max, span: max - min };
    },
    drawerTodayPct: function () {
      if (!this.drawerDateRange.span) return null;
      var today = new Date();
      today.setHours(0, 0, 0, 0);
      var ts = today.getTime();
      if (ts < this.drawerDateRange.min || ts > this.drawerDateRange.max) return null;
      return ((ts - this.drawerDateRange.min) / this.drawerDateRange.span) * 100;
    },
    drawerDateTicks: function () {
      var range = this.drawerDateRange;
      if (!range.span) return [];
      var ticks = [];
      var count = 5;
      var months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
      for (var i = 0; i <= count; i++) {
        var ts = range.min + (range.span * i / count);
        var d = new Date(ts);
        ticks.push({ pct: (i / count) * 100, label: months[d.getMonth()] + " " + d.getDate() });
      }
      return ticks;
    },
  },
  watch: {
    project: function (val) {
      if (val && this.collapsed) {
        this.collapsed = false;
      }
      this.activeTab = "timeline";
    },
  },
  methods: {
    statusLabel: function (status) {
      var map = { 0: "Active", 1: "Completed", 2: "Archived" };
      return map[status] || "Active";
    },
    statusKey: function (status) {
      var map = { 0: "active", 1: "completed", 2: "archived" };
      return map[status] || "active";
    },
    formatDate: function (iso) {
      if (!iso) return "\u2014";
      var d = new Date(iso);
      var months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
      return months[d.getMonth()] + " " + d.getDate() + ", " + d.getFullYear();
    },
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
    eventDescription: function (event) {
      var typeMap = {
        project_created: "created the project",
        member_added: "added a member",
        member_removed: "removed a member",
        timeline_item_created: "added a timeline item",
        timeline_item_updated: "updated a timeline item",
        status_changed: "changed the status",
        note_added: "added a note",
        file_uploaded: "uploaded a file",
        board_linked: "linked a Deck board",
        folder_linked: "linked a shared folder",
        whiteboard_linked: "linked a whiteboard",
      };
      return typeMap[event.eventType] || event.eventType.replace(/_/g, " ");
    },
    drawerDatePct: function (isoDate) {
      if (!isoDate || !this.drawerDateRange.span) return 0;
      var ts = new Date(isoDate + "T00:00:00").getTime();
      return ((ts - this.drawerDateRange.min) / this.drawerDateRange.span) * 100;
    },
    drawerBarStyle: function (item) {
      var left = this.drawerDatePct(item.startDate || item.endDate);
      var right = this.drawerDatePct(item.endDate || item.startDate);
      var width = Math.max(right - left, 1);
      return { left: left + "%", width: width + "%", background: item.color || "#3b82f6" };
    },
    drawerMilestoneStyle: function (item) {
      var pct = this.drawerDatePct(item.startDate || item.endDate);
      return { left: "calc(" + pct + "% - 7px)" };
    },
    timeAgo: function (iso) {
      if (!iso) return "";
      var now = new Date();
      var then = new Date(iso);
      var diff = Math.floor((now - then) / 1000);
      if (diff < 60) return "just now";
      if (diff < 3600) return Math.floor(diff / 60) + "m ago";
      if (diff < 86400) return Math.floor(diff / 3600) + "h ago";
      var days = Math.floor(diff / 86400);
      if (days < 30) return days + "d ago";
      return this.formatDate(iso);
    },
  },
};
</script>

<style scoped>
.project-drawer {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  margin-bottom: var(--spacing-xl, 32px);
  overflow: hidden;
}
.project-drawer__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-md, 16px) var(--spacing-lg, 24px);
  cursor: pointer;
  user-select: none;
  transition: background 0.15s;
}
.project-drawer__header:hover { background: #fafbfd; }
.project-drawer__title {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  margin: 0; padding: 0; border: none;
  display: flex;
  align-items: center;
  gap: 8px;
}
.project-drawer__title svg { color: #c878c8; }
.project-drawer__chevron {
  color: var(--color-text-muted, #9ca3af);
  transition: transform 0.3s;
}
.project-drawer__chevron--rotated { transform: rotate(180deg); }
.project-drawer__body {
  padding: 0 var(--spacing-lg, 24px) var(--spacing-lg, 24px);
}
.project-drawer__placeholder {
  text-align: center;
  padding: 20px 0;
  font-size: 13px;
  color: var(--color-text-muted, #9ca3af);
  font-style: italic;
}

/* Project header */
.project-drawer__project-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
}
.project-drawer__project-name {
  font-size: 16px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  margin: 0; padding: 0; border: none;
  flex: 1;
}
.project-drawer__project-status {
  font-size: 10px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 6px;
  flex-shrink: 0;
}
.project-drawer__project-status--active { background: #dcfce7; color: #166534; }
.project-drawer__project-status--completed { background: #e0f2fe; color: #0369a1; }
.project-drawer__project-status--archived { background: #f3f4f6; color: #6b7280; }

/* Meta grid */
.project-drawer__meta-grid {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.project-drawer__meta-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.project-drawer__meta-label {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-muted, #9ca3af);
}
.project-drawer__meta-value {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-primary, #1a1a2e);
}

/* Description */
.project-drawer__description {
  font-size: 13px;
  color: var(--color-text-secondary, #6b7280);
  line-height: 1.6;
  margin: 0;
  white-space: pre-wrap;
}

/* Links */
.project-drawer__links {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.project-drawer__link {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  font-weight: 600;
  color: #4a90d9;
  text-decoration: none;
  padding: 6px 12px;
  border-radius: 8px;
  background: #e8f0fe;
  transition: background 0.15s;
}
.project-drawer__link:hover { background: #d4e5fb; }

/* Tabs */
.project-drawer__tabs {
  display: flex;
  gap: 2px;
  border-bottom: 2px solid #f3f4f6;
  margin-bottom: 14px;
}
.project-drawer__tab {
  padding: 8px 14px;
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
  background: none;
  border: none;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: color 0.15s, border-color 0.15s;
  display: flex;
  align-items: center;
  gap: 5px;
}
.project-drawer__tab:hover { color: var(--color-text-primary, #1a1a2e); }
.project-drawer__tab--active {
  color: #4a90d9;
  border-bottom-color: #4a90d9;
}
.project-drawer__tab-count {
  font-size: 10px;
  font-weight: 700;
  background: #f3f4f6;
  padding: 1px 5px;
  border-radius: 6px;
}
.project-drawer__tab--active .project-drawer__tab-count {
  background: #e8f0fe;
  color: #4a90d9;
}
.project-drawer__tab-body {
  min-height: 60px;
}
.project-drawer__empty-tab {
  text-align: center;
  padding: 20px 0;
  font-size: 13px;
  color: var(--color-text-muted, #9ca3af);
  font-style: italic;
}

/* Section titles */
.project-drawer__section {
  margin-bottom: 14px;
}
.project-drawer__section-title {
  font-size: 12px;
  font-weight: 700;
  color: var(--color-text-secondary, #6b7280);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin: 0 0 8px 0;
  padding: 0;
  border: none;
}

/* Drawer Gantt chart */
.pd-gantt {
  position: relative;
  overflow: hidden;
  min-width: 0;
  margin-bottom: 4px;
}
.pd-gantt__axis,
.pd-gantt__row {
  display: flex;
  align-items: center;
  min-height: 28px;
}
.pd-gantt__label-col {
  width: 120px;
  min-width: 120px;
  flex-shrink: 0;
  padding-right: 10px;
  display: flex;
  align-items: center;
  gap: 5px;
  overflow: hidden;
}
.pd-gantt__bar-col {
  flex: 1;
  position: relative;
  min-width: 200px;
  height: 100%;
}
.pd-gantt__ticks {
  position: relative;
  height: 20px;
  border-bottom: 1px solid #e5e7eb;
}
.pd-gantt__tick {
  position: absolute;
  top: 0;
  transform: translateX(-50%);
  font-size: 9px;
  color: var(--color-text-muted, #9ca3af);
  white-space: nowrap;
}
.pd-gantt__today-line {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 2px;
  background: #ef4444;
  box-shadow: 0 0 8px rgba(239, 68, 68, 0.45);
  z-index: 3;
  pointer-events: none;
}
.pd-gantt__today-pill {
  position: absolute;
  top: 3px;
  left: 50%;
  transform: translateX(-50%);
  background: #ef4444;
  color: #fff;
  font-size: 9px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  white-space: nowrap;
  letter-spacing: 0.05em;
  box-shadow: 0 1px 4px rgba(239, 68, 68, 0.4);
}
.pd-gantt__row {
  min-height: 30px;
  padding: 2px 0;
}
.pd-gantt__row:hover { background: #fafbfd; }
.pd-gantt__item-label {
  font-size: 11px;
  font-weight: 500;
  color: var(--color-text-primary, #1a1a2e);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 78px;
}
.pd-gantt__item-type {
  font-size: 9px;
  font-weight: 600;
  padding: 1px 4px;
  border-radius: 3px;
  text-transform: capitalize;
  flex-shrink: 0;
}
.pd-gantt__item-type--phase { background: #e0f2fe; color: #0369a1; }
.pd-gantt__item-type--milestone { background: #fef3c7; color: #92400e; }
.pd-gantt__bar {
  position: absolute;
  top: 4px;
  height: 20px;
  border-radius: 5px;
  min-width: 4px;
  display: flex;
  align-items: center;
  padding: 0 5px;
  overflow: hidden;
  opacity: 0.88;
  transition: opacity 0.15s;
}
.pd-gantt__row:hover .pd-gantt__bar { opacity: 1; }
.pd-gantt__bar-text {
  font-size: 9px;
  font-weight: 600;
  color: #fff;
  white-space: nowrap;
  text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}
.pd-gantt__milestone {
  position: absolute;
  top: 6px;
}

/* Notes */
.project-drawer__note-card {
  padding: 12px;
  border: 1px solid #f3f4f6;
  border-radius: 10px;
  margin-bottom: 8px;
}
.project-drawer__note-header {
  margin-bottom: 4px;
}
.project-drawer__note-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
}
.project-drawer__note-content {
  font-size: 12px;
  color: var(--color-text-secondary, #6b7280);
  line-height: 1.6;
  margin: 0 0 4px 0;
  white-space: pre-wrap;
}
.project-drawer__note-meta {
  font-size: 10px;
  color: var(--color-text-muted, #9ca3af);
}

/* Activity feed */
.project-drawer__activity {
  display: flex;
  flex-direction: column;
}
.project-drawer__event {
  display: flex;
  gap: 10px;
  padding: 8px 0;
  position: relative;
}
.project-drawer__event + .project-drawer__event { border-top: 1px solid #f9fafb; }
.project-drawer__event-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #d1d5db;
  flex-shrink: 0;
  margin-top: 6px;
}
.project-drawer__event-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.project-drawer__event-text {
  font-size: 12px;
  color: var(--color-text-secondary, #6b7280);
  line-height: 1.4;
}
.project-drawer__event-text strong { color: var(--color-text-primary, #1a1a2e); font-weight: 600; }
.project-drawer__event-time {
  font-size: 10px;
  color: var(--color-text-muted, #9ca3af);
}
</style>
