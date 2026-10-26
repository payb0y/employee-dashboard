<template>
  <!-- Every project's plan on one chart, as projectcreatoraio's Planning tab
       draws one: each project's System Planning row and its phases, on a
       shared date range (portfolioGanttOf in src/lib/projectTimeline.mjs).
       The cards stay in the Project Detail drawer, one click away. -->
  <section class="iz-panel iz-panel--list gantt-widget">
    <div class="gantt-widget__header" @click="collapsed = !collapsed">
      <h3 class="gantt-widget__title">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="4" y1="9" x2="20" y2="9" /><line x1="4" y1="15" x2="20" y2="15" />
          <line x1="10" y1="3" x2="8" y2="21" /><line x1="16" y1="3" x2="14" y2="21" />
        </svg>
        Project Timeline
        <span v-if="gantt.hasContent" class="gantt-widget__count">{{ gantt.projects.length }} {{ gantt.projects.length === 1 ? "project" : "projects" }}</span>
      </h3>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="gantt-widget__chevron" :class="{ 'gantt-widget__chevron--rotated': collapsed }">
        <polyline points="18 15 12 9 6 15" />
      </svg>
    </div>

    <div v-show="!collapsed" class="gantt-widget__body">
      <div v-if="loading && !loaded" class="iz-empty">Loading the project timelines…</div>
      <div v-else-if="error" class="iz-empty gantt-widget__state">
        <span>{{ error }}</span>
        <button type="button" class="iz-btn iz-btn--sm" @click="load">Try again</button>
      </div>
      <div v-else-if="!gantt.hasContent" class="iz-empty">No project timelines yet.</div>

      <div v-else class="ptl__planner">
        <div class="ptl__toolbar">
          <span v-if="anyDelays" class="ptl__delays" title="Schedule slippage detected">
            <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24"><path fill="currentColor" d="M13,13H11V7H13M13,17H11V15H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z" /></svg>
            Delays Detected
          </span>
          <div class="ptl__controls">
            <div class="ptl__control-group">
              <button type="button" class="ptl__control" aria-label="Previous" @click="scrollBy(-30 * dayWidth)">
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M15.41,16.58L10.83,12L15.41,7.41L14,6L8,12L14,18L15.41,16.58Z" /></svg>
              </button>
              <button type="button" class="ptl__control ptl__control--today" @click="navigateToday">Today</button>
              <button type="button" class="ptl__control" aria-label="Next" @click="scrollBy(30 * dayWidth)">
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M8.59,16.58L13.17,12L8.59,7.41L10,6L16,12L10,18L8.59,16.58Z" /></svg>
              </button>
            </div>
            <div class="ptl__control-group">
              <button type="button" class="ptl__control" title="Zoom out" aria-label="Zoom out" @click="zoom(-1)">
                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M15.5,14H14.71L14.43,13.73C15.41,12.59 16,11.11 16,9.5A6.5,6.5 0 0,0 9.5,3A6.5,6.5 0 0,0 3,9.5A6.5,6.5 0 0,0 9.5,16C11.11,16 12.59,15.41 13.73,14.43L14,14.71V15.5L19,20.5L20.5,19L15.5,14M9.5,14C7,14 5,12 5,9.5C5,7 7,5 9.5,5C12,5 14,7 14,9.5C14,12 12,14 9.5,14M7,9H12V10H7V9Z" /></svg>
              </button>
              <span class="ptl__zoom">{{ dayWidth }}px/d</span>
              <button type="button" class="ptl__control" title="Zoom in" aria-label="Zoom in" @click="zoom(1)">
                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M15.5,14L20.5,19L19,20.5L14,15.5V14.71L13.73,14.43C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.43,13.73L14.71,14H15.5M9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14M12,10H10V12H9V10H7V9H9V7H10V9H12V10Z" /></svg>
              </button>
            </div>
          </div>
        </div>

        <div ref="grid" class="ptl__gantt">
          <!-- Column 1: the rows, named -->
          <div ref="sidebar" class="ptl__sidebar">
            <div class="ptl__head-cell" :style="{ height: headHeight + 'px' }">Projects</div>
            <template v-for="project in projectRows">
              <div :key="'side-p-' + project.id" class="ptl__side-row ptl__side-row--project">
                <button
                  type="button"
                  class="ptl__toggle"
                  :aria-expanded="project.open ? 'true' : 'false'"
                  :aria-label="(project.open ? 'Hide the phases of ' : 'Show the phases of ') + project.name"
                  @click="toggle(project.id)"
                >
                  <svg v-if="project.open" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M7.41,8.58L12,13.17L16.59,8.58L18,10L12,16L6,10L7.41,8.58Z" /></svg>
                  <svg v-else aria-hidden="true" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M8.59,16.58L13.17,12L8.59,7.41L10,6L16,12L10,18L8.59,16.58Z" /></svg>
                </button>
                <span class="ptl__side-name ptl__project-name" :title="project.name">{{ project.name }}</span>
                <button type="button" class="iz-btn iz-btn--sm gantt-widget__open" @click="$emit('select-project', project.id)">Open</button>
              </div>
              <div v-if="project.system" :key="'side-sp-' + project.id" class="ptl__side-row ptl__side-row--system" title="System Planning (calculated)">
                <span class="ptl__sp-badge">1</span>
                <span class="ptl__sp-icon">
                  <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M12,15.5A3.5,3.5 0 0,1 8.5,12A3.5,3.5 0 0,1 12,8.5A3.5,3.5 0 0,1 15.5,12A3.5,3.5 0 0,1 12,15.5M19.43,12.97C19.47,12.65 19.5,12.33 19.5,12C19.5,11.67 19.47,11.34 19.43,11L21.54,9.37C21.73,9.22 21.78,8.95 21.66,8.73L19.66,5.27C19.54,5.05 19.27,4.96 19.05,5.05L16.56,6.05C16.04,5.66 15.5,5.32 14.87,5.07L14.5,2.42C14.46,2.18 14.25,2 14,2H10C9.75,2 9.54,2.18 9.5,2.42L9.13,5.07C8.5,5.32 7.96,5.66 7.44,6.05L4.95,5.05C4.73,4.96 4.46,5.05 4.34,5.27L2.34,8.73C2.21,8.95 2.27,9.22 2.46,9.37L4.57,11C4.53,11.34 4.5,11.67 4.5,12C4.5,12.33 4.53,12.65 4.57,12.97L2.46,14.63C2.27,14.78 2.21,15.05 2.34,15.27L4.34,18.73C4.46,18.95 4.73,19.03 4.95,18.95L7.44,17.94C7.96,18.34 8.5,18.68 9.13,18.93L9.5,21.58C9.54,21.82 9.75,22 10,22H14C14.25,22 14.46,21.82 14.5,21.58L14.87,18.93C15.5,18.67 16.04,18.34 16.56,17.94L19.05,18.95C19.27,19.03 19.54,18.95 19.66,18.73L21.66,15.27C21.78,15.05 21.73,14.78 21.54,14.63L19.43,12.97Z" /></svg>
                </span>
                <div class="ptl__side-content">
                  <div class="ptl__side-top">
                    <span class="ptl__side-name">System Planning</span>
                    <span class="ptl__duration">{{ project.system.duration }}</span>
                  </div>
                  <div class="ptl__side-dates">{{ project.system.dates }}</div>
                </div>
              </div>
              <template v-if="project.open">
                <div
                  v-for="phase in project.rows"
                  :key="'side-ph-' + project.id + '-' + phase.key"
                  class="ptl__side-row ptl__side-row--phase"
                  :style="{ borderLeftColor: phase.color }"
                >
                  <span class="ptl__toggle-space" aria-hidden="true"></span>
                  <span class="ptl__order" :style="{ backgroundColor: phase.color }">{{ phase.order }}</span>
                  <div class="ptl__side-content">
                    <div class="ptl__side-top">
                      <span class="ptl__side-name" :title="phase.name">{{ phase.name }}</span>
                      <span v-if="phase.scheduled" class="ptl__duration">{{ phase.duration }}</span>
                    </div>
                    <div class="ptl__side-dates">{{ phase.dates }}</div>
                  </div>
                </div>
              </template>
            </template>
          </div>

          <!-- Column 2: the dates. Drag sideways to scroll, as in Planning. -->
          <div
            ref="main"
            class="ptl__main"
            :class="{ 'ptl__main--dragging': drag.active }"
            aria-hidden="true"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="endDrag"
            @pointercancel="endDrag"
            @pointerleave="endDrag"
          >
            <div class="ptl__timeline" :style="{ width: timelineWidth + 'px' }">
              <div class="ptl__timeline-head" :style="{ height: headHeight + 'px' }">
                <div v-if="gantt.years.length" class="ptl__head-row">
                  <span v-for="year in gantt.years" :key="year.key" class="ptl__year" :style="{ width: year.days * dayWidth + 'px' }">{{ year.label }}</span>
                </div>
                <div class="ptl__head-row">
                  <span v-for="month in gantt.months" :key="month.key" class="ptl__month" :style="{ width: month.days * dayWidth + 'px' }">{{ month.days * dayWidth < 40 ? "" : month.label }}</span>
                </div>
                <div class="ptl__head-row">
                  <span v-for="week in gantt.weeks" :key="week.key" class="ptl__week" :style="{ width: week.days * dayWidth + 'px' }" :title="week.tooltip">{{ week.days * dayWidth < 16 ? "" : week.label }}</span>
                </div>
              </div>

              <div class="ptl__content" :style="{ height: canvasHeight + 'px' }">
                <div v-if="dayWidth >= 8" class="ptl__weekends" :style="weekendStyle"></div>
                <div v-if="dayWidth >= 3" class="ptl__week-lines" :style="weekLinesStyle"></div>
                <div class="ptl__grid-lines">
                  <div v-for="month in gantt.months" :key="'grid-' + month.key" class="ptl__grid-column" :style="{ width: month.days * dayWidth + 'px' }"></div>
                </div>

                <div class="ptl__today" :style="{ left: gantt.today.offset * dayWidth + 'px' }">
                  <div class="ptl__today-line"></div>
                  <div class="ptl__today-badge">Today</div>
                </div>

                <template v-for="project in projectRows">
                  <div :key="'c-p-' + project.id" class="ptl__row ptl__row--project"></div>
                  <div v-if="project.system" :key="'c-sp-' + project.id" class="ptl__sp">
                    <div
                      v-for="bar in project.system.bars"
                      :key="'sp-' + project.id + '-' + bar.key"
                      class="ptl__sp-bar"
                      :class="'ptl__sp-bar--' + bar.tone"
                      :style="{ left: bar.offset * dayWidth + 'px', width: bar.days * dayWidth + 'px' }"
                      :title="project.name + ' · ' + bar.title"
                    >
                      <span v-if="bar.days * dayWidth > (bar.key === 'float' ? 40 : 50)" class="ptl__sp-label">{{ bar.label }}</span>
                    </div>
                    <div
                      v-for="marker in project.system.markers"
                      :key="'mk-' + project.id + '-' + marker.key"
                      class="ptl__sp-marker"
                      :class="'ptl__sp-marker--' + marker.key"
                      :style="{ left: marker.offset * dayWidth + 'px' }"
                      :title="marker.label + ': ' + marker.date"
                    >
                      <div class="ptl__sp-pin">
                        <svg v-if="marker.key === 'min-start'" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M12 6.46C11.72 6.46 11.44 6.56 11.22 6.78L6.78 11.22C6.35 11.65 6.35 12.35 6.78 12.78L11.22 17.22C11.65 17.65 12.35 17.65 12.78 17.22L17.22 12.78C17.65 12.35 17.65 11.65 17.22 11.22L12.78 6.78C12.56 6.56 12.28 6.46 12 6.46Z" /></svg>
                        <svg v-else-if="marker.key === 'desired-start'" width="14" height="14" viewBox="0 0 24 24"><path fill="currentColor" d="M8,5.14V19.14L19,12.14L8,5.14Z" /></svg>
                        <svg v-else width="14" height="14" viewBox="0 0 24 24"><path fill="currentColor" d="M14.4,6L14,4H5V21H7V14H12.6L13,16H20V6H14.4Z" /></svg>
                      </div>
                      <div class="ptl__sp-tag">
                        <span class="ptl__sp-tag-title">{{ marker.label }}</span>
                        <span class="ptl__sp-tag-date">{{ marker.date }}</span>
                      </div>
                      <div class="ptl__sp-guide"></div>
                    </div>
                    <div class="ptl__sp-legend" :style="{ left: Math.max(16, project.system.legendOffset * dayWidth) + 'px' }">
                      <span class="ptl__sp-legend-item"><i class="ptl__sp-swatch ptl__sp-swatch--deck"></i>Deck tasks</span>
                      <span class="ptl__sp-legend-item"><i class="ptl__sp-swatch ptl__sp-swatch--prep"></i>Preparation</span>
                      <span class="ptl__sp-legend-item"><i class="ptl__sp-swatch ptl__sp-swatch--float"></i>Float</span>
                      <span class="ptl__sp-legend-item"><span class="ptl__sp-icon-key ptl__sp-icon-key--min-start">◆</span>Minimum start</span>
                      <span v-if="project.system.legend.desired" class="ptl__sp-legend-item"><span class="ptl__sp-icon-key ptl__sp-icon-key--desired-start">▶</span>Desired start</span>
                      <span v-if="project.system.legend.actual" class="ptl__sp-legend-item"><span class="ptl__sp-icon-key ptl__sp-icon-key--actual-start">⚑</span>Actual start</span>
                    </div>
                  </div>
                  <template v-if="project.open">
                    <div v-for="phase in project.rows" :key="'c-ph-' + project.id + '-' + phase.key" class="ptl__row ptl__row--phase">
                      <div
                        v-if="phase.bar"
                        class="ptl__phase-bar"
                        :style="{ left: phase.bar.offset * dayWidth + 'px', width: phase.bar.days * dayWidth + 'px', borderColor: phase.color, backgroundColor: phase.tint }"
                        :title="project.name + ' · ' + phase.name + ': ' + phase.dates"
                      >
                        <span v-if="phase.bar.days * dayWidth > 90" class="ptl__phase-bar-label">{{ phase.name }}</span>
                      </div>
                      <div v-if="phase.milestone" class="ptl__milestone" :style="{ left: phase.milestone.offset * dayWidth + 'px' }" :title="phase.milestone.title">
                        <div class="ptl__diamond" :style="{ backgroundColor: phase.color }"></div>
                        <span class="ptl__milestone-label">{{ phase.milestone.label }}</span>
                      </div>
                    </div>
                  </template>
                </template>
              </div>
            </div>
          </div>

          <!-- Column 3: the traffic light -->
          <div class="ptl__status-col">
            <div class="ptl__head-cell ptl__head-cell--center" :style="{ height: headHeight + 'px' }">Status</div>
            <template v-for="project in projectRows">
              <div :key="'st-p-' + project.id" class="ptl__status-row ptl__status-row--project"></div>
              <div v-if="project.system" :key="'st-sp-' + project.id" class="ptl__status-row ptl__status-row--system">
                <span class="ptl__pill" :class="'ptl__pill--' + project.status.tone"><span class="ptl__pill-dot"></span>{{ project.status.label }}</span>
              </div>
              <template v-if="project.open">
                <div v-for="phase in project.rows" :key="'st-ph-' + project.id + '-' + phase.key" class="ptl__status-row ptl__status-row--phase">
                  <span class="ptl__pill" :class="'ptl__pill--' + phase.status.tone"><span class="ptl__pill-dot"></span>{{ phase.status.label }}</span>
                </div>
              </template>
            </template>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";
import { portfolioGanttOf, ROW_HEIGHT } from "../lib/projectTimeline.mjs";

// The Planning tab's zoom steps, px per day, and where it starts.
var ZOOM_IN = [3, 4, 6, 8, 12, 16];
var ZOOM_OUT = [2, 3, 4, 6, 8, 12];
// A project's name row.
var PROJECT_ROW = 44;
// Up to this many projects show their phases from the start.
var OPEN_UP_TO = 3;
var DRAG_THRESHOLD_PX = 4;

export default {
  name: "GanttWidget",
  props: {
    // The dashboard's (filtered) projects; the chart follows its filters.
    projects: { type: Array, default: function () { return []; } },
  },
  data: function () {
    return {
      collapsed: false,
      entries: [],
      loading: false,
      loaded: false,
      error: "",
      open: {},
      dayWidth: 4,
      drag: { pointerId: null, startX: 0, startScroll: 0, active: false },
    };
  },
  computed: {
    gantt: function () {
      var wanted = {};
      this.projects.forEach(function (p) { wanted[p.id] = true; });
      return portfolioGanttOf(this.entries.filter(function (e) { return wanted[e.id]; }));
    },
    projectRows: function () {
      var self = this;
      var many = this.gantt.projects.length > OPEN_UP_TO;
      return this.gantt.projects.map(function (project) {
        var state = self.open[project.id];
        return Object.assign({}, project, {
          open: state === undefined ? !many : state,
          rows: project.rows.map(function (phase) {
            return Object.assign({}, phase, {
              tint: /^#[0-9a-f]{6}$/i.test(phase.color) ? phase.color + "25" : "color-mix(in srgb, " + phase.color + " 15%, transparent)",
            });
          }),
        });
      });
    },
    anyDelays: function () {
      return this.gantt.projects.some(function (p) { return p.hasActiveDelays; });
    },
    headHeight: function () {
      return this.gantt.years && this.gantt.years.length ? 80 : 54;
    },
    timelineWidth: function () {
      return (this.gantt.totalDays || 0) * this.dayWidth;
    },
    canvasHeight: function () {
      return this.projectRows.reduce(function (total, p) {
        return total + PROJECT_ROW + (p.system ? ROW_HEIGHT.system : 0) + (p.open ? p.rows.length * ROW_HEIGHT.phase : 0);
      }, 0);
    },
    weekLinesStyle: function () {
      return {
        backgroundImage: "linear-gradient(to right, var(--color-border) 0 1px, transparent 1px)",
        backgroundSize: 7 * this.dayWidth + "px 100%",
        backgroundPosition: this.gantt.firstMondayOffset * this.dayWidth + "px 0",
      };
    },
    weekendStyle: function () {
      var saturdayPx = 5 * this.dayWidth;
      return {
        backgroundImage: "linear-gradient(to right, transparent " + saturdayPx + "px, var(--bg-inset) " + saturdayPx + "px)",
        backgroundSize: 7 * this.dayWidth + "px 100%",
        backgroundPosition: this.gantt.firstMondayOffset * this.dayWidth + "px 0",
      };
    },
  },
  mounted: function () {
    this.load();
  },
  methods: {
    load: function () {
      var self = this;
      this.loading = true;
      this.error = "";
      axios
        .get(generateUrl("/apps/employee_dashboard/api/project-timelines"))
        .then(function (response) {
          var list = response.data && response.data.projects;
          if (!Array.isArray(list)) throw new Error("Invalid response");
          self.entries = list;
          self.loaded = true;
        })
        .catch(function () {
          self.error = "The project timelines could not be loaded.";
        })
        .then(function () {
          self.loading = false;
        });
    },
    toggle: function (id) {
      var current = this.projectRows.find(function (p) { return p.id === id; });
      this.open = Object.assign({}, this.open, { [id]: !(current && current.open) });
    },
    scroller: function () {
      var grid = this.$refs.grid;
      var main = this.$refs.main;
      if (!grid || !main) return null;
      if (getComputedStyle(main).overflowX === "auto") return { el: main, width: main.clientWidth };
      var pinned = (this.$refs.sidebar ? this.$refs.sidebar.offsetWidth : 0) + 110;
      return { el: grid, width: Math.max(0, grid.clientWidth - pinned) };
    },
    scrollBy: function (px) {
      var s = this.scroller();
      if (s) s.el.scrollLeft = Math.max(0, s.el.scrollLeft + px);
    },
    navigateToday: function () {
      var s = this.scroller();
      if (s) s.el.scrollLeft = Math.max(0, this.gantt.today.offset * this.dayWidth - (s.width || 400) / 2);
    },
    zoom: function (direction) {
      var dw = this.dayWidth;
      var levels = direction > 0 ? ZOOM_IN : ZOOM_OUT;
      var idx = levels.findIndex(function (l) { return l >= dw; });
      var next = direction > 0 ? levels[Math.min(levels.length - 1, idx + 1)] : levels[Math.max(0, idx - 1)];
      var s = this.scroller();
      if (!s) {
        this.dayWidth = next;
        return;
      }
      var centerDays = (s.el.scrollLeft + s.width / 2) / dw;
      var self = this;
      this.dayWidth = next;
      this.$nextTick(function () {
        s.el.scrollLeft = Math.max(0, centerDays * self.dayWidth - s.width / 2);
      });
    },
    onPointerDown: function (e) {
      if (e.button !== undefined && e.button !== 0) return;
      var s = this.scroller();
      if (s) this.drag = { pointerId: e.pointerId, startX: e.clientX, startScroll: s.el.scrollLeft, active: false };
    },
    onPointerMove: function (e) {
      if (this.drag.pointerId !== e.pointerId) return;
      var s = this.scroller();
      if (!s) return;
      var dx = e.clientX - this.drag.startX;
      if (!this.drag.active) {
        if (Math.abs(dx) < DRAG_THRESHOLD_PX) return;
        this.drag.active = true;
        try {
          e.currentTarget.setPointerCapture(e.pointerId);
        } catch (err) {
          // a pointer that already went away
        }
      }
      e.preventDefault();
      s.el.scrollLeft = Math.max(0, this.drag.startScroll - dx);
    },
    endDrag: function () {
      this.drag = { pointerId: null, startX: 0, startScroll: 0, active: false };
    },
  },
};
</script>

<style scoped>
.gantt-widget {  margin-bottom: var(--spacing-xl, 32px);
  overflow: hidden;
}
.gantt-widget__header {
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
.gantt-widget__header:hover { background: var(--bg-subtle); }
.gantt-widget__title {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  margin: 0; padding: 0; border: none;
  display: flex;
  align-items: center;
  gap: 8px;
}
.gantt-widget__title svg { color: var(--accent); }
.gantt-widget__count {
  font-size: 11px;
  font-weight: 600;
  background: var(--accent-bg);
  color: var(--accent-hover);
  padding: 2px 8px;
  border-radius: 8px;
}
.gantt-widget__chevron {
  color: var(--color-text-muted, #9ca3af);
  transition: transform 0.3s;
}
.gantt-widget__chevron--rotated { transform: rotate(180deg); }
.gantt-widget__body {
  /* padding-top rather than none: the header's own bottom padding alone left
     the content sitting on the header, which reads as one block — especially
     where the first thing below is a bordered box. */
  padding: 4px 20px 20px;
}

.gantt-widget__state {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 12px;
}

/* ── What the multi-project chart adds ── */
.gantt-widget__body .ptl__planner {
  container-type: inline-size;
}
.ptl__side-row--project,
.ptl__row--project,
.ptl__status-row--project {
  height: 44px;
  background: var(--bg-inset);
}
.ptl__side-row--project {
  gap: 6px;
}
.ptl__project-name {
  flex: 1;
  min-width: 0;
}
.iz-app .gantt-widget__open {
  flex-shrink: 0;
}
.ptl__side-row--phase,
.ptl__row--phase,
.ptl__status-row--phase {
  height: 56px;
}
.ptl__toggle-space {
  width: 24px;
  flex-shrink: 0;
  margin-right: 4px;
}

/* ── Gantt — copied from ProjectTimeline.vue's Planning-tab styles, which
   are themselves projectcreatoraio's. Keep the two in step. ──
   Original note:
   Geometry, row heights and colours are GanttChart.vue's and
   SystemPlanningRow.vue's, so the two charts read the same. The hexes below
   are that palette: the theme has no tokens for it, and it is kept in one
   place here so a change in Planning is one edit. Surfaces, borders and text
   use the theme's tokens as everywhere else. */
.ptl__planner {
  --ptl-blue: #3b82f6;
  --ptl-deck: #1d4ed8;
  --ptl-prep: #7c3aed;
  --ptl-indigo: #4f46e5;
  --ptl-emerald: #059669;
  --ptl-green: #10b981;
  --ptl-red: #ef4444;
  --ptl-red-text: #dc2626;
  --ptl-amber: #f59e0b;
  --ptl-amber-text: #d97706;
  --ptl-slate: #94a3b8;
  --ptl-slate-text: #64748b;
  --ptl-violet: #8b5cf6;
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
}

.ptl__toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
}
.ptl__delays {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: var(--radius-pill);
  background: rgba(239, 68, 68, 0.12);
  color: var(--ptl-red-text);
  font-size: var(--iz-fs-xs);
  font-weight: 800;
}
.ptl__controls {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin-left: auto;
}
.ptl__control-group {
  display: flex;
  align-items: center;
  gap: 2px;
  padding: 4px;
  border: 1px solid var(--color-border);
  border-radius: 14px;
  background: var(--bg-inset);
}
.iz-app .ptl__control {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 34px;
  min-height: 34px;
  margin: 0;
  padding: 0 8px;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: var(--color-text-primary);
  font-size: var(--iz-fs-md);
  font-weight: 600;
  cursor: pointer;
}
.iz-app .ptl__control:hover {
  background: var(--bg-subtle);
}
.iz-app .ptl__control--today {
  padding: 0 14px;
}
.ptl__zoom {
  min-width: 50px;
  padding: 0 8px;
  font-size: var(--iz-fs-xs);
  font-weight: 700;
  text-align: center;
  color: var(--color-text-secondary);
}

/* The grid: names | dates | status. It scrolls both ways, the side columns
   pinned while the dates move and the header pinned while the rows do. */
.ptl__gantt {
  display: grid;
  grid-template-columns: 280px minmax(max-content, 1fr) 110px;
  max-height: calc(100vh - 120px);
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--bg-card);
}
.ptl__sidebar,
.ptl__status-col {
  position: sticky;
  z-index: 30;
  background: var(--bg-card);
}
.ptl__sidebar {
  left: 0;
  border-right: 1px solid var(--color-border);
}
.ptl__status-col {
  right: 0;
  border-left: 1px solid var(--color-border);
}
.ptl__head-cell {
  position: sticky;
  top: 0;
  z-index: 1;
  display: flex;
  align-items: center;
  box-sizing: border-box;
  padding: 0 16px;
  border-bottom: 1px solid var(--color-border);
  background: var(--bg-inset);
  font-size: var(--iz-fs-sm);
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-secondary);
}
.ptl__head-cell--center {
  justify-content: center;
}

/* Column 1 */
.ptl__side-row {
  display: flex;
  align-items: center;
  box-sizing: border-box;
  padding: 0 16px;
  border-bottom: 1px solid var(--color-border);
  background: var(--bg-card);
  transition: background 0.15s ease;
}
.ptl__side-row:hover {
  background: var(--bg-subtle);
}
.ptl__side-row--system {
  height: 76px;
  border-left: 3px solid var(--ptl-blue);
  background: rgba(59, 130, 246, 0.05);
}
.ptl__side-row--phase {
  border-left: 4px solid var(--ptl-blue);
  background: var(--bg-subtle);
  cursor: pointer;
  user-select: none;
}
.ptl__side-row--phase:hover {
  background: var(--bg-inset);
}
.ptl__side-row--task {
  padding-left: 32px;
}
.ptl__sp-badge,
.ptl__order {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  margin-right: 8px;
  border-radius: 50%;
  color: #fff;
  font-size: var(--iz-fs-xs);
  font-weight: 800;
}
.ptl__sp-badge {
  background: var(--ptl-blue);
}
.ptl__sp-icon {
  display: flex;
  flex-shrink: 0;
  margin-right: 8px;
  color: var(--ptl-blue);
}
.iz-app .ptl__toggle {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 0;
  margin: 0 4px 0 0;
  padding: 4px;
  border: 0;
  border-radius: 4px;
  background: none;
  color: var(--color-text-secondary);
  cursor: pointer;
}
.iz-app .ptl__toggle:hover {
  background: rgba(0, 0, 0, 0.08);
  color: var(--color-text-primary);
}
.ptl__tree {
  position: relative;
  width: 20px;
  height: 100%;
  flex-shrink: 0;
  margin-right: 4px;
}
.ptl__tree-v,
.ptl__tree-h {
  position: absolute;
  left: 8px;
  background: var(--color-border);
}
.ptl__tree-v {
  top: 0;
  bottom: 0;
  width: 1px;
}
.ptl__tree-h {
  top: 50%;
  width: 10px;
  height: 1px;
}
.ptl__deck-icon {
  display: flex;
  flex-shrink: 0;
  margin-right: 6px;
  color: var(--accent);
  opacity: 0.85;
}
.ptl__side-content {
  flex: 1;
  min-width: 0;
}
.ptl__side-top {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
}
.ptl__side-name,
.ptl__task-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: var(--iz-fs-md);
  color: var(--color-text-primary);
}
.ptl__side-name {
  font-weight: 700;
}
.ptl__task-name {
  font-weight: 600;
}
.ptl__task-name--done {
  text-decoration: line-through;
  opacity: 0.7;
}
.ptl__task-name--delayed {
  color: var(--ptl-red-text);
  font-weight: 700;
}
.ptl__duration {
  flex-shrink: 0;
  padding: 2px 6px;
  border-radius: var(--radius-pill);
  background: var(--bg-inset);
  font-size: var(--iz-fs-micro);
  font-weight: 800;
  color: var(--color-text-secondary);
}
.ptl__delay-tag {
  flex-shrink: 0;
  padding: 1px 6px;
  border-radius: var(--radius-pill);
  background: var(--ptl-red);
  font-size: var(--iz-fs-micro);
  font-weight: 800;
  color: #fff;
  white-space: nowrap;
}
.ptl__side-dates {
  margin-top: 2px;
  font-size: var(--iz-fs-xs);
  color: var(--color-text-secondary);
}

/* Column 2 */
.ptl__main {
  background: var(--bg-card);
  cursor: grab;
}
.ptl__main--dragging {
  cursor: grabbing;
  user-select: none;
}
.ptl__timeline {
  position: relative;
  min-height: 100%;
}
.ptl__timeline-head {
  position: sticky;
  top: 0;
  z-index: 20;
  box-sizing: border-box;
  border-bottom: 1px solid var(--color-border);
  background: var(--bg-inset);
}
.ptl__head-row {
  display: flex;
  border-bottom: 1px solid var(--color-border);
}
.ptl__year,
.ptl__month,
.ptl__week {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-sizing: border-box;
  overflow: hidden;
  border-right: 1px solid var(--color-border);
  white-space: nowrap;
}
.ptl__year {
  height: 32px;
  font-size: var(--iz-fs-md);
  font-weight: 800;
}
.ptl__month {
  height: 26px;
  font-size: var(--iz-fs-xs);
  font-weight: 600;
}
.ptl__week {
  height: 22px;
  font-size: 9px;
  font-weight: 700;
  color: var(--color-text-muted);
}
.ptl__content {
  position: relative;
  padding-bottom: 1px;
}
.ptl__grid-lines {
  position: absolute;
  inset: 0;
  display: flex;
  pointer-events: none;
}
.ptl__grid-column {
  flex-shrink: 0;
  height: 100%;
  box-sizing: border-box;
  border-right: 1px solid var(--color-border);
  opacity: 0.3;
}
.ptl__week-lines,
.ptl__weekends {
  position: absolute;
  inset: 0;
  background-repeat: repeat-x;
  pointer-events: none;
}
.ptl__week-lines {
  opacity: 0.35;
}
.ptl__weekends {
  opacity: 0.5;
}

/* Dependency arrows */
.ptl__deps {
  position: absolute;
  top: 0;
  left: 0;
  z-index: 4;
  pointer-events: none;
}
.ptl__dep {
  fill: none;
  stroke: var(--ptl-slate);
  stroke-width: 1.5;
  stroke-linejoin: round;
  stroke-linecap: round;
  opacity: 0.8;
}
.ptl__dep--active {
  stroke: var(--ptl-blue);
  stroke-width: 2;
}
.ptl__dep--delayed {
  stroke: var(--ptl-red);
  stroke-width: 2;
  stroke-dasharray: 4 2;
}
.ptl__dep-arrow {
  fill: var(--ptl-slate);
}
.ptl__dep-arrow--active {
  fill: var(--ptl-blue);
}
.ptl__dep-arrow--delayed {
  fill: var(--ptl-red);
}

/* Today */
.ptl__today {
  position: absolute;
  top: 0;
  bottom: 0;
  z-index: 15;
  pointer-events: none;
}
.ptl__today-line {
  position: relative;
  width: 2px;
  height: 100%;
  background: var(--ptl-red);
  box-shadow: 0 0 8px rgba(239, 68, 68, 0.4);
}
.ptl__today-line::before {
  content: "";
  position: absolute;
  top: 0;
  left: 50%;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--ptl-red);
  transform: translateX(-50%);
}
.ptl__today-badge {
  position: absolute;
  top: 12px;
  left: 50%;
  padding: 3px 8px;
  border-radius: 12px;
  background: var(--ptl-red);
  color: #fff;
  font-size: var(--iz-fs-micro);
  font-weight: 800;
  text-transform: uppercase;
  white-space: nowrap;
  transform: translateX(-50%);
  box-shadow: 0 2px 6px rgba(239, 68, 68, 0.4);
}

/* Guide lines for the System Planning dates */
.ptl__guide {
  --ptl-guide: var(--color-success);
  position: absolute;
  top: 0;
  bottom: 0;
  z-index: 6;
  pointer-events: none;
}
.ptl__guide-line {
  width: 2px;
  height: 100%;
  border-left: 2px solid var(--ptl-guide);
}
.ptl__guide--min-start {
  --ptl-guide: var(--ptl-prep);
}
.ptl__guide--desired-start {
  --ptl-guide: var(--ptl-indigo);
}
.ptl__guide--actual-start {
  --ptl-guide: var(--ptl-emerald);
}
.ptl__guide--end .ptl__guide-line {
  border-left-color: var(--ptl-violet);
}
.ptl__guide--min-start .ptl__guide-line,
.ptl__guide--desired-start .ptl__guide-line {
  border-left-style: dashed;
  opacity: 0.7;
}
.ptl__guide--actual-start .ptl__guide-line {
  border-left-style: dashed;
  opacity: 0.8;
}
.ptl__guide-flag {
  position: absolute;
  left: 3px;
  padding: 1px 6px;
  border-left: 2px solid var(--ptl-guide);
  border-radius: 0 8px 8px 0;
  background: var(--bg-card);
  color: var(--ptl-guide);
  font-size: var(--iz-fs-micro);
  font-weight: 700;
  white-space: nowrap;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
}

/* 1. System Planning */
.ptl__sp {
  position: relative;
  height: 76px;
  box-sizing: border-box;
  border-bottom: 1px solid var(--color-border);
  /* Planning's rgba(241, 245, 249, 0.4) is a light-only band; the theme's
     subtle surface is the same step in both schemes. */
  background: var(--bg-subtle);
}
.ptl__sp-bar {
  position: absolute;
  top: 12px;
  z-index: 2;
  display: flex;
  align-items: center;
  box-sizing: border-box;
  height: 28px;
  padding: 0 8px;
  overflow: hidden;
  border-radius: 6px;
  white-space: nowrap;
}
.ptl__sp-label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: var(--iz-fs-xs);
  font-weight: 700;
  letter-spacing: 0.2px;
}
.ptl__sp-bar--deck {
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
  background: var(--ptl-deck);
  color: #fff;
}
.ptl__sp-bar--prep {
  border-top-left-radius: 0;
  border-bottom-left-radius: 0;
  background: var(--ptl-prep);
  color: #fff;
}
.ptl__sp-bar--float,
.ptl__sp-bar--behind {
  top: 14px;
  z-index: 1;
  height: 24px;
}
.ptl__sp-bar--float {
  border: 2px dashed var(--ptl-green);
  background: rgba(16, 185, 129, 0.12);
  color: #047857;
}
.ptl__sp-bar--behind {
  border: 2px dashed var(--ptl-red);
  background: rgba(239, 68, 68, 0.12);
  color: #b91c1c;
}
.ptl__sp-marker {
  position: absolute;
  top: 0;
  z-index: 4;
  height: 100%;
}
.ptl__sp-pin {
  position: absolute;
  top: -4px;
  left: -8px;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
}
.ptl__sp-tag {
  position: absolute;
  top: -38px;
  left: -35px;
  z-index: 5;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 2px 6px;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  background: var(--bg-card);
  white-space: nowrap;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
}
.ptl__sp-tag-title {
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--color-text-secondary);
}
.ptl__sp-tag-date {
  font-size: var(--iz-fs-micro);
  font-weight: 700;
  color: var(--color-text-primary);
}
.ptl__sp-guide {
  position: absolute;
  top: 10px;
  left: 0;
  width: 2px;
  height: 38px;
  opacity: 0.7;
}
.ptl__sp-marker--min-start {
  color: var(--ptl-prep);
}
.ptl__sp-marker--desired-start {
  color: var(--ptl-indigo);
}
.ptl__sp-marker--actual-start {
  color: var(--ptl-emerald);
}
.ptl__sp-marker .ptl__sp-guide {
  background: currentColor;
}
.ptl__sp-legend {
  position: absolute;
  top: 48px;
  z-index: 3;
  display: flex;
  align-items: center;
  gap: 16px;
  font-size: var(--iz-fs-xs);
  font-weight: 600;
  color: var(--color-text-secondary);
  white-space: nowrap;
  pointer-events: none;
}
.ptl__sp-legend-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.ptl__sp-swatch {
  width: 11px;
  height: 11px;
  flex-shrink: 0;
  border-radius: 3px;
}
.ptl__sp-swatch--deck {
  background: var(--ptl-deck);
}
.ptl__sp-swatch--prep {
  background: var(--ptl-prep);
}
.ptl__sp-swatch--float {
  border: 1.5px dashed var(--ptl-green);
  background: rgba(16, 185, 129, 0.15);
}
.ptl__sp-icon-key--min-start {
  color: var(--ptl-prep);
}
.ptl__sp-icon-key--desired-start {
  color: var(--ptl-indigo);
}
.ptl__sp-icon-key--actual-start {
  color: var(--ptl-emerald);
}

/* Rows */
.ptl__row {
  position: relative;
  display: flex;
  align-items: center;
  box-sizing: border-box;
  border-bottom: 1px solid var(--color-border);
}
.ptl__row--phase {
  background: rgba(0, 0, 0, 0.015);
}
.ptl__phase-bar {
  position: absolute;
  top: 50%;
  z-index: 5;
  display: flex;
  align-items: center;
  box-sizing: border-box;
  height: 18px;
  padding: 0 8px;
  border: 2px solid var(--ptl-blue);
  border-radius: 6px;
  transform: translateY(-50%);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}
.ptl__phase-bar-label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: var(--iz-fs-micro);
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-primary);
}
.ptl__milestone {
  position: absolute;
  top: 50%;
  z-index: 8;
  display: flex;
  align-items: center;
  transform: translateY(-50%);
}
.ptl__diamond {
  width: 14px;
  height: 14px;
  flex-shrink: 0;
  border: 2px solid #fff;
  border-radius: 2px;
  transform: rotate(45deg);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
}
.ptl__milestone-label {
  margin-left: 8px;
  padding: 1px 6px;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  background: var(--bg-card);
  font-size: var(--iz-fs-xs);
  font-weight: 700;
  color: var(--color-text-primary);
  white-space: nowrap;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.ptl__bar {
  position: absolute;
  top: 50%;
  z-index: 5;
  display: flex;
  align-items: center;
  box-sizing: border-box;
  height: 26px;
  padding: 0 10px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 6px;
  transform: translateY(-50%);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}
.ptl__bar--task {
  gap: 6px;
}
.ptl__bar--done {
  opacity: 0.85;
}
.ptl__bar--at-risk {
  border: 2px solid var(--ptl-red);
  box-shadow: 0 0 6px rgba(239, 68, 68, 0.4);
}
.ptl__bar--ghost {
  z-index: 3;
  border: 1.5px dashed var(--ptl-slate);
  background: rgba(148, 163, 184, 0.12);
  box-shadow: none;
}
.ptl__done-icon {
  flex-shrink: 0;
  color: #fff;
}
.ptl__bar-label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: var(--iz-fs-xs);
  font-weight: 700;
  color: #fff;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}
.ptl__delay-badge {
  flex-shrink: 0;
  margin-left: auto;
  padding: 1px 5px;
  border-radius: var(--radius-pill);
  background: var(--ptl-red);
  color: #fff;
  font-size: 9px;
  font-weight: 800;
  box-shadow: 0 1px 3px rgba(239, 68, 68, 0.4);
}
.ptl__item-milestone {
  position: absolute;
  top: 50%;
  z-index: 8;
  width: 16px;
  height: 16px;
  border: 2px solid #fff;
  border-radius: 3px;
  background: var(--ptl-marker, #0f172a);
  transform: translate(-50%, -50%) rotate(45deg);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}

/* Hover guide */
.ptl__hover {
  position: absolute;
  inset: 0;
  z-index: 21;
  pointer-events: none;
}
.ptl__hover-line {
  position: absolute;
  bottom: 0;
  width: 0;
  border-left: 1px dashed var(--accent);
  transform: translateX(-0.5px);
}
.ptl__hover-head {
  position: sticky;
  top: 0;
  height: 0;
}
.ptl__hover-week {
  position: absolute;
  height: 22px;
  background: var(--accent-bg);
  opacity: 0.6;
}
.ptl__hover-chip {
  position: absolute;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 2px 8px;
  border-radius: 10px;
  background: var(--accent);
  color: var(--iz-accent-text);
  font-size: var(--iz-fs-xs);
  font-weight: 600;
  white-space: nowrap;
  transform: translateX(-50%);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}
.ptl__hover-week-tag {
  font-weight: 800;
}
.ptl__hover-relative {
  font-weight: 500;
  opacity: 0.8;
}

/* Column 3: status */
.ptl__status-row {
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  padding: 0 10px;
  border-bottom: 1px solid var(--color-border);
}
.ptl__status-row--system {
  height: 76px;
  background: rgba(59, 130, 246, 0.03);
}
.ptl__status-row--phase {
  background: var(--bg-subtle);
}
.ptl__pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 8px;
  border: 1px solid transparent;
  border-radius: var(--radius-pill);
  font-size: var(--iz-fs-xs);
  font-weight: 700;
  white-space: nowrap;
}
.ptl__pill-dot {
  width: 7px;
  height: 7px;
  flex-shrink: 0;
  border-radius: 50%;
}
.ptl__pill--on-track {
  border-color: rgba(16, 185, 129, 0.25);
  background: rgba(16, 185, 129, 0.12);
  color: var(--ptl-emerald);
}
.ptl__pill--on-track .ptl__pill-dot {
  background: var(--ptl-green);
}
.ptl__pill--attention {
  border-color: rgba(245, 158, 11, 0.25);
  background: rgba(245, 158, 11, 0.12);
  color: var(--ptl-amber-text);
}
.ptl__pill--attention .ptl__pill-dot {
  background: var(--ptl-amber);
}
.ptl__pill--at-risk {
  border-color: rgba(239, 68, 68, 0.25);
  background: rgba(239, 68, 68, 0.12);
  color: var(--ptl-red-text);
}
.ptl__pill--at-risk .ptl__pill-dot {
  background: var(--ptl-red);
}
.ptl__pill--not-started {
  border-color: rgba(148, 163, 184, 0.25);
  background: rgba(148, 163, 184, 0.12);
  color: var(--ptl-slate-text);
}
.ptl__pill--not-started .ptl__pill-dot {
  background: var(--ptl-slate);
}
.ptl__pill--completed {
  border-color: rgba(5, 150, 105, 0.3);
  background: rgba(5, 150, 105, 0.15);
  color: #047857;
}
.ptl__pill--completed .ptl__pill-dot {
  background: var(--ptl-emerald);
}

/* Narrow cards stack the three columns and the dates scroll by themselves —
   the Planning tab does the same below 900px. */
@container (max-width: 900px) {
  .ptl__gantt {
    grid-template-columns: minmax(0, 1fr);
    max-height: none;
    overflow: visible;
  }
  .ptl__main {
    overflow-x: auto;
  }
  .ptl__sidebar,
  .ptl__status-col,
  .ptl__head-cell {
    position: static;
  }
  .ptl__sidebar {
    border-right: 0;
  }
  .ptl__status-col {
    border-left: 0;
  }
}
</style>
