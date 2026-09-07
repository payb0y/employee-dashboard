<template>
  <div class="upsign__overlay" @click.self="close">
    <div class="iz-panel upsign" role="dialog" aria-modal="true" aria-label="Upload for signing">
      <div class="upsign__head">
        <span class="upsign__title">Upload for Signing</span>
        <button type="button" class="upsign__x" :disabled="busy" @click="close">✕</button>
      </div>

      <div class="upsign__body">
        <!-- Step 1. The project app picks a folder out of its file tree. This
             app has no tree and should not grow one, so the destination is one
             of the employee's own projects — the same list the header filters,
             carrying the folderPath the API already returns. -->
        <div class="upsign__field">
          <label class="upsign__label" for="upsign-project">Destination</label>
          <select
            id="upsign-project"
            v-model.number="projectId"
            class="iz-select upsign__control"
            :disabled="busy"
          >
            <option v-for="p in withFolders" :key="p.id" :value="p.id">
              {{ p.name }}{{ p.number ? " · " + p.number : "" }}
            </option>
          </select>
          <span v-if="selectedProject" class="upsign__folder">{{ selectedProject.folderPath }}</span>
          <span v-if="selectedProject && selectedProject.clientName" class="upsign__hint">
            Client on this project: <strong>{{ selectedProject.clientName }}</strong>
          </span>
        </div>

        <div class="upsign__field">
          <label class="upsign__label" for="upsign-signers">Client / external signer emails</label>
          <textarea
            id="upsign-signers"
            v-model="signersText"
            class="iz-input upsign__textarea"
            placeholder="client@example.com"
            :disabled="busy"
          ></textarea>
          <span class="upsign__hint">One per line. The PDFs are uploaded and sent for signature immediately.</span>
        </div>

        <div class="upsign__field">
          <label class="upsign__label" for="upsign-flow">Signing flow</label>
          <select id="upsign-flow" v-model="flow" class="iz-select upsign__control" :disabled="busy">
            <option value="parallel">Parallel</option>
            <option value="ordered_numeric">Ordered</option>
          </select>
        </div>

        <div class="upsign__field">
          <span class="upsign__label">PDFs</span>
          <div class="upsign__filerow">
            <button type="button" class="iz-btn iz-btn--sm" :disabled="busy" @click="pick">Choose PDFs</button>
            <span class="upsign__hint">
              {{ files.length === 0
                ? "No PDFs selected yet."
                : files.length + " PDF" + (files.length === 1 ? "" : "s") + " selected." }}
            </span>
          </div>
          <!-- accept is a hint the file dialog honours, not a guarantee; the
               check in addFiles() is what actually holds. -->
          <input
            ref="fileInput"
            type="file"
            accept="application/pdf,.pdf"
            multiple
            class="upsign__fileinput"
            @change="onPicked"
          />
          <ul v-if="files.length" class="upsign__filelist">
            <li v-for="(f, i) in files" :key="f.name + '-' + f.size" class="upsign__fileitem">
              <span class="upsign__filename">{{ f.name }}</span>
              <span class="upsign__filesize">{{ humanSize(f.size) }}</span>
              <button type="button" class="upsign__drop" :disabled="busy" @click="files.splice(i, 1)">✕</button>
            </li>
          </ul>
        </div>

        <div v-if="progress" class="upsign__note">{{ progress }}</div>
        <div v-if="error" class="upsign__error">{{ error }}</div>
      </div>

      <div class="upsign__foot">
        <button type="button" class="iz-btn iz-btn--sm" :disabled="busy" @click="close">Cancel</button>
        <button
          type="button"
          class="iz-btn iz-btn--sm iz-btn--primary"
          :disabled="busy || !canSend"
          @click="uploadAndSend"
        >{{ busy ? "Sending…" : "Upload and send" }}</button>
      </div>
    </div>
  </div>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl, generateRemoteUrl } from "@nextcloud/router";

export default {
  name: "UploadForSigningModal",
  props: {
    projects: { type: Array, default: function () { return []; } },
    uid: { type: String, default: "" },
  },
  data: function () {
    return {
      projectId: null,
      signersText: "",
      flow: "parallel",
      files: [],
      busy: false,
      error: "",
      progress: "",
    };
  },
  computed: {
    // Only projects with a folder can receive an upload.
    withFolders: function () {
      return this.projects.filter(function (p) {
        return p.folderPath && String(p.folderPath).trim() !== "";
      });
    },
    selectedProject: function () {
      var id = this.projectId;
      return this.withFolders.filter(function (p) { return p.id === id; })[0] || null;
    },
    signers: function () {
      var seen = {};
      var out = [];
      this.signersText.split("\n").forEach(function (line) {
        var email = line.trim().toLowerCase();
        // Same shape ProjectSigningService::normalizeSigners expects; it
        // validates again server-side, which is what actually decides.
        if (email.indexOf("@") < 1 || seen[email]) return;
        seen[email] = true;
        out.push({ email: email, displayName: email });
      });
      return out;
    },
    canSend: function () {
      return this.selectedProject !== null && this.files.length > 0 && this.signers.length > 0;
    },
  },
  mounted: function () {
    if (this.withFolders.length) {
      this.projectId = this.withFolders[0].id;
    }
  },
  methods: {
    close: function () {
      if (!this.busy) this.$emit("close");
    },
    pick: function () {
      this.$refs.fileInput.click();
    },
    onPicked: function (event) {
      var picked = Array.prototype.slice.call(event.target.files || []);
      var rejected = [];
      var self = this;
      picked.forEach(function (f) {
        // The service refuses anything but application/pdf, so catch it here
        // rather than after the file has already been uploaded.
        var isPdf = f.type === "application/pdf" || /\.pdf$/i.test(f.name);
        if (isPdf) self.files.push(f); else rejected.push(f.name);
      });
      this.error = rejected.length
        ? "Only PDFs can be sent for signature — skipped " + rejected.join(", ") + "."
        : "";
      event.target.value = "";
    },
    humanSize: function (bytes) {
      if (bytes < 1024) return bytes + " B";
      if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + " KB";
      return (bytes / 1024 / 1024).toFixed(1) + " MB";
    },
    readBytes: function (file) {
      return new Promise(function (resolve, reject) {
        var reader = new FileReader();
        reader.onload = function () { resolve(reader.result); };
        reader.onerror = function () { reject(reader.error); };
        reader.readAsArrayBuffer(file);
      });
    },
    davUrl: function (folderPath, name) {
      var segments = String(folderPath).split("/").filter(Boolean).concat([name]);
      var encoded = segments.map(function (s) { return encodeURIComponent(s); }).join("/");
      return generateRemoteUrl("dav/files/" + encodeURIComponent(this.uid) + "/" + encoded);
    },
    /**
     * PUT the file, returning the new node's id.
     *
     * If-None-Match: * makes the server refuse rather than overwrite a file of
     * the same name, which is the `overwrite: false` the project app passes its
     * webdav client. On refusal the name is suffixed and retried, the same idea
     * as its uniqueUploadName(). A server that ignores the precondition simply
     * overwrites, which is the behaviour we would have had anyway.
     *
     * Nextcloud returns the new file's id in OC-FileId, so there is no need to
     * walk a folder listing afterwards to find what was just written.
     */
    putFile: function (folderPath, file, bytes) {
      var self = this;
      var dot = file.name.lastIndexOf(".");
      var stem = dot > 0 ? file.name.slice(0, dot) : file.name;
      var ext = dot > 0 ? file.name.slice(dot) : "";

      function attempt(n) {
        var name = n === 0 ? file.name : stem + " (" + n + ")" + ext;
        return axios
          .put(self.davUrl(folderPath, name), bytes, {
            headers: { "Content-Type": "application/pdf", "If-None-Match": "*" },
          })
          .then(function (response) {
            // OC-FileId is the node id followed by the instance id, e.g.
            // "00002438oci653bdzcfx" — the leading digits are the number the
            // signing endpoint wants. Taken deliberately rather than leaning on
            // parseInt happening to stop at the first letter.
            var header = String(response.headers["oc-fileid"] || "");
            var id = parseInt((header.match(/^\d+/) || [""])[0], 10);
            if (!id) throw new Error("Upload of " + name + " returned no file id.");
            return { fileId: id, name: name };
          })
          .catch(function (e) {
            var status = e.response && e.response.status;
            if (status === 412 && n < 20) return attempt(n + 1);
            throw e;
          });
      }
      return attempt(0);
    },
    /**
     * Hand off to the app that owns signing rather than repeating it here.
     * ProjectSigningService already carries the guards, the OCS call to the
     * Signatures app and the failure bookkeeping, and its access check admits
     * any member of the project's group — which is exactly how this dashboard
     * decided the project was yours in the first place. Sending through it also
     * records the row that gives the document its project label on the way back.
     */
    createRequest: function (projectId, fileId) {
      var url = generateUrl(
        "/apps/projectcreatoraio/api/v1/projects/" + projectId + "/files/" + fileId + "/signing/request"
      );
      return axios.post(
        url,
        { signature_flow: this.flow, signers: this.signers, placements: [] },
        { headers: { "OCS-APIRequest": "true" } }
      );
    },
    uploadAndSend: async function () {
      if (!this.canSend || this.busy) return;
      this.busy = true;
      this.error = "";

      var project = this.selectedProject;
      var sent = 0;
      var failed = [];

      for (var i = 0; i < this.files.length; i++) {
        var file = this.files[i];
        this.progress = "Uploading " + file.name + " (" + (i + 1) + " of " + this.files.length + ")…";
        try {
          var bytes = await this.readBytes(file);
          var put = await this.putFile(project.folderPath, file, bytes);
          this.progress = "Sending " + put.name + " for signature…";
          await this.createRequest(project.id, put.fileId);
          sent++;
        } catch (e) {
          console.error("Upload for signing failed", e);
          failed.push(file.name + (this.reason(e) ? " — " + this.reason(e) : ""));
        }
      }

      this.busy = false;
      this.progress = "";

      if (sent > 0) {
        this.$emit("sent", {
          count: sent,
          project: project.name,
          failed: failed,
        });
        return;
      }
      this.error = "Could not send " + failed.join("; ") + ".";
    },
    // The signing endpoint answers with a real message when it refuses — wrong
    // mime type, no signers, Signatures disabled — and it is worth showing.
    reason: function (e) {
      var data = e && e.response && e.response.data;
      if (!data) return "";
      return data.message || (data.ocs && data.ocs.meta && data.ocs.meta.message) || "";
    },
  },
};
</script>

<style scoped>
.upsign__overlay {
  position: fixed;
  inset: 0;
  z-index: 10000;
  background: rgba(0, 0, 0, 0.42);
  display: flex;
  padding: var(--spacing-lg, 24px);
  overflow-y: auto;
}

/* Centred by margin:auto on the child rather than align-items:center on the
   overlay. Both centre, but a centred flex item that grows taller than the
   viewport has its overflowing top cut off and unreachable — margin:auto
   scrolls to the top edge instead. The body caps at 60vh so it rarely gets
   that tall, but a long error or a wide file list can push it there. */
.upsign {
  margin: auto;
  width: 100%;
  max-width: 560px;
  padding: 0;
  overflow: hidden;
}

.upsign__head {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 13px 16px;
  border-bottom: 1px solid var(--color-border);
}

.upsign__title {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-text-primary);
}

.upsign__x {
  margin-left: auto;
  border: none;
  background: none;
  color: var(--color-text-muted);
  cursor: pointer;
  font-size: 16px;
  line-height: 1;
  padding: 2px 5px;
  font-family: inherit;
  min-height: 0;
}
.upsign__x:hover { color: var(--color-text-primary); }

.upsign__body {
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-height: 60vh;
  overflow-y: auto;
}

.upsign__field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.upsign__label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

/* .iz-select is width:100% already; this only stops the shared control rules
   from being fought over by the two element types. */
.upsign__control {
  width: 100%;
}

.upsign__textarea {
  width: 100%;
  min-height: 58px;
  resize: vertical;
  font-family: var(--iz-font-mono);
  font-size: 12px;
  line-height: 1.6;
}

.upsign__folder {
  font-family: var(--iz-font-mono);
  font-size: 11px;
  color: var(--color-text-secondary);
  background: var(--bg-subtle);
  border-radius: var(--radius-sm);
  padding: 5px 8px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.upsign__hint {
  font-size: 11px;
  color: var(--color-text-muted);
}

.upsign__filerow {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.upsign__fileinput {
  display: none;
}

.upsign__filelist {
  display: flex;
  flex-direction: column;
  gap: 2px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 6px 8px;
}

.upsign__fileitem {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 12px;
}

.upsign__filename {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.upsign__filesize {
  margin-left: auto;
  font-family: var(--iz-font-mono);
  font-size: 11px;
  color: var(--color-text-muted);
  flex-shrink: 0;
}

.upsign__drop {
  border: none;
  background: none;
  color: var(--color-text-muted);
  cursor: pointer;
  font-family: inherit;
  font-size: 12px;
  line-height: 1;
  padding: 2px 4px;
  min-height: 0;
  flex-shrink: 0;
}
.upsign__drop:hover { color: var(--color-danger-text); }

.upsign__note {
  font-size: 12px;
  color: var(--accent-on-bg);
  background: var(--accent-bg);
  border-radius: var(--radius-sm);
  padding: 7px 9px;
}

.upsign__error {
  font-size: 12px;
  color: var(--color-badge-danger-text);
  background: var(--color-badge-danger-bg);
  border-radius: var(--radius-sm);
  padding: 7px 9px;
}

.upsign__foot {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 12px 16px;
  border-top: 1px solid var(--color-border);
}
</style>
