// The translation helper: Dutch from l10n/nl.json when that bundle is
// registered, the English msgid when it is not, and numbers in the user's way.
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

const nl = JSON.parse(readFileSync(new URL("../l10n/nl.json", import.meta.url), "utf8"));

// What Nextcloud sets up in the browser before the app bundle runs.
function registerDutch() {
  globalThis.window = globalThis;
  globalThis.document = { documentElement: { lang: "nl", dataset: { locale: "nl_NL" } } };
  globalThis._oc_l10n_registry_translations = { employee_dashboard: nl.translations };
  globalThis._oc_l10n_registry_plural_functions = { employee_dashboard: (n) => (n != 1 ? 1 : 0) };
  globalThis._nc_l10n_locale = "nl_NL"; // read once at load in the browser, set here per test
}
function unregister() {
  globalThis._oc_l10n_registry_translations = {};
  globalThis._oc_l10n_registry_plural_functions = {};
  globalThis._nc_l10n_locale = "en_US";
}

const { t, n, tc, formatNumber } = await import("../src/lib/l10n.mjs");

test("without a bundle the English msgid comes back, placeholders filled", () => {
  unregister();
  assert.equal(t("Error loading data: {error}", { error: "R&D" }), "Error loading data: R&D");
  assert.equal(n("%nd ago", "%nd ago", 1), "1d ago");
  assert.equal(tc("Open", "status"), "Open");
});

test("Dutch from the bundle, never HTML-escaped", () => {
  registerDutch();
  assert.equal(t("Error loading data: {error}", { error: "R&D <x>" }), "Fout bij het laden van de gegevens: R&D <x>");
  assert.equal(n("%nd ago", "%nd ago", 1), "1 dag geleden");
  assert.equal(n("%nd ago", "%nd ago", 3), "3 dagen geleden");
  unregister();
});

test("one English word, two meanings: Open as a button and as a status", () => {
  registerDutch();
  assert.equal(t("Open"), "Openen");
  assert.equal(tc("Open", "status"), "Open");
  unregister();
});

test("fractional counts follow the user's locale", () => {
  registerDutch();
  assert.equal(formatNumber(4.5), "4,5");
  assert.equal(n("%n week", "%n weeks", 4.5), "4,5 weken");
  assert.equal(n("%n week", "%n weeks", 3), "3 weken");
  unregister();
});
