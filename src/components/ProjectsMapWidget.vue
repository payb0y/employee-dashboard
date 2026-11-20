<template>
  <section class="iz-panel iz-panel--list proj-map">
    <div class="proj-map__header" :aria-expanded="collapsed ? 'false' : 'true'" @click="collapsed = !collapsed">
      <h3 class="proj-map__title">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M1 6v15l7-3 8 3 7-3V3l-7 3-8-3-7 3z" />
          <line x1="8" y1="3" x2="8" y2="18" />
          <line x1="16" y1="6" x2="16" y2="21" />
        </svg>
        Project locations
        <span class="proj-map__count">{{ projects.length }}</span>
      </h3>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="proj-map__chevron" :class="{ 'proj-map__chevron--rotated': collapsed }">
        <polyline points="18 15 12 9 6 15" />
      </svg>
    </div>
    <div v-show="!collapsed" class="proj-map__body">
      <div v-if="projects.length === 0" class="proj-map__empty">
        No project locations available
      </div>
      <div v-else ref="mapRoot" class="proj-map__container"></div>
    </div>
  </section>
</template>

<script>
import L from "leaflet";
import "leaflet/dist/leaflet.css";

const MARKER_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="#b91c1c" stroke="#7f1d1d" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 1 1 18 0z"/><circle cx="12" cy="10" r="3" fill="#fff" stroke="#7f1d1d"/></svg>';

export default {
  name: "ProjectsMapWidget",
  props: {
    projects: { type: Array, default: function () { return []; } },
    activeProjectId: { type: Number, default: null },
  },
  data: function () {
    return {
      collapsed: false,
      _map: null,
      _markerGroup: null,
    };
  },
  mounted: function () {
    if (this.projects.length > 0) {
      this.initMap();
    }
  },
  beforeDestroy: function () {
    if (this._map) {
      this._map.remove();
      this._map = null;
      this._markerGroup = null;
    }
  },
  watch: {
    // A map laid out while folded has no size; once it unfolds, let Leaflet
    // measure the container again and re-fit the markers.
    collapsed: function (isCollapsed) {
      if (isCollapsed || !this._map) {
        return;
      }
      this.$nextTick(function () {
        if (this._map) {
          this._map.invalidateSize();
          this.renderMarkers();
        }
      });
    },
    projects: function (newList) {
      if (!this._map && newList.length > 0) {
        this.$nextTick(this.initMap);
        return;
      }
      if (this._map && newList.length === 0) {
        this._map.remove();
        this._map = null;
        this._markerGroup = null;
        return;
      }
      if (this._map) {
        this.renderMarkers();
      }
    },
  },
  methods: {
    initMap: function () {
      var el = this.$refs.mapRoot;
      if (!el) {
        return;
      }
      this._map = L.map(el, { scrollWheelZoom: true });
      L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        // OSM requires a Referer; override Nextcloud only for tiles, without page paths.
        referrerPolicy: "strict-origin",
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors',
      }).addTo(this._map);
      this.renderMarkers();
    },
    renderMarkers: function () {
      var self = this;
      if (this._markerGroup) {
        this._markerGroup.remove();
        this._markerGroup = null;
      }
      var icon = L.divIcon({
        className: "proj-map__marker",
        html: MARKER_SVG,
        iconSize: [28, 28],
        iconAnchor: [14, 28],
        popupAnchor: [0, -24],
      });
      var markers = [];
      this.projects.forEach(function (p) {
        if (p.lat == null || p.lng == null) {
          return;
        }
        var marker = L.marker([p.lat, p.lng], { icon: icon });
        var label = p.name;
        if (p.number) {
          label += " (" + p.number + ")";
        }
        var tooltipNode = document.createElement("span");
        tooltipNode.textContent = label;
        marker.bindTooltip(tooltipNode, { direction: "top", offset: [0, -24] });
        marker.on("click", function () {
          self.$emit("filter-project", p.id);
        });
        markers.push(marker);
      });

      if (markers.length === 0) {
        return;
      }

      this._markerGroup = L.featureGroup(markers).addTo(this._map);

      if (markers.length === 1) {
        this._map.setView([this.projects[0].lat, this.projects[0].lng], 14);
      } else {
        this._map.fitBounds(this._markerGroup.getBounds(), {
          padding: [40, 40],
          maxZoom: 14,
        });
      }
    },
  },
};
</script>

<style scoped>
.proj-map {  margin-bottom: var(--spacing-lg, 24px);
  overflow: hidden;
  isolation: isolate;
}
.proj-map__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  /* 20px sides, matching --iz-pad-panel. */
  padding: var(--spacing-md, 16px) 20px;
  cursor: pointer;
  user-select: none;
  transition: background 0.15s;
}
.proj-map__header:hover { background: var(--bg-subtle); }
.proj-map__title {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  margin: 0; padding: 0; border: none;
  display: flex;
  align-items: center;
  gap: 8px;
}
.proj-map__chevron {
  color: var(--color-text-muted, #9ca3af);
  transition: transform 0.3s;
}
.proj-map__chevron--rotated { transform: rotate(180deg); }
.proj-map__body {
  /* 4px on top so the map does not sit flush against the header. */
  padding: 4px 20px 20px;
}
.proj-map__count {
  font-size: 11px;
  font-weight: 600;
  background: var(--chart-5-bg);
  color: var(--chart-5);
  padding: 2px 8px;
  border-radius: 8px;
}
.proj-map__container {
  height: 360px;
  width: 100%;
  border-radius: 8px;
  overflow: hidden;
  background: var(--bg-inset);
}
.proj-map__empty {
  font-size: 13px;
  color: var(--color-text-muted, #9ca3af);
  padding: 48px 24px;
  text-align: center;
  border: 1px dashed var(--color-border, #e5e7eb);
  border-radius: 8px;
}
</style>

<style>
.proj-map__marker {
  background: transparent !important;
  border: none !important;
}
</style>
