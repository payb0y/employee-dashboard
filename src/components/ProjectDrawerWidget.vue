<template>
  <section class="iz-panel iz-panel--list project-drawer">
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
        <div class="iz-tabs project-drawer__tabs">
          <button v-for="tab in tabs" :key="tab.key"
            class="iz-tab"
            :class="{ 'iz-tab--active': activeTab === tab.key }"
            @click="activeTab = tab.key">
            {{ tab.label }}
            <span v-if="tab.count !== undefined" class="iz-tab__count">{{ tab.count }}</span>
          </button>
        </div>

        <!-- Tab: Timeline — projectcreatoraio's plan for this project: the
             Overview's timeline figures over the Planning tab's Gantt
             (ProjectTimeline.vue, vendored from adminpage). -->
        <div v-if="activeTab === 'timeline'" class="project-drawer__tab-body">
          <ProjectTimeline :src="timelineSrc" :items-src="timelineItemsSrc" :planning-url="planningUrl" />
        </div>

        <!-- Tab: Notes -->
        <div v-if="activeTab === 'notes'" class="project-drawer__tab-body">
          <div v-if="projectNotes.length === 0" class="iz-empty project-drawer__empty-tab">No notes for this project.</div>

          <div v-for="note in projectNotes" :key="note.id" class="project-drawer__note-card">
            <div class="project-drawer__note-header">
              <strong class="project-drawer__note-title">{{ note.title }}</strong>
            </div>
            <p v-if="note.content" class="project-drawer__note-content">{{ note.content }}</p>
            <span class="project-drawer__note-meta">{{ note.userId }} &middot; {{ timeAgo(note.updatedAt || note.createdAt) }}</span>
          </div>
        </div>

        <!-- Tab: Activity — projectcreatoraio's activity, a page at a time,
             grouped by day (ProjectActivityFeed.vue). -->
        <div v-if="activeTab === 'activity'" class="project-drawer__tab-body">
          <ProjectActivityFeed :project-id="project.id" />
        </div>
      </template>
    </div>
  </section>
</template>

<script>
import { generateUrl } from "@nextcloud/router";
import ProjectTimeline from "./ProjectTimeline.vue";
import ProjectActivityFeed from "./ProjectActivityFeed.vue";

export default {
  name: "ProjectDrawerWidget",
  components: { ProjectTimeline, ProjectActivityFeed },
  props: {
    project: { type: Object, default: null },
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
    timelineSrc: function () {
      if (!this.project) return null;
      return generateUrl("/apps/projectcreatoraio/api/v1/projects/{id}/timeline/summary", { id: this.project.id });
    },
    timelineItemsSrc: function () {
      if (!this.project) return null;
      return generateUrl("/apps/projectcreatoraio/api/v1/projects/{id}/timeline", { id: this.project.id });
    },
    planningUrl: function () {
      if (!this.project) return null;
      return generateUrl("/apps/projectcreatoraio/new/projects/{id}?tab=planning", { id: this.project.id });
    },
    projectNotes: function () {
      if (!this.project) return [];
      var pid = this.project.id;
      return this.notes.filter(function (n) { return n.projectId === pid; });
    },
    tabs: function () {
      return [
        { key: "timeline", label: "Timeline" },
        { key: "notes", label: "Notes", count: this.projectNotes.length },
        { key: "activity", label: "Activity" },
      ];
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
.project-drawer {  margin-bottom: var(--spacing-xl, 32px);
  overflow: hidden;
}
.project-drawer__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  /* 20px sides, matching --iz-pad-panel, so this panel's content lines up with
     the ones that let .iz-panel do their padding. */
  padding: var(--spacing-md, 16px) 20px;
  cursor: pointer;
  user-select: none;
  transition: background 0.15s;
}
.project-drawer__header:hover { background: var(--bg-subtle); }
.project-drawer__title {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  margin: 0; padding: 0; border: none;
  display: flex;
  align-items: center;
  gap: 8px;
}
.project-drawer__title svg { color: var(--accent); }
.project-drawer__chevron {
  color: var(--color-text-muted, #9ca3af);
  transition: transform 0.3s;
}
.project-drawer__chevron--rotated { transform: rotate(180deg); }
.project-drawer__body {
  /* padding-top rather than none: the header's own bottom padding alone left
     the content sitting on the header, which reads as one block — especially
     where the first thing below is a bordered box. */
  padding: 4px 20px 20px;
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
.project-drawer__project-status--active { background: var(--color-badge-success-bg); color: var(--color-badge-success-text); }
.project-drawer__project-status--completed { background: var(--accent-bg); color: var(--accent-on-bg); }
.project-drawer__project-status--archived { background: var(--bg-subtle); color: var(--color-text-secondary); }

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
  color: var(--accent);
  text-decoration: none;
  padding: 6px 12px;
  border-radius: 8px;
  background: var(--accent-bg);
  transition: background 0.15s;
}
.project-drawer__link:hover { background: var(--accent-bg); }

/* Tabs */
/* Bar, tab, active underline and count pill all come from .iz-tabs / .iz-tab
   / .iz-tab__count — including the dark-mode contrast lift on the active
   label that the theme documents. Only the spacing below the bar is local. */
.project-drawer__tabs {
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
  color: var(--accent);
  border-bottom-color: var(--accent);
}
.project-drawer__tab-count {
  font-size: 10px;
  font-weight: 700;
  background: var(--bg-subtle);
  padding: 1px 5px;
  border-radius: 6px;
}
.project-drawer__tab--active .project-drawer__tab-count {
  background: var(--accent-bg);
  color: var(--accent);
}
.project-drawer__tab-body {
  min-height: 60px;
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

/* Notes */
.project-drawer__note-card {
  padding: 12px;
  border: 1px solid var(--bg-subtle);
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
</style>
