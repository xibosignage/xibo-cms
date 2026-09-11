# Test Plan: Daypart Page

Covers the Dayparting page (`frontend/src/pages/Schedule/Daypart/`), its two modals, and the
row/bulk action wiring between them.

**Scope-shaping features of this page** (these decide which sections exist below):

| Feature | Present? | Effect on the plan |
|---|---|---|
| Folder sidebar / folder permissions | **No** | No folder section |
| Bulk actions | **Yes** — Delete Selected, Share Selected | Bulk sections in §1, §5, §6 |
| Special / locked rows | **Yes** — `isAlways === 1 \|\| isCustom === 1` | Row-action visibility rules in §1 |
| Multi-tab modal | **Yes** — General / Description / Exceptions | §2–§4 split by tab |
| Hydration gate | **Yes** — `isHydrated` | Its own section, §8 |
| Auto-opened follow-up modal after create | **No** | Not covered |

---

## 1. Daypart Page (`Daypart.tsx`)

**Default state**
- Page loads showing the daypart table, one row per daypart
- An empty state is shown when no dayparts exist
- Default columns are visible (plus the row Actions column)
- Rows are paginated at 10 per page
- The tab navigation shows "Dayparting" as the active tab
- The "Add Daypart" button is visible
- A fetch error renders an error alert above the table

**Search**
- Typing in the search box fetches results filtered by that keyword
- Changing the search term resets to page 1
- Clearing the search restores the full list

**Filters panel**
- The panel is hidden by default; clicking "Filters" opens it, clicking again closes it
- The Name filter updates the query and resets to page 1
- The Retired filter updates the query and resets to page 1
- The Name AND/OR control changes the query operator
- The regex control sends `useRegexForName` when a Name filter is set
- "Reset" clears the filter inputs but does **not** close the panel

**Column visibility**
- The Columns button opens the column picker
- The picker lists every hideable column
- The Name column cannot be hidden (no toggle shown)
- Unchecking a column hides it; re-checking brings it back

**Row actions**
- A normal row exposes Edit and Delete as quick actions, and Share in the overflow menu
- A **special** row (`isAlways` or `isCustom`) hides Edit and Delete — these are the built-in
  Always/Custom dayparts and must not be modified or removed

**Bulk actions**
- Selecting rows enables "Delete Selected" and "Share Selected"
- "Delete Selected" opens the delete modal showing the selected count

## 2. Add Daypart (`AddAndEditDaypartModal` — add mode)

- **Opening** — the modal opens with the title "Add Daypart"
- **Tabs** — all three tabs are present (General, Description, Exceptions)
- **Fields** — General shows Name, Retired, Start Time, End Time; Description shows the multiline
  Description field; fields are empty / unchecked by default
- **In-use warning** — not shown in add mode
- **Validation** — an empty Name shows a validation error and does not save; empty Start/End times
  show the time validation errors; a validation error **switches back to the General tab**
- **Save** — a successful save sends the entered values to `createDaypart` and closes
- **Cancel** — closes without saving
- **Pending & error** — the Save button shows "Saving…" while in flight; an API error is displayed
  in the modal

## 3. Edit Daypart (`AddAndEditDaypartModal` — edit mode)

- **Opening** — title is "Edit Daypart"; fields are pre-filled, including Description and Retired
- **In-use warning** — shown in edit mode
- **Save** — a successful save sends the updated values to `updateDaypart`
- **Cancel** — closes without calling `updateDaypart`
- **Error** — an API error is displayed in the modal

## 4. Exceptions tab (`AddAndEditDaypartModal` — exceptions)

- The tab shows its helper text
- "Add Exception" adds an exception row
- The Day dropdown offers every day, Monday through Sunday
- The remove button deletes an exception row
- A day chosen in one row is **disabled** in the others
- "Add Exception" is disabled once all seven days are used
- On save, exceptions are serialised into the three parallel payload arrays

## 5. Delete Daypart (`DeleteDaypartModal.tsx`)

**Single delete**
- The Delete row action opens the confirmation modal showing the daypart name
- The heading is singular
- Cancel closes without deleting
- "Yes, Delete" removes the daypart, refreshes the table and closes the modal
- The button shows "Deleting…" while the request is in progress
- A failed delete keeps the modal open and shows the error

**Bulk delete**
- The heading is plural and shows the selected count
- Confirming deletes every selected item
- A partial failure shows the error and refreshes the table

## 6. Share (row + bulk wiring)

- The Share row action opens the Share modal for that daypart
- "Share Selected" opens the Share modal for all selected dayparts

`ShareModal` is a shared component and is **stubbed**; only the wiring and the ids passed through
are asserted. Its internals are out of scope — see Notes.

## 7. Modal routing (`DaypartModals.tsx`)

- The "Add Daypart" button opens the Add modal
- Each row action opens its own modal
- Only one modal is open at a time
- The table is refreshed after a successful save (add and edit)

## 8. Hydration gate

- A loading pulse message appears while user preferences are being restored
- The "Add Daypart" button is disabled while preferences are loading

---

## Notes / coverage decisions

- **Hooks are not separate test files.** `useDaypartData`, `useDaypartActions` and
  `useDaypartFilterOptions` are covered transitively by the page and modal tests. Testing them in
  isolation would assert implementation rather than behaviour.
- **`ShareModal` is stubbed**, matching the approach on Campaigns, Commands and Displays. Only the
  Share wiring (which ids reach it) is tested here; the modal's own behaviour belongs to its own suite.
- **No folder sidebar on this page**, so there is no folder-permission section. Compare SyncGroups,
  which has one.
- **Special rows are a visibility rule, not a permission rule.** `isAlways`/`isCustom` is row *data*;
  the permission checks (`canModify`, `canEdit`, `canDelete`) are separate and layered on top. Both
  paths are covered in §1.
- **No auto-opened follow-up modal.** Unlike SyncGroups (which opens Members after create), Daypart
  simply closes and refreshes.
