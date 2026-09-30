# Testmo sync for player test suites

This tool copies the Xibo player test suite (`.docx`) into Testmo and keeps it in step as the suite changes. When a new suite version comes out, you run two commands:

- **`update`** changes the cases that are already in Testmo: edits, moves, renames and retirements.
- **`push`** adds the new cases.

The tool never deletes anything, and it won't overwrite edits someone made by hand in Testmo unless you tell it to.

In Testmo, everything lives under the **Player Test Suite** folder, with one sub-folder per area (INST, ACT, CFG, ...).

---

## 1. One-time setup

You need **Python 3.8 or newer** (`python3 --version`) and a copy of the `xibo-cms` repo. There's nothing else to install.

**Get a Testmo API token.** In Testmo, open your avatar menu > *Profile* > *API access* > create a token. Put it in your shell. Never commit it or paste it into chat:

```bash
export TESTMO_TOKEN=paste-your-token-here
# To avoid doing this every session, add that line to ~/.bashrc (your machine only).
```

**Open a terminal in the tool folder.** Every command below runs from here:

```bash
cd xibo-cms/tools/testmo-import
```

`config.json` (which Testmo project, which folders, which fields) is already set up in the repo. You don't need to change it.

---

## 2. Syncing a new suite version

Do this whenever a new suite docx is ready (e.g. `Xibo-Player-Test-Suite-v1.4.docx`).

**Before you start:**
- Only **one person** should sync at a time. Say so in the team chat.
- Run `git pull` so you have the latest `sync-state.json` (see [section 5](#5-the-sync-state-file)).

**Step 1: preview the changes to existing cases.** This changes nothing:

```bash
python3 testmo_import.py update ~/Downloads/Xibo-Player-Test-Suite-v1.4.docx --dry-run
```

Read the report. [Section 3](#3-reading-the-update-report) explains each part. Sort out any **conflicts** and **possible renames** before going on.

**Step 2: apply the changes to existing cases:**

```bash
python3 testmo_import.py update ~/Downloads/Xibo-Player-Test-Suite-v1.4.docx
```

**Step 3: preview, then add the new cases:**

```bash
python3 testmo_import.py push ~/Downloads/Xibo-Player-Test-Suite-v1.4.docx --dry-run
python3 testmo_import.py push ~/Downloads/Xibo-Player-Test-Suite-v1.4.docx
```

Always run **update before push**. If you run push first, a test whose ID was renamed would be added as a new case instead of being renamed.

**Step 4: commit the state file** so the next person starts from your sync:

```bash
git add sync-state.json
git commit -m "Testmo sync: player suite v1.4"
```

Then push or open a PR the way you normally would.

**Step 5: check in Testmo.** Open *Player Test Suite* and look over two or three of the changed cases.

---

## 3. Reading the update report

| Section | What it means | What you do |
|---|---|---|
| **Update** | The docx changed these cases. It lists which fields changed. | Nothing. They'll be updated. |
| **Rename Test ID** | You gave a rename. The case keeps its history and gets the new ID. | Nothing. |
| **Un-retire** | A retired test is back in the docx. | Nothing. It becomes active again. |
| **Retire (removed from docx)** | These tests aren't in the docx any more. They'll be set to *Retired*, not deleted. | Check that the removal was intended. To delete for real, do it by hand in Testmo later. |
| **Skipped: conflict** | Someone edited the case in Testmo **and** the docx changed it too. | See [4a](#4a-conflicts). |
| **Not retired: possible rename** | A test disappeared and a new one with the same name appeared under a different ID. | See [4b](#4b-renamed-test-ids). |
| **Edited in Testmo, docx unchanged** | Someone edited it in Testmo; the docx doesn't touch it. | Nothing. It's left alone. |
| **Not in Testmo** | New tests. | They're added in Step 3 (push). |
| **Unchanged / Matches Testmo** | Already up to date. | Nothing. |

A full copy of the report is saved to `update-report.json`.

---

## 4. Handling the special cases

### 4a. Conflicts

A conflict means the tool would have to overwrite someone's hand edit in Testmo, so it stops and asks. Open the case in Testmo and decide:

- **The docx is right:** apply it to that case. Only the fields the docx changed are overwritten; other hand edits stay.
  ```bash
  python3 testmo_import.py update suite.docx --override INST-002
  python3 testmo_import.py update suite.docx --override "INST-002,SCH-*"   # several; wildcards OK
  ```
- **The Testmo edit is right:** leave it. Better still, ask the suite owner to add that change to the docx so the two agree.

`--override-all` applies the docx to every conflict. Use it only when you're sure no hand edits should be kept.

### 4b. Renamed Test IDs

The tool matches cases by the Test ID at the start of their name. So if `SCH-004` becomes `SCH-099` in the docx, the tool can't tell whether it's the same test. By default it holds both: it won't retire SCH-004 or add SCH-099. Tell it which you mean:

- **It's the same test with a new ID** (the usual case). Add the rename so the Testmo case, and its run history, carries over:
  ```bash
  python3 testmo_import.py update suite.docx --rename SCH-004=SCH-099
  ```
  Repeat `--rename` for each one. Or ask the maintainer to add it permanently under `"renames"` in `config.json`.

  The tool checks every rename before changing anything, and stops if any is wrong. SCH-004 must exist in Testmo, SCH-099 must not exist yet, and SCH-099 must be in the docx.
- **They really are different tests:** add `--retire-suspected` to update (retires the old one) and `--add-suspected` to push (adds the new one).

### 4c. Syncing only part of the suite

Every command accepts these filters:

```bash
--only INST-001,SCH-*     # Test IDs, wildcards allowed
--area INST,SCH           # area codes
--priority P0,P1
--player Android          # tests that list this player
--limit 3                 # first N tests (good for a trial)
```

When you use a filter, **update doesn't retire anything**, because it can't tell "removed from the docx" from "not selected". Run without filters to retire.

---

## 5. The sync state file

`sync-state.json` records what was last written to each Testmo case. It's how the tool tells "the docx changed" from "someone edited this in Testmo". It holds no secrets and it's shared through git.

- **Pull before you sync, and commit after.**
- **An out-of-date copy is safe.** If a teammate synced after your last pull, cases that already match the docx are just recorded as done. At worst a case shows as a conflict. The tool never overwrites because of an out-of-date copy.
- **Don't delete or hand-edit it.** Without it, every case that differs from the docx becomes a conflict.

---

## 6. Other commands

| Command | What it's for |
|---|---|
| `python3 testmo_import.py diff old.docx new.docx [-v]` | Offline report: added, removed, changed and possibly renamed tests between two docx versions. Needs no token and never touches Testmo. Handy for reviewing a new version. `-v` shows old and new text. |
| `python3 testmo_import.py parse suite.docx -o suite.json` | Turns the docx into JSON so you can inspect it. `update` and `push` accept the docx directly, so you don't need this. |
| `python3 testmo_import.py discover` | Lists the Testmo project's templates, fields, Priority options and states. For the maintainer when changing `config.json`. |

**Other safeguards in `push`:**
- A case that was imported before and later deleted in Testmo isn't re-added. Use `--readd` if you want it back.
- A Test ID used by more than one Testmo case is reported and skipped.

---

## 7. What goes where in Testmo

| Docx column | Testmo |
|---|---|
| Test ID + Test Scenario | Case name, e.g. `INST-001 Install via MSI installer` |
| Section heading (INST – ...) | Sub-folder under *Player Test Suite* |
| Test Steps | Steps: one Testmo step per numbered line |
| Expected Result | Expected result of the last step |
| Players, Pre-requisite | Description |
| Estimated Execution Time | Estimate (`5 min`, `1 hr`, `2 hrs`, `1 hr 30 min`, `30 sec` are all understood; anything else is left out with a warning) |
| Priority | Priority field: P0 and P1 → High, P2 → Medium, P3 → Low. The exact level is also a tag (`p0`...`p3`). |
| Players, area | Tags (e.g. `android`, `inst`, `p1`; Testmo stores tags lower-case) |
| Actual Result | Not imported (it's filled in during test runs) |

**Keep the docx format.** The tool reads the layout the `xibo-player-test-*` skills produce:
- a Heading 1 per area (`CODE – Name`)
- a table with a **Test ID** column
- numbered steps (`1. ...`)

Tables under *Revision Notes* or *Assets* are ignored. Don't hand-edit case names in Testmo so they lose the Test ID prefix; the tool would stop recognising those cases.

---

## 8. Troubleshooting

| Message | Fix |
|---|---|
| `Set TESTMO_TOKEN ...` | Run `export TESTMO_TOKEN=...` in this terminal. |
| `Testmo API ... 401` / `403` | The token is wrong or expired, or your Testmo account can't edit this project. |
| `Rename map rejected ...` | One of your `--rename` pairs is wrong. The message says which and why. Nothing was changed. |
| `... would be retired but no state named 'Retired' exists` | Ask the maintainer to set `retired_state` in `config.json`, or run with `--no-retire`. |
| `mapping.name must start with "{test_id}"` | `config.json` was changed wrongly. The case name must begin with the Test ID. |
| `429` / `5xx` retry messages | Testmo is rate-limiting or busy. The tool retries by itself. |

---

## For the maintainer: `config.json`

| Key | Meaning |
|---|---|
| `testmo.base_url`, `testmo.project_id` | Which Testmo instance and project |
| `testmo.template_id`, `testmo.state_id` | Template and state for new cases (`null` = project default) |
| `folders.root`, `folders.parent_id` | Top folder name (and optional parent folder id) |
| `folders.per_area`, `folders.area_name` | One sub-folder per area, and how it's named |
| `mapping` | Which Testmo field gets what (see below) |
| `expected_mode` | `last_step` (default) or `none`, then map `{expected}` to a field yourself |
| `priority_map` | P0–P3 → Priority option ids (from `discover`) |
| `retired_state` | State name or id used for removed tests |
| `renames` | Permanent Test ID renames, `{"OLD": "NEW"}` |
| `state_file` | Where the sync state lives (default `sync-state.json` next to the config) |

In `mapping`, each key is a Testmo field system name (from `discover`). Each value is one of:

- `"@steps"`: the numbered Test Steps, one Testmo step each
- `"@priority"`: P0–P3 through `priority_map`
- `"@estimate"`: the Estimated Execution Time column converted to seconds (Testmo's estimate unit)
- a template built from docx columns: `{test_id} {priority} {scenario} {players} {prerequisite} {estimated_time} {expected} {actual} {area} {area_name} {section}`

In `tags`, `"@players"` makes one tag per player. Delete a key to stop syncing that field. `name` must start with `{test_id}`.

Changing `mapping` makes the next `update` see every case as changed. Do it deliberately and preview with `--dry-run` first.

Cases are sent to Testmo 100 at a time (the API's limit per request). Updates with identical changes (e.g. the same new estimate) share a request; otherwise it's one request per case.
