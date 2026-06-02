# Dataset Feature — Full Test Plan

> **Trigger**: When the user says "Dataset", revisit this file to create the next batch of remaining test scenarios.
>
> **Test suite location**: `src/pages/Library/Dataset/__tests__/`
> **Last updated**: 2026-05-20

---

## Legend

- ✅ **COVERED** — test already exists in the current suite
- ⬜ **REMAINING** — test not yet written

---

## LIST 1 — All Possible Test Scenarios

### 1. AddAndEditDatasetModal (`components/AddAndEditDatasetModal.tsx`)

#### 1.1 Add Mode
| # | Scenario | Status |
|---|----------|--------|
| 1 | Renders "Add Dataset" title with empty Name, Description, Code fields | ✅ |
| 2 | Shows validation error "Name is required" when saving with empty name | ✅ |
| 3 | Calls `fetchDataConnectorSource` on mount | ✅ |
| 4 | Calls `createDataset` with correct payload on valid submit | ⬜ |
| 5 | Calls `onSave` and `onClose` after successful create | ⬜ |
| 6 | Displays API error message when `createDataset` rejects | ⬜ |
| 7 | Description field accepts and stores text | ⬜ |
| 8 | Code field accepts and stores text | ⬜ |
| 9 | Cancel button calls `onClose` without submitting | ⬜ |
| 10 | Save button is disabled while submission is pending | ⬜ |

#### 1.2 Edit Mode
| # | Scenario | Status |
|---|----------|--------|
| 11 | Renders "Edit Dataset" title and pre-populates Name, Description, Code | ✅ |
| 12 | Calls `updateDataset` with correct payload on valid submit | ⬜ |
| 13 | Calls `onSave` and `onClose` after successful update | ⬜ |
| 14 | Displays API error message when `updateDataset` rejects | ⬜ |

#### 1.3 Real-Time Dataset
| # | Scenario | Status |
|---|----------|--------|
| 15 | Data Connector Source dropdown hidden by default (Real-time unchecked) | ✅ |
| 16 | Data Connector Source dropdown appears when Real-time checkbox is checked | ✅ |
| 17 | Data Connector Source dropdown hides again when Real-time is unchecked | ✅ |
| 18 | Pre-populates Data Connector Source for existing real-time dataset in edit mode | ✅ |
| 19 | Connector source options populate from `fetchDataConnectorSource` response | ⬜ |
| 20 | Selecting a connector source updates the draft payload | ⬜ |

#### 1.4 Remote Dataset — Tab Visibility
| # | Scenario | Status |
|---|----------|--------|
| 21 | Remote, Authentication, Data, Advanced tabs hidden when Remote is unchecked | ✅ |
| 22 | Remote, Authentication, Data, Advanced tabs shown when Remote is checked | ✅ |
| 23 | Extra tabs hide again when Remote is unchecked | ✅ |
| 24 | Shows URI validation error when Remote is checked and URI is empty on save | ✅ |

#### 1.5 Remote Tab Fields
| # | Scenario | Status |
|---|----------|--------|
| 25 | URI field accepts a valid HTTP URL | ⬜ |
| 26 | HTTP method dropdown defaults to GET | ⬜ |
| 27 | Selecting POST method reveals Post Data textarea | ⬜ |
| 28 | Selecting GET method hides Post Data textarea | ⬜ |
| 29 | Data Root field accepts a JSON path value | ⬜ |
| 30 | Source type dropdown (JSON / CSV) changes visible fields | ⬜ |
| 31 | CSV Separator field appears when source type is CSV | ⬜ |
| 32 | Ignore First Row checkbox appears when source type is CSV | ⬜ |
| 33 | Row Limit accepts a numeric value | ⬜ |
| 34 | Limit Policy dropdown shows stop / FIFO / truncate options | ⬜ |
| 35 | Summarize dropdown changes visible Summarize Field input | ⬜ |

#### 1.6 Authentication Tab Fields
| # | Scenario | Status |
|---|----------|--------|
| 36 | Authentication type defaults to "None" with no extra fields | ⬜ |
| 37 | Selecting "Basic" reveals Username and Password fields | ⬜ |
| 38 | Selecting "Digest" reveals Username and Password fields | ⬜ |
| 39 | Selecting "NTLM" reveals Username and Password fields | ⬜ |
| 40 | Selecting "Bearer Token" reveals Token field | ⬜ |
| 41 | Switching back to "None" hides credential fields | ⬜ |

#### 1.7 Data Tab Fields
| # | Scenario | Status |
|---|----------|--------|
| 42 | Refresh Rate accepts a numeric value | ⬜ |
| 43 | Clear Rate accepts a numeric value | ⬜ |
| 44 | Truncate on Empty checkbox toggles state | ⬜ |
| 45 | Runs After dropdown lists available datasets | ⬜ |

#### 1.8 Advanced Tab Fields
| # | Scenario | Status |
|---|----------|--------|
| 46 | Custom Headers field accepts header strings | ⬜ |
| 47 | User Agent field accepts a custom string | ⬜ |

#### 1.9 Folder Assignment
| # | Scenario | Status |
|---|----------|--------|
| 48 | SelectFolder component is rendered when `canViewFolders` is true | ⬜ |
| 49 | SelectFolder component is not rendered when `canViewFolders` is false | ⬜ |

---

### 2. CopyDatasetModal (`components/CopyDatasetModal.tsx`)

| # | Scenario | Status |
|---|----------|--------|
| 50 | Does not render when `isOpen` is false | ✅ |
| 51 | Initializes with incremented name and pre-populated Description and Code | ✅ |
| 52 | Shows "Name is required" error when saving with empty name | ✅ |
| 53 | Shows duplicate name error when name matches existing (case-insensitive) | ✅ |
| 54 | Trims whitespace from name before submitting | ✅ |
| 55 | Loading state disables all inputs and shows "Saving…" | ✅ |
| 56 | "Copy rows?" checkbox is unchecked by default | ⬜ |
| 57 | Checking "Copy rows?" passes `copyRows: true` to `onConfirm` | ⬜ |
| 58 | Cancel button calls `onClose` without triggering `onConfirm` | ⬜ |
| 59 | Correct `onConfirm` arguments: trimmed name, description, code, copyRows | ⬜ |

---

### 3. DeleteDatasetModal (`components/DeleteDatasetModal.tsx`)

| # | Scenario | Status |
|---|----------|--------|
| 60 | Renders "Delete Dataset?" heading for single item | ⬜ |
| 61 | Renders "Delete Datasets?" heading and item count for multiple items | ⬜ |
| 62 | Displays dataset name in confirmation message for single delete | ⬜ |
| 63 | "Delete any associated data?" checkbox is unchecked by default | ⬜ |
| 64 | Checking the checkbox passes `{ deleteData: true }` to `onDelete` | ⬜ |
| 65 | Unchecked checkbox passes `{ deleteData: false }` to `onDelete` | ⬜ |
| 66 | Clicking "Yes, Delete" calls `onDelete` | ⬜ |
| 67 | Clicking "Cancel" calls `onClose` | ⬜ |
| 68 | Loading state shows "Deleting…" and disables the confirm button | ⬜ |
| 69 | Error message is displayed when `error` prop is provided | ⬜ |

---

### 4. ImportDatasetCsvModal (`components/ImportDatasetCsvModal.tsx`)

| # | Scenario | Status |
|---|----------|--------|
| 70 | Renders drop zone when no file is selected | ⬜ |
| 71 | Accepts a `.csv` file via drop and shows file name and size | ⬜ |
| 72 | Remove (×) button clears the selected file and returns to drop zone | ⬜ |
| 73 | "Done" button is disabled when no file is selected | ⬜ |
| 74 | "Overwrite existing data?" checkbox defaults to unchecked | ⬜ |
| 75 | "Ignore first row?" checkbox defaults to checked | ⬜ |
| 76 | Fetches dataset columns (`fetchDatasetColumns`) when modal opens | ⬜ |
| 77 | Column mapping section shows only Value-type columns | ⬜ |
| 78 | Column mappings default to sequential 1-based order | ⬜ |
| 79 | Changing a mapping input updates the mapping state | ⬜ |
| 80 | Clicking "Done" calls `importDatasetCsv` with file, overwrite, ignoreFirstRow, and mappings | ⬜ |
| 81 | Progress bar appears during upload and reflects progress percentage | ⬜ |
| 82 | Success notification fires and modal closes after successful import | ⬜ |
| 83 | Error notification fires on import failure with error message | ⬜ |
| 84 | Failure to load columns shows error notification | ⬜ |
| 85 | All state resets (file, progress, overwrite, ignoreFirstRow) when modal closes | ⬜ |
| 86 | Inputs are disabled while upload is in progress | ⬜ |

---

### 5. DatasetModals (`components/DatasetModals.tsx`)

| # | Scenario | Status |
|---|----------|--------|
| 87 | Renders nothing when `activeModal` is null | ✅ |
| 88 | Renders `AddAndEditDatasetModal` when `activeModal` is "edit" | ✅ |
| 89 | Renders `CopyDatasetModal` when `activeModal` is "copy" | ✅ |
| 90 | Renders `DeleteDatasetModal` when `activeModal` is "delete" | ✅ |
| 91 | Renders `ShareModal` when `activeModal` is "share" | ✅ |
| 92 | Renders `ImportDatasetCsvModal` when `activeModal` is "import" | ✅ |
| 93 | Does not render `ImportDatasetCsvModal` when `selectedDatasetId` is null | ✅ |
| 94 | Renders `MoveModal` when `activeModal` is "move" | ⬜ |
| 95 | Renders `FolderActionModals` component | ⬜ |

---

### 6. useDatasetActions (`hooks/useDatasetActions.ts`)

| # | Scenario | Status |
|---|----------|--------|
| 96 | Initializes with `isDeleting: false`, `isCloning: false`, `deleteError: null` | ✅ |
| 97 | `confirmDelete` — deletes all items and calls `handleRefresh`, `closeModal`, `setRowSelection({})` | ✅ |
| 98 | `confirmDelete` — sets `deleteError` when any deletion fails | ✅ |
| 99 | `confirmDelete` — sets `isDeleting: true` during deletion, `false` on completion | ⬜ |
| 100 | `confirmDelete` — `closeModal` not called when some items fail | ⬜ |
| 101 | `confirmDelete` — `handleRefresh` still called even when some deletions fail | ⬜ |
| 102 | `handleConfirmClone` — clones dataset and fires success notification | ✅ |
| 103 | `handleConfirmClone` — fires error notification and keeps modal open on failure | ✅ |
| 104 | `handleConfirmClone` — does nothing when `selectedDataset` is null | ✅ |
| 105 | `handleConfirmClone` — sets `isCloning: true` during clone, `false` on completion | ⬜ |
| 106 | `handleConfirmClone` — passes `copyRows: true` when flag is set | ⬜ |
| 107 | `handleConfirmMove` — moves all items and calls `handleRefresh` and `closeModal` | ✅ |
| 108 | `handleConfirmMove` — fires error notification when all moves fail | ✅ |
| 109 | `handleConfirmMove` — fires warning notification on partial failure | ✅ |
| 110 | `handleConfirmMove` — does nothing when `itemsToMove` is empty | ✅ |
| 111 | `handleConfirmMove` — calls `selectFolder` with correct `targetType: "dataset"` | ⬜ |

---

### 7. Columns Sub-Page

#### 7a. AddAndEditDatasetColumnModal
| # | Scenario | Status |
|---|----------|--------|
| 112 | Renders "Add Column" title in add mode with empty default fields | ⬜ |
| 113 | Renders "Edit Column" title in edit mode with pre-populated fields | ⬜ |
| 114 | Shows validation error when Heading is empty on save | ⬜ |
| 115 | Data Type dropdown defaults to "String" | ⬜ |
| 116 | Column Type dropdown defaults to "Value" | ⬜ |
| 117 | Value type: shows List Content, Tooltip, and Required fields | ⬜ |
| 118 | Value type: hides Remote Data Path and Formula fields | ⬜ |
| 119 | Formula type: shows Formula textarea and hides List Content/Tooltip/Required | ⬜ |
| 120 | Formula type: shows informational hint about `[DisplayId]` substitutions | ⬜ |
| 121 | Formula type: shows hint about client-side `$dateFormat()` formula | ⬜ |
| 122 | Remote type: shows Remote Data Path field and hides List Content/Formula | ⬜ |
| 123 | Remote type + Date data type: shows Date Format field | ⬜ |
| 124 | Remote type + JSON source (sourceId=1): shows JSON path help text | ⬜ |
| 125 | Remote type + CSV source (sourceId=2): shows column-number help text | ⬜ |
| 126 | Show Filter checkbox toggles state | ⬜ |
| 127 | Show Sort checkbox toggles state | ⬜ |
| 128 | Column Order number input accepts a value | ⬜ |
| 129 | Calls `createDatasetColumn` with correct payload in add mode | ⬜ |
| 130 | Calls `updateDatasetColumn` with correct payload in edit mode | ⬜ |
| 131 | Calls `onSave` and `onClose` after successful save | ⬜ |
| 132 | Displays API error message when save fails | ⬜ |
| 133 | Cancel button calls `onClose` without submitting | ⬜ |
| 134 | Save button disabled while pending | ⬜ |

#### 7b. CopyDatasetColumnModal
| # | Scenario | Status |
|---|----------|--------|
| 135 | Renders "Copy Column" title with pre-populated column name (appended with "(1)") | ⬜ |
| 136 | Shows error when name is empty on submit | ⬜ |
| 137 | Shows error when name duplicates an existing column name (case-insensitive) | ⬜ |
| 138 | Submits with trimmed new heading | ⬜ |
| 139 | Cancel button calls `onClose` | ⬜ |

#### 7c. DeleteDatasetColumnModal
| # | Scenario | Status |
|---|----------|--------|
| 140 | Renders confirmation message with column heading | ⬜ |
| 141 | Clicking confirm calls `onDelete` | ⬜ |
| 142 | Clicking cancel calls `onClose` | ⬜ |
| 143 | Loading state disables confirm button | ⬜ |

#### 7d. DatasetColumnsModals (Modal Router)
| # | Scenario | Status |
|---|----------|--------|
| 144 | Renders nothing when `activeModal` is null | ⬜ |
| 145 | Renders `AddAndEditDatasetColumnModal` when `activeModal` is "edit" | ⬜ |
| 146 | Renders `CopyDatasetColumnModal` when `activeModal` is "copy" | ⬜ |
| 147 | Renders `DeleteDatasetColumnModal` when `activeModal` is "delete" | ⬜ |

---

### 8. Data Sub-Page

#### 8a. AddAndEditDatasetDataModal (Data Rows)
| # | Scenario | Status |
|---|----------|--------|
| 148 | Renders "Add Data" title in add mode | ⬜ |
| 149 | Renders "Edit Data" title in edit mode | ⬜ |
| 150 | Only renders fields for Value-type columns (excludes Formula and Remote) | ⬜ |
| 151 | String column (dataTypeId=1) renders a TextInput | ⬜ |
| 152 | String column with listContent renders a SelectDropdown with parsed options | ⬜ |
| 153 | Number column (dataTypeId=2) renders a NumberInput | ⬜ |
| 154 | Date column (dataTypeId=3) renders a DatePickerInput | ⬜ |
| 155 | Date value is formatted to SQL datetime string (`YYYY-MM-DD HH:mm:ss`) | ⬜ |
| 156 | Library Image column (dataTypeId=5) renders a MediaInput | ⬜ |
| 157 | External Image column (dataTypeId=4) renders a TextInput | ⬜ |
| 158 | HTML column (dataTypeId=6) renders a TextInput | ⬜ |
| 159 | Columns are sorted by `columnOrder` ascending | ⬜ |
| 160 | Required column shows asterisk (*) in label | ⬜ |
| 161 | Saving with an empty required field shows error "The field X is required." | ⬜ |
| 162 | Edit mode pre-populates draft from `rowData` keyed by heading | ⬜ |
| 163 | Edit mode pre-populates draft from `rowData` keyed by column ID (fallback) | ⬜ |
| 164 | Calls `createDatasetRow` with correct payload in add mode | ⬜ |
| 165 | Calls `updateDatasetRow` with correct `rowId` and payload in edit mode | ⬜ |
| 166 | Calls `onSave` and `onClose` after successful save | ⬜ |
| 167 | Displays API error message when save fails | ⬜ |
| 168 | Throws "Row ID is missing" error when edit mode row has no ID | ⬜ |

#### 8b. CopyDatasetDataModal
| # | Scenario | Status |
|---|----------|--------|
| 169 | Renders copy confirmation dialog | ⬜ |
| 170 | Confirm button calls `onConfirm` | ⬜ |
| 171 | Cancel button calls `onClose` | ⬜ |

#### 8c. DeleteDatasetDataModal
| # | Scenario | Status |
|---|----------|--------|
| 172 | Renders confirmation message | ⬜ |
| 173 | Confirm button calls `onDelete` | ⬜ |
| 174 | Cancel button calls `onClose` | ⬜ |
| 175 | Loading state disables confirm button | ⬜ |

#### 8d. DatasetDataModals (Modal Router)
| # | Scenario | Status |
|---|----------|--------|
| 176 | Renders nothing when `activeModal` is null | ⬜ |
| 177 | Renders `AddAndEditDataModal` when `activeModal` is "edit" | ⬜ |
| 178 | Renders `CopyDatasetDataModal` when `activeModal` is "copy" | ⬜ |
| 179 | Renders `DeleteDatasetDataModal` when `activeModal` is "delete" | ⬜ |

---

### 9. RSS Sub-Page

#### 9a. AddAndEditDatasetRssModal (`subPages/Rss/components/AddAndEditDatasetRssModal.tsx`)
| # | Scenario | Status |
|---|----------|--------|
| 180 | Renders "Add RSS" title in add mode | ⬜ |
| 181 | Renders "Edit RSS" title in edit mode | ⬜ |
| 182 | Renders General, Order, Filter tabs | ⬜ |
| 183 | Shows "Title is required" error when saving with empty title | ⬜ |
| 184 | Title and Author fields accept and store text | ⬜ |
| 185 | Fetches dataset columns from `fetchDatasetColumns` when modal opens | ⬜ |
| 186 | Title Column, Summary Column, Content Column, Published Date Column dropdowns populate with fetched columns | ⬜ |
| 187 | Selecting a column updates the corresponding column ID in draft | ⬜ |
| 188 | "Regenerate PSK" checkbox only visible in edit mode | ⬜ |
| 189 | Edit mode pre-populates title, author, and column IDs from `rss` prop | ⬜ |
| 190 | Edit mode parses and restores `sort` JSON into order rows | ⬜ |
| 191 | Edit mode parses and restores `filter` JSON into filter rows | ⬜ |
| 192 | Order tab: clicking "+" adds a new order row | ⬜ |
| 193 | Order tab: clicking "−" removes an order row (not first row) | ⬜ |
| 194 | Order tab: direction dropdown has ASC and DESC options | ⬜ |
| 195 | Order tab: "Advanced Order Clause" checkbox switches to SQL text input | ⬜ |
| 196 | Order tab: unchecking advanced restores the row builder | ⬜ |
| 197 | Filter tab: clicking "+" adds a new filter row | ⬜ |
| 198 | Filter tab: clicking "−" removes a filter row (not first row) | ⬜ |
| 199 | Filter tab: AND/OR operator dropdown appears for rows after the first | ⬜ |
| 200 | Filter tab: criteria dropdown includes equals, starts-with, contains, greater-than, less-than | ⬜ |
| 201 | Filter tab: "Advanced Filter Clause" checkbox switches to SQL text input | ⬜ |
| 202 | Filter tab: unchecking advanced restores the row builder | ⬜ |
| 203 | Calls `createDatasetRss` with correct payload in add mode | ⬜ |
| 204 | Calls `updateDatasetRss` with correct payload in edit mode | ⬜ |
| 205 | Calls `onSave` and `onClose` after successful save | ⬜ |
| 206 | Displays API error message when save fails | ⬜ |
| 207 | Validation error tabs back to "General" on failed save | ⬜ |

#### 9b. CopyDatasetRssModal
| # | Scenario | Status |
|---|----------|--------|
| 208 | Renders with pre-populated title (appended with "(1)") | ⬜ |
| 209 | Shows error when title is empty | ⬜ |
| 210 | Submits with new title | ⬜ |
| 211 | Cancel calls `onClose` | ⬜ |

#### 9c. DeleteDatasetRssModal
| # | Scenario | Status |
|---|----------|--------|
| 212 | Renders confirmation message with RSS title | ⬜ |
| 213 | Confirm button calls `onDelete` | ⬜ |
| 214 | Cancel button calls `onClose` | ⬜ |

#### 9d. DatasetRssModals (Modal Router)
| # | Scenario | Status |
|---|----------|--------|
| 215 | Renders nothing when `activeModal` is null | ⬜ |
| 216 | Renders `AddAndEditDatasetRssModal` when `activeModal` is "edit" | ⬜ |
| 217 | Renders `CopyDatasetRssModal` when `activeModal` is "copy" | ⬜ |
| 218 | Renders `DeleteDatasetRssModal` when `activeModal` is "delete" | ⬜ |

---

### 10. Data Connector Sub-Page (`subPages/DataConnector/DatasetDataConnector.tsx`)

| # | Scenario | Status |
|---|----------|--------|
| 219 | Renders script editor panel | ⬜ |
| 220 | Renders "Test Parameters" input section | ⬜ |
| 221 | Renders Logs, DataSet Data, Other Data, Schedule Criteria tabs/panels | ⬜ |
| 222 | Save button calls `updateDataConnectorScript` with current script value | ⬜ |
| 223 | Displays success notification after successful script save | ⬜ |
| 224 | Displays error notification when script save fails | ⬜ |
| 225 | Test button triggers script execution with provided parameters | ⬜ |
| 226 | Log entries rendered with timestamp and message | ⬜ |
| 227 | DataSet Data panel renders returned data in table format | ⬜ |
| 228 | Schedule criteria metrics panel shows TTL values | ⬜ |

---

### 11. Schema Validation (`schema/dataset.ts`)

| # | Scenario | Status |
|---|----------|--------|
| 229 | Dataset schema: rejects empty name | ⬜ |
| 230 | Dataset schema: rejects remote dataset with missing URI | ⬜ |
| 231 | Dataset schema: accepts valid local dataset payload | ⬜ |
| 232 | Dataset schema: accepts valid remote dataset payload with URI | ⬜ |
| 233 | Column schema: rejects empty heading | ⬜ |
| 234 | Column schema: accepts valid column payload | ⬜ |
| 235 | RSS schema: rejects empty title | ⬜ |
| 236 | RSS schema: accepts valid RSS payload | ⬜ |

---

### 12. useDatasetFilterOptions (`hooks/useDatasetFilterOptions.ts`)

| # | Scenario | Status |
|---|----------|--------|
| 237 | Initializes with empty/default filter state | ⬜ |
| 238 | User list populates the Owner filter dropdown | ⬜ |
| 239 | Filter value update (name, code) changes filter state | ⬜ |
| 240 | Logic operator toggle (AND/OR) updates state | ⬜ |
| 241 | Regex toggle updates `useRegexForName` state | ⬜ |

---

## LIST 2 — Remaining Test Scenarios (Not Yet Written)

> All scenarios below are **⬜ REMAINING** — they have no existing test coverage. Grouped by suggested test file.

---

### File: `__tests__/AddAndEditDatasetModal.test.tsx` (extend existing file)

1. Calls `createDataset` with correct payload on valid add-mode submit
2. Calls `onSave` and `onClose` after successful create
3. Displays API error message when `createDataset` rejects
4. Description field accepts and stores text
5. Code field accepts and stores text
6. Cancel button calls `onClose` without submitting
7. Save button is disabled while submission is pending
8. Calls `updateDataset` with correct payload on valid edit-mode submit
9. Calls `onSave` and `onClose` after successful update
10. Displays API error message when `updateDataset` rejects
11. Connector source options populate from `fetchDataConnectorSource` response
12. Selecting a connector source updates the draft payload
13. URI field accepts a valid HTTP URL (Remote tab)
14. HTTP method dropdown defaults to GET
15. Selecting POST method reveals Post Data textarea
16. Selecting GET method hides Post Data textarea
17. Data Root field accepts a JSON path value
18. CSV Separator field appears when source type is CSV
19. Ignore First Row checkbox appears when source type is CSV
20. Row Limit accepts a numeric value
21. Limit Policy dropdown shows stop / FIFO / truncate options
22. Authentication type defaults to "None" with no extra fields
23. Selecting "Basic" reveals Username and Password fields
24. Selecting "Bearer Token" reveals Token field
25. Switching back to "None" hides credential fields
26. Refresh Rate and Clear Rate fields accept numeric values
27. Truncate on Empty checkbox toggles state
28. SelectFolder rendered when `canViewFolders` is true
29. SelectFolder not rendered when `canViewFolders` is false

---

### File: `__tests__/CopyDatasetModal.test.tsx` (extend existing file)

30. "Copy rows?" checkbox is unchecked by default
31. Checking "Copy rows?" passes `copyRows: true` to `onConfirm`
32. Cancel button calls `onClose` without triggering `onConfirm`
33. `onConfirm` receives trimmed name, description, code, and copyRows flag

---

### File: `__tests__/DeleteDatasetModal.test.tsx` (new file)

34. Renders "Delete Dataset?" heading for single item
35. Renders "Delete Datasets?" heading and count for multiple items
36. Displays dataset name in confirmation message for single delete
37. "Delete any associated data?" checkbox is unchecked by default
38. Checking the checkbox passes `{ deleteData: true }` to `onDelete`
39. Unchecked checkbox passes `{ deleteData: false }` to `onDelete`
40. Clicking "Yes, Delete" calls `onDelete`
41. Clicking "Cancel" calls `onClose`
42. Loading state shows "Deleting…" and disables confirm button
43. Error message displayed when `error` prop is provided

---

### File: `__tests__/ImportDatasetCsvModal.test.tsx` (new file)

44. Renders drop zone when no file is selected
45. Accepts a `.csv` file via drop and shows file name and size
46. Remove (×) button clears selected file and returns to drop zone
47. "Done" button is disabled when no file selected
48. "Overwrite existing data?" checkbox defaults to unchecked
49. "Ignore first row?" checkbox defaults to checked
50. Fetches dataset columns when modal opens (`fetchDatasetColumns` called)
51. Column mapping section shows only Value-type columns
52. Column mappings default to sequential 1-based order
53. Changing a mapping input updates the mapping state
54. Clicking "Done" calls `importDatasetCsv` with file, overwrite, ignoreFirstRow, and mappings
55. Progress bar appears during upload
56. Success notification fires and modal closes after successful import
57. Error notification fires on import failure with error description
58. Failure to load columns shows error notification
59. All state resets (file, progress, overwrite, ignoreFirstRow) on close
60. Inputs are disabled while upload is in progress

---

### File: `__tests__/DatasetModals.test.tsx` (extend existing file)

61. Renders `MoveModal` when `activeModal` is "move"
62. Renders `FolderActionModals` component

---

### File: `__tests__/useDatasetActions.test.tsx` (extend existing file)

63. `confirmDelete` sets `isDeleting: true` during deletion, `false` on completion
64. `confirmDelete` — `closeModal` not called when some items fail to delete
65. `confirmDelete` — `handleRefresh` still called even when some deletions fail
66. `handleConfirmClone` — sets `isCloning: true` during clone, `false` after
67. `handleConfirmClone` — passes `copyRows: true` when flag is set
68. `handleConfirmMove` — calls `selectFolder` with `targetType: "dataset"`

---

### File: `__tests__/AddAndEditDatasetColumnModal.test.tsx` (new file)

69. Renders "Add Column" title in add mode with empty default fields
70. Renders "Edit Column" title in edit mode with pre-populated fields
71. Shows validation error when Heading is empty on save
72. Data Type dropdown defaults to "String"
73. Column Type dropdown defaults to "Value"
74. Value type: shows List Content, Tooltip, Required fields; hides Remote Data Path and Formula
75. Formula type: shows Formula textarea; hides List Content, Tooltip, Required; shows info hints
76. Remote type: shows Remote Data Path field; hides List Content and Formula
77. Remote type + Date: shows Date Format field
78. Remote type + JSON source: shows JSON path help text
79. Remote type + CSV source: shows column-number help text
80. Show Filter checkbox toggles state
81. Show Sort checkbox toggles state
82. Column Order input accepts a numeric value
83. Calls `createDatasetColumn` with correct payload in add mode
84. Calls `updateDatasetColumn` with correct payload in edit mode
85. Calls `onSave` and `onClose` after successful save
86. Displays API error message when save fails
87. Cancel button calls `onClose`

---

### File: `__tests__/CopyDatasetColumnModal.test.tsx` (new file)

88. Renders "Copy Column" title with pre-populated heading
89. Shows error when heading is empty
90. Shows error for duplicate column heading (case-insensitive)
91. Submits with trimmed new heading
92. Cancel calls `onClose`

---

### File: `__tests__/DeleteDatasetColumnModal.test.tsx` (new file)

93. Renders confirmation with column heading
94. Confirm button calls `onDelete`
95. Cancel button calls `onClose`
96. Loading state disables confirm button

---

### File: `__tests__/DatasetColumnsModals.test.tsx` (new file)

97. Renders nothing when `activeModal` is null
98. Renders `AddAndEditDatasetColumnModal` when `activeModal` is "edit"
99. Renders `CopyDatasetColumnModal` when `activeModal` is "copy"
100. Renders `DeleteDatasetColumnModal` when `activeModal` is "delete"

---

### File: `__tests__/AddAndEditDatasetDataModal.test.tsx` (new file)

101. Renders "Add Data" title in add mode
102. Renders "Edit Data" title in edit mode
103. Only renders fields for Value-type columns
104. String column renders a TextInput
105. String column with listContent renders a SelectDropdown with parsed options
106. Number column renders a NumberInput
107. Date column renders a DatePickerInput
108. Date value formatted to SQL datetime string
109. Library Image column renders a MediaInput
110. External Image column renders a TextInput
111. HTML column renders a TextInput
112. Columns sorted by `columnOrder` ascending
113. Required column shows asterisk in label
114. Required field empty on save — shows error "The field X is required."
115. Edit mode pre-populates from `rowData` by heading
116. Edit mode pre-populates from `rowData` by column ID (fallback)
117. Calls `createDatasetRow` with correct payload in add mode
118. Calls `updateDatasetRow` with correct `rowId` and payload in edit mode
119. Calls `onSave` and `onClose` after successful save
120. Displays API error message when save fails

---

### File: `__tests__/DatasetDataModals.test.tsx` (new file)

121. Renders nothing when `activeModal` is null
122. Renders `AddAndEditDataModal` when `activeModal` is "edit"
123. Renders `CopyDatasetDataModal` when `activeModal` is "copy"
124. Renders `DeleteDatasetDataModal` when `activeModal` is "delete"

---

### File: `__tests__/AddAndEditDatasetRssModal.test.tsx` (new file)

125. Renders "Add RSS" title in add mode
126. Renders "Edit RSS" title in edit mode
127. Renders General, Order, Filter tabs
128. Shows "Title is required" error on save with empty title
129. Title and Author fields accept and store text
130. Fetches dataset columns when modal opens
131. Column dropdowns (Title, Summary, Content, Published Date) populate with fetched columns
132. Selecting a column updates the draft
133. "Regenerate PSK" checkbox only visible in edit mode
134. Edit mode pre-populates title, author, and column IDs
135. Edit mode parses `sort` JSON into order rows
136. Edit mode parses `filter` JSON into filter rows
137. Order tab: "+" button adds a new order row
138. Order tab: "−" button removes an order row (not the first)
139. Order tab: direction dropdown has ASC and DESC options
140. Order tab: "Advanced" checkbox switches to SQL text input
141. Filter tab: "+" button adds a new filter row
142. Filter tab: "−" button removes a filter row (not the first)
143. Filter tab: AND/OR operator dropdown appears for rows after the first
144. Filter tab: criteria dropdown includes equals, starts-with, contains, greater-than, less-than
145. Filter tab: "Advanced" checkbox switches to SQL text input
146. Calls `createDatasetRss` with correct payload in add mode
147. Calls `updateDatasetRss` with correct payload in edit mode
148. Calls `onSave` and `onClose` after successful save
149. Displays API error message when save fails
150. Validation failure switches active tab back to "General"

---

### File: `__tests__/DatasetRssModals.test.tsx` (new file)

151. Renders nothing when `activeModal` is null
152. Renders `AddAndEditDatasetRssModal` when `activeModal` is "edit"
153. Renders `CopyDatasetRssModal` when `activeModal` is "copy"
154. Renders `DeleteDatasetRssModal` when `activeModal` is "delete"

---

### File: `__tests__/datasetSchema.test.ts` (new file)

155. Dataset schema: rejects empty name
156. Dataset schema: rejects remote dataset with missing URI
157. Dataset schema: accepts valid local dataset payload
158. Dataset schema: accepts valid remote dataset payload with URI
159. Column schema: rejects empty heading
160. Column schema: accepts valid column payload
161. RSS schema: rejects empty title
162. RSS schema: accepts valid RSS payload

---

### File: `__tests__/useDatasetFilterOptions.test.ts` (new file)

163. Initializes with empty/default filter state
164. User list populates the Owner filter dropdown
165. Name filter update changes filter state
166. Logic operator toggle (AND/OR) updates state
167. Regex toggle updates `useRegexForName` state

---

## Summary Stats

| Category | Total Scenarios | Covered | Remaining |
|----------|----------------|---------|-----------|
| AddAndEditDatasetModal | 49 | 12 | 37 |
| CopyDatasetModal | 10 | 6 | 4 |
| DeleteDatasetModal | 10 | 0 | 10 |
| ImportDatasetCsvModal | 17 | 0 | 17 |
| DatasetModals | 9 | 7 | 2 |
| useDatasetActions | 16 | 10 | 6 |
| Columns — Add/Edit Column Modal | 23 | 0 | 23 |
| Columns — Copy Column Modal | 5 | 0 | 5 |
| Columns — Delete Column Modal | 4 | 0 | 4 |
| Columns — Modal Router | 4 | 0 | 4 |
| Data — Add/Edit Data Modal | 21 | 0 | 21 |
| Data — Modal Router | 4 | 0 | 4 |
| RSS — Add/Edit RSS Modal | 28 | 0 | 28 |
| RSS — Modal Router | 4 | 0 | 4 |
| Data Connector Sub-Page | 10 | 0 | 10 |
| Schema Validation | 8 | 0 | 8 |
| useDatasetFilterOptions | 5 | 0 | 5 |
| **TOTAL** | **242** | **35** | **207** |
