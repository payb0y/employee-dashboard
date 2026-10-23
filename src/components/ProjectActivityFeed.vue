<template>
  <!-- A project's activity as projectcreatoraio's Activity tab shows it —
       source filter, day groups, one line per event — read a page at a time
       from projectcreatoraio's own API, plus folding of repeated updates. -->
  <div class="paf">
    <div class="paf__filters">
      <div class="paf__chips" role="group" aria-label="Filter by source">
        <button
          v-for="option in sourceOptions"
          :key="'source-' + option.value"
          type="button"
          class="iz-chip"
          :class="{ 'iz-chip--active': source === option.value }"
          :aria-pressed="String(source === option.value)"
          @click="selectSource(option.value)"
        >
          <span v-if="option.value" class="paf__dot" :class="option.toneClass"><span class="iz-dot"></span></span>
          {{ option.label }}
        </button>
      </div>
      <label class="paf__fold">
        <input v-model="fold" type="checkbox" />
        Fold repeated updates
      </label>
    </div>

    <div v-if="loading && !events.length" class="paf__state" role="status">Loading activity…</div>
    <div v-else-if="error && !events.length" class="paf__state" role="alert">
      <span>{{ error }}</span>
      <button type="button" class="iz-btn iz-btn--sm" @click="reload">Try again</button>
    </div>
    <div v-else-if="!events.length" class="paf__state">
      {{ source ? "No activity from this source yet." : "No activity yet." }}
    </div>

    <template v-else>
      <!-- Its own scroll box, so each day's header stays in view. -->
      <div class="paf__scroll">
        <section v-for="day in days" :key="day.day" class="paf__day" :aria-label="day.label">
          <h5 class="paf__day-title">
            {{ day.label }}
            <span class="paf__day-count">{{ day.eventCount }} {{ day.eventCount === 1 ? "event" : "events" }}</span>
          </h5>
          <ol class="paf__list">
            <li v-for="line in day.lines" :key="line.id">
              <div class="paf__row">
                <time class="paf__time" :datetime="line.events[0].occurredAt">{{ line.events.length > 1 ? line.oldest + "–" + line.newest : line.newest }}</time>
                <img v-if="line.actorUid" class="paf__avatar" :src="avatarUrl(line.actorUid)" alt="" width="32" height="32" loading="lazy" />
                <span v-else class="paf__avatar paf__avatar--none" aria-hidden="true">?</span>
                <p class="paf__text">
                  <strong>{{ line.actorName }}</strong>{{ " " }}<span>{{ line.text }}</span>
                  <template v-if="line.events.length > 1">
                    <span class="paf__count">{{ line.events.length }}×</span>
                    <button
                      type="button"
                      class="paf__more"
                      :aria-expanded="String(!!open[line.id])"
                      @click="toggle(line.id)"
                    >{{ open[line.id] ? "Hide" : "Show all" }}</button>
                  </template>
                </p>
                <span class="iz-pill paf__source" :class="sourceTone(line.source)">{{ line.sourceLabel }}</span>
              </div>
              <ol v-if="line.events.length > 1 && open[line.id]" class="paf__list paf__list--sub" :aria-label="'Each of the ' + line.events.length + ' updates'">
                <li v-for="item in line.events" :key="item.id" class="paf__sub">
                  <time class="paf__time" :datetime="item.occurredAt">{{ item.time }}</time>
                  <span class="paf__sub-text">{{ line.actorName }} {{ line.text }}</span>
                </li>
              </ol>
            </li>
          </ol>
        </section>
      </div>

      <div class="iz-pagination paf__footer">
        <span>Showing {{ events.length }} {{ events.length === 1 ? "event" : "events" }}, back to {{ oldest }}</span>
        <span v-if="error" class="paf__footer-error" role="alert">{{ error }}</span>
        <button v-if="hasMore" type="button" class="iz-btn" :disabled="loading" @click="loadOlder">
          {{ loading ? "Loading…" : "Load older activity" }}
        </button>
        <span v-else class="paf__start">Start of this project's activity</span>
      </div>
    </template>
  </div>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";
import { SOURCES, buildFeed, lineCount, oldestLabel } from "../lib/activityFeed.mjs";

// The API's largest page.
var PAGE = 100;
// Each load reaches back until this many more lines show, so a folded run of
// a hundred autosaves still brings in something new.
var LINES_PER_LOAD = 12;
// …but never more than this many requests for one click.
var MAX_REQUESTS = 4;
// Sources take the theme's category colours, as in projectcreatoraio.
var SOURCE_TONES = {
  internal: "iz-pill--cat-2",
  deck: "iz-pill--cat-1",
  files: "iz-pill--cat-3",
  talk: "iz-pill--cat-4",
  whiteboard: "iz-pill--cat-5",
};
var DOT_TONES = {
  internal: "paf__dot--cat-2",
  deck: "paf__dot--cat-1",
  files: "paf__dot--cat-3",
  talk: "paf__dot--cat-4",
  whiteboard: "paf__dot--cat-5",
};

export default {
  name: "ProjectActivityFeed",
  props: {
    projectId: { type: [Number, String], default: null },
  },
  data: function () {
    return {
      events: [],
      nextCursor: null,
      hasMore: false,
      loading: false,
      error: "",
      source: "",
      fold: true,
      open: {},
      request: 0,
    };
  },
  computed: {
    sourceOptions: function () {
      return [{ value: "", label: "All sources" }].concat(
        SOURCES.map(function (s) {
          return { value: s.value, label: s.label, toneClass: DOT_TONES[s.value] };
        })
      );
    },
    days: function () {
      return buildFeed(this.events, { fold: this.fold });
    },
    oldest: function () {
      return oldestLabel(this.events);
    },
  },
  watch: {
    projectId: {
      immediate: true,
      handler: function () {
        this.reload();
      },
    },
    fold: function () {
      this.open = {};
    },
  },
  methods: {
    avatarUrl: function (uid) {
      return generateUrl("/avatar/{uid}/64", { uid: uid });
    },
    sourceTone: function (source) {
      return SOURCE_TONES[source] || "iz-pill--muted";
    },
    selectSource: function (value) {
      if (this.source === value) return;
      this.source = value;
      this.reload();
    },
    toggle: function (id) {
      this.open = Object.assign({}, this.open, { [id]: !this.open[id] });
    },
    reload: function () {
      this.request += 1;
      this.events = [];
      this.nextCursor = null;
      this.hasMore = false;
      this.open = {};
      this.error = "";
      if (this.projectId === null || this.projectId === undefined || this.projectId === "") return;
      this.fill(LINES_PER_LOAD, this.request);
    },
    loadOlder: function () {
      if (this.loading || !this.hasMore) return;
      this.fill(lineCount(this.events, this.fold) + LINES_PER_LOAD, this.request);
    },
    // Fetch pages until `target` lines show, the activity runs out, or
    // MAX_REQUESTS pages have come in.
    fill: async function (target, request) {
      this.loading = true;
      this.error = "";
      var url = generateUrl("/apps/projectcreatoraio/api/v1/projects/{id}/activity", { id: this.projectId });
      try {
        for (var i = 0; i < MAX_REQUESTS; i++) {
          var params = { limit: PAGE };
          if (this.nextCursor) params.cursor = this.nextCursor;
          if (this.source) params.source = this.source;
          var response = await axios.get(url, { params: params, headers: { "OCS-APIRequest": "true" } });
          if (request !== this.request) return;
          var data = response.data || {};
          if (!Array.isArray(data.events)) throw new Error("Invalid activity response");
          this.events = this.events.concat(data.events);
          this.nextCursor = data.nextCursor || null;
          this.hasMore = !!data.hasMore && !!this.nextCursor;
          if (!this.hasMore || lineCount(this.events, this.fold) >= target) break;
        }
      } catch (e) {
        if (request !== this.request) return;
        var status = e && e.response ? e.response.status : 0;
        this.error =
          status === 403 || status === 404
            ? "This project's activity isn't available here."
            : this.events.length
              ? "Older activity could not be loaded. Try again."
              : "Activity could not be loaded.";
      } finally {
        if (request === this.request) this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.paf {
  display: flex;
  flex-direction: column;
  min-width: 0;
  container-type: inline-size;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  overflow: clip;
}

.paf__filters {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px 16px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--color-border);
}
.paf__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.paf__dot {
  display: inline-flex;
}
.paf__dot--cat-1 { color: var(--iz-cat-1); }
.paf__dot--cat-2 { color: var(--iz-cat-2); }
.paf__dot--cat-3 { color: var(--iz-cat-3); }
.paf__dot--cat-4 { color: var(--iz-cat-4); }
.paf__dot--cat-5 { color: var(--iz-cat-5); }
.paf__fold {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-left: auto;
  min-height: 32px;
  font-size: var(--iz-fs-md);
  color: var(--color-text-primary);
  cursor: pointer;
}

.paf__state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 40px 16px;
  font-size: var(--iz-fs-md);
  color: var(--color-text-secondary);
  text-align: center;
}

.paf__scroll {
  max-height: 560px;
  overflow-y: auto;
}
.paf__day-title {
  position: sticky;
  top: 0;
  z-index: 1;
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  margin: 0;
  padding: 8px 16px;
  border-bottom: 1px solid var(--color-border);
  background: var(--bg-subtle);
  font-size: var(--iz-fs-micro);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--color-text-secondary);
}
.paf__day-count {
  font-weight: 500;
  text-transform: none;
  letter-spacing: 0;
}
.paf__list {
  margin: 0;
  padding: 0;
  list-style: none;
}
.paf__row {
  display: grid;
  grid-template-columns: 92px 32px minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px;
  padding: 10px 16px;
  border-bottom: 1px solid var(--color-border);
}
.paf__time {
  font-size: var(--iz-fs-xs);
  color: var(--color-text-secondary);
  font-variant-numeric: tabular-nums;
}
.paf__avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
  background: var(--bg-inset);
}
.paf__avatar--none {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--iz-fs-sm);
  font-weight: 700;
  color: var(--color-text-secondary);
}
.paf__text {
  margin: 0;
  min-width: 0;
  font-size: var(--iz-fs-md);
  line-height: 1.45;
  overflow-wrap: anywhere;
}
.paf__text strong {
  font-weight: 600;
}
.paf__text > span:not(.paf__count) {
  color: var(--color-text-secondary);
}
.paf__count {
  display: inline-flex;
  margin-left: 6px;
  padding: 1px 7px;
  border-radius: var(--radius-pill);
  background: var(--bg-inset);
  font-size: var(--iz-fs-xs);
  font-weight: 700;
  color: var(--color-text-secondary);
}
.iz-app .paf__more {
  min-height: 0;
  margin: 0 0 0 8px;
  padding: 0;
  border: 0;
  background: none;
  font-size: var(--iz-fs-sm);
  font-weight: 600;
  color: var(--accent);
  cursor: pointer;
}
.iz-app .paf__more:hover {
  text-decoration: underline;
}
.paf__source {
  text-transform: none;
}
.paf__list--sub {
  background: var(--bg-subtle);
}
.paf__sub {
  display: grid;
  grid-template-columns: 92px minmax(0, 1fr);
  gap: 12px;
  padding: 6px 16px 6px 16px;
  border-bottom: 1px solid var(--color-border);
}
.paf__sub-text {
  padding-left: 44px;
  font-size: var(--iz-fs-sm);
  color: var(--color-text-secondary);
}

.paf__footer {
  flex-wrap: wrap;
  padding: 12px 16px;
  border-top: 1px solid var(--color-border);
}
.paf__footer-error {
  color: var(--color-danger-text);
}
.paf__start {
  font-weight: 600;
}

/* Narrow: avatar left, the sentence, then time, count and source under it —
   projectcreatoraio's Activity tab below 650px. */
@container (max-width: 600px) {
  .paf__row {
    grid-template-columns: 32px minmax(0, 1fr);
    row-gap: 4px;
  }
  .paf__avatar {
    grid-row: 1 / span 3;
    align-self: start;
  }
  .paf__time {
    grid-column: 2;
    grid-row: 2;
  }
  .paf__row .paf__source {
    grid-column: 2;
    justify-self: start;
  }
  .paf__fold {
    margin-left: 0;
  }
  .paf__chips {
    flex-wrap: nowrap;
    overflow-x: auto;
  }
  .paf__sub-text {
    padding-left: 0;
  }
}
</style>
