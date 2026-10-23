import test from "node:test";
import assert from "node:assert/strict";
import {
  describe,
  sourceLabel,
  foldKey,
  dayLabel,
  clockTime,
  buildFeed,
  lineCount,
  oldestLabel,
} from "../src/lib/activityFeed.mjs";

const ev = (id, occurredAt, actorUid, eventType, source, payload = {}) => ({
  id,
  occurredAt,
  actorUid,
  actorDisplayName: actorUid,
  eventType,
  source,
  payload,
});

test("events read in projectcreatoraio's words", () => {
  assert.equal(
    describe(ev(1, "", "a", "whiteboard_updated", "whiteboard")),
    "updated the whiteboard"
  );
  assert.equal(
    describe(ev(1, "", "a", "project_updated", "internal", { changedFields: ["disciplines"] })),
    "updated the project (disciplines)"
  );
  assert.equal(
    describe(ev(1, "", "a", "file_created", "files", { fileName: "plan.pdf" })),
    'created "plan.pdf"'
  );
  assert.equal(
    describe(
      ev(1, "", "a", "note_created", "internal", { title: "Late client", noteType: "risk_blocker" })
    ),
    'created risk / blocker note "Late client"'
  );
  assert.equal(
    describe(ev(1, "", "a", "note_created", "internal", { title: "Scope", noteType: "general" })),
    'created note "Scope"'
  );
  assert.equal(
    describe(
      ev(1, "", "a", "deck_x", "deck", {
        nativeActivity: true,
        nativeSubject: "card_update_stackId",
        cardTitle: "AVP",
        stackTitle: "Done",
      })
    ),
    'moved Deck card "AVP" to "Done"'
  );
  assert.equal(describe(ev(1, "", "a", "something_new", "internal")), "something new");
  assert.equal(sourceLabel("internal"), "Project");
  assert.equal(sourceLabel(undefined), "Project");
});

test("what the server redacted stays redacted", () => {
  assert.equal(
    describe(ev(1, "", "b", "note_updated", "internal", { redacted: true, visibility: "private" })),
    "updated a private note"
  );
  assert.equal(
    describe(
      ev(1, "", "b", "talk_direct_chat_created", "talk", { redacted: true, isDirectChat: true })
    ),
    "started a direct chat"
  );
});

test("a run folds only on one person, one action, one thing", () => {
  const save = ev(1, "", "admin", "whiteboard_updated", "whiteboard", { fileSize: 1 });
  assert.equal(foldKey(save), foldKey({ ...save, id: 2, payload: { fileSize: 2 } }));
  const a = ev(1, "", "e", "file_updated", "files", { fileName: "a.pdf" });
  assert.notEqual(foldKey(a), foldKey({ ...a, payload: { fileName: "b.pdf" } }));
  assert.notEqual(foldKey(a), foldKey({ ...a, actorUid: "f" }));
  assert.notEqual(foldKey(a), foldKey({ ...a, eventType: "file_created" }));
});

test("days are Today, Yesterday, then dated", () => {
  const now = new Date(2026, 9, 24, 10, 0);
  assert.equal(dayLabel("2026-10-24", now), "Today");
  assert.equal(dayLabel("2026-10-23", now), "Yesterday");
  assert.equal(dayLabel("2026-10-17", now), "17 October 2026");
  assert.equal(clockTime("2026-10-17T00:40:55"), "00:40");
  assert.equal(clockTime("nonsense"), "");
});

const events = [
  ev(10, "2026-10-17T00:40:55", "admin", "whiteboard_updated", "whiteboard"),
  ev(9, "2026-10-17T00:35:02", "admin", "whiteboard_updated", "whiteboard"),
  ev(8, "2026-10-17T00:29:14", "admin", "whiteboard_updated", "whiteboard"),
  ev(7, "2026-10-13T21:00:32", "admin", "whiteboard_updated", "whiteboard"),
  ev(6, "2026-09-07T21:00:09", "emp", "file_updated", "files", { fileName: "a.pdf" }),
  ev(5, "2026-09-07T21:00:09", "emp", "file_created", "files", { fileName: "a.pdf" }),
  ev(4, "2026-09-07T20:59:48", "emp", "whiteboard_updated", "whiteboard"),
  ev(3, "2026-09-07T20:58:00", "admin", "whiteboard_updated", "whiteboard"),
];

test("folded, a run is one line that keeps every event; a new day starts a new run", () => {
  const days = buildFeed(events, { now: new Date(2026, 9, 24) });
  assert.deepEqual(
    days.map((d) => [d.label, d.eventCount, d.lines.length]),
    [
      ["17 October 2026", 3, 1],
      ["13 October 2026", 1, 1],
      ["7 September 2026", 4, 4],
    ]
  );
  const run = days[0].lines[0];
  assert.deepEqual(
    [run.actorName, run.text, run.sourceLabel, run.newest, run.oldest, run.events.length],
    ["admin", "updated the whiteboard", "Whiteboard", "00:40", "00:29", 3]
  );
  assert.deepEqual(
    run.events.map((e) => e.time),
    ["00:40", "00:35", "00:29"]
  );
});

test("unfolded, every event is its own line", () => {
  const days = buildFeed(events, { fold: false, now: new Date(2026, 9, 24) });
  assert.equal(days[0].lines.length, 3);
  assert.equal(lineCount(events, false), 8);
  assert.equal(lineCount(events, true), 6);
});

test("the footer names the oldest loaded day", () => {
  assert.equal(oldestLabel(events), "7 September 2026");
  assert.equal(oldestLabel([]), "");
});
