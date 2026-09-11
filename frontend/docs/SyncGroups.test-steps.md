# SyncGroups Tests — Steps

All tests live under `frontend/src/pages/Displays/SyncGroups/__tests__/`.

Each numbered step reads **load → act → assert**. Test names match the `test('…')` descriptions in
the suite one-for-one.

---

## `SyncGroups.render.test.tsx` — Page default state (8)

1. **table renders with sync group rows** — load with multiple groups; each name appears.
2. **empty state is shown when no sync groups exist**
3. **"Add Sync Group" button is visible**
4. **search input is present with the correct placeholder**
5. **Filters button is visible**
6. **tab nav includes "Sync Groups"**
7. **a fetch error renders the error alert above the table**
8. **sync groups are paginated at 10 per page by default**

## `SyncGroups.filters.test.tsx` — Search and filter panel (10)

1. **filter panel is hidden by default**
2. **clicking Filters opens the panel**
3. **clicking Filters again closes the panel**
4. **typing in the search box fetches results with that keyword**
5. **typing in the search box resets pagination to page 1**
6. **clearing the search box restores the full list**
7. **entering a Lead Display ID updates the query and resets to page 1**
8. **changing a filter value (Name) resets to page 1**
9. **clicking Reset clears all filter inputs**
10. **Reset keeps the filter panel open**

## `SyncGroups.column.test.tsx` — Column visibility (7)

1. **Name column is always visible**
2. **ID column is visible by default**
3. **Columns button opens the column picker**
4. **the column picker lists every hideable column**
5. **the Name column cannot be hidden**
6. **unchecking a column hides it from the table**
7. **re-checking a hidden column brings it back**

## `SyncGroups.folder.test.tsx` — Folder sidebar (3)

1. **folder sidebar and breadcrumb are NOT rendered when the user lacks `folder.view`** — render with
   a user missing the feature; neither element is in the tree.
2. **changing folder selection resets pagination to page 1** — go to page 2, change folder, assert
   `start: 0`.
3. **changing folder selection clears the current row selection** — select rows, change folder, the
   selection is empty. Without this, a bulk delete could act on rows from a folder the user has
   navigated away from.

## `SyncGroups.hydration.test.tsx` — Preference hydration gate (2)

1. **a loading pulse message appears while user preferences are being restored** — render without
   seeding the preference query key.
2. **"Add Sync Group" button is disabled while preferences are loading**

> As on Daypart, these are the only two tests that render **without** the seeded preference key.

## `SyncGroups.add.test.tsx` — Add wiring (3)

1. **"Add Sync Group" button opens the Add modal**
2. **after a successful add the Members modal opens automatically** — the page-specific behaviour; a
   new group has no displays, so the flow continues straight into assigning them.
3. **table is refreshed after save**

## `SyncGroups.edit.test.tsx` — Edit wiring (2)

1. **clicking Edit on a row opens the Edit modal for that sync group**
2. **table is refreshed after saving an edit**

## `SyncGroups.delete.test.tsx` — Delete (9)

### Single delete
1. **Delete row action opens the confirmation modal showing the sync group name**
2. **clicking Cancel closes the modal without deleting**
3. **clicking Yes, Delete removes the sync group and closes the modal**
4. **Delete button shows "Deleting…" while the request is in progress** — controlled promise.
5. **a failed delete keeps the modal open and shows the error**
6. **single item: heading is singular**

### Bulk delete
7. **multiple items: heading is plural**
8. **bulk confirm deletes every selected item** — selection is driven through the header
   "select all" checkbox; the file's `selectAllRows` helper clicks it.
9. **a partial bulk delete failure shows the error and refreshes the table** — asserts the call count
   and the **set** of ids, not their order: `Promise.allSettled` fires concurrently.

## `modals/add-edit.test.tsx` — AddAndEditSyncGroupModal (19)

### Add mode (11)
1. **modal opens with the title "Add Sync Group"**
2. **all expected fields are present**
3. **Lead Display is not shown in add mode**
4. **default values are applied when the modal opens**
5. **saving with an empty Name shows a validation error**
6. **saving with Publisher Port set to 0 shows a validation error**
7. **a summary error message appears above the buttons when validation fails**
8. **a successful save sends the correct data to the API**
9. **clicking Cancel closes the modal without saving**
10. **Save button shows "Saving…" while the request is in progress**
11. **an API error is displayed in the modal**

### Edit mode (8)
12. **modal opens with the title "Edit Sync Group"**
13. **Lead Display field is shown in edit mode** — the paired assertion to add-mode #3.
14. **saving with an empty Name shows a validation error (edit mode)**
15. **saving with Publisher Port set to 0 shows a validation error (edit mode)**
16. **a successful save sends the updated values to the API**
17. **an API error is displayed in the modal**

## `modals/members.test.tsx` — ManageMembersModal (12)

1. **modal opens with the correct title**
2. **a sync group with no current members shows the empty-state copy**
3. **currently assigned displays are loaded on open**
4. **adding a display that is already assigned does not add it again** — the de-duplication rule.
5. **adding a display adds it to the assigned list**
6. **removing a display removes it from the assigned list**
7. **Clear All removes every assigned display**
8. **clicking Cancel closes the modal without saving**
9. **saving with no changes closes the modal without calling the API** — no pointless request.
10. **saving after changes calls the API with the correct add and remove lists** — the payload
    assertion for this modal; the reconciliation between the original and current sets is the most
    intricate logic on the page. See `TESTING_STANDARDS.md` §4.1.
11. **Save button shows "Saving…" while the request is in progress**
12. **an API error is displayed in the modal**
