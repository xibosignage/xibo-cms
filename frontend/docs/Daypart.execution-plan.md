# Execution Plan: Daypart Tests

## Status summary (as of 11 September 2026)

| Suite | Files | Tests |
|---|---|---|
| `Daypart.render.test.tsx` | 1 file | 12 passing |
| `Daypart.filters.test.tsx` | 1 file | 12 passing |
| `Daypart.column.test.tsx` | 1 file | 7 passing |
| `Daypart.hydration.test.tsx` | 1 file | 2 passing |
| `Daypart.add.test.tsx` | 1 file | 3 passing |
| `Daypart.edit.test.tsx` | 1 file | 2 passing |
| `Daypart.delete.test.tsx` | 1 file | 9 passing |
| `Daypart.share.test.tsx` | 1 file | 2 passing |
| `modals/add-edit.test.tsx` | 1 file | 20 passing |
| `modals/exceptions.test.tsx` | 1 file | 7 passing |

**Total: 76 tests passing across 10 files.**

Verified with `npx vitest run src/pages/Schedule/Daypart/__tests__/` — 10 passed (10), 76 passed (76).

## Known issues

| # | Issue | Location |
|---|---|---|
| — | **None known.** `DataTable` injects the selection checkbox column automatically, so the bulk-selection blocker that affected SyncGroups does not apply here. | — |

## Files to create

```
frontend/src/pages/Schedule/Daypart/__tests__/
├── fixtures/      └── daypart.ts                  ✓ done
├── mocks/         └── daypartApi.ts               ✓ done
├── helpers/       └── renderDaypartPage.tsx       ✓ done
├── modals/
│   ├── helpers/   └── renderAddEditModal.tsx      ✓ done
│   ├── add-edit.test.tsx                          ✓ done
│   └── exceptions.test.tsx                        ✓ done
├── Daypart.render.test.tsx                        ✓ done
├── Daypart.filters.test.tsx                       ✓ done
├── Daypart.column.test.tsx                        ✓ done
├── Daypart.hydration.test.tsx                     ✓ done
├── Daypart.add.test.tsx                           ✓ done
├── Daypart.edit.test.tsx                          ✓ done
├── Daypart.delete.test.tsx                        ✓ done
└── Daypart.share.test.tsx                         ✓ done
```

No `modals/delete.test.tsx` — `DeleteDaypartModal` is simple enough that its content is asserted
from `Daypart.delete.test.tsx` alongside the wiring. No `modals/share.test.tsx` — `ShareModal` is
stubbed (see the test plan's Notes).

## DaypartConfig.tsx coverage

### Row action wiring (click → modal opens)

| Action | Wiring tested | Modal content tested |
|---|---|---|
| Edit | `Daypart.edit.test.tsx` | `modals/add-edit.test.tsx` (edit mode) |
| Delete | `Daypart.delete.test.tsx` | `Daypart.delete.test.tsx` |
| Share | `Daypart.share.test.tsx` | Out of scope — stubbed |
| Add (page button) | `Daypart.add.test.tsx` | `modals/add-edit.test.tsx` (add mode) |

### Row-action visibility (special rows)

| Rule | Tested |
|---|---|
| `isAlways === 1 \|\| isCustom === 1` hides Edit | `Daypart.render.test.tsx` #12 |
| `isAlways === 1 \|\| isCustom === 1` hides Delete | `Daypart.render.test.tsx` #12 |
| A normal row shows Edit + Delete as quick actions | `Daypart.render.test.tsx` #10 |
| A normal row shows Share in the overflow menu | `Daypart.render.test.tsx` #11 |
| `canModify` / `canEdit` / `canDelete` permission gates | Indirect — `mockUser` carries the flags |

### Bulk actions

| Bulk action | Tested |
|---|---|
| Delete Selected | `Daypart.delete.test.tsx` #7–9 |
| Share Selected | `Daypart.share.test.tsx` #2 |

### Hooks

| Hook | Coverage |
|---|---|
| `useDaypartData` | Indirect — every page test exercises it |
| `useDaypartActions` | Indirect — add/edit/delete/share wiring tests |
| `useDaypartFilterOptions` | Indirect — `Daypart.filters.test.tsx` |

## Coverage map — test plan item → file

### §1 Daypart Page

| Plan item | File |
|---|---|
| Table renders rows / empty state | `Daypart.render.test.tsx` #1–2 |
| Add button, default columns, search placeholder, Filters button, tab nav | `Daypart.render.test.tsx` #3–7 |
| Paginated at 10 per page | `Daypart.render.test.tsx` #8 |
| Fetch error alert | `Daypart.render.test.tsx` #9 |
| Row actions, normal and special | `Daypart.render.test.tsx` #10–12 |
| Search behaviour | `Daypart.filters.test.tsx` #4, 5, 8 |
| Filter panel open/close, each filter, AND/OR, regex, Reset | `Daypart.filters.test.tsx` #1–3, 6, 7, 9–12 |
| Column visibility | `Daypart.column.test.tsx` #1–7 |
| Bulk actions enabled + delete modal count | `Daypart.delete.test.tsx` #7, `Daypart.share.test.tsx` #2 |

### §2–§3 Add / Edit modal

| Plan item | File |
|---|---|
| Title, tabs, fields, defaults, in-use warning (add) | `modals/add-edit.test.tsx` #1–6 |
| Validation incl. tab snap-back | `modals/add-edit.test.tsx` #7–9 |
| Save payload, Cancel, pending, API error (add) | `modals/add-edit.test.tsx` #10–13 |
| Title, pre-fill, in-use warning (edit) | `modals/add-edit.test.tsx` #14–17 |
| Cancel, save payload, API error (edit) | `modals/add-edit.test.tsx` #18–20 |

### §4 Exceptions tab

| Plan item | File |
|---|---|
| All seven bullets | `modals/exceptions.test.tsx` #1–7 |

### §5 Delete

| Plan item | File |
|---|---|
| Single delete, all six bullets | `Daypart.delete.test.tsx` #1–6 |
| Bulk delete, all three bullets | `Daypart.delete.test.tsx` #7–9 |

### §6 Share

| Plan item | File |
|---|---|
| Row Share wiring | `Daypart.share.test.tsx` #1 |
| Bulk Share wiring | `Daypart.share.test.tsx` #2 |
| ShareModal internals | **Out of scope** — shared component, stubbed |

### §7 Modal routing

| Plan item | File |
|---|---|
| Add opens add modal; only one modal at a time; refresh after save | `Daypart.add.test.tsx` #1–3 |
| Row action opens its own modal; refresh after edit | `Daypart.edit.test.tsx` #1–2 |

### §8 Hydration gate

| Plan item | File |
|---|---|
| Loading pulse; Add disabled while hydrating | `Daypart.hydration.test.tsx` #1–2 |

## What goes in each support file

### `fixtures/daypart.ts`
`buildDaypart(overrides?)` factory with minimal valid defaults, plus the named fixtures the tests
assert on: `mockDaypart`, `SINGLE_DAYPART`, `EMPTY_DAYPART_TABLE`, `MULTIPLE_DAYPARTS`,
`ALWAYS_DAYPART` and `SINGLE_SPECIAL_DAYPART` (the special-row cases), `buildDaypartException`,
`mockUser` (timezone + the feature flags the row actions check), and `queryKeys` for the preference
key the render helper seeds.

### `mocks/daypartApi.ts`
`mockFetchDaypart(data)` and `mockFetchDaypartScheduleCount(n)` — thin, no business logic, no
pagination or filtering rules inside the mock.

### `helpers/renderDaypartPage.tsx`
Wraps the page in `QueryClientProvider` / `UserProvider` / `MemoryRouter`, and **seeds
`testQueryClient` with the preference query key so the hydration gate resolves**. Without that seed
every page test hangs on the pulse — which is exactly why `Daypart.hydration.test.tsx` renders
without it, deliberately.

### `modals/helpers/renderAddEditModal.tsx`
Renders `AddAndEditDaypartModal` directly with providers; takes mode / data / onClose / onSave so a
test can land in add or edit mode without going through the page.

## APIs to mock per test file

**Page-level files** (`Daypart.*.test.tsx`): the daypart API, `userApi` preferences, the manual
`Modal` mock, and the filter-options hook stub.

**Wiring files** (`add`, `edit`, `share`): additionally stub the modal bodies, so the test asserts
routing rather than modal content.

**Modal files** (`modals/*.test.tsx`): the daypart API plus heavy inputs — `TimePicker` and
`SelectDropdown` as native stubs (see `TESTING_STANDARDS.md` §6).

## Edge cases

- **Hydration gate** — `findByText` the pulse before interacting, or seed the preference key.
- **Validation snaps to the first tab** — submit from the Description tab and assert the General tab
  is active again (`modals/add-edit.test.tsx` #9).
- **Bulk delete uses `Promise.allSettled`** — the partial-failure test asserts the error *and* the
  refresh, not just the error.
- **`useTransition` / "Saving…"** — use a controlled promise, not a timer
  (`TESTING_STANDARDS.md` §4.4).
- **Row-action menu is a portal** — bump the per-test timeout if it flakes under parallel load, and
  document why inline.
- **`testQueryClient.clear()` + `vi.clearAllMocks()` in `beforeEach`** for every file — the query
  client is module-scoped.
- **Exceptions serialise to three parallel arrays**, not an array of objects. The payload assertion
  in `modals/exceptions.test.tsx` #7 is the one that pins this.
