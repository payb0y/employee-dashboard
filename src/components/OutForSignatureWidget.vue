<template>
  <!-- Unlike WaitingOnYouWidget, this one does NOT vanish when it is empty.
       That panel only reports; this one also carries the action that creates
       its own contents, and a self-hiding panel hides the button with it —
       someone who has never sent a document could never start. So it collapses
       to its header instead: one row, the action still there. -->
  <section class="iz-panel out-signature" :class="{ 'out-signature--empty': total === 0 }">
    <div class="out-signature__header">
      <div class="out-signature__icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m22 2-7 20-4-9-9-4Z" />
          <path d="M22 2 11 13" />
        </svg>
      </div>
      <span class="out-signature__title">Out for signature</span>
      <span v-if="total > 0" class="iz-badge iz-badge--cat-5 out-signature__count">{{ total }}</span>
      <span v-else class="out-signature__clear">Nothing awaiting a signature</span>
      <!-- Hands off rather than composing: signer invitation, field placement
           and identity checks are built already, in organization, in
           projectcreatoraio and in the Signatures app's own screens. -->
      <a class="iz-btn iz-btn--sm out-signature__send" :href="appUrl">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Send a document
      </a>
    </div>

    <!-- Drafts first, and apart. A draft has no signers, so it cannot be
         waiting on anyone — the sender never sent it. Filed with the chased
         documents it would hide the one row you can clear yourself. -->
    <div v-if="drafts.length" class="out-signature__group">
      <div class="out-signature__group-head">
        <span class="out-signature__group-name">Not sent yet</span>
        <span class="out-signature__group-n">{{ drafts.length }}</span>
      </div>
      <a
        v-for="doc in shownDrafts"
        :key="doc.id"
        class="out-signature__row"
        :href="docUrl"
      >
        <span class="out-signature__main">
          <span class="out-signature__name">{{ doc.fileName }}</span>
          <span class="out-signature__meta">Never sent — no signers yet</span>
        </span>
        <span class="out-signature__age out-signature__age--draft">Draft</span>
      </a>
      <button
        v-if="drafts.length > limit"
        type="button"
        class="out-signature__more"
        @click="$emit('switch-view', 'cards')"
      >+{{ drafts.length - limit }} more in Cards</button>
    </div>

    <div v-if="waiting.length" class="out-signature__group">
      <div class="out-signature__group-head">
        <span class="out-signature__group-name">Waiting on others</span>
        <span class="out-signature__group-n">{{ waiting.length }}</span>
      </div>
      <a
        v-for="doc in shownWaiting"
        :key="doc.id"
        class="out-signature__row"
        :href="docUrl"
      >
        <span class="out-signature__main">
          <span class="out-signature__name">{{ doc.fileName }}</span>
          <span class="out-signature__signers">
            <span
              v-for="(signer, i) in doc.signers"
              :key="i"
              class="out-signature__chip"
              :class="{ 'out-signature__chip--done': signer.signed }"
              :title="signerTitle(signer)"
            >
              <svg
                v-if="signer.signed"
                class="out-signature__tick"
                xmlns="http://www.w3.org/2000/svg"
                width="10" height="10" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="3"
                stroke-linecap="round" stroke-linejoin="round"
              >
                <polyline points="20 6 9 17 4 12" />
              </svg>
              {{ signer.name }}
            </span>
            <span v-if="doc.projectName" class="out-signature__project">{{ doc.projectName }}</span>
          </span>
        </span>
        <span class="out-signature__age" :class="ageClass(doc.daysWaiting)">{{ doc.daysWaiting }}d</span>
      </a>
      <button
        v-if="waiting.length > limit"
        type="button"
        class="out-signature__more"
        @click="$emit('switch-view', 'cards')"
      >+{{ waiting.length - limit }} more in Cards</button>
    </div>
  </section>
</template>

<script>
import { generateUrl } from "@nextcloud/router";

export default {
  name: "OutForSignatureWidget",
  props: {
    // Already sorted longest-wait-first by the service, drafts last, and never
    // narrowed by activeProjectId: most documents carry no project at all, so
    // scoping this list would empty it the moment a project is selected.
    documents: { type: Array, default: function () { return []; } },
  },
  data: function () {
    // A summary, not an inbox. Past this the Cards view holds the full list.
    return { limit: 3 };
  },
  computed: {
    total: function () {
      return this.documents.length;
    },
    drafts: function () {
      return this.documents.filter(function (d) { return d.status === 0; });
    },
    waiting: function () {
      return this.documents.filter(function (d) { return d.status !== 0; });
    },
    shownDrafts: function () {
      return this.drafts.slice(0, this.limit);
    },
    shownWaiting: function () {
      return this.waiting.slice(0, this.limit);
    },
    // `signatures`, not `libresign`: this stack ships LibreSign rebranded, so
    // the app id is signatures while its tables stay oc_libresign_*. Same
    // literal as SignaturesPanel and adminpage's ProjectDetailsPanel.
    appUrl: function () {
      return generateUrl("/apps/signatures/");
    },
    // The document list rather than a per-file deep link: the Signatures SPA
    // resolves its own sub-routes client-side and none was confirmed to work
    // for a sender's status view, so this stops at a page known to exist.
    docUrl: function () {
      return generateUrl("/apps/signatures/f/");
    },
  },
  methods: {
    // Under a fortnight is quiet, then it warns, then it goes red. The whole
    // point of this panel is noticing the one that fell through a crack.
    ageClass: function (days) {
      if (days >= 30) return "out-signature__age--bad";
      if (days >= 14) return "out-signature__age--warn";
      return "out-signature__age--fresh";
    },
    signerTitle: function (signer) {
      var how = signer.via === "email" ? "by email" : "by account";
      return signer.signed
        ? signer.name + " — signed (" + how + ")"
        : signer.name + " — not signed yet (" + how + ")";
    },
  },
};
</script>

<style scoped>
/* Chrome is .iz-panel; everything here is layout plus the two chip tones. */
.out-signature {
  margin-bottom: var(--spacing-xl, 32px);
}

/* Collapsed to its header: no body, so the header's bottom margin would be
   dead space under the only row. */
.out-signature--empty .out-signature__header {
  margin-bottom: 0;
}

.out-signature__clear {
  font-size: 12px;
  color: var(--color-text-muted);
}

.out-signature__header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.out-signature__icon {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  background: var(--chart-5-bg);
  color: var(--chart-5);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.out-signature__title {
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text-primary);
}

/* The button takes the right edge; the count stays beside the title. */
.out-signature__send {
  margin-left: auto;
  flex-shrink: 0;
  gap: 5px;
  text-decoration: none;
}

.out-signature__group + .out-signature__group {
  margin-top: 10px;
  padding-top: 9px;
  border-top: 1px solid var(--color-border);
}

.out-signature__group-head {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
}

.out-signature__group-name {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.out-signature__group-n {
  margin-left: auto;
  font-size: 11px;
  font-weight: 700;
  color: var(--color-text-muted);
  font-variant-numeric: tabular-nums;
}

/* The #employee-dashboard-root resets never apply — Vue replaces the mount
   element — so the anchor and the button undo Nextcloud core themselves. */
.out-signature__row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 7px 6px;
  border-radius: var(--radius-sm);
  color: inherit;
  text-decoration: none;
}
.out-signature__row:hover {
  background: var(--bg-subtle);
}

.out-signature__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.out-signature__name {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-primary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.out-signature__meta {
  font-size: 11px;
  color: var(--color-text-secondary);
}

.out-signature__signers {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.out-signature__chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  max-width: 190px;
  padding: 2px 8px;
  border-radius: var(--radius-pill);
  background: var(--bg-subtle);
  color: var(--color-text-secondary);
  font-size: 11px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.out-signature__chip--done {
  background: var(--color-badge-success-bg);
  color: var(--color-badge-success-text);
}

.out-signature__tick {
  flex-shrink: 0;
}

/* Only on the ~40% of documents that were sent through a project. */
.out-signature__project {
  display: inline-flex;
  align-items: center;
  padding: 2px 2px;
  font-size: 11px;
  color: var(--color-text-muted);
  max-width: 160px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.out-signature__age {
  flex-shrink: 0;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: var(--radius-pill);
  font-variant-numeric: tabular-nums;
}
.out-signature__age--fresh {
  background: var(--bg-subtle);
  color: var(--color-text-secondary);
}
.out-signature__age--warn {
  background: var(--color-badge-warning-bg);
  color: var(--color-badge-warning-text);
}
.out-signature__age--bad {
  background: var(--color-badge-danger-bg);
  color: var(--color-badge-danger-text);
}
.out-signature__age--draft {
  background: var(--accent-bg);
  color: var(--accent-on-bg);
}

.out-signature__more {
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
.out-signature__more:hover {
  background: var(--bg-subtle);
}

@media (max-width: 700px) {
  .out-signature__send span {
    display: none;
  }
}
</style>
