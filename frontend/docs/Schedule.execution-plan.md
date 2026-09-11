# Execution Plan: Schedule (Events) Tests

## Status summary (as of 11 September 2026)

| Suite | Files | Tests |
|---|---|---|
| **Table view — page** | | |
| `table/page/render.test.tsx` | 1 file | 7 passing |
| `table/page/filters.test.tsx` | 1 file | 10 passing |
| `table/page/columns.test.tsx` | 1 file | 4 passing |
| `table/page/actions.test.tsx` | 1 file | 7 passing |
| `table/page/bulkDelete.test.tsx` | 1 file | 4 passing |
| **Calendar view** | | |
| `calendar/view.test.tsx` | 1 file | 6 passing |
| `calendar/grid/cells.test.tsx` | 1 file | 5 passing |
| `calendar/grid/icons.test.tsx` | 1 file | 9 passing |
| `calendar/grid/timezone.test.tsx` | 1 file | 7 passing |
| `calendar/events/recurrence.test.tsx` | 1 file | 6 passing |
| `calendar/events/recurrence-edge.test.tsx` | 1 file | 10 passing |
| `calendar/events/date-clamping.test.tsx` | 1 file | 4 passing |
| `calendar/panel/open-close.test.tsx` | 1 file | 8 passing |
| `calendar/panel/content.test.tsx` | 1 file | 10 passing |
| `calendar/contextmenu.test.tsx` | 1 file | 10 passing |
| **Schedule Event modal** | | |
| `table/modals/scheduleModal/addMode.test.tsx` | 1 file | 8 passing |
| `table/modals/scheduleModal/editMode.test.tsx` | 1 file | 9 passing |
| `table/modals/scheduleModal/navigation.test.tsx` | 1 file | 6 passing |
| `table/modals/scheduleModal/payload.test.tsx` | 1 file | 3 passing |
| `table/modals/scheduleModal/validation.test.tsx` | 1 file | 4 passing |
| `table/modals/scheduleModal/validationRouting.test.tsx` | 1 file | 3 passing |
| `table/modals/scheduleModal/steps/content.test.tsx` | 1 file | 7 passing |
| `table/modals/scheduleModal/steps/displays.test.tsx` | 1 file | 5 passing |
| `table/modals/scheduleModal/steps/time.test.tsx` | 1 file | 7 passing |
| `table/modals/scheduleModal/steps/optional.test.tsx` | 1 file | 11 passing |
| `table/modals/scheduleModal/steps/optionalTabPersistence.test.tsx` | 1 file | 4 passing + **2 expected fail** |
| **Agenda** | | |
| `table/modals/agendaModal/render.test.tsx` | 1 file | 18 passing |
| `table/modals/agendaModal/interaction.test.tsx` | 1 file | 20 passing + **1 expected fail** |
| `table/modals/agendaModal/nested-edit.test.tsx` | 1 file | 5 passing |
| `table/hooks/useAgendaData.test.tsx` | 1 file | 4 passing |
| **Other** | | |
| `table/modals/CopyEventModal.test.tsx` | 1 file | 8 passing |
| `table/modals/DeleteEventModal.test.tsx` | 1 file | 13 passing |
| `table/modals/routing.test.tsx` | 1 file | 13 passing |
| `table/hooks/useEventActions.test.tsx` | 1 file | 16 passing |
| `table/scenarios/createEditDelete.test.tsx` | 1 file | 1 passing |

**Total: 272 tests passing + 3 expected fail (275) across 35 files.**

Verified with `npx vitest run src/pages/Schedule/Schedule/__tests__/ --maxWorkers=2` —
35 passed (35), 272 passed | 3 expected fail (275). Green on three consecutive runs.

> `--maxWorkers=2` is deliberate. A folder-wide run of a `__tests__` tree this size can be OOM-killed on
> a WSL2 dev box, which surfaces as a scatter of unrelated timeouts in files you never touched.

## Known issues

These are **open bugs in the page**, not gaps in the tests. Each is held by a `test.fails()` rather than a
`test.skip()`, so a real fix turns the test green and surfaces immediately.

| # | Issue | Location |
|---|---|---|
| 1 | A **reminder** configured on the Custom daypart survives switching back to Always, and is still sent to `createEvent`. The Repeats half of this was fixed; the Reminder half was not — the Dayparting `onSelect` clears the recurrence fields but not `draft.reminders`. | `ScheduleEventModal.tsx`, Dayparting `onSelect`. Held by 2 `test.fails` in `scheduleModal/steps/optionalTabPersistence.test.tsx:251,266`. See `bug-reports/schedule-modal-stale-repeat-reminder-survives-daypart-switch-to-always.md` |
| 2 | An agenda event whose geo-fence is stored as a GeoJSON **FeatureCollection** silently disappears whenever the viewer sets a location. `isPointInGeoJSON` reads `geoJSON.coordinates` for that type, but a FeatureCollection keeps its members in `.features` — the lookup yields `undefined`, `[].some(...)` is `false`, and the event is filtered out. A fence drawn in the CMS map editor exports as exactly this shape. | `AgendaModal.tsx:156`. Held by 1 `test.fails` in `agendaModal/interaction.test.tsx` |
| 3 | **Not covered, needs a decision.** The agenda's `pageIndex` is never reset when the filtered set shrinks: geo-filter while on page 2 of a type table and `sortedEvents.slice(10,20)` is empty, so the section renders its count badge and zero rows. | `AgendaModal.tsx:967` |
| 4 | **Dead plumbing.** `Events.tsx` holds `shareEntityIds`/`setShareEntityIds` state and threads it into `EventModals`, which never reads it and renders no `ShareModal`. There is no Share on this page. Either wire it up or delete the state — until then there is nothing to test. | `Events.tsx:176,569`; `EventModals.tsx:51-52` |

### Accessibility gaps that constrain the tests

Not bugs in behaviour, but they force weaker queries than `TESTING_STANDARDS.md` §5 wants:

| Control | Problem | Consequence |
|---|---|---|
| Agenda column sort | A bare `<div onClick>` with no role or name (`AgendaModal.tsx:893`) | The two sorting tests reach it via `columnheader → querySelector('div.cursor-pointer')`, the one documented escape hatch in this suite. Make it a `<button aria-label="Sort by …">` and the hatch goes away |
| Agenda timeline slider | Pointer-only `<div>`; `getBoundingClientRect()` is all zeros in JSDOM, so any drag computes 23:59 | Only the *toggle* is tested, via the request it changes. The slider itself is untestable until it becomes a `role="slider"` |
| Agenda tab scroll arrows | Nameless icon `Button`s, and `Element.prototype.scrollBy` does not exist in JSDOM | Untested. Never do a blind `getAllByRole('button')` sweep in this component — clicking one throws |
| Agenda row / sidebar highlighting | Expressed only as `bg-xibo-blue-100` / `-50` classes | Not asserted — §1.8 forbids class assertions. Layout-row selection is observable through the breadcrumb; display-group and campaign selection has no observable effect at all |

## Files

```
frontend/src/pages/Schedule/Schedule/__tests__/
├── fixtures/
│   ├── event.ts                                        ✓ done
│   ├── user.ts                                         ✓ done
│   └── agenda.ts                                       ✓ done  (new)
├── mocks/
│   └── api.ts                                          ✓ done
├── calendar/
│   ├── helpers/ buildCalendarEvents.ts, renderCalendar.tsx   ✓ done
│   ├── events/  recurrence, recurrence-edge, date-clamping   ✓ done
│   ├── grid/    cells, icons, timezone                       ✓ done
│   ├── panel/   open-close, content                          ✓ done
│   ├── contextmenu.test.tsx                            ✓ done
│   └── view.test.tsx                                   ✓ done
└── table/
    ├── helpers/ eventActions.ts, renderEventsPage.tsx  ✓ done
    ├── hooks/
    │   ├── useEventActions.test.tsx                    ✓ done
    │   └── useAgendaData.test.tsx                      ✓ done  (new)
    ├── modals/
    │   ├── CopyEventModal.test.tsx                     ✓ done
    │   ├── DeleteEventModal.test.tsx                   ✓ done
    │   ├── routing.test.tsx                            ✓ done
    │   ├── agendaModal/                                        (new)
    │   │   ├── helpers/renderAgendaModal.tsx           ✓ done
    │   │   ├── render.test.tsx                         ✓ done
    │   │   ├── interaction.test.tsx                    ✓ done
    │   │   └── nested-edit.test.tsx                    ✓ done
    │   └── scheduleModal/
    │       ├── helpers/renderScheduleModal.tsx         ✓ done
    │       ├── steps/ content, displays, time,
    │       │          optional, optionalTabPersistence ✓ done
    │       ├── addMode, editMode, navigation           ✓ done
    │       ├── payload.test.tsx                        ✓ done
    │       └── validation, validationRouting           ✓ done
    ├── page/ render, filters, columns, actions, bulkDelete    ✓ done
    └── scenarios/createEditDelete.test.tsx             ✓ done
```

**Naming.** Unlike Daypart's flat `Daypart.render.test.tsx`, this suite drops the page prefix because the
path already carries the context (`table/page/render.test.tsx`) — `TESTING_STANDARDS.md` §3.4. The two
view-mode clusters (`table/`, `calendar/`) with shared `fixtures/` at the top are the symmetric structure
§3.1 cites as its reference implementation.

**There is no `table/page/share.test.tsx`** — there is no Share on this page (Known issue 4).

## `EventsConfig.tsx` coverage

### Row action wiring (click → modal opens)

| Action | Wiring tested | Modal content tested |
|---|---|---|
| Edit | `table/page/actions.test.tsx` #4 | `scheduleModal/editMode.test.tsx` |
| Make a Copy | `table/page/actions.test.tsx` #5 | `table/modals/CopyEventModal.test.tsx` |
| Delete | `table/page/actions.test.tsx` #6–7 | `table/modals/DeleteEventModal.test.tsx` |
| Add (page button) | `table/page/actions.test.tsx` #1–3 | `scheduleModal/addMode.test.tsx` |
| Agenda (calendar day panel) | `calendar/panel/open-close.test.tsx` #7–8 | `agendaModal/*` |

### Row-action visibility

| Rule | Tested |
|---|---|
| `isEditable === false` suppresses every row action and forces the lock badge | **Not covered** — see Coverage map §1 |
| Agenda button gated on the `schedule.agenda` feature | `calendar/panel/open-close.test.tsx` #8 |
| Agenda breadcrumb edit gated on `schedule.modify` | `agendaModal/nested-edit.test.tsx` #4 |
| Agenda breadcrumb edit gated on the event's own `isEditable` | `agendaModal/nested-edit.test.tsx` #5 |

### Bulk actions

| Bulk action | Tested |
|---|---|
| Delete Selected (visibility, count, one call per id) | `table/page/bulkDelete.test.tsx` #1–4, `useEventActions.test.tsx` #9–10 |
| Share Selected | **Does not exist** — Known issue 4 |

### Hooks

| Hook | Coverage |
|---|---|
| `useEventData` | Indirect — every `table/page/*` file drives it |
| `useEventFilterOptions` | Indirect — always stubbed at page level; the Layout/Campaign dropdown paging inside it is **not covered** |
| `useEventActions` | Direct — `table/hooks/useEventActions.test.tsx` (16) |
| `useAgendaData` | Direct — `table/hooks/useAgendaData.test.tsx` (4) |

## Coverage map — test plan item → file

### §1 Events page — table view

| Plan item | File |
|---|---|
| Default state, all six bullets | `table/page/render.test.tsx` #1–7 |
| Filters panel, all seven bullets | `table/page/filters.test.tsx` #1–10 |
| Column visibility, all three bullets | `table/page/columns.test.tsx` #1–4 |
| Row actions, all four bullets | `table/page/actions.test.tsx` #1–7 |
| Bulk actions, both bullets | `table/page/bulkDelete.test.tsx` #1–4 |
| Locked rows (`isEditable === false`) | **Out of scope** — no fixture exercises a non-editable row at page level. The same rule *is* covered inside the agenda (`nested-edit.test.tsx` #5) |

### §2 Calendar view

| Plan item | File |
|---|---|
| Switching views | `calendar/view.test.tsx` #1–6 |
| The month grid | `calendar/grid/cells.test.tsx` #1–5, `calendar/grid/icons.test.tsx` #1–9 |
| Recurrence | `calendar/events/recurrence.test.tsx` #1–6, `recurrence-edge.test.tsx` #1–10, `date-clamping.test.tsx` #1–4 |
| Timezones | `calendar/grid/timezone.test.tsx` #1–7 |
| Day detail panel | `calendar/panel/open-close.test.tsx` #1–6, `calendar/panel/content.test.tsx` #1–10 |
| Agenda button and its feature gate | `calendar/panel/open-close.test.tsx` #7–8 |
| Context menu | `calendar/contextmenu.test.tsx` #1–10 |

### §3 Schedule Event modal

| Plan item | File |
|---|---|
| Add mode | `scheduleModal/addMode.test.tsx` #1–8 |
| Edit mode | `scheduleModal/editMode.test.tsx` #1–9 |
| Stepper navigation | `scheduleModal/navigation.test.tsx` #1–6 |
| Content step | `scheduleModal/steps/content.test.tsx` #1–7 |
| Displays step | `scheduleModal/steps/displays.test.tsx` #1–5 |
| Time step | `scheduleModal/steps/time.test.tsx` #1–7 |
| Optional step | `scheduleModal/steps/optional.test.tsx` #1–11, `optionalTabPersistence.test.tsx` #1–6 |
| Validation | `scheduleModal/validation.test.tsx` #1–4, `validationRouting.test.tsx` #1–3 |
| What the save request carries | `scheduleModal/payload.test.tsx` #1–3 |

### §4–§6 Copy, Delete, actions

| Plan item | File |
|---|---|
| Copy modal, all seven bullets | `table/modals/CopyEventModal.test.tsx` #1–8 |
| Delete modal — single, recurring, bulk, pending, error | `table/modals/DeleteEventModal.test.tsx` #1–13 |
| Copy / delete / bulk / occurrence actions and their flags | `table/hooks/useEventActions.test.tsx` #1–16 |

### §7 Agenda

| Plan item | File |
|---|---|
| What the agenda shows (all seven bullets) | `agendaModal/render.test.tsx` #1–15 |
| What the agenda asks the API for (all four bullets) | `agendaModal/render.test.tsx` #16–18, `interaction.test.tsx` #1, #3–4 |
| Acting on the agenda | `agendaModal/interaction.test.tsx` #2, #5–13 |
| Filtering by location | `agendaModal/interaction.test.tsx` #14–21 |
| Editing from the agenda | `agendaModal/nested-edit.test.tsx` #1–5 |
| The hook behind it | `table/hooks/useAgendaData.test.tsx` #1–4 |
| Timeline slider, tab scroll arrows, status icons, row highlighting | **Out of scope** — see *Accessibility gaps* above |

### §8–§9 Routing and end to end

| Plan item | File |
|---|---|
| Every `activeModal` branch, including agenda and its null guard | `table/modals/routing.test.tsx` #1–13 |
| Create → rename → delete | `table/scenarios/createEditDelete.test.tsx` #1 |

## What goes in each support file

### `fixtures/event.ts`
`buildEvent(overrides?)` plus `mockEvent`, `mockEvent2`, `mockRecurringEvent` and the table shapes
`SINGLE_EVENT` / `TWO_EVENTS` / `SINGLE_RECURRING_EVENT` / `EMPTY_EVENT_TABLE`. This is the `/schedule`
**grid** shape.

### `fixtures/agenda.ts` (new)
The agenda endpoint returns a different type — `AgendaScheduleEvent` has a flat `displayGroupId`,
unix-second `fromDt`/`toDt` and `intermediateDisplayGroupIds`, where the grid's `Event` has
`displayGroups[]` objects and string dates. Two shapes, two files; §3.2's "one source of truth" is
satisfied by keeping them apart rather than by merging them.

Exports `buildAgendaEvent` / `buildAgendaLayout` / `buildAgendaDisplayGroup` / `buildAgendaCampaign`
factories, a `buildAgendaResponse(events, layouts, displayGroups, campaigns)` assembler that keys the
records the way the API keys them, the fixed `AGENDA_DATE` (UTC, so `startOf`/`endOf('day')` are stable
regardless of the machine's `TZ`) with its derived `AGENDA_DAY_START` / `AGENDA_DAY_END` strings, the
`FOYER_GROUP` / `ATRIUM_GROUP` display groups, and the geo fixtures.

**The geo fences sit in positive coordinate space on purpose**: the lat/lng fields are number inputs, and
typing a leading minus mid-keystroke yields `NaN`, which the input reports as a cleared field.

### `fixtures/user.ts`
`mockUser` from `PERSONAS.superAdmin`, timezone UTC with a **date-only** `DATE_FORMAT_JS`
(`DD/MM/YYYY`).

> **Dangerous default (§1.2).** Under `mockUser` the agenda's From/To columns render without a time,
> and UTC is the identity conversion — so a dropped time or an ignored timezone is invisible. That is
> why `mockUserInSydney` exists (`Australia/Sydney`, `DD/MM/YYYY HH:mm`): an event at 09:00 UTC must
> read 20:00, and `render.test.tsx` #7 is the test that pins it.

### `mocks/api.ts`
Per-service `setup*Mocks` helpers plus the composite `setupScheduleModalMocks()`, the daypart row sets
`ALWAYS_ONLY` / `ALWAYS_AND_CUSTOM`, and `mockDaypartRows`.

> **Dangerous default (§1.2).** `setupScheduleModalMocks()` stubs sync-group members to `[]` — and that
> empty list *was* bug #3927. `scheduleModal/payload.test.tsx` must override it with
> `vi.mocked(fetchSyncGroupDisplays).mockResolvedValue(SYNC_MEMBERS)`; without the override the payload
> test passes against the bug.
>
> `fetchAgendaEvents` is **not** covered by any `setup*` helper. Bare-auto-mocked it resolves `undefined`
> and React Query rejects before any assertion runs, so every agenda file sets it in `beforeEach`.

### `table/helpers/renderEventsPage.tsx` / `table/helpers/eventActions.ts`
Page render wrapper (`.tsx`, touches React) and pure mock factories (`.ts`) — the §3.2 split.

### `table/modals/agendaModal/helpers/renderAgendaModal.tsx` (new)
Renders `AgendaModal` the way `EventModals` does: always open, one day, the display groups the page
derived. `MemoryRouter` is required — the breadcrumb's layout link uses `useNavigate`/`useLocation`.
Returns the `onClose` spy alongside the render result.

## APIs to mock per test file

**Page-level** (`table/page/*`): `eventApi`, `userApi` preferences, the manual `Modal` mock, plus stubs
for `DateRangeController`, `DisplayGroupMultiSelect` and `EventCalendar`.

**Wiring** (`table/page/actions`, `bulkDelete`): additionally stub the modal bodies, so the test asserts
routing rather than modal content.

**Schedule modal** (`scheduleModal/*`): eleven service mocks via `setupScheduleModalMocks()`, plus
`DatePickerInput`, `GeoScheduleMap`, `DataTable` and `DisplayGroupMultiSelect` as stubs, `notify`, and
`hasFeature → true`.

**Agenda** (`agendaModal/*`): `eventApi` only, plus per-file extras —
- `render.test.tsx` and `nested-edit.test.tsx` use the manual `Modal` mock;
- `interaction.test.tsx` uses the **real** `Modal`, because `AgendaModal` passes no footer actions and
  its only close control is the header X from `showCloseButton`, which the manual mock does not
  implement. `variant="tabbed"` is irrelevant to this choice: on the real Modal that variant only
  switches height styling — the per-group tabs are `AgendaModal`'s own markup, passed as children;
- `nested-edit.test.tsx` stubs `ScheduleEventModal` (the real one fires a dozen unmocked requests) and
  `hasFeature`;
- **Leaflet is not stubbed anywhere.** The real map mounts cleanly in JSDOM — verified, not assumed.

## Edge cases

- **Hydration gate** — the page has two preference keys, `event_page` and `event_page_date_range`; the
  render helper seeds them so the pulse resolves.
- **`testQueryClient` is module-scoped and `useAgendaData` sets `staleTime: 30s`.** Without
  `testQueryClient.clear()` a later test is served the earlier one's cached agenda and
  `fetchAgendaEvents` is never called — so a call-args assertion passes against the *previous* test's
  call. `clear()` then `vi.clearAllMocks()` then the data setup, in that order, in every `beforeEach`.
- **Both agenda request shapes come off one ternary.** Assert the positive arm *and*
  `not.toHaveProperty` on the other arm's key — a request carrying both `date` and `startDate` renders
  identically here and behaves differently server-side.
- **Three dialogs can be open at once** in the agenda (agenda + editor + preview), so every
  `getByRole('dialog')` there passes a `name`.
- **`listitem` has no name-from-content**, so the agenda sidebar is asserted with
  `within(heading.closest('section')).getByText(...)`, not `getByRole('listitem', { name })`.
- **JSDOM has no `navigator.geolocation`** — the geo describe defines it in `beforeEach` and deletes it
  in `afterEach`.
- **Bulk delete uses `Promise.allSettled`** — the partial-failure test asserts the error *and* the
  refresh.
- **`.at(-1)` fails typecheck** (TS2550; root tsconfig is ES2020). Use `calls[calls.length - 1]!`.
- **Run one page's suite at a time.** Running Commands + SyncGroups together produced 7 failures across
  4 files that were green individually; this suite is larger than both.
