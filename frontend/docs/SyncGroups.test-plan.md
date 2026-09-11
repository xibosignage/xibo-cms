# Test Plan: SyncGroups Page

Covers the Sync Groups page (`frontend/src/pages/Displays/SyncGroups/`), its add/edit and
**Manage Members** modals, and the row/bulk action wiring.

**Scope-shaping features of this page:**

| Feature | Present? | Effect on the plan |
|---|---|---|
| Folder sidebar / folder permissions | **Yes** | Its own section, §6 |
| Bulk actions | **Delete Selected only** | Bulk delete in §5; **no Share** |
| Special / locked rows | **No** | Every row exposes the same actions |
| Multi-tab modal | **No** — single form | §2–§3 |
| Hydration gate | **Yes** — `isHydrated` | Its own section, §8 |
| Auto-opened follow-up modal after create | **Yes** — Members opens after add | Asserted in §7 |

Two features distinguish this page from Commands and Daypart: a **folder sidebar** gated on the
`folder.view` feature, and a **Manage Members** modal that opens automatically after a sync group is
created — a new group is useless until displays are assigned to it.

**There is no Share action on this page.** The row actions are Edit, Members and Delete; the only
bulk action is Delete Selected. See Notes.

---

## 1. SyncGroups Page (`SyncGroups.tsx`)

**Default state**
- The table renders one row per sync group; an empty state is shown when there are none
- The "Add Sync Group" button is visible
- The search input carries the correct placeholder, and the Filters button is visible
- The tab navigation includes "Sync Groups"
- Rows are paginated at 10 per page
- A fetch error renders the error alert above the table

**Search**
- Typing fetches results with that keyword and resets to page 1
- Clearing the search restores the full list

**Filters panel**
- Hidden by default; Filters opens it, clicking again closes it
- The Lead Display ID filter updates the query and resets to page 1
- Changing the Name filter resets to page 1
- Reset clears all inputs but keeps the panel open

**Column visibility**
- The Name column is always visible and cannot be hidden
- The ID column is visible by default
- The Columns button opens the picker, which lists every hideable column
- Unchecking hides a column; re-checking brings it back

## 2. Add Sync Group (`AddAndEditSyncGroupModal` — add mode)

- Opens with the title "Add Sync Group"; all expected fields are present with their defaults
- **Lead Display is not shown in add mode** — there are no members yet, so there is nothing to lead
- Validation: an empty Name and a Publisher Port of 0 each show their error, and a summary error
  message appears above the buttons when validation fails
- A successful save sends the correct data to the API
- Cancel closes without saving
- The Save button shows "Saving…" while in flight; an API error is displayed in the modal

## 3. Edit Sync Group (`AddAndEditSyncGroupModal` — edit mode)

- Opens with the title "Edit Sync Group"
- **The Lead Display field is shown in edit mode** — members exist by now, so one can be nominated
- The same two validation rules apply in edit mode
- A successful save sends the updated values; an API error is displayed in the modal

## 4. Manage Members (`ManageMembersModal.tsx`)

- Opens with the correct title
- A sync group with no current members shows the empty-state copy
- Currently assigned displays are loaded when the modal opens
- Adding a display adds it to the assigned list
- **Adding a display that is already assigned does not add it again**
- Removing a display removes it from the assigned list
- "Clear All" removes every assigned display
- Cancel closes without saving
- **Saving with no changes closes without calling the API** — no pointless request
- Saving after changes calls the API with the correct **add and remove** lists
- The Save button shows "Saving…" while in flight; an API error is displayed in the modal

## 5. Delete Sync Group (`DeleteSyncGroupModal.tsx`)

**Single delete** — the row action opens the confirmation modal showing the sync group name; the
heading is singular; Cancel closes without deleting; confirming removes it and closes; the button
shows "Deleting…" while in flight; a failed delete keeps the modal open and shows the error.

**Bulk delete** — the heading is plural; confirming deletes every selected item; a partial failure
shows the error and refreshes the table.

## 6. Folder sidebar

- The folder sidebar and breadcrumb are **not** rendered when the user lacks the `folder.view` feature
- Changing the folder selection resets pagination to page 1
- Changing the folder selection **clears the current row selection** — rows from the previous folder
  must not stay selected, or a bulk delete would hit items the user can no longer see

## 7. Modal routing (`SyncGroupModals.tsx`)

- "Add Sync Group" opens the Add modal
- **After a successful add, the Members modal opens automatically**
- Clicking Edit on a row opens the Edit modal for that sync group
- The table is refreshed after a successful add and after a successful edit

## 8. Hydration gate

- A loading pulse message appears while user preferences are being restored
- The "Add Sync Group" button is disabled while preferences are loading

---

## Notes / coverage decisions

- **There is no Share on this page**, so there is no share section and no `SyncGroups.share.test.tsx`.
  The row actions defined in `SyncGroupsConfig.tsx` are Edit, Members and Delete; the only bulk action
  is Delete Selected. This is a page-design decision, not a coverage gap.
- **Hooks are not separate test files.** `useSyncGroupsData`, `useSyncGroupActions` and
  `useSyncGroupFilterOptions` are covered transitively.
- **The folder sidebar is covered for the behaviours that affect the table** — permission gating,
  pagination reset and selection clearing. The folder tree component itself is shared and has its own
  tests; re-testing it here would duplicate them.
- **The Members modal is tested directly**, not through the page, because its add/remove
  reconciliation is the most intricate logic on this page and deserves isolation.
