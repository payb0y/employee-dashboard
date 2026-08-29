<template>
  <!-- Absent, not empty. The Cards view keeps a "No unread mentions" state
       because its wall needs every cell to hold its height; the overview is a
       vertical stack, where a row that says "nothing" on most days is a row
       people learn to scroll past. So the whole panel goes when both feeds are. -->
  <section v-if="total > 0" class="iz-panel waiting-widget">
    <div class="waiting-widget__header">
      <div class="waiting-widget__icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 11V6a2 2 0 0 0-4 0v5" />
          <path d="M14 10V4a2 2 0 0 0-4 0v6" />
          <path d="M10 10.5V6a2 2 0 0 0-4 0v8" />
          <path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2a8 8 0 0 1-8-8" />
        </svg>
      </div>
      <span class="waiting-widget__title">Waiting on you</span>
      <span class="iz-badge iz-badge--warning waiting-widget__count">{{ total }}</span>
    </div>

    <!-- Two columns where there is width for them, one below 700px. Talk and
         LibreSign are unrelated systems, but from this side they are one
         sentence — someone is blocked on me — so they share a panel. -->
    <div class="waiting-widget__cols">
      <div v-if="mentions.length" class="waiting-widget__group">
        <div class="waiting-widget__group-head">
          <svg class="waiting-widget__group-icon waiting-widget__group-icon--talk" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12a8 8 0 0 1-8 8H7l-4 3v-6a8 8 0 0 1 8-8h2a8 8 0 0 1 8 3Z" />
          </svg>
          <span class="waiting-widget__group-name">Talk mentions</span>
          <span class="waiting-widget__group-n">{{ mentions.length }}</span>
        </div>
        <a
          v-for="m in shownMentions"
          :key="m.roomId"
          class="waiting-widget__row"
          :href="roomUrl(m.token)"
        >
          <span class="waiting-widget__row-title">{{ m.name }}</span>
          <span class="waiting-widget__row-meta">You were mentioned</span>
        </a>
        <button
          v-if="mentions.length > limit"
          type="button"
          class="waiting-widget__more"
          @click="$emit('switch-view', 'cards')"
        >+{{ mentions.length - limit }} more in Cards</button>
      </div>

      <div v-if="signatures.length" class="waiting-widget__group">
        <div class="waiting-widget__group-head">
          <svg class="waiting-widget__group-icon waiting-widget__group-icon--sign" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
            <path d="M14 2v6h6" />
          </svg>
          <span class="waiting-widget__group-name">To sign</span>
          <span class="waiting-widget__group-n">{{ signatures.length }}</span>
        </div>
        <a
          v-for="s in shownSignatures"
          :key="s.id"
          class="waiting-widget__row"
          :href="signUrl(s.uuid)"
        >
          <span class="waiting-widget__row-title">{{ s.fileName }}</span>
          <span class="waiting-widget__row-meta">From {{ s.requestedBy }} · {{ shortDate(s.createdAt) }}</span>
        </a>
        <button
          v-if="signatures.length > limit"
          type="button"
          class="waiting-widget__more"
          @click="$emit('switch-view', 'cards')"
        >+{{ signatures.length - limit }} more in Cards</button>
      </div>
    </div>
  </section>
</template>

<script>
import { generateUrl } from "@nextcloud/router";

export default {
  name: "WaitingOnYouWidget",
  props: {
    // Both raw, never project-filtered: neither feed carries a projectId, so
    // activeProjectId has nothing to narrow — the same reasoning that sends
    // upcomingEvents to FocusNowWidget unfiltered.
    mentions: { type: Array, default: function () { return []; } },
    signatures: { type: Array, default: function () { return []; } },
  },
  data: function () {
    // A summary, not an inbox: past this the Cards view has both full panels,
    // which is what keeps this to one tidy row at typical volume.
    return { limit: 3 };
  },
  computed: {
    total: function () {
      return this.mentions.length + this.signatures.length;
    },
    shownMentions: function () {
      return this.mentions.slice(0, this.limit);
    },
    shownSignatures: function () {
      return this.signatures.slice(0, this.limit);
    },
  },
  methods: {
    roomUrl: function (token) {
      return generateUrl("/call/" + token);
    },
    // Two things are easy to get wrong here.
    //
    // The app id is `signatures`, not `libresign`. This stack ships LibreSign
    // rebranded — appinfo/info.xml says <id>signatures</id> and occ lists it
    // under that name — while its tables keep the oc_libresign_* prefix, which
    // is what makes `libresign` look right. Measured against the running
    // instance: /apps/signatures/p/sign/{uuid} answers, /apps/libresign/... 404s.
    //
    // And the uuid is the per-signer sign_request's, never libresign_file's —
    // only that one resolves for the person being asked to sign.
    signUrl: function (uuid) {
      return generateUrl("/apps/signatures/p/sign/" + uuid);
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
/* Chrome is .iz-panel. Everything here is layout, plus the two group tints. */
.waiting-widget {
  margin-bottom: var(--spacing-xl, 32px);
}

.waiting-widget__header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.waiting-widget__icon {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  background: var(--color-badge-warning-bg);
  color: var(--color-badge-warning-text);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.waiting-widget__title {
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text-primary);
}

.waiting-widget__count {
  margin-left: auto;
}

.waiting-widget__cols {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--spacing-md, 16px);
}

/* A single group must not sit in half the panel with the other half empty. */
.waiting-widget__group:only-child {
  grid-column: 1 / -1;
}

.waiting-widget__group-head {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
}

.waiting-widget__group-icon {
  flex-shrink: 0;
}
.waiting-widget__group-icon--talk { color: var(--chart-3); }
.waiting-widget__group-icon--sign { color: var(--chart-5); }

.waiting-widget__group-name {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.waiting-widget__group-n {
  margin-left: auto;
  font-size: 11px;
  font-weight: 700;
  color: var(--color-text-muted);
  font-variant-numeric: tabular-nums;
}

/* The #employee-dashboard-root resets never apply — Vue replaces the mount
   element — so anchors and buttons have to undo Nextcloud core here. */
.waiting-widget__row {
  display: flex;
  flex-direction: column;
  gap: 1px;
  padding: 6px;
  border-radius: var(--radius-sm);
  color: inherit;
  text-decoration: none;
}
.waiting-widget__row:hover {
  background: var(--bg-subtle);
}

.waiting-widget__row-title {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-primary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.waiting-widget__row-meta {
  font-size: 11px;
  color: var(--color-text-secondary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.waiting-widget__more {
  display: block;
  font-family: inherit;
  font-size: 11px;
  font-weight: 600;
  text-align: left;
  padding: 5px 6px;
  border: none;
  border-radius: var(--radius-sm);
  background: none;
  color: var(--accent);
  cursor: pointer;
  min-height: 0;
}
.waiting-widget__more:hover {
  background: var(--bg-subtle);
}

@media (max-width: 700px) {
  .waiting-widget__cols {
    grid-template-columns: 1fr;
  }
}
</style>
