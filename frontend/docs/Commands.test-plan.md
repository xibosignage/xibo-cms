# Test Plan: Commands Page

Covers the Commands page (`frontend/src/pages/Displays/Commands/`), its add/edit modal including the
**Command Builder**, and the row/bulk action wiring.

**Scope-shaping features of this page:**

| Feature | Present? | Effect on the plan |
|---|---|---|
| Folder sidebar / folder permissions | **No** | No folder section |
| Bulk actions | **Yes** — Delete Selected, Share Selected | Bulk sections in §1, §5, §6 |
| Special / locked rows | **No** | Every row exposes the same actions |
| Multi-tab modal | **No** — single form, but with a **type-driven Command Builder** | §2–§4; the builder gets its own section |
| Hydration gate | **Yes** — `isHydrated` | Covered inside §1 rather than its own file |
| Auto-opened follow-up modal after create | **No** | Explicitly asserted — see §7 |

The distinguishing feature of this page is the **Command Builder**: the command string is a
pipe-delimited format whose shape depends on the selected type (free text, Philips Android LED,
RS232, Android Intent, HTTP). Parsing and building that string is pure logic and is tested
separately from the UI — see §4.

---

## 1. Commands Page (`Commands.tsx`)

**Default state**
- The table renders one row per command; an empty state is shown when there are none
- The "Add Command" button is visible
- The search input carries the correct placeholder, and the Filters button is visible
- The tab navigation includes "Commands"
- Rows are paginated at 10 per page
- A fetch error renders the error alert above the table
- While preferences load, a loading pulse is shown and the controls are disabled

**Search**
- Typing fetches results with that keyword and resets to page 1
- Clearing the search restores the full list

**Filters panel**
- Hidden by default; Filters opens it, clicking again closes it
- The Name and Code filters each expose AND/OR and regex toggles
- Each filter updates the query and resets to page 1
- Reset clears all inputs but keeps the panel open

**Column visibility**
- The Name column is always visible and cannot be hidden
- The ID column and the other defaults are visible on first load
- The Columns button opens the picker, which lists every hideable column
- Unchecking hides a column; re-checking brings it back

**Bulk actions**
- Selecting rows reveals the bulk action buttons

## 2. Add Command (`AddEditCommandModal` — add mode)

- Opens with the title "Add Command"; all expected fields are present with their default values
- The **Code** field is editable in add mode
- Validation: empty Name, empty Code, and an invalid Code format each show their error
- A successful save sends the correct data to the API
- Cancel closes without saving
- The Save button shows "Saving…" while in flight; an API error is displayed in the modal

## 3. Edit Command (`AddEditCommandModal` — edit mode)

- Opens with the title "Edit Command"
- The **Code** field is disabled — a command's code is immutable once created
- "Available On" is populated from the stored comma-separated value
- The Command Builder is pre-populated by parsing the stored command string
- A successful save sends the updated values **without the code**
- Cancel closes without saving; an API error is displayed in the modal

## 4. Command Builder string format (`commandStringUtils.ts`)

Pure functions, tested directly with no DOM. Three groups:

**Defaults** — the documented starting state for each type: RS232 serial defaults, an HTTP GET with
empty headers and body, empty free text, Philips Android starting at colour "off", Android Intent
as an activity with no name or extras, HTTP defaulting to form-urlencoded.

**Parsing** (`parseCommandString`) — empty/missing is empty free text; a plain string with no pipe is
free text; Philips Android, RS232, Android Intent and HTTP each parse into their structured shape;
an unknown type prefix falls back to free text carrying the whole value.

**Building** (`buildCommandString`) — each type serialises back to its documented wire format,
including Android Intent coercing extra values to their declared type and omitting the extras
segment when there are none.

**Round-trip** — parse followed by build returns the original string.

## 5. Delete Command (`DeleteCommandModal.tsx`)

**Single delete** — the row action opens the confirmation modal showing the command name; the
heading is singular; Cancel closes without deleting; confirming removes the command and closes;
the button shows "Deleting…" while in flight; a failed delete keeps the modal open and shows the error.

**Bulk delete** — selecting rows reveals the bulk buttons; the heading is plural with the count;
confirming deletes every selected item; a partial failure shows the error and refreshes the table.

## 6. Share (row + bulk wiring)

- The Share row action opens the Share modal for that command
- "Share Selected" opens it for the selected commands

`ShareModal` is stubbed; only the wiring is asserted.

## 7. Modal routing (`CommandModals.tsx`)

- "Add Command" opens the Add modal
- The Edit action opens the Edit modal — **not** Share or Delete
- The table is refreshed after a successful add and after a successful edit
- **No second modal opens automatically after add** (contrast SyncGroups, which opens Members)

---

## Notes / coverage decisions

- **The Command Builder's field components are not tested individually.** `FreeTextFields`,
  `HttpFields`, `IntentFields`, `KeyValueBuilder`, `PhilipsAndroidFields` and `Rs232Fields` are
  covered through the modal tests (pre-population in edit mode) and through the pure-function tests
  for the string format. Testing each field component in isolation would assert markup rather than
  behaviour; the string format is where the real logic lives, and that has its own file.
- **Hooks are not separate test files.** `useCommandsData`, `useCommandActions` and
  `useCommandFilterOptions` are covered transitively.
- **`ShareModal` is stubbed**, matching Campaigns, Displays and Daypart.
- **No folder sidebar on this page.**
- **The hydration gate has no dedicated file here** — unlike Daypart, it is a single assertion inside
  `Commands.render.test.tsx`. Both approaches are acceptable; a dedicated file is worth it only when
  there is more than one behaviour to cover.
