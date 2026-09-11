# Execution Plan: Commands Tests

## Status summary (as of 11 September 2026)

| Suite | Files | Tests |
|---|---|---|
| `Commands.render.test.tsx` | 1 file | 9 passing |
| `Commands.filters.test.tsx` | 1 file | 11 passing |
| `Commands.column.test.tsx` | 1 file | 8 passing |
| `Commands.add.test.tsx` | 1 file | 3 passing |
| `Commands.edit.test.tsx` | 1 file | 3 passing |
| `Commands.delete.test.tsx` | 1 file | 10 passing |
| `Commands.share.test.tsx` | 1 file | 2 passing |
| `modals/add-edit.test.tsx` | 1 file | 19 passing |
| `modals/command-builder-utils.test.ts` | 1 file | 25 passing |

**Total: 90 tests passing across 9 files.**

Verified with `npx vitest run src/pages/Displays/Commands/__tests__/` — 9 passed (9), 90 passed (90).

> `command-builder-utils.test.ts` uses `test.each`, so a grep for `test(` under-counts it (21 blocks
> → 25 cases). Take counts from the runner, not from a grep.

## Known issues

| # | Issue | Location |
|---|---|---|
| — | **None known.** `DataTable` injects the selection checkbox column automatically, so bulk selection is drivable — `Commands.share.test.tsx` clicks `getByRole('checkbox', { name: /select all rows/i })`. | — |
| 1 | **Environment, not code.** Running this suite together with SyncGroups (19 files) produced 7 unrelated failures; each suite alone is green. See the note at the end of this document. | — |

## Files to create

```
frontend/src/pages/Displays/Commands/__tests__/
├── fixtures/      └── command.ts                          ✓ done
├── mocks/         └── commandApi.ts                       ✓ done
├── helpers/       └── renderCommandsPage.tsx              ✓ done
├── modals/
│   ├── helpers/   └── renderAddEditCommandModal.tsx       ✓ done
│   ├── add-edit.test.tsx                                  ✓ done
│   └── command-builder-utils.test.ts                      ✓ done
├── Commands.render.test.tsx                               ✓ done
├── Commands.filters.test.tsx                              ✓ done
├── Commands.column.test.tsx                               ✓ done
├── Commands.add.test.tsx                                  ✓ done
├── Commands.edit.test.tsx                                 ✓ done
├── Commands.delete.test.tsx                               ✓ done
└── Commands.share.test.tsx                                ✓ done
```

No `Commands.hydration.test.tsx` — the gate is a single assertion inside
`Commands.render.test.tsx` #9. No `modals/delete.test.tsx` — the modal's content is asserted from
`Commands.delete.test.tsx` alongside the wiring.

## CommandsConfig.tsx coverage

### Row action wiring (click → modal opens)

| Action | Wiring tested | Modal content tested |
|---|---|---|
| Edit | `Commands.edit.test.tsx` #1–2 | `modals/add-edit.test.tsx` (edit mode) |
| Delete | `Commands.delete.test.tsx` #1 | `Commands.delete.test.tsx` #2–6 |
| Share | `Commands.share.test.tsx` #1 | Out of scope — stubbed |
| Add (page button) | `Commands.add.test.tsx` #1 | `modals/add-edit.test.tsx` (add mode) |

### Row-action visibility

| Rule | Tested |
|---|---|
| No special/locked rows on this page — every row exposes the same actions | n/a |
| Permission gates (`canModify` / `canEdit` / `canDelete`) | Indirect — `mockUser` carries the flags |

### Bulk actions

| Bulk action | Tested |
|---|---|
| Delete Selected | `Commands.delete.test.tsx` #7–10 |
| Share Selected | `Commands.share.test.tsx` #2 |

### Hooks

| Hook | Coverage |
|---|---|
| `useCommandsData` | Indirect — every page test |
| `useCommandActions` | Indirect — add/edit/delete/share wiring |
| `useCommandFilterOptions` | Indirect — `Commands.filters.test.tsx` |

### Command Builder components

| Component | Coverage |
|---|---|
| `commandStringUtils.ts` | **Direct** — `modals/command-builder-utils.test.ts`, 25 cases |
| `FreeTextFields`, `HttpFields`, `IntentFields`, `KeyValueBuilder`, `PhilipsAndroidFields`, `Rs232Fields` | Indirect — pre-population asserted in `modals/add-edit.test.tsx` #15 |

## Coverage map — test plan item → file

### §1 Commands Page

| Plan item | File |
|---|---|
| Table rows / empty state / Add button / placeholder / Filters button / tab nav | `Commands.render.test.tsx` #1–6 |
| Fetch error alert | `Commands.render.test.tsx` #7 |
| Paginated at 10 | `Commands.render.test.tsx` #8 |
| Hydration pulse and disabled controls | `Commands.render.test.tsx` #9 |
| Search behaviour | `Commands.filters.test.tsx` #5–7 |
| Filter panel, AND/OR + regex toggles, each filter, Reset | `Commands.filters.test.tsx` #1–4, 8–11 |
| Column visibility | `Commands.column.test.tsx` #1–8 |
| Selecting rows reveals bulk buttons | `Commands.delete.test.tsx` #7 |

### §2–§3 Add / Edit modal

| Plan item | File |
|---|---|
| Title, fields, defaults, editable Code (add) | `modals/add-edit.test.tsx` #1–4 |
| Validation: empty Name, empty Code, invalid Code | `modals/add-edit.test.tsx` #5–7 |
| Save payload, Cancel, pending, API error (add) | `modals/add-edit.test.tsx` #8–11 |
| Title, disabled Code, Available On, builder pre-population (edit) | `modals/add-edit.test.tsx` #12–15 |
| Save without code, Cancel, API error (edit) | `modals/add-edit.test.tsx` #16–18 |

### §4 Command Builder string format

| Plan item | File |
|---|---|
| Defaults, parsing, building, round-trip | `modals/command-builder-utils.test.ts` — all 25 |

### §5 Delete

| Plan item | File |
|---|---|
| Single delete, all six bullets | `Commands.delete.test.tsx` #1–6 |
| Bulk delete, all four bullets | `Commands.delete.test.tsx` #7–10 |

### §6 Share

| Plan item | File |
|---|---|
| Row and bulk Share wiring | `Commands.share.test.tsx` #1–2 |
| ShareModal internals | **Out of scope** — shared component, stubbed |

### §7 Modal routing

| Plan item | File |
|---|---|
| Add opens add modal; refresh after add; no auto follow-up modal | `Commands.add.test.tsx` #1–3 |
| Edit opens the edit modal, not Share or Delete; refresh after edit | `Commands.edit.test.tsx` #1–3 |

## What goes in each support file

### `fixtures/command.ts`
`buildCommand(overrides?)` factory with minimal valid defaults, named fixtures for the single /
empty / multiple table shapes, a command with a stored command string for the builder
pre-population test, `mockUser` (feature flags the row actions check) and the preference query key.

### `mocks/commandApi.ts`
Thin `mockFetchCommands(data)`-style helpers — no filtering or pagination logic inside the mock.

### `helpers/renderCommandsPage.tsx`
Wraps the page in `QueryClientProvider` / `UserProvider` / `MemoryRouter` and seeds
`testQueryClient` with the preference key so the hydration gate resolves. `Commands.render.test.tsx`
#9 is the one test that deliberately renders **without** the seed.

### `modals/helpers/renderAddEditCommandModal.tsx`
Renders `AddEditCommandModal` directly with providers; mode / data / onClose / onSave options so a
test can land in add or edit mode without going through the page.

## APIs to mock per test file

**Page-level files:** the command API, `userApi` preferences, the manual `Modal` mock, and the
filter-options hook stub.

**Wiring files** (`add`, `edit`, `share`): additionally stub the modal bodies.

**Modal file:** the command API plus `SelectDropdown` as a native stub (see
`TESTING_STANDARDS.md` §6).

**`command-builder-utils.test.ts`:** nothing — pure functions, no DOM, no mocks. This is the model
for rule 1.6 in `TESTING_STANDARDS.md`.

## Edge cases

- **The Code field is immutable after create** — add mode asserts it is editable, edit mode asserts
  it is disabled, and the edit save asserts the code is **absent** from the payload. All three are
  needed; any one alone would miss a regression.
- **`test.each` under-counts under grep** — take counts from the runner.
- **Bulk delete uses `Promise.allSettled`** — assert the call count and the set of ids, not order.
- **`useTransition` / "Saving…"** — controlled promise, not a timer.
- **`testQueryClient.clear()` + `vi.clearAllMocks()` in `beforeEach`** for every file.

## Environment note — running with other suites

Running this suite together with SyncGroups in one command (19 files) produced **7 failures spread
across 4 files**; running each suite on its own is green (Commands 90/90, SyncGroups 75/75). This is
the JSDOM/memory contention described in the `frontend-test-workflow` skill's Environment note, not a
real regression. **Run one page's suite at a time**, or add `--maxWorkers=2`, before believing a
folder-wide failure.
