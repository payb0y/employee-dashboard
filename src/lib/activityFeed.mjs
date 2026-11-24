/*
 * A project's activity, shaped for the Project Detail drawer's Activity tab.
 *
 * The events come from projectcreatoraio's activity API
 * (GET /apps/projectcreatoraio/api/v1/projects/{id}/activity), which already
 * redacts what this viewer may not read (other people's private notes and
 * direct chats) and merges in Deck's own activity. The words are
 * projectcreatoraio's — a port of formatDescription in
 * src/components/ProjectActivity/ProjectActivity.vue — so the drawer and the
 * project's Activity tab say the same thing.
 *
 * On top of projectcreatoraio's day groups, a run of identical events — one
 * person, the same action, on the same thing, back to back within a day — can
 * fold into one line. A whiteboard saves itself every few seconds, and
 * unfolded those saves are most of a project's history.
 */

import { t, locale } from "./l10n.mjs";

// The source keys are the API's; only the label is translated.
const SOURCE_VALUES = ["internal", "deck", "files", "talk", "whiteboard"];

function sourceName(value) {
  switch (value) {
    case "internal":
      return t("Project");
    case "deck":
      return t("Deck");
    case "files":
      return t("Files");
    case "talk":
      return t("Talk");
    case "whiteboard":
      return t("Whiteboard");
    default:
      return null;
  }
}

// `label` is a getter so it is translated when read, after Nextcloud has
// registered the app's bundle, not when this module loads.
export const SOURCES = SOURCE_VALUES.map((value) => ({
  value,
  get label() {
    return sourceName(value);
  },
}));

export function sourceLabel(source) {
  return sourceName(source) || source || t("Project");
}

// projectcreatoraio's note types (src/constants/note-types.js), as the
// sentence names them: "created risk / blocker note", plain "note" for general.
function noteTypeLabel(value) {
  switch (value) {
    case "customer":
      return t("customer");
    case "internal":
      return t("internal");
    case "decision":
      return t("decision");
    case "risk_blocker":
      return t("risk / blocker");
    case "action_point":
      return t("action point");
    case "technical":
      return t("technical");
    case "audit":
      return t("audit");
    default:
      return "";
  }
}

/*
 * A note sentence: 'created {type}note "{title}"' and its siblings.
 *
 * English glues the type onto "note" ("created risk / blocker note", or just
 * "created note" for a general note); Dutch puts it in brackets ("heeft de
 * notitie ‘X’ (klant) aangemaakt"). {type} is filled with the label between
 * two markers, then: an empty bracket goes, a label glued to the next word
 * gets its space, and the markers go. Only the markers are matched, so a
 * note title is never touched.
 */
const TYPE_OPEN = "\u0001";
const TYPE_CLOSE = "\u0002";
function noteSentence(sentence, p) {
  const label = noteTypeLabel(p.noteType);
  return sentence({ type: TYPE_OPEN + label + TYPE_CLOSE, title: p.title || t("Untitled") })
    .replace(/\s*\(\u0001\u0002\)/, "")
    .replace("\u0001\u0002", "")
    .replace(/\u0001([^\u0002]*)\u0002(?=[^\s)\]}.,;:!?])/, "$1 ")
    .replace(/\u0001([^\u0002]*)\u0002/, "$1");
}

function nativeDeck(subject, payload) {
  const card = t('Deck card "{title}"', { title: payload.cardTitle || t("Untitled") });
  const board = payload.boardTitle
    ? t('Deck board "{title}"', { title: payload.boardTitle })
    : t("the Deck board");
  const stack = t('Deck stack "{title}"', { title: payload.stackTitle || t("Untitled") });
  switch (subject) {
    case "board_create":
      return t("created {target}", { target: board });
    case "board_update":
    case "board_update_title":
      return t("updated {target}", { target: board });
    case "board_update_archived":
      return t("archived {target}", { target: board });
    case "board_delete":
      return t("deleted {target}", { target: board });
    case "board_restore":
      return t("restored {target}", { target: board });
    case "board_share":
      return t("shared {board}", { board });
    case "board_unshare":
      return t("unshared {board}", { board });
    case "stack_create":
      return t("created {target}", { target: stack });
    case "stack_update":
    case "stack_update_title":
    case "stack_update_order":
      return t("updated {target}", { target: stack });
    case "stack_delete":
      return t("deleted {target}", { target: stack });
    case "card_create":
      return t("created {target}", { target: card });
    case "card_delete":
      return t("deleted {target}", { target: card });
    case "card_restore":
      return t("restored {target}", { target: card });
    case "card_update_title":
      return t("renamed {card}", { card });
    case "card_update_description":
      return t("updated the description of {card}", { card });
    case "card_update_duedate":
      return t("updated the due date of {card}", { card });
    case "card_update_archive":
      return t("archived {target}", { target: card });
    case "card_update_unarchive":
      return t("unarchived {card}", { card });
    case "card_update_done":
      return t("marked {card} as done", { card });
    case "card_update_undone":
      return t("marked {card} as not done", { card });
    case "card_update_stackId":
      return t('moved {card} to "{stack}"', {
        card,
        stack: payload.stackTitle || t("another stack"),
      });
    default:
      return t("updated {target}", { target: card });
  }
}

export function describe(event) {
  const type = String(event.eventType || "");
  const p = event.payload || {};
  if (p.redacted === true) {
    // The server's redaction: a direct chat between two other people, or
    // someone else's private note.
    if (p.isDirectChat) return t("started a direct chat");
    if (type === "note_created") return t("created a private note");
    if (type === "note_deleted") return t("deleted a private note");
    return t("updated a private note");
  }
  if (p.nativeActivity === true) return nativeDeck(p.nativeSubject, p);

  const someone = () => t("someone");
  const aFile = () => t("a file");
  const untitled = () => t("Untitled");
  switch (type) {
    case "project_created":
      return t("created the project");
    case "project_updated":
      return t("updated the project ({fields})", {
        fields: (p.changedFields || []).join(", ") || t("details"),
      });
    case "project_archived":
      return t("archived the project");
    case "project_restored":
      return t("restored the project");
    case "member_added":
      return t("added {member}", { member: p.memberDisplayName || p.memberUid || t("a member") });
    case "member_removed":
      return t("removed {member}", { member: p.memberDisplayName || p.memberUid || t("a member") });
    case "note_created":
      return noteSentence((vars) => t('created {type}note "{title}"', vars), p);
    case "note_updated":
      return noteSentence((vars) => t('updated {type}note "{title}"', vars), p);
    case "note_deleted":
      return noteSentence((vars) => t('deleted {type}note "{title}"', vars), p);
    case "project_notes_updated":
      return t("updated project notes");
    case "timeline_item_created":
      return t('created timeline item "{label}"', { label: p.label || untitled() });
    case "timeline_item_updated":
      return t('updated timeline item "{label}"', { label: p.label || untitled() });
    case "timeline_item_deleted":
      return t('deleted timeline item "{label}"', { label: p.label || untitled() });
    case "timeline_reordered":
      return t("reordered timeline items");

    case "deck_card_created":
      return t('created Deck card "{title}"', { title: p.cardTitle || untitled() });
    case "deck_card_updated":
      return t('updated Deck card "{title}"', { title: p.cardTitle || untitled() });
    case "deck_card_deleted":
      return t('deleted Deck card "{title}"', { title: p.cardTitle || untitled() });
    case "deck_acl_added":
      return t("added {participant} to the Deck board", { participant: p.participant || someone() });
    case "deck_acl_removed":
      return t("removed {participant} from the Deck board", {
        participant: p.participant || someone(),
      });
    case "deck_acl_updated":
      return t("updated access for {participant} on the Deck board", {
        participant: p.participant || someone(),
      });
    case "deck_board_created":
      return t("created the Deck board");
    case "deck_board_updated":
      return t("updated the Deck board");
    case "deck_board_deleted":
      return t("deleted the Deck board");

    case "file_created":
      return t('created "{file}"', { file: p.fileName || aFile() });
    case "file_updated":
      return t('updated "{file}"', { file: p.fileName || aFile() });
    case "file_deleted":
      return t('deleted "{file}"', { file: p.fileName || aFile() });
    case "file_renamed":
      return t('renamed "{old}" to "{new}"', {
        old: p.oldName || aFile(),
        new: p.newName || t("new name"),
      });
    case "file_moved":
      return t('moved "{file}"', { file: p.fileName || aFile() });
    case "file_copied":
      return t('copied "{file}"', { file: p.fileName || aFile() });
    case "folder_created":
      return t('created folder "{folder}"', { folder: p.folderName || untitled() });
    case "folder_deleted":
      return t('deleted folder "{folder}"', { folder: p.folderName || t("a folder") });

    case "talk_message_sent":
      return p.messagePreview
        ? t('sent a Talk message: "{preview}"', { preview: p.messagePreview })
        : t("sent a Talk message");
    case "talk_participant_added":
      return t("added {participant} to Talk", {
        participant: p.participantDisplayName || p.participantUid || someone(),
      });
    case "talk_participant_removed":
      return t("removed {participant} from Talk", {
        participant: p.participantDisplayName || p.participantUid || someone(),
      });
    case "talk_call_started":
      return t("started a call");
    case "talk_call_ended":
      return t("ended a call");
    case "talk_room_updated":
      return t("updated the Talk conversation");
    case "talk_reaction_added":
      return t("reacted with {reaction} to a message", { reaction: p.reaction || "" });
    case "talk_reaction_removed":
      return t("removed reaction {reaction} from a message", { reaction: p.reaction || "" });
    case "talk_user_joined":
      return t("joined the Talk conversation");
    case "talk_direct_chat_created":
      return t("started a direct chat with {participant}", {
        participant: p.targetDisplayName || p.targetUserId || someone(),
      });

    case "whiteboard_updated":
      return t("updated the whiteboard");

    default:
      // An event type this port doesn't know yet, shown as its own words.
      return t("{event type}", { "event type": type.replace(/_/g, " ") });
  }
}

// What an event acts on, so two saves of one file fold and two files don't.
function targetOf(event) {
  const p = event.payload || {};
  if (p.redacted === true) return "";
  return String(
    p.nativeSubject && p.cardTitle !== undefined
      ? `${p.nativeSubject}:${p.cardTitle}`
      : p.fileName ??
          p.cardTitle ??
          p.noteId ??
          p.title ??
          p.label ??
          p.folderName ??
          p.memberUid ??
          p.participant ??
          (p.changedFields || []).join(",")
  );
}

export function foldKey(event) {
  return [event.actorUid || "", event.eventType || "", event.source || "", targetOf(event)].join(
    "|"
  );
}

// The viewer's calendar day and clock, as projectcreatoraio shows them.
const pad2 = (value) => String(value).padStart(2, "0");
export function localDay(value) {
  const d = new Date(value);
  return Number.isNaN(d.getTime())
    ? ""
    : `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}
export function clockTime(value) {
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? "" : `${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
}
export function dayLabel(day, now = new Date()) {
  const today = localDay(now);
  const y = new Date(now);
  y.setDate(y.getDate() - 1);
  if (day === today) return t("Today");
  if (day === localDay(y)) return t("Yesterday");
  return longDate(day);
}

// "17 October 2026" / "17 oktober 2026": the month's name from Intl in the
// user's locale, the order from the translation.
function longDate(day) {
  if (!day) return "";
  const [yy, mm, dd] = day.split("-").map(Number);
  const month = new Intl.DateTimeFormat(locale(), { month: "long" }).format(new Date(yy, mm - 1, 1));
  return t("{day} {month} {year}", { day: dd, month, year: yy });
}

/**
 * Day groups of lines, newest first, from events the API returned newest
 * first. With `fold`, back-to-back events sharing a foldKey on one day are one
 * line, carrying every event so the line can open up.
 */
export function buildFeed(events, { fold = true, now = new Date() } = {}) {
  const days = [];
  let lastKey = null;
  (events || []).forEach((event) => {
    const day = localDay(event.occurredAt);
    let group = days[days.length - 1];
    if (!group || group.day !== day) {
      group = { day, label: dayLabel(day, now), eventCount: 0, lines: [] };
      days.push(group);
      lastKey = null;
    }
    group.eventCount += 1;
    const key = foldKey(event);
    const line = group.lines[group.lines.length - 1];
    const item = {
      id: String(event.id),
      time: clockTime(event.occurredAt),
      occurredAt: event.occurredAt,
    };
    if (fold && line && key === lastKey) {
      line.events.push(item);
      line.oldest = item.time;
    } else {
      group.lines.push({
        id: `${day}-${event.id}`,
        actorUid: event.actorUid || null,
        actorName: event.actorDisplayName || event.actorUid || t("Unknown user"),
        text: describe(event),
        source: event.source || "internal",
        sourceLabel: sourceLabel(event.source),
        newest: item.time,
        oldest: item.time,
        events: [item],
      });
    }
    lastKey = key;
  });
  return days;
}

export function lineCount(events, fold) {
  return buildFeed(events, { fold }).reduce((total, day) => total + day.lines.length, 0);
}

// "24 August 2026" — how far back the loaded activity reaches.
export function oldestLabel(events) {
  if (!events || !events.length) return "";
  return longDate(localDay(events[events.length - 1].occurredAt));
}
