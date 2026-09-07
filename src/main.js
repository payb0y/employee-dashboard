import Vue from "vue";
import Dashboard from "./components/Dashboard.vue";
import axios from "@nextcloud/axios";
import { generateUrl, generateFilePath } from "@nextcloud/router";

/*
 * Where webpack fetches lazily-loaded chunks from.
 *
 * It bakes in /apps/employee_dashboard/js/, which 404s here: this app is
 * installed under custom_apps and Nextcloud serves it from
 * /custom_apps/employee_dashboard/js/ — the same path Util::addScript produces
 * for the main bundle. Nothing noticed while everything shipped in one file;
 * the Placement Studio loads pdf.js on demand, so the wrong base would mean the
 * studio silently failing to open. generateFilePath knows the real app root.
 */
__webpack_public_path__ = generateFilePath("employee_dashboard", "", "js/");

Vue.mixin({
  methods: {
    t: (app, text) => text,
  },
});

const mountEl = document.getElementById("employee-dashboard-root");

if (mountEl) {
  new Vue({
    el: mountEl,
    data() {
      return {
        dashboardData: null,
        loading: true,
        error: null,
      };
    },
    render(h) {
      if (this.loading) {
        return h("div", { class: "emp-loading" }, [
          h("div", { class: "emp-loading__spinner" }),
          h("p", "Loading your dashboard..."),
        ]);
      }
      if (this.error) {
        return h("div", { class: "emp-error" }, [
          h("p", "Error loading data: " + this.error),
        ]);
      }
      return h(Dashboard, {
        props: { data: this.dashboardData },
        // Sending a document creates rows this view fetched before they
        // existed, so the panel asks for the data again rather than lying.
        on: { refresh: this.fetchData },
      });
    },
    mounted() {
      this.fetchData();
    },
    methods: {
      async fetchData() {
        try {
          var url = generateUrl("/apps/employee_dashboard/api/data");
          var response = await axios.get(url);
          this.dashboardData = response.data;
        } catch (e) {
          console.error("Failed to load employee dashboard data", e);
          this.error = e.message || "Unknown error";
        } finally {
          this.loading = false;
        }
      },
    },
  });
}
