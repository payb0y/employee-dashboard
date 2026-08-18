<template>
  <CardPanel title="Talk mentions" :total="mentions.length" empty="No unread mentions">
    <template #icon>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 12a8 8 0 0 1-8 8H7l-4 3v-6a8 8 0 0 1 8-8h2a8 8 0 0 1 8 3Z" />
      </svg>
    </template>

    <a
      v-for="m in mentions"
      :key="m.roomId"
      class="mentions-panel__row"
      :href="roomUrl(m.token)"
    >
      <span class="mentions-panel__name">{{ m.name }}</span>
      <span class="mentions-panel__hint">You were mentioned</span>
    </a>
  </CardPanel>
</template>

<script>
import CardPanel from "./CardPanel.vue";
import { generateUrl } from "@nextcloud/router";

export default {
  name: "MentionsPanel",
  components: { CardPanel },
  props: {
    mentions: { type: Array, default: function () { return []; } },
  },
  methods: {
    roomUrl: function (token) {
      return generateUrl("/call/" + token);
    },
  },
};
</script>

<style scoped>
.mentions-panel__row {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 6px;
  border-radius: var(--radius-sm);
}
.mentions-panel__row:hover { background: var(--bg-subtle); }
.mentions-panel__name {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-primary);
}
.mentions-panel__hint {
  font-size: 11px;
  color: var(--color-text-secondary);
}
</style>
