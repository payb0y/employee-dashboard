<template>
  <CardPanel title="Upcoming events" :total="events.length" empty="Nothing in the next 7 days">
    <template #icon>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2" />
        <line x1="16" y1="2" x2="16" y2="6" />
        <line x1="8" y1="2" x2="8" y2="6" />
        <line x1="3" y1="10" x2="21" y2="10" />
      </svg>
    </template>

    <div v-for="ev in events" :key="ev.uid" class="events-panel__row">
      <span class="events-panel__title">{{ ev.title }}</span>
      <span class="events-panel__when">{{ when(ev) }}</span>
    </div>
  </CardPanel>
</template>

<script>
import CardPanel from "./CardPanel.vue";

export default {
  name: "EventsPanel",
  components: { CardPanel },
  props: {
    events: { type: Array, default: function () { return []; } },
  },
  methods: {
    when: function (ev) {
      var d = new Date(ev.startsAt);
      if (isNaN(d.getTime())) return "";
      if (ev.allDay) {
        return d.toLocaleDateString(undefined, { weekday: "short", day: "numeric", month: "short" });
      }
      return d.toLocaleDateString(undefined, { weekday: "short" }) +
        " " + d.toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" });
    },
  },
};
</script>

<style scoped>
.events-panel__row {
  /* The panel body is a column flex container, so height is the MAIN axis
     and these rows are shrinkable by default. Once the list overflows,
     flex-shrink crushes every row toward zero — with min-height reset to
     0 they collapse to their padding and the text spills over the row
     below. The body scrolls; it must never compress its items. */
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 4px 0;
}
.events-panel__title {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-primary);
}
.events-panel__when {
  font-size: 11px;
  color: var(--color-text-secondary);
}
</style>
