# Frontend Testing Standards

Standards for Vitest + React Testing Library tests in `frontend/`.

Two kinds of content, and it is worth knowing which you are reading:

- **Rules** — things a reviewer can hold you to. Sections 1–4.
- **Patterns and traps** — accumulated know-how, mostly learned the hard way. Sections 5–10.

---

## 1. The rules

Each exists because of a specific fault that reached production.

### 1.1 Assert the payload on every save

Any test that exercises a Save, Create, Update or Delete **must assert what was sent**, not only what
was rendered. Not instead of checking the screen — as well as. Full method in §4.

**Why:** a rough count across `frontend/src` found ~3,805 assertions on rendered output against ~326
on what was sent — about twelve to one. The largest family of faults crossed a boundary and was lost
there while the UI looked correct throughout. A render assertion cannot distinguish those two states,
by construction.

### 1.2 Audit what your mocks return by default

Before asserting anything, ask what the shared mock helpers return, and **override any default that
matters to the field under test**.

**Why:** `setupScheduleModalMocks()` stubs the sync-group member fetch to `[]`. An empty member list
*was* the bug. A test written without overriding that default passes against broken code and proves
nothing. A mock's default silently becomes an assumption; when it equals the broken state, the test
is decorative.

### 1.3 Never let a mock's default be its only tested value

Wherever a mock returns an empty list, a single row, or one page, add at least one test with a
populated, multi-item, multi-page value.

**Why:** daypart fixtures always set `totalCount === rows.length`, so the paginated case cannot arise
in any test — which is why four dropdown pagination faults were invisible.

### 1.4 Prove every new test red

A test that has never been observed to fail is not evidence that it works. Break the thing it
protects, watch it fail, then restore.

```bash
npx vitest run <file>          # green
# reintroduce the fault in the source
npx vitest run <file>          # MUST be red — read the message
git checkout -- <source file>
npx vitest run <file>          # green again
```

**Why:** during the gap analysis a companion test passed **three times** against deliberately broken
code, because a cache was serving a stale response. Without the red step we would have shipped a test
that checked nothing.

### 1.5 A bug fix ships with an assertion that fails without the fix

Not "we updated the tests". An assertion that is red before the fix and green after.

**Why:** three fixes in the review changed test files — a renamed CSS class, a type update, some
provider scaffolding — and added no assertion about the bug. In a pull request, "tests touched" and
"tests added" look identical.

### 1.6 Don't mock over the boundary you are trying to protect

Service modules in `src/services/` are mocked by nearly every test. They also contain ~595 lines of
hand-built `URLSearchParams` serialisation across 38 files, with **no tests at all**. Test them
directly — pure function in, query string out, no DOM, no mocks.

### 1.7 Prefer one contract test over twenty page tests

Where a pattern repeats across pages, write the check once against the shared behaviour:

- formatted columns must export what they render (`meta.getExportValue` vs `cell`)
- every sortable column must reach the API; every filter must narrow the result
- bulk actions must send all N ids — pair with `trackSequentialCalls()` from
  `src/testUtils/sequentialMock.ts`

**Why:** these three account for 21 of the 115 faults, and each is one helper applied everywhere.

### 1.8 Select by role and label, never by CSS class or DOM shape

**Why:** 49 Cypress spec files became worthless the moment the interface was rebuilt, because they
were coupled to markup rather than to what things *are*. The investment was not wasted by the tool
choice.

---

## 2. Definition of done, for a bug fix

Three lines. No tooling required.

1. The fix ships with a check that was **observed** to fail before the fix.
2. If the code path needed new fixture data to be reachable, **the fixture is part of the fix**.
3. If a change removes tests, **the description says so**.

Line 2 exists because one fault was unreachable by any test that could have been written — the seed
data contained two of ten event types, and the failing branch was not among them. More test-writing
effort would not have helped; only a fixture would.

---

## 3. Structure and layering

### 3.1 Folder structure

Each layer has exactly one responsibility. Never mix concerns across layers.

```
__tests__/
├── fixtures/          — data only (no logic, no assertions)
├── mocks/             — API behaviour (thin, dumb, behaviour-neutral)
├── helpers/           — interaction and render utilities (actions + DOM state only)
└── *.test.tsx         — behaviour assertions only
```

When a page has multiple views (a table view and a calendar view, say), use **symmetric view-mode
clusters** — each view gets its own cluster of the same shape, with shared fixtures at the top:

```
__tests__/
├── fixtures/                  ← shared canonical fixtures
│   ├── event.ts               ← buildEvent factory + named fixtures
│   └── user.ts
├── table/
│   ├── helpers/
│   │   ├── eventActions.ts        ← pure mock factories (.ts, no JSX)
│   │   └── renderEventsPage.tsx   ← DOM render + interaction (.tsx)
│   ├── modals/
│   │   ├── CopyEventModal.test.tsx
│   │   └── scheduleModal/         ← multi-step modal sub-cluster
│   │       ├── helpers/
│   │       ├── steps/
│   │       ├── navigation.test.tsx
│   │       └── payload.test.tsx
│   ├── page/
│   └── hooks/
└── calendar/                  ← same shape
```

Reference: `src/pages/Schedule/Schedule/__tests__/`.

### 3.2 Layer rules

**`fixtures/`**
- Minimal but valid data — not comprehensive replicas of the backend shape
- A factory function (`buildDisplayGroup(overrides?)`) so tests override only what they care about
- Only fields used in assertions or required for rendering carry meaningful values; everything else
  uses the zero value for its type (`''`, `0`, `[]`, `false`)
- One source of truth per fixture. **If the same data appears in two files, one of them is wrong.**
- Keep fixtures updated when the backend type changes — stale fixtures are silent bugs

**`mocks/`**
- Thin and dumb: return data, never embed business logic
- No pagination rules, filtering rules, or conditional responses inside mocks
- One file per service (`displayGroupApi.ts`, not a catch-all)
- Document which `vi.mock(...)` call the test file must include for each helper to work

**`helpers/`**
- Perform actions and return DOM state — nothing else
- **Never assert inside a helper** — that belongs in the test
- **Never decide correctness inside a helper** — that belongs in the test
- Helpers that open UI flows must await the resulting element and return it, so callers have a
  deterministic starting point

```ts
export const openEditModal = async (user: UserEvent) => {
  await screen.findByText(mockDisplayGroup.displayGroup);
  await user.click(screen.getByRole('button', { name: /edit/i }));
  return screen.findByRole('dialog');   // returns DOM state, does not assert
};
```

Split helper files by concern: `.tsx` for anything touching React (render wrappers, modal openers),
`.ts` for pure factories (`mockData(...)`, `defaultActions(...)`, type re-exports). The split keeps
pure factories importable without dragging in JSX/DOM dependencies.

### 3.3 When to split a test file

Split when flows have **different user intent** (delete vs edit vs create), **different setup
complexity** (form isolation vs full page integration), or **different mock requirements** (real
modal vs stub modal).

Do not split purely for line count. Fragmentation across too many files makes behaviour hard to trace.

### 3.4 Naming

- **Folder context drops the redundant prefix.** `Events.actions.test.tsx` becomes `actions.test.tsx`
  once it lives in `page/` — the path `table/page/actions.test.tsx` is already unambiguous.
- **Component tests keep the component name** — `CopyEventModal.test.tsx`, even inside `modals/`.
- **Cross-cutting flows use feature names** — `routing.test.tsx`, `validation.test.tsx`,
  `addMode.test.tsx`, `payload.test.tsx`.
- Helpers: `renderXxxPage.tsx` (full page), `renderXxx.tsx` (single component), `xxxActions.ts`
  (pure factories), `buildXxx.ts` (domain factories built on a base factory).

### 3.5 Test descriptions read like sentences

Every `test('...')` description should read like something a non-tester would understand, and
comments should explain WHY in everyday terms rather than referencing internal state.

```ts
// Bad — references internal implementation detail
test('mockEventData updates useEventData mock with rawData', ...)

// Good — describes what the user sees
test('the table shows the default set of columns when the page first opens', ...)
```

```ts
// Bad
// hasFeature returns false → useEffect resets optionalTab to 'general'

// Good
// When the user lacks the Geo Location feature, that sub-tab shouldn't be in the nav at all.
```

---

## 4. What to assert

### 4.1 The payload — how to write one

The highest-value pattern in this document. Worked example:
`src/pages/Schedule/Schedule/__tests__/table/modals/scheduleModal/payload.test.tsx`.

**How to get to the test, from a bug report:**

1. **Start from the fix diff, not the issue title.** The title said "Sync event does not assign any
   displayGroup". The diff changed one line — `? []` → `? syncDisplays.map(d => d.displayGroupId)` —
   which tells you the fault is in what gets *sent*, not what gets rendered.
2. **Ask what is observably different** between working and broken. If nothing on screen changes, it
   is a payload test and no render assertion can ever catch it.
3. **Find the boundary.** Follow the value until it leaves the component — usually a call into
   `src/services/`. Everything above is component state; below is HTTP. Assert at the crossing.
4. **Confirm the gap.** `grep -rn "createEvent).toHaveBeenCalledWith" src/pages/Schedule` returned
   one hit, checking a different field. Confirm rather than assume.
5. **Find the cheapest route to the save.** Read the component for prefill props before writing
   interaction code — `contentId` doubles as the sync group, so no dropdown clicking was needed.
6. **Audit the mock defaults** (rule 1.2). This is the step people skip.
7. **Assert three ways** (below).
8. **Prove it red** (rule 1.4).

**The three assertions, deliberately at different strengths:**

```ts
// A. The exact value — pins the specific fix.
//    objectContaining because the payload has ~40 fields; asserting the whole object breaks
//    whenever an unrelated one is added.
await waitFor(() => {
  expect(createEvent).toHaveBeenCalledWith(
    expect.objectContaining({ syncGroupId: 7, displayGroupIds: [101, 102] }),
    expect.any(String),          // the timezone arg — we don't care what it is
  );
});

// B. The invariant — survives a rewrite of HOW the value is built, and covers a server-side
//    fallback. Reach into mock.calls when inspecting rather than matching.
const calls = vi.mocked(createEvent).mock.calls;
const payload = calls[calls.length - 1]![0];    // NOT .at(-1) — see §9
expect(payload.displayGroupIds).not.toHaveLength(0);

// C. The opposite branch — both arms usually come off one ternary, so a fix to one can break the
//    other. C is what proves the tests DISCRIMINATE: with the fault reintroduced, A and B went red
//    and C stayed green.
```

**Overriding the default that matters:**

```ts
beforeEach(() => {
  testQueryClient.clear();
  vi.clearAllMocks();
  setupScheduleModalMocks();                                         // shared defaults — all empty
  mockDaypartRows(ALWAYS_ONLY);
  vi.mocked(fetchSyncGroupDisplays).mockResolvedValue(SYNC_MEMBERS); // ← without this, worthless
});
```

**Waiting for data that arrives outside the draft.** When the value under assertion lands via an
effect rather than props, opening the modal is not enough:

```ts
await screen.findByRole('combobox', { name: 'Dayparting' });   // 1. modal is open
await waitFor(() => expect(fetchSyncGroupDisplays).toHaveBeenCalled());  // 2. data requested
```

### 4.2 API call assertions

| Scenario | Rule |
|---|---|
| User-triggered action (form submit, button click) | `toHaveBeenCalledTimes(1)` is acceptable |
| Background fetches, React Query refetches | Assert UI outcome instead — count is non-deterministic |
| Payload correctness | `expect.objectContaining({ key fields })` — lock the fields you assert on, not the full object |
| Failure path | Assert the API was called **and** check the error message content (`toHaveTextContent('…')`). Checking only that an alert appeared gives false confidence — any error, or an unrelated alert, would pass |
| Ordering matters | `invocationCallOrder`, not call counts — see §9 |

### 4.3 What to assert, by kind of fault

| If the fault is… | Assert on… |
|---|---|
| The wrong data was saved | the mutation's arguments (§4.1) |
| A control does nothing | the request that control should have caused |
| Export or download content | `meta.getExportValue`, not the `cell` renderer |
| A value read back wrong | the pure function that reads it, directly |
| Only some of N items changed | call count **and** ordering — `trackSequentialCalls()` |
| Layout, overflow, z-index | not a Vitest test — needs visual regression |
| Anything server-side | not a Vitest test — PHPUnit, `tests/` |

### 4.4 Pending state coverage

Test loading states for every user-triggered mutation, using a controlled promise:

```ts
let resolve!: () => void;
const pending = new Promise<void>((res) => { resolve = res; });
vi.mocked(someApi).mockReturnValue(pending);

// assert disabled button / loading label here

resolve();
await waitFor(() => expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled());
```

This covers the double-submit prevention path and ensures clean teardown.

---

## 5. Queries, interaction and async

### 5.1 Query strategy

| Priority | Query | When |
|---|---|---|
| ✅ Always prefer | `getByRole`, `findByRole` | Interactive elements, dialogs, checkboxes, buttons |
| ✅ Prefer | `getByLabelText`, `findByText` | Form fields with labels, visible text |
| ⚠️ Fallback only | `getByPlaceholderText` | Only when no accessible name exists |
| ❌ Avoid | `getByTitle`, `getByTestId` | Not user-centric; breaks on refactor |

Accessible names must be explicit on components. Icon-only buttons require `aria-label`:

```tsx
<button aria-label={action.label} title={action.label}>
  <SomeIcon />
</button>
```

If `getByRole` cannot find an element because it has no accessible name, **the fix is to add
accessibility to the component** — not to fall back to `getByPlaceholderText` or `getByTestId`. Add
an `aria-label`, a visible `<label>`, or `aria-labelledby`. This improves real screen-reader support
at the same time.

### 5.2 Interaction

- Always `userEvent.setup()` — never `fireEvent`. `userEvent` fires the full browser event sequence.
- One `user` instance per test, passed down to helpers.
- Type the parameter as `UserEvent` from `@testing-library/user-event`, not
  `ReturnType<typeof userEvent.setup>`.

### 5.3 Async

- Use `findBy*` to wait for async DOM changes — do not manually wrap in `act()`. RTL already wraps
  most updates; manual `act()` is almost always wrong.
- Use `waitFor` only for a non-DOM side effect (a mock having been called, say).
- Await `findByRole('dialog', { name: /modal title/i })` to confirm a modal opened — **always include
  `name`**, so the test fails if the wrong dialog opens.
- If a component fires a `useEffect` fetch on mount, even synchronous-looking tests must be `async`
  and use `findBy*` for their first assertion. This drains the microtask queue and prevents "not
  wrapped in act()" warnings from the state update after the promise resolves.

### 5.4 Mock state and isolation

- `testQueryClient.clear()` and `vi.clearAllMocks()` in `beforeEach` for every suite.
- Call data-setup helpers **after** clearing, not before.
- Centralise shared query keys in `fixtures/` so a key change is one edit, not a search.
- Use `as unknown as T` for intentional partial mock casts — the double cast is explicit in review.

---

## 6. Mock patterns

The recipes used repeatedly for testing complex components.

**Manual Modal mock.** The repo provides `src/components/ui/modals/__mocks__/Modal.tsx`, which
renders the dialog content and actions as plain DOM with `role="dialog"`. Pick it up with no factory:

```ts
vi.mock('@/components/ui/modals/Modal');
```

Note it does **not** implement `showCloseButton` — it renders `role="dialog"`, an `<h1>` title, a
`role="alert"` for errors, the children and the `actions` buttons. A component whose Close comes from
`showCloseButton` must be tested against the real `Modal` (just omit the `vi.mock`); the real one
renders `aria-label="Close"` and portals to `document.body`, both fine in JSDOM. This bites hardest on
a modal that passes **no `actions`** — under the manual mock it then has no close affordance at all,
and neither a close-button test nor an Escape test can exist.

The mock also ignores `variant`, but that costs nothing: on the real `Modal`, `variant="tabbed"` only
swaps `max-h-[90vh]` for `h-[90vh]`. It renders no tab UI — a "tabbed" modal's tabs are always its own
markup, passed as children, which the mock renders normally. Don't reach for the real `Modal` on
account of `variant` alone.

**API service mocks.** One `vi.mock` per service the component imports, each function returning a
shape-matching empty default:

```ts
vi.mock('@/services/eventApi', () => ({
  createEvent: vi.fn().mockResolvedValue({}),
  updateEvent: vi.fn().mockResolvedValue({}),
  fetchEventById: vi.fn().mockResolvedValue({}),
}));
```

**Heavy components as stubs.** Date pickers, maps, complex tables — replace with simple stubs and
assert on presence/absence:

```ts
vi.mock('@/components/ui/forms/DatePickerInput', () => ({
  default: ({ label }: { label?: string }) => <input aria-label={label} readOnly />,
}));
vi.mock('@/components/ui/GeoScheduleMap', () => ({
  default: () => <div role="img" aria-label="Geo schedule map" />,
}));
```

Be aware of the cost: stubbing removes the stubbed component's behaviour from every test in the file.
Four dropdown pagination faults lived in code no test ever executed, because `SelectDropdown` was
always replaced by a native `<select>`.

**Multi-button stubs** for components that emit different `onChange` shapes — one button per shape,
so tests can drive each path:

```ts
vi.mock('@/pages/Schedule/Schedule/components/DisplayGroupMultiSelect', () => ({
  DisplayGroupMultiSelect: ({ onChange }: { onChange: (v: {...}) => void }) => (
    <>
      <button onClick={() => onChange({ displaySpecificGroupIds: [10], displayGroupIds: [] })}>
        Pick a display group
      </button>
      <button onClick={() => onChange({ displaySpecificGroupIds: [], displayGroupIds: [] })}>
        Clear group
      </button>
    </>
  ),
}));
```

**Native `<select>` for `SelectDropdown`.** The real component has internal search and keyboard logic;
for tests that interact with it, mock it as a native select, then use
`getByRole('combobox', { name })` and `fireEvent.change`. When iterating options, filter out the
empty-value placeholder.

**Notification and permissions:**

```ts
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/utils/permissions', () => ({ hasFeature: vi.fn().mockReturnValue(true) }));
```

`hasFeature` defaults to `true`; flip it per-test with `vi.mocked(hasFeature).mockReturnValue(false)`
for negative-flag scenarios.

**Modal stubs in page-level tests.** The stub triggers callbacks only (`onSave`, `onClose`) — no form
logic, no API calls. Pass `data` through as-is. The stub must expose `role="dialog"` so tests can
assert it opened. Form validation and payload verification belong in form-level tests, not page-level.

**Why mock blocks are duplicated per file.** `vi.mock` is hoisted above imports per file, so a
centralised setup would have to either commit to global mocking (coupling unrelated suites to one
config) or use `vi.hoisted()` gymnastics. The duplication is deliberate.

---

## 7. Multi-step modal shortcuts

Walking a user through a four-step wizard in every test is slow and noisy. Land on the step you want:

- **`contentId` prop** pre-fills content for the default event type and starts at Step 1.
- **Sync mode** skips the Displays step entirely — `{ eventTypeId: EventTypeId.Sync, contentId: 42 }`
  lands on the Time step with no interaction. `contentId` doubles as `syncGroupId` here.
- **`mode: 'edit'`** opens at Step 0 with all steps unlocked, so you can jump via the stepper.
- **Daypart fixture with an Always row** so the modal auto-selects it and `isTimeStepValid` is true —
  otherwise Finish never appears.

Read the component's draft factory before writing interaction code; most modals have a prefill route.

---

## 8. Banned patterns

These test shapes are **banned** and should be deleted on sight, not refactored.

### Heading-only / render-presence tests

```ts
// ❌ Banned — render-only when a behaviour test for the same variant already exists
test('foo modal renders "Foo" heading', () => {
  renderModals({ activeModal: 'foo' });
  expect(screen.getByRole('heading', { name: /foo/i })).toBeInTheDocument();
  expect(screen.getByRole('button', { name: /confirm/i })).toBeInTheDocument();
});
```

**Why:** it can only fail if the component throws or the text changes. It passes even if the confirm
button is disabled, wired to the wrong handler, or calls nothing. The companion handler test already
proves the component rendered and the button exists.

**The precise rule:** every modal variant must have at least one behaviour test — a handler
assertion, a button state check, or an API call assertion. A render-only test is redundant *only when
that behaviour test already exists*. If no behaviour test exists at all, a render-only test is better
than nothing but must be upgraded, not deleted.

### i18n key string tests (hard ban)

```ts
// ❌ Hard ban — the t mock makes this meaningless
expect(screen.getByText('Send a command to {{count}} display(s).')).toBeInTheDocument();
```

**Why:** with `t` mocked as `(key) => key`, this asserts the raw translation key, not anything a user
sees. It passes even if `Trans` interpolation is broken, the `count` prop is ignored, or the key is
wrong. **Never assert a string containing `{{…}}` or one you know to be an i18n key.**

### Hardcoded static-string tests (soft ban)

```ts
// ❌ Soft ban — redundant when other tests already prove the component renders
expect(screen.getByText('Are you sure you want to request a collection to occur?')).toBeInTheDocument();
```

**Why:** not a false positive — the string is what the user sees — but it breaks whenever copy is
updated, which is not a regression, and other tests in the file already prove the component renders.
If it is the *only* test for that component, upgrade it to assert behaviour instead of deleting it.

---

## 9. Anti-patterns and pitfalls

### Anti-patterns

| Anti-pattern | Why it fails |
|---|---|
| `getByPlaceholderText` | Placeholder text changes frequently; not an accessible name |
| `getByTitle` | Tooltip semantics; not a reliable accessible name |
| `getByTestId` | Implementation detail; survives refactors that break real UX |
| `getByText` for interactive/structural elements | Matches heading, button and span alike — use `getByRole(role, { name })` |
| `fireEvent` | Does not fire the full event sequence; misses focus, pointer, keyboard state |
| `new Promise(() => {})` | Leaks — use a controlled promise with a resolve handle |
| `as T` on partial mocks | May silently accept incomplete objects — use `as unknown as T` |
| Asserting `aria-*` attributes | Tests implementation, not behaviour — assert visible text |
| Business logic in helpers | Helpers become hidden test frameworks that obscure intent |
| Hardcoded query keys | Breaks silently when the key changes — centralise in `fixtures/` |
| `toHaveBeenCalledTimes` on refetch APIs | React Query may validly call several times — assert UI outcome |
| `expect(await findByRole(...)).toBeInTheDocument()` | `findByRole` already throws if absent; the matcher is always true. The query **is** the assertion — drop it or use `toHaveTextContent` |
| `.toBeDefined()` / `.toBeTruthy()` on query results | Passes for almost any non-null value — use `toHaveTextContent`, `toHaveValue`, `toBeDisabled` |
| A mock default that equals the broken state | The test passes against the bug — see rule 1.2 |

### Pitfalls

**`vi.mock` is hoisted ABOVE imports.** You cannot reference a module-level binding inside a
`vi.mock(...)` factory — it is in the temporal dead zone at hoist time. Inline the factory body, or
use `vi.hoisted()`.

**`Array.prototype.at()` requires `lib: es2022`.** The root `tsconfig.json` uses `ES2020`, so `.at(-1)`
fails typecheck with TS2550. Use index access:

```ts
const calls = vi.mocked(saveUserPreference).mock.calls;
const lastCall = calls[calls.length - 1]!;
```

**JSDOM contention under parallel runs.** A few tests pass in isolation but flake at the 5s default
when the whole suite races. Bump the per-test timeout and **document why**, so a future reader does
not delete it: `}, 20_000); // bumped — runs in ~1s in isolation`.

**Factories auto-increment at module load.** `buildEvent()`'s counter advances per call, including
when named fixtures are constructed at import time. Never assume `eventId === 1`; only that IDs are
unique.

**`testQueryClient` lives across tests.** Module-scoped — always `testQueryClient.clear()` in
`beforeEach`, or React Query state leaks between tests.

**Manual mocks need an adjacent `__mocks__` directory.** `vi.mock('@/components/ui/modals/Modal')`
with no factory only works because `__mocks__/Modal.tsx` sits beside the real file.

**A factory-less `vi.mock` on a service with no `__mocks__` file auto-mocks every export to
`undefined`.** The component then rejects on `undefined.then` before your assertion is reached. Give
the whole surface resolved values in one helper rather than per test. Contrast `@/services/folderApi`,
which *does* have a `__mocks__` file — there the bare call is enough.

**One mocked function can feed two things.** `fetchDisplays` backs both the page grid and the
Add Display lookup, so a helper that sets it to `[freshlyRegistered]` also renders that row and
`getByText` matches twice. In page-level tests pass an empty list to the feature helper and set the
grid separately — and remember whichever helper runs last in `beforeEach` wins.

**Accessible names concatenate when icons are `aria-hidden`.** A card-style button with a title *and*
a description has one accessible name made of both. Export the matcher next to the render helper so
every file uses the same one. For icon-only buttons with no label at all, select by exclusion.

**`FilterInputs` text filters have two renderers.** With `showAndOr`/`showRegex` it renders
`TextInput`, whose label IS associated — `getByRole('textbox', { name: /^name$/i })` works. Without
toggles it renders `InputFilter`, whose label is not associated and whose placeholder may be a blank
space, so it has **no accessible name**. Select it as the filter-panel textbox that is not the search
box. `FilterInputs` also sets `aria-hidden` while collapsed — open the panel before querying.

**Chained-`setTimeout` pollers usually need no fake timers.** A recursive `setTimeout` fires its first
request immediately, before any timer is scheduled, so a test needing only the terminal state should
set the resolved value before rendering and use no fake timers. Only a *transition* needs timers, and
then only the `userEvent`-compatible form (`vi.useFakeTimers({ shouldAdvanceTime: true })` plus
`userEvent.setup({ advanceTimers: vi.advanceTimersByTime })`). Always pair with a file-level
`afterEach(() => vi.useRealTimers())`.

**Assert ordering with `invocationCallOrder`, not call counts:**

```ts
expect(vi.mocked(fetchHighestDisplayId).mock.invocationCallOrder[0]!).toBeLessThan(
  vi.mocked(addDisplayViaCode).mock.invocationCallOrder[0]!,
);
```

**`listitem` has no name-from-content.** `getByRole('listitem', { name: /Foyer Screens/ })` always
fails, however plain the `<li>` text — an `li` takes its accessible name only from `aria-label`/
`aria-labelledby`. Reach list content by scoping instead:

```tsx
const heading = await screen.findByRole('heading', { name: 'Display Groups', level: 3 });
expect(within(heading.closest('section')!).getByText('Foyer Screens')).toBeInTheDocument();
```

(`row`, `button` and `heading` *do* take their name from content — this is an `li`/`listitem` quirk.)

**Number inputs and the leading minus.** `user.type(latField, '-2.24')` types character by character,
and the intermediate value `-` is not a number: `valueAsNumber` is `NaN`, and a `NumberInput` that maps
`NaN` to "cleared" wipes the field mid-keystroke. Where a test just needs two disjoint values, choose
fixture coordinates in **positive** space rather than fighting the input; where the minus itself is
under test, set the value in one go.

**Assert the sibling arm of a ternary, not only the arm you care about.** Where one request shape is
built by `cond ? {a, b} : {a, c}`, a positive assertion on `b` still passes if the code wrongly sends
`b` *and* `c`. Pair it with `expect(params).not.toHaveProperty('c')` — the two shapes usually render
identically and diverge only server-side.

**Verify a JSDOM "this can't work" claim by running it.** Heavy libraries are not automatically
unusable: `react-leaflet` + `leaflet` mount cleanly in JSDOM under this config (`.leaflet-container`
appears, `MapZoomControls` attaches) and `AgendaModal`'s map is tested for real. Stub such a library
when you need to *assert* something it hides (a map's centre coordinates, say), not on the assumption
that it will crash — a reflexive stub silently removes real coverage.

---

## 10. Verification

Run from `frontend/`:

```bash
npx tsc --noEmit -p .                              # catches missed renames and type drift
npx vitest run src/pages/<Page>/<Page>/__tests__/   # focused suite
npm test                                            # everything

# Stability under parallel load — run 3x if flakiness is suspected
for i in 1 2 3; do npx vitest run src/pages/<Page>/ 2>&1 | tail -3; done
```

Green typecheck + green suite + three green parallel runs is the definition of ready to commit for a
test-folder change.

### Review checklist

- [ ] Does a test that exercises a save assert what was **sent** (rule 1.1)?
- [ ] Has every mock default relevant to the assertion been overridden (rule 1.2)?
- [ ] Is there at least one test with populated / multi-item / multi-page data (rule 1.3)?
- [ ] Was the test **observed** to fail before the fix (rule 1.4)?
- [ ] Are selectors by role and label rather than CSS or testId (rule 1.8, §5.1)?
- [ ] If tests were removed, is that called out in the description?
- [ ] Right location, right cluster, right name (§3.1, §3.4)?
- [ ] Fixtures from `__tests__/fixtures/`, helpers from the matching cluster?
- [ ] `testQueryClient.clear()` + `vi.clearAllMocks()` in `beforeEach`?
- [ ] Do descriptions read like sentences a non-tester would understand (§3.5)?
- [ ] Any banned patterns present (§8)?

---

## The one-line version

> Assert what crosses the boundary, not just what renders — and make sure your mock's default isn't
> the bug you're trying to catch.
