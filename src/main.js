import Vue from "vue";
import Dashboard from "./components/Dashboard.vue";
import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";

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
      return h(Dashboard, { props: { data: this.dashboardData } });
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
