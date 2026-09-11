# Daypart Tests — Steps

All tests live under `frontend/src/pages/Schedule/Daypart/__tests__/`.

Each numbered step reads **load → act → assert**. This file is the spec the test code implements;
the test names below match the `test('…')` descriptions in the suite one-for-one.

---

## `Daypart.render.test.tsx` — Page default state (12)

1. **the table renders a row for each daypart** — load with `MULTIPLE_DAYPARTS`; each name appears.
2. **an empty state is shown when no dayparts exist** — load with `EMPTY_DAYPART_TABLE`.
3. **the "Add Daypart" button is visible** — load; the button is in the document.
4. **the default columns are visible** — load; assert each default column header.
5. **the search input has the placeholder "Search daypart..."** — load; query by placeholder.
6. **the Filters button is visible** — load; the button is present.
7. **the tab navigation includes the "Dayparting" tab** — load; the tab is shown and active.
8. **dayparts are paginated at 10 per page by default** — load; the fetch is called with length 10.
9. **a fetch error renders the error alert above the table** — make the fetch reject; an alert shows.
10. **a normal daypart row shows Edit and Delete quick actions** — load `SINGLE_DAYPART`; both present.
11. **a normal daypart row exposes the Share action in the overflow menu** — open the row menu; Share present.
12. **a special (Always) daypart hides the Edit and Delete actions** — load `ALWAYS_DAYPART`; neither present.

## `Daypart.filters.test.tsx` — Search and filter panel (12)

1. **the filter panel is hidden by default** — load; the filter inputs are not in the tree.
2. **clicking Filters opens the panel** — click Filters; the inputs appear.
3. **clicking Filters again closes the panel** — click twice; the inputs are gone.
4. **typing in the search box fetches results with that keyword** — type; assert the fetch argument.
5. **typing in the search box resets to page 1** — go to page 2, type, assert `start: 0`.
6. **entering a Name filter updates the query and resets to page 1** — open panel, type a name.
7. **changing the Retired filter updates the query and resets to page 1** — change the select.
8. **clearing the search restores the unfiltered list** — type then clear; the keyword is dropped.
9. **toggling the Name AND/OR control updates the query operator** — toggle; assert the operator.
10. **toggling the regex control sends `useRegexForName` when a Name filter is set** — set a name,
    toggle regex, assert the flag is sent.
11. **clicking Reset clears the filter inputs** — set filters, Reset, inputs are empty.
12. **Reset keeps the filter panel open** — after Reset the inputs are still in the tree.

## `Daypart.column.test.tsx` — Column visibility (7)

1. **the Name column header is visible** — load; the header is present.
2. **the ID column header is visible by default** — load; the header is present.
3. **the Columns button opens the column picker** — click; the picker appears.
4. **the column picker lists every hideable column** — open; assert each toggle.
5. **the Name column cannot be hidden** — open; there is no Name toggle.
6. **unchecking a column hides it from the table** — uncheck; the header goes.
7. **re-checking a hidden column brings it back** — re-check; the header returns.

## `Daypart.hydration.test.tsx` — Preference hydration gate (2)

1. **a loading pulse message appears while preferences are being restored** — render without seeding
   the preference query key; the pulse text is shown.
2. **the "Add Daypart" button is disabled while preferences are loading** — same setup; the button
   is disabled.

> These two are the only tests that render **without** the seeded preference key. Every other page
> test seeds it via `renderDaypartPage`, or the suite would hang on the gate.

## `Daypart.add.test.tsx` — Add wiring (3)

1. **the "Add Daypart" button opens the Add modal** — click; the add modal is shown.
2. **only one modal is open at a time** — open add; assert no other modal is present.
3. **the table is refreshed after a successful save** — save; the fetch is called again.

## `Daypart.edit.test.tsx` — Edit wiring (2)

1. **clicking Edit on a row opens the Edit modal for that daypart** — click Edit; modal shows that row.
2. **the table is refreshed after saving an edit** — save; the fetch is called again.

## `Daypart.delete.test.tsx` — Delete (9)

### Single delete
1. **the Delete row action opens the confirmation modal showing the daypart name**
2. **clicking Cancel closes the modal without deleting** — no API call.
3. **clicking Yes, Delete removes the daypart, refreshes the table, and closes the modal**
4. **the Delete button shows "Deleting…" while the request is in progress** — controlled promise.
5. **a failed delete keeps the modal open and shows the error**
6. **single item: the heading is singular**

### Bulk delete
7. **multiple items: the heading is plural and shows the count**
8. **bulk confirm deletes every selected item** — one call per id.
9. **a partial bulk delete failure shows the error and refreshes the table**

## `Daypart.share.test.tsx` — Share wiring, ShareModal stubbed (2)

1. **the Share row action opens the Share modal for that daypart** — assert the single id passed.
2. **"Share Selected" opens the Share modal for all selected dayparts** — assert the id array.

## `modals/add-edit.test.tsx` — AddAndEditDaypartModal (20)

### Add mode (13)
1. **the modal opens with the title "Add Daypart"**
2. **all three tabs are present**
3. **the General tab shows the Name, Retired, Start Time and End Time fields**
4. **the Description tab shows the multiline Description field**
5. **the in-use warning is not shown in add mode**
6. **the fields are empty / unchecked by default**
7. **saving with an empty Name shows a validation error and does not save**
8. **saving with empty Start/End times shows the time validation errors**
9. **a validation error switches back to the General tab** — submit from the Description tab.
10. **a successful save sends the entered values to `createDaypart` and closes**
11. **clicking Cancel closes the modal without saving**
12. **the Save button shows "Saving…" while the request is in progress** — controlled promise.
13. **an API error is displayed in the modal**

### Edit mode (7)
14. **the modal opens with the title "Edit Daypart"**
15. **the fields are pre-filled with the daypart values**
16. **the Description and Retired fields are pre-filled in edit mode**
17. **the in-use warning is shown in edit mode**
18. **clicking Cancel in edit mode closes without calling `updateDaypart`**
19. **a successful save sends the updated values to `updateDaypart`**
20. **an API error is displayed in the modal**

## `modals/exceptions.test.tsx` — Exceptions tab (7)

1. **the Exceptions tab shows the helper text**
2. **clicking "Add Exception" adds an exception row**
3. **the Day dropdown offers every day Monday through Sunday**
4. **the remove button deletes an exception row**
5. **a day chosen in one row is disabled in the other rows**
6. **"Add Exception" is disabled once all seven days are used**
7. **exceptions are serialised into the three parallel payload arrays on save** — the payload
   assertion for this tab; see `TESTING_STANDARDS.md` §4.1.
