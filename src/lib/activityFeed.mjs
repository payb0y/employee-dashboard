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

const MONTHS = [
  "January",
  "February",
  "March",
  "April",
  "May",
  "June",
  "July",
  "August",
  "September",
  "October",
  "November",
  "December",
];

export const SOURCES = [
  { value: "internal", label: "Project" },
  { value: "deck", label: "Deck" },
  { value: "files", label: "Files" },
  { value: "talk", label: "Talk" },
  { value: "whiteboard", label: "Whiteboard" },
];

export function sourceLabel(source) {
  const found = SOURCES.find((s) => s.value === source);
  return found ? found.label : source || "Project";
}

// projectcreatoraio's note types (src/constants/note-types.js), as the
// sentence names them: "created risk / blocker note", plain "note" for general.
const NOTE_TYPES = {
  customer: "customer",
  internal: "internal",
  decision: "decision",
  risk_blocker: "risk / blocker",
  action_point: "action point",
  technical: "technical",
  audit: "audit",
};
const noteType = (value) => (NOTE_TYPES[value] ? `${NOTE_TYPES[value]} ` : "");

function nativeDeck(subject, payload) {
  const card = `Deck card "${payload.cardTitle || "Untitled"}"`;
  const board = payload.boardTitle ? `Deck board "${payload.boardTitle}"` : "the Deck board";
  const stack = `Deck stack "${payload.stackTitle || "Untitled"}"`;
  switch (subject) {
    case "board_create":
      return `created ${board}`;
    case "board_update":
    case "board_update_title":
      return `updated ${board}`;
    case "board_update_archived":
      return `archived ${board}`;
    case "board_delete":
      return `deleted ${board}`;
    case "board_restore":
      return `restored ${board}`;
    case "board_share":
      return `shared ${board}`;
    case "board_unshare":
      return `unshared ${board}`;
    case "stack_create":
      return `created ${stack}`;
    case "stack_update":
    case "stack_update_title":
    case "stack_update_order":
      return `updated ${stack}`;
    case "stack_delete":
      return `deleted ${stack}`;
    case "card_create":
      return `created ${card}`;
    case "card_delete":
      return `deleted ${card}`;
    case "card_restore":
      return `restored ${card}`;
    case "card_update_title":
      return `renamed ${card}`;
    case "card_update_description":
      return `updated the description of ${card}`;
    case "card_update_duedate":
      return `updated the due date of ${card}`;
    case "card_update_archive":
      return `archived ${card}`;
    case "card_update_unarchive":
      return `unarchived ${card}`;
    case "card_update_done":
      return `marked ${card} as done`;
    case "card_update_undone":
      return `marked ${card} as not done`;
    case "card_update_stackId":
      return `moved ${card} to "${payload.stackTitle || "another stack"}"`;
    default:
      return `updated ${card}`;
  }
}

export function describe(event) {
  const type = String(event.eventType || "");
  const p = event.payload || {};
  if (p.redacted === true) {
    // The server's redaction: a direct chat between two other people, or
    // someone else's private note.
    if (p.isDirectChat) return "started a direct chat";
    return `${type.replace("note_", "")} a private note`;
  }
  if (p.nativeActivity === true) return nativeDeck(p.nativeSubject, p);

  switch (type) {
    case "project_created":
      return "created the project";
    case "project_updated":
      return `updated the project (${(p.changedFields || []).join(", ") || "details"})`;
    case "project_archived":
      return "archived the project";
    case "project_restored":
      return "restored the project";
    case "member_added":
      return `added ${p.memberDisplayName || p.memberUid || "a member"}`;
    case "member_removed":
      return `removed ${p.memberDisplayName || p.memberUid || "a member"}`;
    case "note_created":
      return `created ${noteType(p.noteType)}note "${p.title || "Untitled"}"`;
    case "note_updated":
      return `updated ${noteType(p.noteType)}note "${p.title || "Untitled"}"`;
    case "note_deleted":
      return `deleted ${noteType(p.noteType)}note "${p.title || "Untitled"}"`;
    case "project_notes_updated":
      return "updated project notes";
    case "timeline_item_created":
      return `created timeline item "${p.label || "Untitled"}"`;
    case "timeline_item_updated":
      return `updated timeline item "${p.label || "Untitled"}"`;
    case "timeline_item_deleted":
      return `deleted timeline item "${p.label || "Untitled"}"`;
    case "timeline_reordered":
      return "reordered timeline items";

    case "deck_card_created":
      return `created Deck card "${p.cardTitle || "Untitled"}"`;
    case "deck_card_updated":
      return `updated Deck card "${p.cardTitle || "Untitled"}"`;
    case "deck_card_deleted":
      return `deleted Deck card "${p.cardTitle || "Untitled"}"`;
    case "deck_acl_added":
      return `added ${p.participant || "someone"} to the Deck board`;
    case "deck_acl_removed":
      return `removed ${p.participant || "someone"} from the Deck board`;
    case "deck_acl_updated":
      return `updated access for ${p.participant || "someone"} on the Deck board`;
    case "deck_board_created":
      return "created the Deck board";
    case "deck_board_updated":
      return "updated the Deck board";
    case "deck_board_deleted":
      return "deleted the Deck board";

    case "file_created":
      return `created "${p.fileName || "a file"}"`;
    case "file_updated":
      return `updated "${p.fileName || "a file"}"`;
    case "file_deleted":
      return `deleted "${p.fileName || "a file"}"`;
    case "file_renamed":
      return `renamed "${p.oldName || "a file"}" to "${p.newName || "new name"}"`;
    case "file_moved":
      return `moved "${p.fileName || "a file"}"`;
    case "file_copied":
      return `copied "${p.fileName || "a file"}"`;
    case "folder_created":
      return `created folder "${p.folderName || "Untitled"}"`;
    case "folder_deleted":
      return `deleted folder "${p.folderName || "a folder"}"`;

    case "talk_message_sent":
      return `sent a Talk message${p.messagePreview ? ': "' + p.messagePreview + '"' : ""}`;
    case "talk_participant_added":
      return `added ${p.participantDisplayName || p.participantUid || "someone"} to Talk`;
    case "talk_participant_removed":
      return `removed ${p.participantDisplayName || p.participantUid || "someone"} from Talk`;
    case "talk_call_started":
      return "started a call";
    case "talk_call_ended":
      return "ended a call";
    case "talk_room_updated":
      return "updated the Talk conversation";
    case "talk_reaction_added":
      return `reacted with ${p.reaction || ""} to a message`;
    case "talk_reaction_removed":
      return `removed reaction ${p.reaction || ""} from a message`;
    case "talk_user_joined":
      return "joined the Talk conversation";
    case "talk_direct_chat_created":
      return `started a direct chat with ${p.targetDisplayName || p.targetUserId || "someone"}`;

    case "whiteboard_updated":
      return "updated the whiteboard";

    default:
      return type.replace(/_/g, " ");
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
const pad2 = (n) => String(n).padStart(2, "0");
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
  if (day === today) return "Today";
  if (day === localDay(y)) return "Yesterday";
  const [yy, mm, dd] = day.split("-").map(Number);
  return `${dd} ${MONTHS[mm - 1]} ${yy}`;
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
        actorName: event.actorDisplayName || event.actorUid || "Unknown user",
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
  return buildFeed(events, { fold }).reduce((n, day) => n + day.lines.length, 0);
}

// "24 August 2026" — how far back the loaded activity reaches.
export function oldestLabel(events) {
  if (!events || !events.length) return "";
  const day = localDay(events[events.length - 1].occurredAt);
  const [yy, mm, dd] = day.split("-").map(Number);
  return `${dd} ${MONTHS[mm - 1]} ${yy}`;
}
