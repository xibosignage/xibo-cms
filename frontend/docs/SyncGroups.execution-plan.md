# Execution Plan: SyncGroups Tests

## Status summary (as of 11 September 2026)

| Suite | Files | Tests |
|---|---|---|
| `SyncGroups.render.test.tsx` | 1 file | 8 passing |
| `SyncGroups.filters.test.tsx` | 1 file | 10 passing |
| `SyncGroups.column.test.tsx` | 1 file | 7 passing |
| `SyncGroups.folder.test.tsx` | 1 file | 3 passing |
| `SyncGroups.hydration.test.tsx` | 1 file | 2 passing |
| `SyncGroups.add.test.tsx` | 1 file | 3 passing |
| `SyncGroups.edit.test.tsx` | 1 file | 2 passing |
| `SyncGroups.delete.test.tsx` | 1 file | 9 passing |
| `modals/add-edit.test.tsx` | 1 file | 19 passing |
| `modals/members.test.tsx` | 1 file | 12 passing |

**Total: 75 tests passing across 10 files.**

Verified with `npx vitest run src/pages/Displays/SyncGroups/__tests__/` — 10 passed (10),
75 passed (75).

## Known issues

| # | Issue | Location |
|---|---|---|
| 1 | **Stale pin, no longer a blocker.** `SyncGroups.tsx:364` pins `tableSelection` in `columnPinning`, but `SyncGroupsConfig.tsx` defines no such column. Earlier notes recorded this as a blocker preventing bulk-selection tests. It is not one today: `DataTable` injects the selection column, and `SyncGroups.delete.test.tsx` drives it through the header "select all" checkbox. The pin is harmless but misleading — worth removing or defining the column explicitly. | `SyncGroups.tsx:364` |
| 2 | **Environment, not code.** Running this suite together with Commands (19 files) produced 7 unrelated failures; each suite alone is green. See the note at the end of this document. | — |

## Files to create

```
frontend/src/pages/Displays/SyncGroups/__tests__/
├── fixtures/      └── syncGroup.ts                    ✓ done
├── mocks/         └── syncGroupApi.ts                 ✓ done
├── helpers/       └── renderSyncGroupsPage.tsx        ✓ done
├── modals/
│   ├── helpers/   └── renderAddEditModal.tsx          ✓ done
│   ├── add-edit.test.tsx                              ✓ done
│   └── members.test.tsx                               ✓ done
├── SyncGroups.render.test.tsx                         ✓ done
├── SyncGroups.filters.test.tsx                        ✓ done
├── SyncGroups.column.test.tsx                         ✓ done
├── SyncGroups.folder.test.tsx                         ✓ done
├── SyncGroups.hydration.test.tsx                      ✓ done
├── SyncGroups.add.test.tsx                            ✓ done
├── SyncGroups.edit.test.tsx                           ✓ done
└── SyncGroups.delete.test.tsx                         ✓ done
```

**No `SyncGroups.share.test.tsx`** — there is no Share action on this page. `SyncGroupsConfig.tsx`
defines Edit, Members and Delete as row actions, and Delete Selected as the only bulk action. This is
a page-design decision, not a coverage gap. No `modals/delete.test.tsx` — the modal's content is
asserted from `SyncGroups.delete.test.tsx`.

## SyncGroupsConfig.tsx coverage

### Row action wiring (click → modal opens)

| Action | Wiring tested | Modal content tested |
|---|---|---|
| Edit | `SyncGroups.edit.test.tsx` #1–2 | `modals/add-edit.test.tsx` (edit mode) |
| Members | `SyncGroups.add.test.tsx` #2 (auto-open after add) | `modals/members.test.tsx` — all 12 |
| Delete | `SyncGroups.delete.test.tsx` #1 | `SyncGroups.delete.test.tsx` #2–6 |
| Add (page button) | `SyncGroups.add.test.tsx` #1 | `modals/add-edit.test.tsx` (add mode) |
| Share | **Not present on this page** | n/a |

### Row-action visibility

| Rule | Tested |
|---|---|
| No special/locked rows — every row exposes the same actions | n/a |
| Folder permission (`folder.view`) hides the sidebar and breadcrumb | `SyncGroups.folder.test.tsx` #1 |
| Permission gates on row actions | Indirect — `mockUser` carries the flags |

### Bulk actions

| Bulk action | Tested |
|---|---|
| Delete Selected | `SyncGroups.delete.test.tsx` #7–9 |
| Share Selected | **Not present on this page** |

### Hooks

| Hook | Coverage |
|---|---|
| `useSyncGroupsData` | Indirect — every page test |
| `useSyncGroupActions` | Indirect — add/edit/delete/members wiring |
| `useSyncGroupFilterOptions` | Indirect — `SyncGroups.filters.test.tsx` |

## Coverage map — test plan item → file

### §1 SyncGroups Page

| Plan item | File |
|---|---|
| Table rows / empty state / Add button / placeholder / Filters / tab nav | `SyncGroups.render.test.tsx` #1–6 |
| Fetch error alert | `SyncGroups.render.test.tsx` #7 |
| Paginated at 10 | `SyncGroups.render.test.tsx` #8 |
| Search behaviour | `SyncGroups.filters.test.tsx` #4–6 |
| Filter panel, Lead Display ID, Name, Reset | `SyncGroups.filters.test.tsx` #1–3, 7–10 |
| Column visibility | `SyncGroups.column.test.tsx` #1–7 |

### §2–§3 Add / Edit modal

| Plan item | File |
|---|---|
| Title, fields, Lead Display hidden, defaults (add) | `modals/add-edit.test.tsx` #1–4 |
| Validation: empty Name, port 0, summary message | `modals/add-edit.test.tsx` #5–7 |
| Save payload, Cancel, pending, API error (add) | `modals/add-edit.test.tsx` #8–11 |
| Title, Lead Display shown (edit) | `modals/add-edit.test.tsx` #12–13 |
| Validation in edit mode, save payload, API error | `modals/add-edit.test.tsx` #14–17 |

### §4 Manage Members

| Plan item | File |
|---|---|
| All twelve bullets | `modals/members.test.tsx` #1–12 |

### §5 Delete

| Plan item | File |
|---|---|
| Single delete, all six bullets | `SyncGroups.delete.test.tsx` #1–6 |
| Bulk delete, all three bullets | `SyncGroups.delete.test.tsx` #7–9 |

### §6 Folder sidebar

| Plan item | File |
|---|---|
| Hidden without `folder.view` | `SyncGroups.folder.test.tsx` #1 |
| Folder change resets pagination | `SyncGroups.folder.test.tsx` #2 |
| Folder change clears row selection | `SyncGroups.folder.test.tsx` #3 |
| The folder tree component's own behaviour | **Out of scope** — shared component with its own tests |

### §7 Modal routing

| Plan item | File |
|---|---|
| Add opens add modal; Members opens automatically after add; refresh after save | `SyncGroups.add.test.tsx` #1–3 |
| Edit opens the edit modal; refresh after edit | `SyncGroups.edit.test.tsx` #1–2 |

### §8 Hydration gate

| Plan item | File |
|---|---|
| Loading pulse; Add disabled while hydrating | `SyncGroups.hydration.test.tsx` #1–2 |

## What goes in each support file

### `fixtures/syncGroup.ts`
`buildSyncGroup(overrides?)` factory with minimal valid defaults, named fixtures for the single /
empty / multiple table shapes, display fixtures for the Members modal, `mockUser` in two variants
(**with and without `folder.view`**, since `SyncGroups.folder.test.tsx` #1 needs the negative case),
and the preference query key.

### `mocks/syncGroupApi.ts`
Thin fetch helpers plus the member add/remove API — no reconciliation logic inside the mock; that
is what `modals/members.test.tsx` #10 asserts.

### `helpers/renderSyncGroupsPage.tsx`
Wraps the page in `QueryClientProvider` / `UserProvider` / `MemoryRouter` and seeds
`testQueryClient` with the preference key so the hydration gate resolves. `SyncGroups.hydration.test.tsx`
is the file that deliberately renders **without** the seed.

### `modals/helpers/renderAddEditModal.tsx`
Renders `AddAndEditSyncGroupModal` directly with providers; mode / data / onClose / onSave options.

## APIs to mock per test file

**Page-level files:** the sync group API, `userApi` preferences, the manual `Modal` mock, and the
filter-options hook stub.

**`SyncGroups.folder.test.tsx`:** additionally the folder API and a `mockUser` **without**
`folder.view` for the negative case.

**Wiring files** (`add`, `edit`): additionally stub the modal bodies — note `add` must **not** stub
the Members modal, since #2 asserts it opens.

**Modal files:** the sync group API plus `SelectDropdown` as a native stub; `members.test.tsx` also
needs the display list API.

## Edge cases

- **Lead Display is add/edit asymmetric** — hidden in add mode (#3), shown in edit (#13). Both
  assertions are needed; either alone would miss a regression in one direction.
- **Members auto-opens after add** — `SyncGroups.add.test.tsx` #2 is the only test of that hand-off,
  and it is the page's most distinctive behaviour.
- **Saving members with no changes must not call the API** (#9) — asserting the absence of a call,
  which is easy to forget and easy to regress.
- **Folder change must clear row selection** (#3 in folder) — otherwise a bulk delete could act on
  rows from a folder the user has navigated away from.
- **Bulk delete uses `Promise.allSettled`** — assert the call count and the set of ids, not order.
  The suite's `selectAllRows` helper clicks the header checkbox.
- **`testQueryClient.clear()` + `vi.clearAllMocks()` in `beforeEach`** for every file.

## Environment note — running with other suites

Running this suite together with Commands in one command (19 files) produced **7 failures spread
across 4 files**; running each on its own is green (SyncGroups 75/75, Commands 90/90). This is the
JSDOM/memory contention described in the `frontend-test-workflow` skill's Environment note, not a
real regression. **Run one page's suite at a time**, or add `--maxWorkers=2`.
