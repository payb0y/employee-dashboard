<template>
  <CardPanel :title="title" :total="visible.length" :empty="empty">
    <template #icon>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="16" rx="2" />
        <line x1="3" y1="10" x2="21" y2="10" />
      </svg>
    </template>

    <template v-if="ranges" #controls>
      <button
        v-for="r in rangeOptions"
        :key="r.days"
        class="iz-chip"
        :class="{ 'iz-chip--active': activeRange === r.days }"
        @click="activeRange = r.days"
      >{{ r.label }}</button>
    </template>

    <button
      v-for="task in visible"
      :key="task.id"
      class="tasks-panel__row"
      @click="$emit('select', task.id)"
    >
      <!-- The flex row lives on this span, not on the button. A <button> does
           not derive its height from flex children, so laying the row out on
           the button collapsed it to its padding and the text spilled over
           the row below. -->
      <span class="tasks-panel__line">
        <span class="tasks-panel__main">
          <span class="tasks-panel__title">{{ task.title }}</span>
          <span class="tasks-panel__sub">{{ task.projectName || task.stackTitle }}</span>
        </span>
        <span v-if="task.duedate" class="tasks-panel__due">{{ shortDate(task.duedate) }}</span>
      </span>
    </button>
  </CardPanel>
</template>

<script>
import CardPanel from "./CardPanel.vue";

export default {
  name: "TasksPanel",
  components: { CardPanel },
  props: {
    title: { type: String, required: true },
    tasks: { type: Array, default: function () { return []; } },
    empty: { type: String, default: "No cards" },
    // Only the Upcoming panel sets this. Narrowing by range is a view concern
    // local to that panel — every other filter lives in CardsView.
    ranges: { type: Boolean, default: false },
  },
  data: function () {
    return {
      // Default to All. The chips narrow; they must never be the reason a
      // task the employee owns is on screen nowhere. Cards due beyond the
      // widest bounded chip (a project a quarter out is ordinary here) were
      // invisible in every panel: not overdue, not today, not tomorrow, and
      // filtered out of upcoming.
      activeRange: null,
      rangeOptions: [
        { days: 7, label: "7d" },
        { days: 30, label: "30d" },
        { days: 90, label: "90d" },
        { days: null, label: "All" },
      ],
    };
  },
  computed: {
    visible: function () {
      if (!this.ranges || this.activeRange === null) return this.tasks;
      var limit = new Date();
      limit.setDate(limit.getDate() + this.activeRange);
      limit.setHours(23, 59, 59, 999);
      return this.tasks.filter(function (t) {
        return t.duedate && new Date(t.duedate) <= limit;
      });
    },
  },
  methods: {
    shortDate: function (value) {
      var d = new Date(value);
      if (isNaN(d.getTime())) return "";
      return d.toLocaleDateString(undefined, { day: "numeric", month: "short" });
    },
  },
};
</script>

<style scoped>
.tasks-panel__row {
  /* The panel body is a column flex container, so height is the MAIN axis
     and these rows are shrinkable by default. Once the list overflows,
     flex-shrink crushes every row toward zero — with min-height reset to
     0 they collapse to their padding and the text spills over the row
     below. The body scrolls; it must never compress its items. */
  flex-shrink: 0;
  /* Nextcloud core styles every bare <button>. The app-wide reset in
     Dashboard.vue is scoped to #employee-dashboard-root, which Vue 2 REPLACES
     on mount — so that block has never applied to anything and cannot be
     relied on here. Reset locally instead. */
  appearance: none;
  background: none;
  border: 0;
  min-height: 0;
  font: inherit;
  color: inherit;
  display: block;
  width: 100%;
  text-align: left;
  padding: 4px 6px;
  border-radius: var(--radius-sm);
  cursor: pointer;
}
/* Layout lives here rather than on the button — see the template comment. */
.tasks-panel__line {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}
.tasks-panel__row:hover { background: var(--bg-subtle); }
.tasks-panel__main {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}
.tasks-panel__title {
  font-size: 12px;
  font-weight: 600;
  line-height: 1.3;
  margin: 0;
  color: var(--color-text-primary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.tasks-panel__sub {
  font-size: 11px;
  line-height: 1.25;
  color: var(--color-text-secondary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.tasks-panel__due {
  font-size: 11px;
  color: var(--color-text-muted);
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
</style>
