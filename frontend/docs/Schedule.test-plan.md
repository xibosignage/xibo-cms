# Test Plan: Schedule (Events) Page

Covers the Schedule page (`frontend/src/pages/Schedule/Schedule/`) in both its view modes, the shared
Schedule Event modal it drives, and the modal routing between them.

> **Naming.** The page folder is `Schedule`, so the docs are `Schedule.*` — but the component is
> `Events.tsx`, the entity is an *Event*, and the test files say `event`. There is no `Events.test-plan.md`.

**Scope-shaping features of this page** (these decide which sections exist below):

| Feature | Present? | Effect on the plan |
|---|---|---|
| Dual view mode (table ↔ calendar) | **Yes** | Two symmetric clusters, `table/` and `calendar/`. Unique to this page — §2 exists only here |
| Folder sidebar / folder permissions | No | No folder section |
| Global search box | **No** | `globalFilter` is hardcoded `""` with a no-op setter; filtering is the panel only |
| Filters panel | **Yes** | Plus two always-visible controls above it (date range, display group) |
| Bulk actions | **Yes** — Delete Selected only | Bulk sections in §1 and §6 |
| Share | **No** | `shareEntityIds` is threaded into `EventModals` but never consumed — see Notes |
| Special / locked rows | **Yes** — `isEditable === false` | Suppresses *all* row actions and forces the lock badge |
| Multi-step **and** multi-tab modal | **Yes** | 4 steps (3 for Sync) × 5 Optional sub-tabs — §3 is the largest section |
| Hydration gate | **Yes** | Plus a *second* preference key, `event_page_date_range` |
| Agenda modal | **Yes** | Feature-flagged `schedule.agenda`, opened from the calendar day panel — §7 |
| Delete a single occurrence | **Yes** | A recurring event can be deleted as one instance or as the whole series |

---

## 1. Events page — table view (`Events.tsx`, `EventsConfig.tsx`)

**Default state**
- The page opens in table view, not calendar view
- A loading message is shown and the table hidden while saved preferences load
- Events are paginated at 10 per page, starting on page 1
- The default set of columns is shown; columns hidden by default do not appear
- An event returned by the API appears as a row
- The table still loads cleanly when there are no events

**Filters panel**
- The panel is closed when the page first loads; the Filters button opens and closes it
- Picking a date range re-fetches using the new from/to dates, and resets to page 1
- A date range saved in a previous session is restored on load
- Picking a display group filters to that group; clearing it restores all events
- Typing in a filter field resets to page 1
- Reset clears the other filters but keeps the date range

**Column visibility**
- Default visibility is used when the user has no saved preferences
- A column turned on (or off) in saved preferences keeps that state across a reload
- Toggling a column triggers a debounced save of the new visibility

**Row actions**
- Add Event is disabled while preferences load and becomes clickable once hydrated
- Edit opens the Schedule modal pre-filled with that event
- Make a Copy opens the Copy modal for that event
- Delete opens the Delete modal for that one event only

**Bulk actions**
- Delete Selected is hidden until at least one row is ticked
- Selecting rows and confirming sends every ticked event for deletion

## 2. Calendar view (`EventCalendar.tsx`, `expandRecurringEvents.ts`)

**Switching views**
- Toggling to Calendar View and back to Table View
- The date-range view-mode selector is hidden in calendar mode and returns afterwards
- A loading overlay appears while calendar data is fetching

**The month grid**
- Today is highlighted; days outside the current month are greyed; days with no events are not clickable
- The legend shows all event type groups
- Event icons are drawn per event type, with a priority badge
- Events spanning the month, ending exactly at midnight, or of zero duration
- An event with no end time is skipped rather than crashing the grid

**Recurrence**
- Weekly, daily and always events expand across the month, including across month boundaries
- A series with no end date continues to the end of the visible month
- A deleted occurrence (including the first) does not appear, while later repeats still do
- Degenerate input — zero interval, missing flag, no weekdays selected — degrades safely
- Monthly events clamp to the last valid day (31st in a 30-day month, 29 Feb outside a leap year)

**Timezones**
- Events shift to the correct calendar day for UTC+, UTC− and fractional-offset users
- Spring-forward and fall-back nights render without crashing

**Day detail panel**
- Clicking a day with events opens the panel; clicking outside, or the same day again, closes it
- Clicking an event in the panel triggers edit
- Time labels for same-day, multi-day (first/middle/last) and midnight-boundary events
- Display group badges, including the `+N` overflow badge
- **The Agenda button hands the day and that day's events back to the page**
- **No Agenda button when the `schedule.agenda` feature is off**

**Context menu**
- Right-clicking an event icon opens the menu, shows the event name, time range and display group
- Delete in the menu triggers the delete flow
- Clicking outside dismisses it; right-clicking another icon moves it

## 3. Schedule Event modal (`@/components/ui/modals/ScheduleEventModal`, shared)

**Add mode**
- Finish with valid fields calls `createEvent` and never `updateEvent`
- A successful save shows the "Added Event" notification, refreshes the table and closes
- Cancel and Escape close without saving; re-opening shows an empty form, not the discarded edits

**Edit mode**
- The full event is fetched on open and fields not present on the table row come from the API
- All steps are unlocked; the footer button reads "Save", not "Finish"
- Save calls `updateEvent` with the event's id and never `createEvent`

**Steps — Content / Displays / Time / Optional**
- Content: the event type list, content fetched per type, content cleared when the type changes,
  the campaign Preview button, and the type-specific fields (Action, Data Connector)
- Displays: Finish appears only once a display is chosen; Sync events skip the step entirely;
  pre-filled display groups skip the manual picking step
- Time: Always hides the date fields; Custom reveals Start/End; Command hides the daypart selector;
  relative time; Interrupt's Share of Voice
- Optional: sub-tab visibility by daypart and feature flag, the per-event-type field sets,
  repeat configuration, and the geo-location map

**Validation**
- Missing required fields surface an error, do not call the API and keep the modal open
- Validation jumps the user back to the step holding the offending field
- The field-level message is the accessible description of its input

**What the save request carries**
- A Sync event is scheduled against the display group of **every** display in the sync group,
  and is never saved with an empty display group list
- A Layout event sends the displays and groups the user actually picked

## 4. Copy an event (`CopyEventModal.tsx`)
Name pre-filled with " (1)"; empty and duplicate-name validation (case-insensitive); the error clears on
typing; the name is trimmed before saving; Cancel is a no-op; a pending save disables the buttons.

## 5. Delete an event (`DeleteEventModal.tsx`)
Single delete shows the event name; a recurring event offers "this occurrence" (the default) or "the
entire series"; bulk delete shows the item count and no occurrence radio; pending and error states.

## 6. Event actions (`useEventActions.ts`)
Copy, delete, bulk delete and occurrence delete: the API calls made, the toast shown, whether the table
refreshes, whether the modal closes, and that every in-progress flag is reset on both success and failure.

## 7. Agenda (`AgendaModal.tsx`, `useAgendaData.ts`)

**What the agenda shows**
- The heading names the day; the scheduled layouts are listed under their event type with a count
- Each event type gets its own table, with the agenda columns
- A layout the viewer cannot see is labelled "Private Item"; an always-on event shows "Always"
- The sidebar lists the day's display groups, and campaigns only when the day has campaign events
- A tab per display group, but only when the page passed more than one
- Loading, failure, empty, and "no display group selected" states

**What the agenda asks the API for**
- The whole of the chosen day, for the first display group
- Choosing another group re-asks for the same day against that group
- "Specific point in time" **replaces** the whole-day range rather than adding to it
- Nothing is requested at all when there are no display groups

**Acting on the agenda**
- Clicking a layout reveals its breadcrumb; clicking it again hides it
- Sorting by a column, and reversing on a second click
- Previewing a layout, without also selecting the row behind it; no preview without a preview token
- Closing by the close button and by Escape

**Filtering by location**
- The map is hidden until asked for
- Choosing a point drops events fenced elsewhere and keeps unfenced ones; the opposite point keeps
  the opposite event; clearing brings them back
- The browser location button fills the coordinate fields

**Editing from the agenda**
- The breadcrumb opens the event editor for the event that was clicked
- Saving re-reads the day; cancelling leaves it alone
- No way in for a viewer without `schedule.modify`, or for an event the server marks not editable

## 8. Modal routing (`EventModals.tsx`)
Each `activeModal` value opens its own modal and no other; delete forwards the recurring/occurrence
options, the error and the pending flag; copy forwards the existing names and the confirm handler;
schedule opens in add mode and edit in edit mode; **agenda opens for the chosen day and its display
groups, and not at all before a day has been chosen**.

## 9. End-to-end
One scenario walks create → rename → delete and checks the table reflects each step.

---

## Notes / coverage decisions

- **`useEventData` and `useEventFilterOptions` have no test files of their own** — they are exercised
  through the page tests, which is where their behaviour is observable. `useEventActions` and
  `useAgendaData` *do* have their own files, because their error/flag handling and cache-key behaviour
  are not fully reachable from the page.
- **There is no Share on this page.** `Events.tsx` holds `shareEntityIds` state and passes it into
  `EventModals`, but `EventModals` never reads it and renders no `ShareModal`. It is dead plumbing, not
  a missing test — recorded in the execution plan's Known issues.
- **`ScheduleEventModal` is shared with six other pages** and is tested here in full rather than stubbed,
  because the Schedule page is its primary home. Page-level wiring tests stub it; the
  `modals/scheduleModal/` cluster tests it for real.
- **The agenda's nested `ScheduleEventModal` is stubbed** in `nested-edit.test.tsx` — the wizard has its
  own cluster, and the real one fires a dozen unmocked requests. Only the event handed to it and what the
  agenda does on save are asserted.
- **Leaflet is not stubbed.** The real map mounts cleanly in JSDOM (verified), so the map toggle is
  exercised for real rather than against a stand-in.
- **Out of scope, deliberately:** `DateRangeController` and `DisplayGroupMultiSelect` in isolation (both
  are always stubbed), `expandRecurringEvents` as a unit (covered through the calendar), the
  `getEventBadge` priority ladder, and the agenda's row/sidebar highlighting (expressed only as CSS
  classes, which `TESTING_STANDARDS.md` §1.8 forbids asserting). See the execution plan.
