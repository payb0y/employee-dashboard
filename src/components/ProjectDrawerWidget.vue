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

        <!-- Timeline for this project -->
        <div v-if="projectTimeline.length > 0" class="project-drawer__section">
          <h5 class="project-drawer__section-title">Timeline</h5>
          <div v-for="item in projectTimeline" :key="item.id" class="project-drawer__tl-item">
            <span class="project-drawer__tl-dot" :style="{ background: item.color || '#94a3b8' }"></span>
            <div class="project-drawer__tl-content">
              <span class="project-drawer__tl-label">{{ item.label }}</span>
              <span class="project-drawer__tl-badge" :class="'project-drawer__tl-badge--' + item.itemType">{{ item.itemType }}</span>
              <span class="project-drawer__tl-date">{{ formatRange(item) }}</span>
            </div>
          </div>
        </div>

        <!-- Activity feed -->
        <div v-if="projectEvents.length > 0" class="project-drawer__section">
          <h5 class="project-drawer__section-title">Activity</h5>
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
  },
  data: function () {
    return { collapsed: false };
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
  },
  watch: {
    project: function (val) {
      if (val && this.collapsed) {
        this.collapsed = false;
      }
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
.project-drawer__header:hover {
  background: #fafbfd;
}
.project-drawer__title {
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
.project-drawer__title svg {
  color: #c878c8;
}
.project-drawer__chevron {
  color: var(--color-text-muted, #9ca3af);
  transition: transform 0.3s;
}
.project-drawer__chevron--rotated {
  transform: rotate(180deg);
}
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
  margin: 0;
  padding: 0;
  border: none;
  flex: 1;
}
.project-drawer__project-status {
  font-size: 10px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 6px;
  flex-shrink: 0;
}
.project-drawer__project-status--active {
  background: #dcfce7;
  color: #166534;
}
.project-drawer__project-status--completed {
  background: #e0f2fe;
  color: #0369a1;
}
.project-drawer__project-status--archived {
  background: #f3f4f6;
  color: #6b7280;
}

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
.project-drawer__link:hover {
  background: #d4e5fb;
}

/* Section titles */
.project-drawer__section {
  margin-top: 16px;
}
.project-drawer__section-title {
  font-size: 12px;
  font-weight: 700;
  color: var(--color-text-secondary, #6b7280);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin: 0 0 10px 0;
  padding: 0;
  border: none;
}

/* Timeline items */
.project-drawer__tl-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 6px 0;
}
.project-drawer__tl-item + .project-drawer__tl-item {
  border-top: 1px solid #f9fafb;
}
.project-drawer__tl-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 4px;
}
.project-drawer__tl-content {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  flex: 1;
}
.project-drawer__tl-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-primary, #1a1a2e);
}
.project-drawer__tl-badge {
  font-size: 9px;
  font-weight: 600;
  padding: 1px 5px;
  border-radius: 4px;
  text-transform: capitalize;
}
.project-drawer__tl-badge--phase {
  background: #e0f2fe;
  color: #0369a1;
}
.project-drawer__tl-badge--milestone {
  background: #fef3c7;
  color: #92400e;
}
.project-drawer__tl-date {
  font-size: 11px;
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
.project-drawer__event + .project-drawer__event {
  border-top: 1px solid #f9fafb;
}
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
.project-drawer__event-text strong {
  color: var(--color-text-primary, #1a1a2e);
  font-weight: 600;
}
.project-drawer__event-time {
  font-size: 10px;
  color: var(--color-text-muted, #9ca3af);
}
</style>
