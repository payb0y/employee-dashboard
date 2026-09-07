<template>
  <div class="pstudio__overlay" @click.self="$emit('close')">
    <div class="iz-panel pstudio">
      <div class="pstudio__head">
        <span class="pstudio__title" :title="fileName">{{ fileName || "Document" }}</span>
        <div class="pstudio__nav">
          <button type="button" class="iz-btn iz-btn--sm" :disabled="page <= 1 || busy" @click="go(page - 1)">‹</button>
          <span class="pstudio__pageno">Page {{ page }} / {{ pageCount || "?" }}</span>
          <button type="button" class="iz-btn iz-btn--sm" :disabled="page >= pageCount || busy" @click="go(page + 1)">›</button>
        </div>
        <button type="button" class="pstudio__x" @click="$emit('close')">✕</button>
      </div>

      <div class="pstudio__body">
        <aside class="pstudio__signers">
          <span class="pstudio__aside-label">Signers</span>
          <button
            v-for="(s, i) in signers"
            :key="s.signerKey"
            type="button"
            class="pstudio__card"
            :class="{
              'pstudio__card--selected': selected === s.signerKey,
              'pstudio__card--placed': !!local[s.signerKey],
            }"
            @click="selected = s.signerKey"
          >
            <span class="pstudio__card-dot" :style="{ background: colour(i) }"></span>
            <span class="pstudio__card-body">
              <span class="pstudio__card-name">{{ s.displayName || s.email }}</span>
              <span class="pstudio__card-state">
                {{ local[s.signerKey] ? "Placed on page " + local[s.signerKey].page : "Not placed" }}
              </span>
            </span>
            <span
              v-if="local[s.signerKey]"
              class="pstudio__card-clear"
              title="Remove placement"
              @click.stop="clear(s.signerKey)"
            >✕</span>
          </button>
          <span class="pstudio__hint">
            Pick a signer, then click the page to drop their signature box. Drag a
            box to move it.
          </span>
        </aside>

        <div class="pstudio__stage">
          <div v-if="error" class="pstudio__error">{{ error }}</div>
          <div v-else-if="busy" class="pstudio__loading">Rendering page…</div>
          <div
            v-show="!busy && !error"
            ref="page"
            class="pstudio__page"
            @click="place"
          >
            <canvas ref="canvas" class="pstudio__canvas"></canvas>
            <span
              v-for="(s, i) in placedOnThisPage"
              :key="s.signerKey"
              class="pstudio__box"
              :style="boxStyle(s, i)"
              @mousedown.stop.prevent="startDrag(s.signerKey, $event)"
            >{{ s.displayName || s.email }}</span>
          </div>
        </div>
      </div>

      <div class="pstudio__foot">
        <span class="pstudio__count">Placed {{ placedCount }} of {{ signers.length }}</span>
        <button type="button" class="iz-btn iz-btn--sm" @click="$emit('close')">Cancel</button>
        <button type="button" class="iz-btn iz-btn--sm iz-btn--primary" @click="save">Save placements</button>
      </div>
    </div>
  </div>
</template>

<script>
// Signature boxes are stored in PDF points, not pixels: the canvas is rendered
// at whatever size fits the screen, but the server places the element on the
// real page. Defaults match ProjectSigningService::normalizePlacements.
var BOX_W = 180;
var BOX_H = 60;
var COLOURS = ["--chart-5", "--chart-3", "--chart-4", "--chart-1", "--chart-2"];

export default {
  name: "PlacementStudio",
  props: {
    file: { type: [Object, File], default: null },
    fileName: { type: String, default: "" },
    signers: { type: Array, default: function () { return []; } },
    value: { type: Object, default: function () { return {}; } },
  },
  data: function () {
    return {
      local: JSON.parse(JSON.stringify(this.value || {})),
      selected: null,
      page: 1,
      pageCount: 0,
      busy: true,
      error: "",
      pageSize: { width: 595, height: 842 },
      drag: null,
    };
  },
  computed: {
    placedCount: function () {
      var local = this.local;
      return this.signers.filter(function (s) { return !!local[s.signerKey]; }).length;
    },
    placedOnThisPage: function () {
      var self = this;
      return this.signers.filter(function (s) {
        var p = self.local[s.signerKey];
        return p && p.page === self.page;
      });
    },
  },
  mounted: function () {
    if (this.signers.length) this.selected = this.signers[0].signerKey;
    this.load();
    document.addEventListener("mousemove", this.onDrag);
    document.addEventListener("mouseup", this.endDrag);
  },
  beforeDestroy: function () {
    document.removeEventListener("mousemove", this.onDrag);
    document.removeEventListener("mouseup", this.endDrag);
    if (this.doc) { try { this.doc.destroy(); } catch (e) { /* already gone */ } }
  },
  methods: {
    colour: function (i) {
      return "var(" + COLOURS[i % COLOURS.length] + ")";
    },
    /**
     * pdf.js is loaded on demand. It is by far the heaviest thing this app
     * depends on, and nobody who is not placing a signature should pay for it
     * in the main bundle — webpack splits it into its own chunk here.
     */
    load: async function () {
      if (!this.file) {
        this.error = "No document to place signatures on.";
        this.busy = false;
        return;
      }
      try {
        var pdfjs = await import(
          /* webpackChunkName: "pdfjs" */ "pdfjs-dist/legacy/build/pdf"
        );
        // The entry shim assigns window.pdfjsWorker, which pdf.js picks up by
        // itself. That avoids handing the bundler a worker URL to resolve —
        // the project app can write `?url` because it builds with Vite, and
        // this app does not. The cost is that the worker runs on the main
        // thread, which for placing a box on a page is not worth solving.
        await import(/* webpackChunkName: "pdfjs" */ "pdfjs-dist/legacy/build/pdf.worker.entry");

        var bytes = await this.readBytes(this.file);
        this.doc = await pdfjs.getDocument({ data: bytes }).promise;
        this.pageCount = this.doc.numPages;
        await this.render();
      } catch (e) {
        console.error("Could not open the PDF for placement", e);
        this.error = "Could not open this PDF. You can still send it without placements.";
        this.busy = false;
      }
    },
    readBytes: function (file) {
      return new Promise(function (resolve, reject) {
        var reader = new FileReader();
        reader.onload = function () { resolve(new Uint8Array(reader.result)); };
        reader.onerror = function () { reject(reader.error); };
        reader.readAsArrayBuffer(file);
      });
    },
    go: function (n) {
      if (n < 1 || n > this.pageCount) return;
      this.page = n;
      this.render();
    },
    render: async function () {
      if (!this.doc) return;
      this.busy = true;
      try {
        var page = await this.doc.getPage(this.page);
        var base = page.getViewport({ scale: 1 });
        // Remember the real page in points — every placement is expressed
        // against this, not against however large the canvas happens to be.
        this.pageSize = { width: base.width, height: base.height };

        var maxWidth = 640;
        var scale = Math.min(maxWidth / base.width, 1.6);
        var viewport = page.getViewport({ scale: scale });
        var canvas = this.$refs.canvas;
        canvas.width = Math.floor(viewport.width);
        canvas.height = Math.floor(viewport.height);
        canvas.style.width = Math.floor(viewport.width) + "px";
        canvas.style.height = Math.floor(viewport.height) + "px";
        await page.render({ canvasContext: canvas.getContext("2d"), viewport: viewport }).promise;
      } catch (e) {
        console.error("Page render failed", e);
        this.error = "Could not render page " + this.page + ".";
      }
      this.busy = false;
    },
    // Canvas pixels -> PDF points, so a placement means the same thing however
    // the page happened to be scaled on screen.
    toPoints: function (px, py) {
      var canvas = this.$refs.canvas;
      return {
        x: (px / canvas.clientWidth) * this.pageSize.width,
        y: (py / canvas.clientHeight) * this.pageSize.height,
      };
    },
    clampBox: function (pt) {
      return {
        left: Math.round(Math.max(0, Math.min(this.pageSize.width - BOX_W, pt.x - BOX_W / 2))),
        top: Math.round(Math.max(0, Math.min(this.pageSize.height - BOX_H, pt.y - BOX_H / 2))),
      };
    },
    place: function (event) {
      if (!this.selected || this.busy) return;
      var rect = this.$refs.canvas.getBoundingClientRect();
      var box = this.clampBox(this.toPoints(event.clientX - rect.left, event.clientY - rect.top));
      this.$set(this.local, this.selected, {
        signerKey: this.selected,
        type: "signature",
        page: this.page,
        left: box.left,
        top: box.top,
        width: BOX_W,
        height: BOX_H,
      });
      this.advance();
    },
    // Placing one signer moves to the next one still missing, so a three-signer
    // document is three clicks rather than three clicks and two selections.
    advance: function () {
      var local = this.local;
      var next = this.signers.filter(function (s) { return !local[s.signerKey]; })[0];
      if (next) this.selected = next.signerKey;
    },
    clear: function (key) {
      this.$delete(this.local, key);
      this.selected = key;
    },
    boxStyle: function (signer, i) {
      var p = this.local[signer.signerKey];
      var canvas = this.$refs.canvas;
      if (!p || !canvas) return { display: "none" };
      var sx = canvas.clientWidth / this.pageSize.width;
      var sy = canvas.clientHeight / this.pageSize.height;
      var index = this.signers.findIndex(function (s) { return s.signerKey === signer.signerKey; });
      return {
        left: p.left * sx + "px",
        top: p.top * sy + "px",
        width: p.width * sx + "px",
        height: p.height * sy + "px",
        color: this.colour(index < 0 ? i : index),
      };
    },
    startDrag: function (key, event) {
      var p = this.local[key];
      if (!p) return;
      var rect = this.$refs.canvas.getBoundingClientRect();
      var start = this.toPoints(event.clientX - rect.left, event.clientY - rect.top);
      this.selected = key;
      this.drag = { key: key, dx: start.x - p.left, dy: start.y - p.top };
    },
    onDrag: function (event) {
      if (!this.drag || !this.$refs.canvas) return;
      var rect = this.$refs.canvas.getBoundingClientRect();
      var pt = this.toPoints(event.clientX - rect.left, event.clientY - rect.top);
      var p = this.local[this.drag.key];
      this.$set(this.local, this.drag.key, Object.assign({}, p, {
        left: Math.round(Math.max(0, Math.min(this.pageSize.width - p.width, pt.x - this.drag.dx))),
        top: Math.round(Math.max(0, Math.min(this.pageSize.height - p.height, pt.y - this.drag.dy))),
      }));
    },
    endDrag: function () {
      this.drag = null;
    },
    save: function () {
      this.$emit("input", this.local);
      this.$emit("done");
    },
  },
};
</script>

<style scoped>
.pstudio__overlay {
  position: fixed;
  inset: 0;
  z-index: 10001;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  padding: var(--spacing-md, 16px);
  overflow: auto;
}

.pstudio {
  margin: auto;
  width: 100%;
  max-width: 940px;
  padding: 0;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.pstudio__head {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 14px;
  border-bottom: 1px solid var(--color-border);
}

.pstudio__title {
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text-primary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 340px;
}

.pstudio__nav {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-left: auto;
}

.pstudio__pageno {
  font-size: 11.5px;
  color: var(--color-text-secondary);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.pstudio__x {
  border: none;
  background: none;
  color: var(--color-text-muted);
  cursor: pointer;
  font-size: 16px;
  line-height: 1;
  padding: 2px 4px;
  font-family: inherit;
  min-height: 0;
}
.pstudio__x:hover { color: var(--color-text-primary); }

.pstudio__body {
  display: grid;
  grid-template-columns: 210px 1fr;
  gap: 14px;
  padding: 14px;
  align-items: start;
}

.pstudio__signers {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.pstudio__aside-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

/* Buttons here need their own reset: the #employee-dashboard-root block in
   Dashboard.vue never applies, because Vue replaces the mount element. */
.pstudio__card {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  text-align: left;
  font-family: inherit;
  padding: 7px 9px;
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--bg-card);
  cursor: pointer;
  min-height: 0;
}
.pstudio__card:hover { background: var(--bg-subtle); }
.pstudio__card--selected { border-color: var(--accent); background: var(--accent-bg); }
.pstudio__card--placed { border-color: var(--color-success); }

.pstudio__card-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  flex-shrink: 0;
}

.pstudio__card-body {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}

.pstudio__card-name {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-primary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pstudio__card-state {
  font-size: 10.5px;
  color: var(--color-text-secondary);
}

.pstudio__card-clear {
  color: var(--color-text-muted);
  font-size: 11px;
  flex-shrink: 0;
}
.pstudio__card-clear:hover { color: var(--color-danger-text); }

.pstudio__hint {
  font-size: 11px;
  color: var(--color-text-muted);
  line-height: 1.45;
  margin-top: 4px;
}

.pstudio__stage {
  display: flex;
  justify-content: center;
  min-height: 320px;
  background: var(--bg-inset);
  border-radius: var(--radius-sm);
  padding: 12px;
  overflow: auto;
}

.pstudio__loading,
.pstudio__error {
  font-size: 12.5px;
  color: var(--color-text-secondary);
  margin: auto;
  text-align: center;
}
.pstudio__error { color: var(--color-badge-danger-text); }

.pstudio__page {
  position: relative;
  cursor: crosshair;
  align-self: flex-start;
  line-height: 0;
}

.pstudio__canvas {
  display: block;
  box-shadow: var(--shadow-card);
  background: #fff;
}

.pstudio__box {
  position: absolute;
  border: 1.5px dashed currentColor;
  border-radius: 3px;
  background: color-mix(in oklab, currentColor 14%, transparent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 600;
  line-height: 1.2;
  overflow: hidden;
  padding: 0 4px;
  cursor: grab;
  text-align: center;
}
.pstudio__box:active { cursor: grabbing; }

.pstudio__foot {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 11px 14px;
  border-top: 1px solid var(--color-border);
}

.pstudio__count {
  font-size: 12px;
  color: var(--color-text-secondary);
  margin-right: auto;
}

@media (max-width: 760px) {
  .pstudio__body { grid-template-columns: 1fr; }
}
</style>
