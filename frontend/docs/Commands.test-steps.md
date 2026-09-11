# Commands Tests — Steps

All tests live under `frontend/src/pages/Displays/Commands/__tests__/`.

Each numbered step reads **load → act → assert**. Test names match the `test('…')` descriptions in
the suite one-for-one.

---

## `Commands.render.test.tsx` — Page default state (9)

1. **table renders with command rows** — load with multiple commands; each name appears.
2. **empty state is shown when no commands exist** — load with an empty table.
3. **"Add Command" button is visible** — load; the button is present.
4. **search input is present with the correct placeholder** — query by placeholder.
5. **Filters button is visible** — load; the button is present.
6. **tab nav includes "Commands"** — the tab is shown.
7. **a fetch error renders the error alert above the table** — make the fetch reject.
8. **commands are paginated at 10 per page by default** — assert the fetch length argument.
9. **shows the loading pulse and disables controls while preferences load** — render without the
   seeded preference key; the pulse shows and controls are disabled.

## `Commands.filters.test.tsx` — Search and filter panel (11)

1. **filter panel is hidden by default**
2. **clicking Filters opens the panel**
3. **the Name and Code filters expose AND/OR and regex toggles** — both filters, both controls.
4. **clicking Filters again closes the panel**
5. **typing in the search box fetches results with that keyword**
6. **typing in the search box resets pagination to page 1**
7. **clearing the search box restores the full list**
8. **entering a Name filter updates the query and resets to page 1**
9. **entering a Code filter updates the query and resets to page 1**
10. **clicking Reset clears all filter inputs**
11. **Reset keeps the filter panel open**

## `Commands.column.test.tsx` — Column visibility (8)

1. **Name column is always visible**
2. **ID column is visible by default**
3. **all default columns are visible on first load**
4. **Columns button opens the column picker**
5. **the column picker lists every hideable column**
6. **the Name column cannot be hidden** — no toggle for it.
7. **unchecking a column hides it from the table**
8. **re-checking a hidden column brings it back**

## `Commands.add.test.tsx` — Add wiring (3)

1. **"Add Command" button opens the Add modal**
2. **the table is refreshed after a successful add**
3. **no second modal opens automatically after add** — the explicit contrast with SyncGroups.

## `Commands.edit.test.tsx` — Edit wiring (3)

1. **clicking Edit on a row opens the Edit modal for that command**
2. **the Edit action opens the Edit modal (not Share or Delete)** — guards action mis-wiring.
3. **the table is refreshed after saving an edit**

## `Commands.delete.test.tsx` — Delete (10)

### Single delete
1. **Delete row action opens the confirmation modal showing the command name**
2. **clicking Cancel closes the modal without deleting**
3. **clicking Yes, Delete removes the command and closes the modal**
4. **Delete button shows "Deleting…" while the request is in progress** — controlled promise.
5. **a failed delete keeps the modal open and shows the error**
6. **single item: heading is singular**

### Bulk delete
7. **selecting rows reveals the bulk action buttons**
8. **multiple items: heading is plural with the count**
9. **bulk confirm deletes every selected item** — one call per id.
10. **a partial bulk delete failure shows the error and refreshes the table**

## `Commands.share.test.tsx` — Share wiring, ShareModal stubbed (2)

1. **the Share row action opens the Share modal for the command**
2. **"Share Selected" opens the Share modal for the selected commands**

## `modals/add-edit.test.tsx` — AddEditCommandModal (19)

### Add mode (11)
1. **modal opens with the title "Add Command"**
2. **all expected fields are present**
3. **default values are applied when the modal opens**
4. **the Code field is editable in add mode**
5. **saving with an empty Name shows a validation error**
6. **saving with an empty Code shows a validation error**
7. **saving with an invalid Code shows the format error**
8. **a successful save sends the correct data to the API**
9. **clicking Cancel closes the modal without saving**
10. **Save button shows "Saving…" while the request is in progress**
11. **an API error is displayed in the modal**

### Edit mode (8)
12. **modal opens with the title "Edit Command"**
13. **the Code field is disabled in edit mode** — a command's code is immutable.
14. **Available On is populated from the comma-separated value**
15. **the Command Builder is pre-populated from the stored command string** — the UI half of the
    parse logic tested in the next file.
16. **a successful save sends the updated values to the API without the code**
17. **clicking Cancel closes the modal without saving**
18. **an API error is displayed in the modal**

## `modals/command-builder-utils.test.ts` — Command string format (20)

Pure functions, **no DOM** — the only `.test.ts` in the suite. Grouped by `describe`:

### default constants (1–3) and `getDefaultParsedCommand` (4–7)
1. **RS232 defaults match the documented serial defaults**
2. **HTTP request-option defaults are a GET with empty headers and body**
3. **free text starts as an empty string**
4. **Philips Android (LED) starts with the colour "off"**
5. **RS232 starts with the default serial config and an empty command**
6. **Android Intent starts as an activity with no name and no extras**
7. **HTTP starts with a form-urlencoded content type and default options**

### `parseCommandString` (8–13)
8. **an empty / missing value is treated as empty free text**
9. **a plain string with no pipe is parsed as free text**
10. **a Philips Android LED command is parsed into its colour**
11. **an RS232 command is parsed into its serial config and command**
12. **an Android Intent command is parsed into type, name and extras (values as strings)**
13. **an HTTP command is parsed into url, content type and request options**
14. **an unknown type prefix falls back to free text with the whole value**

### `buildCommandString` (15–19)
15. **free text is returned as-is**
16. **a Philips Android LED command is serialised with its colour**
17. **an RS232 command is serialised as `rs232|<config>|<command>`**
18. **an Android Intent command coerces extra values to their declared type**
19. **an Android Intent with no extras omits the extras segment**
20. **an HTTP command is serialised as `http|<url>|<contenttype>|<options>`**

### parse ↔ build round-trip
Covered by the round-trip `describe` — parsing then rebuilding returns the original string.
