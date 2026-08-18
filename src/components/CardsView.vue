<template>
  <div class="cards-view">
    <div class="cards-view__grid">
      <ProjectsPanel :projects="projects" @select="$emit('filter-project', $event)" />
      <TasksPanel
        title="Overdue"
        :tasks="overdue"
        empty="Nothing overdue. You're clear."
        @select="$emit('select-task', $event)"
      />
      <TasksPanel
        title="Due today"
        :tasks="dueToday"
        empty="Nothing due today"
        @select="$emit('select-task', $event)"
      />
      <TasksPanel
        title="Due tomorrow"
        :tasks="dueTomorrow"
        empty="Nothing due tomorrow"
        @select="$emit('select-task', $event)"
      />
      <TasksPanel
        title="Upcoming"
        :tasks="upcoming"
        ranges
        empty="Nothing scheduled"
        @select="$emit('select-task', $event)"
      />
      <TasksPanel
        title="No due date"
        :tasks="noDueDate"
        empty="Every card has a date"
        @select="$emit('select-task', $event)"
      />
      <EventsPanel :events="events" />
      <MentionsPanel :mentions="mentions" />
      <SignaturesPanel :signatures="signatures" />
    </div>
  </div>
</template>

<script>
import TasksPanel from "./cards/TasksPanel.vue";
import ProjectsPanel from "./cards/ProjectsPanel.vue";
import EventsPanel from "./cards/EventsPanel.vue";
import MentionsPanel from "./cards/MentionsPanel.vue";
import SignaturesPanel from "./cards/SignaturesPanel.vue";

function dayBounds(offsetDays) {
  var start = new Date();
  start.setHours(0, 0, 0, 0);
  start.setDate(start.getDate() + offsetDays);
  var end = new Date(start.getTime() + 86400000 - 1);
  return { start: start, end: end };
}

export default {
  name: "CardsView",
  components: { TasksPanel, ProjectsPanel, EventsPanel, MentionsPanel, SignaturesPanel },
  props: {
    tasks: { type: Array, default: function () { return []; } },
    projects: { type: Array, default: function () { return []; } },
    events: { type: Array, default: function () { return []; } },
    mentions: { type: Array, default: function () { return []; } },
    signatures: { type: Array, default: function () { return []; } },
  },
  computed: {
    openTasks: function () {
      return this.tasks.filter(function (t) { return !t.done; });
    },
    overdue: function () {
      var today = dayBounds(0);
      return this.openTasks.filter(function (t) {
        return t.duedate && new Date(t.duedate) < today.start;
      });
    },
    dueToday: function () {
      var today = dayBounds(0);
      return this.openTasks.filter(function (t) {
        if (!t.duedate) return false;
        var d = new Date(t.duedate);
        return d >= today.start && d <= today.end;
      });
    },
    dueTomorrow: function () {
      var tomorrow = dayBounds(1);
      return this.openTasks.filter(function (t) {
        if (!t.duedate) return false;
        var d = new Date(t.duedate);
        return d >= tomorrow.start && d <= tomorrow.end;
      });
    },
    // Strictly after tomorrow: the five buckets must be disjoint, or a card
    // due tomorrow is listed by both "Due tomorrow" and "Upcoming" and the
    // totals no longer add up to the employee's open work.
    upcoming: function () {
      var tomorrow = dayBounds(1);
      return this.openTasks.filter(function (t) {
        return t.duedate && new Date(t.duedate) > tomorrow.end;
      });
    },
    noDueDate: function () {
      return this.openTasks.filter(function (t) { return !t.duedate; });
    },
  },
};
</script>

<style scoped>
/* Flex, not grid: five equal grid columns cannot centre four items on a
   second row — that needs a half-column offset. Flex wrap plus
   justify-content: center gives it for free. */
.cards-view__grid {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-md);
  justify-content: center;
  align-items: flex-start;
}
.cards-view__grid > * {
  flex: 0 0 calc((100% - 4 * var(--spacing-md)) / 5);
}

/* Reuse the app's existing three breakpoints rather than adding a fourth.
   The height release below 900px lives in CardPanel.vue. */
@media (max-width: 900px) {
  .cards-view__grid > * {
    flex-basis: calc((100% - 2 * var(--spacing-md)) / 3);
  }
}
@media (max-width: 700px) {
  .cards-view__grid > * {
    flex-basis: calc((100% - var(--spacing-md)) / 2);
  }
}
@media (max-width: 600px) {
  .cards-view__grid > * {
    flex-basis: 100%;
  }
}
</style>
