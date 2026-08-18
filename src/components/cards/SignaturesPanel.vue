<template>
  <CardPanel title="To sign" :total="signatures.length" empty="Nothing awaiting your signature">
    <template #icon>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
        <path d="M14 2v6h6" />
      </svg>
    </template>

    <a
      v-for="s in signatures"
      :key="s.id"
      class="signatures-panel__row"
      :href="signUrl(s.uuid)"
    >
      <span class="signatures-panel__name">{{ s.fileName }}</span>
      <span class="signatures-panel__meta">From {{ s.requestedBy }} · {{ shortDate(s.createdAt) }}</span>
    </a>
  </CardPanel>
</template>

<script>
import CardPanel from "./CardPanel.vue";
import { generateUrl } from "@nextcloud/router";

export default {
  name: "SignaturesPanel",
  components: { CardPanel },
  props: {
    signatures: { type: Array, default: function () { return []; } },
  },
  methods: {
    signUrl: function (uuid) {
      return generateUrl("/apps/libresign/p/sign/" + uuid);
    },
    shortDate: function (value) {
      var d = new Date(value);
      if (isNaN(d.getTime())) return "";
      return d.toLocaleDateString(undefined, { day: "numeric", month: "short" });
    },
  },
};
</script>

<style scoped>
.signatures-panel__row {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 6px;
  border-radius: var(--radius-sm);
}
.signatures-panel__row:hover { background: var(--bg-subtle); }
.signatures-panel__name {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-primary);
}
.signatures-panel__meta {
  font-size: 11px;
  color: var(--color-text-secondary);
}
</style>
