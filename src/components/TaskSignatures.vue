<template>
  <details v-if="total" class="iz-panel iz-panel--list task-signatures">
    <summary class="task-signatures__summary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" /><path d="M14 2v6h6" />
      </svg>
      <strong>Signatures</strong>
      <span class="task-signatures__counts">{{ summary }}</span>
      <svg class="task-signatures__chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
    </summary>
    <div class="task-signatures__body" tabindex="0" role="region" aria-label="Signature documents">
      <section v-if="signatures.length">
        <h4 class="iz-label">Awaiting you</h4>
        <div v-for="doc in signatures" :key="doc.id" class="task-signatures__row">
          <div class="task-signatures__main">
            <strong>{{ doc.fileName }}</strong>
            <span class="task-signatures__meta">{{ requestedLabel(doc) }}</span>
          </div>
          <span class="iz-badge iz-badge--warning">Awaiting signature</span>
        </div>
      </section>
      <section v-if="waiting.length">
        <h4 class="iz-label">Waiting on others</h4>
        <div v-for="doc in waiting" :key="doc.id" class="task-signatures__row">
          <div class="task-signatures__main">
            <strong>{{ doc.fileName }}</strong>
            <span v-if="doc.projectName" class="task-signatures__meta">{{ doc.projectName }}</span>
            <div v-if="doc.signers && doc.signers.length" class="task-signatures__signers">
              <span v-for="(signer, index) in doc.signers" :key="index" class="iz-badge" :class="signer.signed ? 'iz-badge--success' : 'iz-badge--muted'">
                {{ signer.name }} · {{ signer.signed ? 'Signed' : 'Pending' }}
              </span>
            </div>
          </div>
          <div class="task-signatures__status">
            <span class="iz-badge iz-badge--cat-5">{{ progress(doc) }}</span>
            <span v-if="doc.daysWaiting != null" class="task-signatures__meta">{{ doc.daysWaiting }}{{ doc.daysWaiting === 1 ? ' day' : ' days' }}</span>
          </div>
        </div>
      </section>
      <section v-if="drafts.length">
        <h4 class="iz-label">Not sent yet</h4>
        <div v-for="doc in drafts" :key="doc.id" class="task-signatures__row">
          <div class="task-signatures__main"><strong>{{ doc.fileName }}</strong><span v-if="doc.projectName" class="task-signatures__meta">{{ doc.projectName }}</span></div>
          <span class="iz-badge iz-badge--muted">Draft</span>
        </div>
      </section>
    </div>
  </details>
</template>

<script>
export default {
  name: "TaskSignatures",
  props: {
    // These feeds have no reliable task/project association. Keep them outside
    // task filters so choosing a project or an empty task bucket cannot hide them.
    signatures: { type: Array, default: function () { return []; } },
    documents: { type: Array, default: function () { return []; } },
  },
  computed: {
    total: function () { return this.signatures.length + this.documents.length; },
    waiting: function () { return this.documents.filter(function (doc) { return doc.status !== 0; }); },
    drafts: function () { return this.documents.filter(function (doc) { return doc.status === 0; }); },
    summary: function () {
      var parts = [];
      if (this.signatures.length) parts.push(this.signatures.length + " awaiting you");
      if (this.waiting.length) parts.push(this.waiting.length + " waiting on others");
      if (this.drafts.length) parts.push(this.drafts.length + (this.drafts.length === 1 ? " draft" : " drafts"));
      return parts.join(" · ");
    },
  },
  methods: {
    progress: function (doc) {
      var signers = doc.signers || [];
      return signers.filter(function (signer) { return !!signer.signed; }).length + " of " + signers.length + " signed";
    },
    requestedLabel: function (doc) {
      var parts = [];
      if (doc.requestedBy) parts.push("Requested by " + doc.requestedBy);
      var date = doc.createdAt ? new Date(doc.createdAt) : null;
      if (date && !isNaN(date.getTime())) parts.push(date.toLocaleDateString(undefined, { day: "numeric", month: "short" }));
      return parts.join(" · ");
    },
  },
};
</script>

<style scoped>
.task-signatures { margin-bottom: var(--spacing-md); }
.task-signatures__summary { display: flex; align-items: center; gap: 10px; padding: 12px 14px; cursor: pointer; list-style: none; font-size: var(--iz-fs-md); }
.task-signatures__summary::-webkit-details-marker { display: none; }
.task-signatures__summary:hover { background: var(--bg-subtle); }
.task-signatures__summary:focus-visible { outline: 2px solid var(--accent); outline-offset: -2px; }
.task-signatures__summary > svg { flex-shrink: 0; }
.task-signatures__counts { flex: 1; color: var(--color-text-secondary); font-size: var(--iz-fs-sm); }
.task-signatures__chevron { margin-left: auto; color: var(--color-text-muted); }
.task-signatures[open] > summary > .task-signatures__chevron { transform: rotate(180deg); color: var(--accent); }
.task-signatures__summary:hover > .task-signatures__chevron { color: var(--accent); }
.task-signatures__body { padding: 4px 14px 12px; max-height: 360px; overflow-y: auto; }
.task-signatures__body:focus-visible { outline: 2px solid var(--accent); outline-offset: -2px; }
.task-signatures__body section + section { margin-top: var(--spacing-md); }
.task-signatures__body h4 { margin: 0 0 4px; }
.task-signatures__row { display: flex; align-items: flex-start; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--color-border); }
.task-signatures__row:last-child { border-bottom: 0; }
.task-signatures__main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 5px; }
.task-signatures__main strong { font-size: var(--iz-fs-md); font-weight: 600; overflow-wrap: anywhere; }
.task-signatures__meta { font-size: var(--iz-fs-sm); color: var(--color-text-secondary); overflow-wrap: anywhere; }
.task-signatures__signers { display: flex; flex-wrap: wrap; gap: 6px; }
.task-signatures__signers .iz-badge { white-space: normal; overflow-wrap: anywhere; }
.task-signatures__status { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; }
@media (max-width: 600px) {
  .task-signatures__summary { flex-wrap: wrap; }
  .task-signatures__counts { order: 4; flex-basis: 100%; }
  .task-signatures__row { flex-wrap: wrap; }
  .task-signatures__main { flex-basis: 100%; }
  .task-signatures__status { flex-direction: row; align-items: center; }
}
</style>
