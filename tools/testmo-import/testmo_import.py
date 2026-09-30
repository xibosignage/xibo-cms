#!/usr/bin/env python3
"""
Import a Xibo player test suite (.docx) into a Testmo repository.

Commands:
    parse     .docx  -> suite.json            (offline, no API calls)
    discover  Testmo -> discover.json          (templates, fields, states, folders)
    update    docx vs Testmo: edit / move / rename / retire / un-retire existing cases (never creates)
    push      docx vs Testmo: add cases that aren't in Testmo yet (never changes existing ones)
    diff      old.docx vs new.docx, offline report (never touches Testmo)

Cases are matched to Testmo by the Test ID at the start of the case name. Run update before
push so renamed Test IDs are renamed rather than added as new cases.

Stdlib only (Python 3.8+). The API token is read from the TESTMO_TOKEN env var.

Examples (run from tools/testmo-import; config.json is read from there by default):
    python3 testmo_import.py update Suite-v1.4.docx --dry-run
    python3 testmo_import.py update Suite-v1.4.docx --rename SCH-004=SCH-099
    python3 testmo_import.py push   Suite-v1.4.docx --dry-run
    python3 testmo_import.py push   Suite-v1.4.docx --priority P0,P1 --area INST,SCH
    python3 testmo_import.py diff   Suite-v1.3.docx Suite-v1.4.docx
    python3 testmo_import.py discover
"""

import argparse
import fnmatch
import hashlib
import html
import json
import os
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
import zipfile

W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"

# Docx header text -> JSON key. Unknown headers are kept, slugified.
COLUMN_KEYS = {
    "test id": "test_id",
    "priority": "priority",
    "test scenario": "scenario",
    "test name": "scenario",
    "players": "players",
    "pre-requisite": "prerequisite",
    "prerequisite": "prerequisite",
    "test steps": "steps",
    "expected result": "expected",
    "actual result": "actual",
}

# Tables whose preceding heading matches one of these are not test cases.
SKIP_SECTIONS = ("revision notes", "assets")

MAX_PER_REQUEST = 100


# --------------------------------------------------------------------------- #
# parse
# --------------------------------------------------------------------------- #

def _slug(text):
    return re.sub(r"[^a-z0-9]+", "_", text.lower()).strip("_")


def _para_text(p):
    out = []
    for node in p.iter():
        if node.tag == W + "t":
            out.append(node.text or "")
        elif node.tag == W + "tab":
            out.append("\t")
        elif node.tag in (W + "br", W + "cr"):
            out.append("\n")
    return "".join(out)


def _cell_lines(tc):
    lines = []
    for p in tc.iter(W + "p"):
        lines.extend(_para_text(p).split("\n"))
    return [ln.strip() for ln in lines if ln.strip()]


def _para_style(p):
    ps = p.find(f"{W}pPr/{W}pStyle")
    return ps.get(W + "val") if ps is not None else ""


STEP_NUM = re.compile(r"^\s*(\d+)[.)]\s+")


def split_steps(lines):
    """'1. Do x' / '2. Do y' lines -> ['Do x', 'Do y']. Unnumbered lines continue the previous step."""
    steps = []
    for ln in lines:
        m = STEP_NUM.match(ln)
        if m or not steps:
            steps.append(STEP_NUM.sub("", ln, count=1))
        else:
            steps[-1] += "\n" + ln
    return steps


def parse_docx(path):
    with zipfile.ZipFile(path) as z:
        root = ET.fromstring(z.read("word/document.xml"))
    body = root.find(W + "body")

    title, subtitle, section = None, None, None
    cases, seen = [], set()

    for el in body:
        if el.tag == W + "p":
            text = _para_text(el).strip()
            style = _para_style(el)
            if not text:
                continue
            if style == "Title" and title is None:
                title = text
            elif style.startswith("Heading"):
                section = text
            elif title and subtitle is None and section is None:
                subtitle = text
            continue

        if el.tag != W + "tbl" or section is None:
            continue
        if section.lower().startswith(SKIP_SECTIONS):
            continue

        rows = el.findall(W + "tr")
        if not rows:
            continue
        headers = [" ".join(_cell_lines(tc)) for tc in rows[0].findall(W + "tc")]
        keys = [COLUMN_KEYS.get(h.lower(), _slug(h)) for h in headers]
        if "test_id" not in keys:
            continue

        # "INST – Installation, Upgrade & Uninstall" -> area INST
        parts = re.split(r"\s+[–—-]\s+", section, maxsplit=1)
        area_code = parts[0].strip()
        area_name = parts[1].strip() if len(parts) > 1 else parts[0].strip()

        for tr in rows[1:]:
            cells = [_cell_lines(tc) for tc in tr.findall(W + "tc")]
            raw = {k: cells[i] if i < len(cells) else [] for i, k in enumerate(keys)}
            test_id = " ".join(raw.get("test_id", [])).strip()
            if not test_id:
                continue
            if test_id in seen:
                print(f"warning: duplicate Test ID {test_id} in docx", file=sys.stderr)
            seen.add(test_id)

            case = {
                "test_id": test_id,
                "area": area_code,
                "area_name": area_name,
                "section": section,
            }
            for k, lines in raw.items():
                if k == "test_id":
                    continue
                if k == "steps":
                    case["steps"] = split_steps(lines)
                elif k == "players":
                    joined = " ".join(lines)
                    case["players"] = [p.strip() for p in joined.split(",") if p.strip()]
                else:
                    case[k] = "\n".join(lines)
            cases.append(case)

    return {
        "source": os.path.basename(path),
        "title": title,
        "subtitle": subtitle,
        "count": len(cases),
        "cases": cases,
    }


def cmd_parse(args):
    suite = parse_docx(args.docx)
    out = args.output or os.path.splitext(os.path.basename(args.docx))[0] + ".json"
    with open(out, "w", encoding="utf-8") as f:
        json.dump(suite, f, indent=2, ensure_ascii=False)
    areas = {}
    for c in suite["cases"]:
        areas[c["area"]] = areas.get(c["area"], 0) + 1
    prios = {}
    for c in suite["cases"]:
        prios[c.get("priority", "?")] = prios.get(c.get("priority", "?"), 0) + 1
    print(f"Parsed {suite['count']} test cases from {suite['source']} -> {out}")
    print("  areas:     " + ", ".join(f"{a}={n}" for a, n in areas.items()))
    print("  priority:  " + ", ".join(f"{p}={n}" for p, n in sorted(prios.items())))
    no_steps = [c["test_id"] for c in suite["cases"] if not c.get("steps")]
    if no_steps:
        print(f"  warning: {len(no_steps)} cases have no steps: {', '.join(no_steps[:10])}")


# --------------------------------------------------------------------------- #
# Testmo API
# --------------------------------------------------------------------------- #

class Testmo:
    def __init__(self, base_url, token, verbose=False):
        self.base = base_url.rstrip("/") + "/api/v1"
        self.token = token
        self.verbose = verbose

    def request(self, method, path, body=None, query=None):
        url = self.base + path
        if query:
            url += "?" + urllib.parse.urlencode({k: v for k, v in query.items() if v is not None})
        data = json.dumps(body).encode() if body is not None else None
        for attempt in range(6):
            req = urllib.request.Request(url, data=data, method=method, headers={
                "Authorization": f"Bearer {self.token}",
                "Content-Type": "application/json",
                "Accept": "application/json",
            })
            try:
                with urllib.request.urlopen(req, timeout=60) as res:
                    raw = res.read()
                    return json.loads(raw) if raw else {}
            except urllib.error.HTTPError as e:
                text = e.read().decode(errors="replace")
                if e.code == 429 or e.code >= 500:
                    wait = int(e.headers.get("Retry-After") or 0) or 2 ** attempt
                    print(f"  {e.code} from Testmo, retrying in {wait}s", file=sys.stderr)
                    time.sleep(wait)
                    continue
                raise SystemExit(f"Testmo API {method} {path} failed: {e.code} {e.reason}\n{text}")
        raise SystemExit(f"Testmo API {method} {path} failed after retries")

    def paginate(self, path, query=None):
        q = dict(query or {}, per_page=100, page=1)
        while True:
            res = self.request("GET", path, query=q)
            yield from res.get("result", [])
            nxt = res.get("next_page")
            if not nxt:
                return
            q["page"] = nxt


def client_from_config(cfg, verbose=False):
    token = os.environ.get("TESTMO_TOKEN")
    if not token:
        raise SystemExit("Set TESTMO_TOKEN to a Testmo API token (Testmo > profile > API access).")
    base = os.environ.get("TESTMO_URL") or cfg["testmo"]["base_url"]
    return Testmo(base, token, verbose)


def load_config(path):
    with open(path, encoding="utf-8") as f:
        return json.load(f)


# --------------------------------------------------------------------------- #
# discover
# --------------------------------------------------------------------------- #

def cmd_discover(args):
    cfg = load_config(args.config)
    api = client_from_config(cfg)
    pid = cfg["testmo"]["project_id"]

    out = {
        "project": api.request("GET", f"/projects/{pid}").get("result"),
        "templates": api.request("GET", f"/projects/{pid}/templates").get("result", []),
        "fields": api.request("GET", f"/projects/{pid}/fields").get("result", []),
        "states": api.request("GET", f"/projects/{pid}/states").get("result", []),
        "folders": list(api.paginate(f"/projects/{pid}/folders")),
    }
    with open(args.output, "w", encoding="utf-8") as f:
        json.dump(out, f, indent=2, ensure_ascii=False)

    print(f"Project: {out['project'].get('name')} (id {pid})\n")
    print("Templates (use id as testmo.template_id):")
    for t in out["templates"]:
        print(f"  {t.get('id'):>5}  {t.get('name')}{'  [default]' if t.get('is_default') else ''}")
        print(f"         fields: {', '.join(f.get('name', '') for f in t.get('fields', []))}")
    print("\nCase fields (use the mapping key in config.json 'mapping'):")
    for fd in out["fields"]:
        if fd.get("entity") not in (None, "case", "cases", "repository_case"):
            continue
        key = fd.get("column_name") or f"custom_{fd.get('system_name')}"
        print(f"  {key:<28} {fd.get('type', ''):<10} {fd.get('name')}")
        for o in fd.get("options") or []:
            for v in (o.get("values") or []) if isinstance(o, dict) else []:
                if fd.get("type") == "dropdown":
                    print(f"      value id {v.get('id')}: {v.get('name')}  (use in priority_map)")
    print("\nCase states (use id as testmo.state_id, name as retired_state):")
    for s in out["states"]:
        if s.get("entity") not in (None, "case", "repository_case"):
            continue
        print(f"  {s.get('id'):>5}  {s.get('name')}{'  [default]' if s.get('is_default') else ''}")
    print(f"\n{len(out['folders'])} existing folders. Full raw output saved to {args.output}")


# --------------------------------------------------------------------------- #
# payload building
# --------------------------------------------------------------------------- #

def esc(text):
    return html.escape(text or "", quote=False).replace("\n", "<br>")


def to_html(text):
    return f"<p>{esc(text)}</p>" if text else ""


def render_template(tpl, case, as_html):
    """Fill {placeholder}s from the case. Lists are comma-joined; values are HTML-escaped in HTML fields."""
    def repl(m):
        key = m.group(1)
        val = case.get(key, "")
        if isinstance(val, list):
            val = ", ".join(val)
        return esc(val) if as_html else str(val)
    return re.sub(r"\{(\w+)\}", repl, tpl)


def build_steps(case, cfg):
    mode = cfg.get("expected_mode", "last_step")
    steps = [{"text1": to_html(s)} for s in case.get("steps", [])]
    if not steps:
        steps = [{"text1": to_html(case.get("scenario", ""))}]
    if mode == "last_step" and case.get("expected"):
        steps[-1]["text3"] = to_html(case["expected"])
    return steps


def build_tags(spec, case):
    tags = []
    for item in spec if isinstance(spec, list) else [spec]:
        if item == "@players":
            tags.extend(case.get("players", []))
        else:
            tags.append(render_template(item, case, as_html=False))
    # Testmo tags: no spaces, deduplicated, order kept.
    clean = []
    for t in tags:
        t = re.sub(r"\s+", "-", t.strip())
        if t and t not in clean:
            clean.append(t)
    return clean


def build_case(case, cfg, folder_id):
    mapping = cfg["mapping"]
    tm = cfg["testmo"]
    payload = {"folder_id": folder_id}
    if tm.get("template_id"):
        payload["template_id"] = tm["template_id"]
    if tm.get("state_id"):
        payload["state_id"] = tm["state_id"]

    for field, spec in mapping.items():
        if spec is None or field.startswith("_"):
            continue
        if spec == "@steps":
            payload[field] = build_steps(case, cfg)
        elif spec == "@priority":
            pmap = cfg.get("priority_map") or {}
            pid = pmap.get(case.get("priority", ""))
            if pid is not None:
                payload[field] = pid
        elif field == "tags":
            tags = build_tags(spec, case)
            if tags:
                payload["tags"] = tags
        elif field == "name":
            payload["name"] = render_template(spec, case, as_html=False)[:255]
        elif field == "estimate":
            payload["estimate"] = spec
        else:
            value = render_template(spec, case, as_html=True)
            # Drop HTML blocks whose placeholders were all empty, e.g. "<p><strong>X:</strong> </p>".
            value = re.sub(r"<p><strong>[^<]*</strong>\s*</p>", "", value)
            if re.sub(r"<[^>]+>", "", value).strip():
                payload[field] = value if value.lstrip().startswith("<") else f"<p>{value}</p>"
    return payload


# Fields that are set on create but never compared or updated.
NOT_SYNCED = ("folder_id", "template_id", "state_id")


def fingerprint(case, cfg):
    """{field: hash} of everything update manages. 'folder' stands for the target area folder."""
    payload = build_case(case, cfg, None)
    fp = {k: _hash(v) for k, v in payload.items() if k not in NOT_SYNCED}
    fp["folder"] = _hash(case["section"])
    return fp


def _hash(value):
    return hashlib.sha1(json.dumps(value, sort_keys=True, ensure_ascii=False).encode()).hexdigest()[:16]


def clear_value(field):
    if field in ("tags",) or field.endswith("_steps"):
        return []
    if field.endswith("_priority") or field == "estimate":
        return None
    return ""


# --------------------------------------------------------------------------- #
# selection, Test IDs, state
# --------------------------------------------------------------------------- #

def _csv(v):
    return [x.strip() for x in v.split(",") if x.strip()] if v else None


def has_filters(args):
    return any(getattr(args, k, None) for k in ("only", "area", "priority", "player", "limit"))


def select_cases(cases, args):
    ids, areas, prios, players = _csv(args.only), _csv(args.area), _csv(args.priority), _csv(args.player)
    out = []
    for c in cases:
        if ids and not any(fnmatch.fnmatch(c["test_id"], pat) for pat in ids):
            continue
        if areas and c["area"] not in areas:
            continue
        if prios and c.get("priority") not in prios:
            continue
        if players and not (set(players) & set(c.get("players", []))):
            continue
        out.append(c)
    if args.limit:
        out = out[: args.limit]
    return out


def matches_any(test_id, patterns):
    return any(fnmatch.fnmatch(test_id, p) for p in patterns or [])


def id_regex(cfg):
    return re.compile(cfg.get("id_pattern", r"^\s*([A-Z][A-Z0-9]*(?:-[A-Z0-9]+)*-\d+)\b"))


def check_name_template(cfg):
    if not cfg["mapping"].get("name", "").lstrip().startswith("{test_id}"):
        raise SystemExit('mapping.name must start with "{test_id}": cases are matched to the docx by that prefix.')


def name_rest(name, rx):
    """Case name without its Test ID prefix, normalised (used to spot renamed IDs)."""
    return re.sub(r"\s+", " ", rx.sub("", name, count=1)).strip(" -–—:").casefold()


def load_suite(path):
    if path.lower().endswith(".docx"):
        return parse_docx(path)
    with open(path, encoding="utf-8") as f:
        return json.load(f)


class State:
    """Local record of what was last written to Testmo, per Test ID (sync-state.json)."""

    def __init__(self, cfg, config_path):
        name = cfg.get("state_file", "sync-state.json")
        self.path = name if os.path.isabs(name) else os.path.join(os.path.dirname(os.path.abspath(config_path)), name)
        self.project_id = cfg["testmo"]["project_id"]
        self.cases = {}
        if os.path.exists(self.path):
            with open(self.path, encoding="utf-8") as f:
                data = json.load(f)
            if data.get("project_id") == self.project_id:
                self.cases = data.get("cases", {})

    def get(self, test_id):
        return self.cases.get(test_id)

    def record(self, test_id, case_id, fields, testmo_ts):
        self.cases[test_id] = {"case_id": case_id, "fields": fields, "testmo_updated": testmo_ts,
                               "synced_at": time.strftime("%Y-%m-%dT%H:%M:%S")}

    def save(self):
        with open(self.path, "w", encoding="utf-8") as f:
            json.dump({"project_id": self.project_id, "cases": self.cases}, f, indent=2, ensure_ascii=False)


def testmo_ts(tc):
    return tc.get("updated_at") or tc.get("created_at")


# --------------------------------------------------------------------------- #
# Testmo scope: the import's folder tree and the cases in it
# --------------------------------------------------------------------------- #

class Scope:
    def __init__(self, api, pid, cfg):
        self.api, self.pid, self.cfg = api, pid, cfg
        self.rx = id_regex(cfg)
        self.folders = list(api.paginate(f"/projects/{pid}/folders")) if api else []
        fcfg = cfg.get("folders", {})
        self.parent = fcfg.get("parent_id")
        self.root = self.find_folder(fcfg["root"], self.parent) if fcfg.get("root") else self.parent
        self.load_cases()

    def find_folder(self, name, parent_id):
        for f in self.folders:
            if f.get("name") == name and _same_id(f.get("parent_id"), parent_id):
                return f["id"]
        return None

    def folder_ids(self):
        if self.root is None:
            return None
        ids, frontier = {self.root}, [self.root]
        while frontier:
            cur = frontier.pop()
            for f in self.folders:
                if _same_id(f.get("parent_id"), cur) and f["id"] not in ids:
                    ids.add(f["id"])
                    frontier.append(f["id"])
        return ids

    def load_cases(self):
        self.cases, self.by_tid, self.untracked = [], {}, 0
        if not self.api:
            return
        fcfg = self.cfg.get("folders", {})
        if self.root is None:
            if fcfg.get("root"):
                return  # root folder not created yet: nothing imported so far
            self.cases = list(self.api.paginate(f"/projects/{self.pid}/cases", {"expands": "tags"}))
        else:
            for fid in self.folder_ids():
                self.cases.extend(self.api.paginate(f"/projects/{self.pid}/cases", {"folder_id": fid, "expands": "tags"}))
        # With expands=tags each case lists tag ids; turn them into names for comparison.
        if any("tags" in tc for tc in self.cases):
            names = {t["id"]: t["name"] for t in self.api.paginate(f"/projects/{self.pid}/tags")}
            for tc in self.cases:
                if isinstance(tc.get("tags"), list):
                    tc["tags"] = [names.get(t, t) if not isinstance(t, dict) else t.get("name") for t in tc["tags"]]
        for tc in self.cases:
            m = self.rx.match(tc.get("name", ""))
            if m:
                self.by_tid.setdefault(m.group(1), []).append(tc)
            else:
                self.untracked += 1

    def lookup(self, test_id):
        return getattr(self, "by_tid", {}).get(test_id, [])

    def ensure_area_folders(self, cases, dry_run):
        """Return {section: folder_id}; creates the root folder and one sub-folder per area as needed."""
        fcfg = self.cfg.get("folders", {})

        def create(name, parent_id):
            body = {"name": name}
            if parent_id:
                body["parent_id"] = parent_id
            if dry_run:
                fake = f"<new:{name}>"
                print(f"  [dry-run] would create folder '{name}'")
            else:
                res = self.api.request("POST", f"/projects/{self.pid}/folders", {"folders": [body]})
                fake = res["result"][0]["id"]
                print(f"  created folder '{name}' (id {fake})")
            self.folders.append({"id": fake, "name": name, "parent_id": parent_id})
            return fake

        if fcfg.get("root") and self.root is None:
            self.root = create(fcfg["root"], self.parent)

        result = {}
        for c in cases:
            if c["section"] in result:
                continue
            if fcfg.get("per_area", True):
                name = render_template(fcfg.get("area_name", "{section}"), c, as_html=False)
                result[c["section"]] = self.find_folder(name, self.root) or create(name, self.root)
            else:
                result[c["section"]] = self.root
        return result


def _same_id(a, b):
    return (str(a) if a not in (None, 0, "") else None) == (str(b) if b not in (None, 0, "") else None)


def suspected_renames(suite_cases, scope, cfg, renames):
    """[(old_tid in Testmo, new_tid in docx)] where the name matches but the Test ID differs."""
    rx = id_regex(cfg)
    docx_ids = {c["test_id"] for c in suite_cases}
    handled_old, handled_new = set(renames), set(renames.values())
    orphans = {}
    for tid, tcs in getattr(scope, "by_tid", {}).items():
        if tid not in docx_ids and tid not in handled_old:
            orphans.setdefault(name_rest(tcs[0]["name"], rx), tid)
    pairs = []
    for c in suite_cases:
        if c["test_id"] in handled_new or scope.lookup(c["test_id"]):
            continue
        rest = name_rest(render_template(cfg["mapping"]["name"], c, as_html=False), rx)
        if rest in orphans:
            pairs.append((orphans[rest], c["test_id"]))
    return pairs


def load_renames(cfg, args):
    renames = dict(cfg.get("renames") or {})
    for item in getattr(args, "rename", None) or []:
        old, sep, new = item.partition("=")
        if not sep or not old.strip() or not new.strip():
            raise SystemExit(f"--rename expects OLD=NEW, got '{item}'")
        renames[old.strip()] = new.strip()
    return renames


def refresh_state(api, pid, cfg, state, touched):
    """Re-read Testmo so the state file holds Testmo's own updated_at for every case we just wrote."""
    scope = Scope(api, pid, cfg)
    by_id = {str(tc["id"]): tc for tc in scope.cases}
    for tid, (case_id, fields) in touched.items():
        tc = by_id.get(str(case_id))
        state.record(tid, case_id, fields, testmo_ts(tc) if tc else None)
    state.save()


# --------------------------------------------------------------------------- #
# push: add new cases only
# --------------------------------------------------------------------------- #

def cmd_push(args):
    cfg = load_config(args.config)
    check_name_template(cfg)
    suite = load_suite(args.suite)
    cases = select_cases(suite["cases"], args)
    if not cases:
        raise SystemExit("No cases match the selection.")
    print(f"Selected {len(cases)} of {len(suite['cases'])} cases.")

    pid = cfg["testmo"]["project_id"]
    api = None
    if not args.dry_run or os.environ.get("TESTMO_TOKEN"):
        api = client_from_config(cfg)
    else:
        print("(dry-run without TESTMO_TOKEN: nothing is looked up in Testmo, so no duplicate checks)")
    scope = Scope(api, pid, cfg)
    state = State(cfg, args.config)
    renames = load_renames(cfg, args)
    suspects = {new: old for old, new in suspected_renames(suite["cases"], scope, cfg, renames)}

    to_add, skipped = [], []
    for c in cases:
        tid = c["test_id"]
        if scope.lookup(tid):
            skipped.append((tid, "already in Testmo (use update to change it)"))
        elif tid in renames.values():
            skipped.append((tid, "rename target, handled by update"))
        elif state.get(tid) and api and not args.readd:
            skipped.append((tid, f"imported before as case {state.get(tid)['case_id']} and since deleted in Testmo (--readd to add again)"))
        elif tid in suspects and not args.add_suspected:
            skipped.append((tid, f"looks like a rename of {suspects[tid]} (add a rename {suspects[tid]}={tid}, or --add-suspected)"))
        else:
            to_add.append(c)

    for tid, why in skipped:
        print(f"  SKIP {tid}: {why}")
    if not to_add:
        print("Nothing to add.")
        return

    folder_map = scope.ensure_area_folders(to_add, args.dry_run)
    payloads = [(c, build_case(c, cfg, folder_map[c["section"]])) for c in to_add]

    if args.dry_run:
        out = args.output or "payload.json"
        batches = [[p for _, p in payloads[i:i + MAX_PER_REQUEST]] for i in range(0, len(payloads), MAX_PER_REQUEST)]
        with open(out, "w", encoding="utf-8") as f:
            json.dump([{"cases": b} for b in batches], f, indent=2, ensure_ascii=False)
        print(f"[dry-run] would add {len(payloads)} cases in {len(batches)} request(s), {len(skipped)} skipped -> {out}")
        print("\nFirst case payload:")
        print(json.dumps(payloads[0][1], indent=2, ensure_ascii=False))
        return

    created, touched = [], {}
    for i in range(0, len(payloads), MAX_PER_REQUEST):
        batch = payloads[i:i + MAX_PER_REQUEST]
        res = api.request("POST", f"/projects/{pid}/cases", {"cases": [p for _, p in batch]})
        result = res.get("result", [])
        if len(result) != len(batch):
            print(f"warning: sent {len(batch)} cases, Testmo returned {len(result)}", file=sys.stderr)
        for (c, _), r in zip(batch, result):
            created.append({"test_id": c["test_id"], "case_id": r.get("id"), "key": r.get("key"), "name": r.get("name")})
            touched[c["test_id"]] = (r.get("id"), fingerprint(c, cfg))
        print(f"  created batch {i // MAX_PER_REQUEST + 1}: {len(result)} cases")

    refresh_state(api, pid, cfg, state, touched)
    report = args.output or "import-result.json"
    with open(report, "w", encoding="utf-8") as f:
        json.dump({"created": created, "skipped": [{"test_id": t, "reason": r} for t, r in skipped]},
                  f, indent=2, ensure_ascii=False)
    print(f"Done: {len(created)} added, {len(skipped)} skipped. Mapping saved to {report}")


# --------------------------------------------------------------------------- #
# update: change existing cases only (edit, move, rename, retire, revive)
# --------------------------------------------------------------------------- #

def _norm_text(v):
    v = re.sub(r"<br\s*/?>|</p>", "\n", str(v or ""), flags=re.I)
    v = html.unescape(re.sub(r"<[^>]+>", "", v))
    return re.sub(r"\s+", " ", v).strip()


def _norm_field(field, value):
    """Comparable form of a field value, whether it came from our payload or from Testmo. None = can't compare."""
    if field == "tags":
        if not isinstance(value, list):
            return None
        # Testmo stores tags lower-cased.
        return sorted(str(t.get("name") if isinstance(t, dict) else t).casefold() for t in value)
    if isinstance(value, list):  # steps
        return [(_norm_text(s.get("text1")), _norm_text(s.get("text3"))) for s in value if isinstance(s, dict)]
    if isinstance(value, dict) and "id" in value:  # dropdowns come back as {"id": 1, "name": "High"}
        return value["id"]
    if isinstance(value, (int, float)) or value is None:
        return value
    return _norm_text(value)


def diff_against_testmo(payload, tc, target_folder):
    """Changed fields when there's no baseline: compare our payload with what Testmo holds now."""
    changed, unknown = [], []
    for field, value in payload.items():
        if field in NOT_SYNCED:
            continue
        if field not in tc:
            unknown.append(field)
            continue
        theirs = _norm_field(field, tc[field])
        if theirs is None:
            unknown.append(field)
        elif _norm_field(field, value) != theirs:
            changed.append(field)
    if not _same_id(tc.get("folder_id"), target_folder):
        changed.append("folder")
    return changed, unknown


def resolve_state_ids(api, pid, cfg):
    states = [s for s in api.request("GET", f"/projects/{pid}/states").get("result", [])
              if s.get("entity") in (None, "case", "repository_case")]
    want = cfg.get("retired_state", "Retired")
    retired = next((s["id"] for s in states if str(s.get("id")) == str(want)
                    or str(s.get("name", "")).casefold() == str(want).casefold()), None)
    active = cfg["testmo"].get("state_id") or next((s["id"] for s in states if s.get("is_default")), None)
    return retired, active


def cmd_update(args):
    cfg = load_config(args.config)
    check_name_template(cfg)
    suite = load_suite(args.suite)
    cases = select_cases(suite["cases"], args)
    full_run = not has_filters(args)
    pid = cfg["testmo"]["project_id"]
    api = client_from_config(cfg)
    scope = Scope(api, pid, cfg)
    state = State(cfg, args.config)
    renames = load_renames(cfg, args)
    override = _csv(args.override) or []
    retired_id, active_id = resolve_state_ids(api, pid, cfg)

    # Renames are validated up front; any problem aborts before anything is written.
    # Entries already applied (old gone, new present) are ignored so the map can stay in config.
    renames = {o: n for o, n in renames.items() if scope.lookup(o) or not scope.lookup(n)}
    docx_ids = {c["test_id"] for c in suite["cases"]}
    errors = []
    for old, new in renames.items():
        if len(scope.lookup(old)) != 1:
            errors.append(f"{old}={new}: {old} is {'not in' if not scope.lookup(old) else 'duplicated in'} Testmo")
        if scope.lookup(new):
            errors.append(f"{old}={new}: {new} already exists in Testmo")
        if new not in docx_ids:
            errors.append(f"{old}={new}: {new} is not in the docx")
    if errors:
        raise SystemExit("Rename map rejected, nothing was changed:\n  " + "\n  ".join(errors))
    new_to_old = {new: old for old, new in renames.items()}

    plan = {k: [] for k in ("UPDATE", "RENAME", "REVIVE", "CONFLICT", "EDITED_IN_TESTMO",
                            "UNCHANGED", "ADOPT", "NOT_IN_TESTMO", "DUPLICATE", "RETIRE", "HOLD")}
    updates = []   # (case, testmo_case, changed_fields, state_key_to_drop)
    adopted = {}

    for c in cases:
        tid = c["test_id"]
        old = new_to_old.get(tid)
        matches = scope.lookup(old or tid)
        if not matches:
            plan["NOT_IN_TESTMO"].append(tid)
            continue
        if len(matches) > 1:
            plan["DUPLICATE"].append(f"{tid} (case ids {', '.join(str(m['id']) for m in matches)})")
            continue
        tc = matches[0]
        fp = fingerprint(c, cfg)
        payload = build_case(c, cfg, None)
        st = state.get(old or tid)
        if st and str(st["case_id"]) != str(tc["id"]):
            st = None  # state points at another case (deleted and re-added): treat as no baseline

        fcfg = cfg.get("folders", {})
        section_folder = scope.find_folder(render_template(fcfg.get("area_name", "{section}"), c, False), scope.root) \
            if fcfg.get("per_area", True) else scope.root
        if st:
            prev = st["fields"]
            changed = sorted(k for k in set(fp) | set(prev) if fp.get(k) != prev.get(k))
            edited = testmo_ts(tc) != st.get("testmo_updated")
            if changed and edited:
                # A shared state file can be behind: a teammate may already have synced this change.
                # If Testmo already holds exactly what the docx says, just refresh the baseline.
                live, unknown = diff_against_testmo(payload, tc, section_folder)
                if not live and not (set(changed) & set(unknown)):
                    changed = []
                    st = None
        else:
            changed, _unknown = diff_against_testmo(payload, tc, section_folder)
            edited = None  # unknown

        revive = retired_id is not None and _same_id(tc.get("state_id"), retired_id)
        label = f"{tid}" + (f" (was {old})" if old else "")

        if not changed and not revive:
            if st is None:
                plan["ADOPT"].append(tid)
                adopted[tid] = (tc["id"], fp)
            elif edited:
                plan["EDITED_IN_TESTMO"].append(tid)
            else:
                plan["UNCHANGED"].append(tid)
            continue

        if changed and edited is not False and not (args.override_all or matches_any(tid, override)):
            why = "edited in Testmo since last sync" if edited else "no sync baseline and it differs from Testmo"
            plan["CONFLICT"].append(f"{label}: {why}; fields {', '.join(changed)} (--override {tid} to apply)")
            continue

        bucket = "RENAME" if old else ("REVIVE" if revive and not changed else "UPDATE")
        plan[bucket].append(f"{label}: {', '.join(changed + (['state'] if revive else []))}")
        updates.append((c, tc, changed, revive, old))

    retire = []
    if full_run and not args.no_retire:
        suspects = {old: new for old, new in suspected_renames(suite["cases"], scope, cfg, renames)}
        for tid, tcs in sorted(scope.by_tid.items()):
            if tid in docx_ids or tid in renames or len(tcs) > 1:
                continue
            tc = tcs[0]
            if retired_id is not None and _same_id(tc.get("state_id"), retired_id):
                continue
            if tid in suspects:
                plan["HOLD"].append(f"{tid}: looks renamed to {suspects[tid]}; add --rename {tid}={suspects[tid]} "
                                    f"(or --retire-suspected)")
                if not args.retire_suspected:
                    continue
            plan["RETIRE"].append(tid)
            retire.append((tid, tc))
        if retire and retired_id is None:
            raise SystemExit(f"{len(retire)} cases would be retired but no state named "
                             f"'{cfg.get('retired_state', 'Retired')}' exists in the project. Set 'retired_state' "
                             f"in config.json to one listed by discover, or pass --no-retire.")
    elif not full_run:
        print("(case filters used: retiring removed cases is skipped; run without filters to retire)")

    print_plan(plan, scope)
    report = args.output or "update-report.json"
    with open(report, "w", encoding="utf-8") as f:
        json.dump({k: v for k, v in plan.items() if v}, f, indent=2, ensure_ascii=False)

    if args.dry_run:
        print(f"\n[dry-run] nothing changed. Report saved to {report}")
        return

    moved = [c for c, _, ch, _, _ in updates if "folder" in ch]
    folder_map = scope.ensure_area_folders(moved, dry_run=False) if moved else {}
    touched = dict(adopted)
    for c, tc, changed, revive, old in updates:
        payload = build_case(c, cfg, None)
        body = {"ids": [tc["id"]]}
        for field in changed:
            if field == "folder":
                body["folder_id"] = folder_map[c["section"]]
            else:
                body[field] = payload.get(field, clear_value(field))
        if revive:
            if active_id is None:
                print(f"  warning: {c['test_id']} is retired but no active state is known "
                      f"(set testmo.state_id); left retired", file=sys.stderr)
            else:
                body["state_id"] = active_id
        api.request("PATCH", f"/projects/{pid}/cases", body)
        if old:
            state.cases.pop(old, None)
        touched[c["test_id"]] = (tc["id"], fingerprint(c, cfg))
    if updates:
        print(f"  updated {len(updates)} cases")

    for i in range(0, len(retire), MAX_PER_REQUEST):
        chunk = retire[i:i + MAX_PER_REQUEST]
        api.request("PATCH", f"/projects/{pid}/cases", {"ids": [tc["id"] for _, tc in chunk], "state_id": retired_id})
    if retire:
        print(f"  retired {len(retire)} cases")
        for tid, tc in retire:
            prev = state.get(tid)
            touched[tid] = (tc["id"], prev["fields"] if prev else {})

    refresh_state(api, pid, cfg, state, touched)
    print(f"Done. Report saved to {report}; state saved to {state.path}")


def print_plan(plan, scope):
    labels = {
        "UPDATE": "Update", "RENAME": "Rename Test ID", "REVIVE": "Un-retire (back in docx)",
        "CONFLICT": "Skipped: conflict", "EDITED_IN_TESTMO": "Edited in Testmo, docx unchanged (left alone)",
        "UNCHANGED": "Unchanged", "ADOPT": "Matches Testmo, baseline recorded",
        "NOT_IN_TESTMO": "Not in Testmo (add them with push)", "DUPLICATE": "Skipped: Test ID used by several cases",
        "RETIRE": "Retire (removed from docx)", "HOLD": "Not retired: possible rename",
    }
    quiet = ("UNCHANGED", "ADOPT")
    for key, items in plan.items():
        if not items:
            continue
        print(f"\n{labels[key]}: {len(items)}")
        if key in quiet:
            continue
        if key == "NOT_IN_TESTMO" and len(items) > 10:
            print(f"  {', '.join(items[:10])}, ... (full list in the report file)")
            continue
        for it in items:
            print(f"  {it}")
    if getattr(scope, "untracked", 0):
        print(f"\n{scope.untracked} cases in the folders have no Test ID prefix and were ignored.")


# --------------------------------------------------------------------------- #
# diff: offline comparison of two suite versions (never touches Testmo)
# --------------------------------------------------------------------------- #

DIFF_FIELDS = ("scenario", "priority", "players", "prerequisite", "steps", "expected", "section")


def cmd_diff(args):
    old = {c["test_id"]: c for c in load_suite(args.old)["cases"]}
    new = {c["test_id"]: c for c in load_suite(args.new)["cases"]}
    added = [t for t in new if t not in old]
    removed = [t for t in old if t not in new]
    changed = []
    for t in new:
        if t in old:
            fields = [f for f in DIFF_FIELDS if old[t].get(f) != new[t].get(f)]
            if fields:
                changed.append((t, fields))
    by_scenario = {old[t].get("scenario", "").casefold(): t for t in removed}
    renames = [(by_scenario[new[t].get("scenario", "").casefold()], t) for t in added
               if new[t].get("scenario", "").casefold() in by_scenario]

    print(f"{args.old} -> {args.new}: {len(old)} -> {len(new)} cases\n")
    print(f"Added ({len(added)}): {', '.join(added) or '-'}")
    print(f"Removed ({len(removed)}): {', '.join(removed) or '-'}")
    print(f"Possible renames ({len(renames)}): {', '.join(f'{a}={b}' for a, b in renames) or '-'}")
    print(f"Changed ({len(changed)}):")
    for t, fields in changed:
        print(f"  {t}: {', '.join(fields)}")
        if args.verbose:
            for f in fields:
                print(f"      - {old[t].get(f)}\n      + {new[t].get(f)}")


# --------------------------------------------------------------------------- #

def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = ap.add_subparsers(dest="cmd", required=True)

    p = sub.add_parser("parse", help="Convert the .docx test suite into structured JSON")
    p.add_argument("docx")
    p.add_argument("-o", "--output")
    p.set_defaults(func=cmd_parse)

    d = sub.add_parser("discover", help="List the project's templates, fields, states and folders")
    d.add_argument("-c", "--config", default="config.json")
    d.add_argument("-o", "--output", default="discover.json")
    d.set_defaults(func=cmd_discover)

    def selection(sp):
        sp.add_argument("suite", help="suite .docx or the JSON from parse")
        sp.add_argument("-c", "--config", default="config.json")
        sp.add_argument("-o", "--output", help="payload / result / report file")
        sp.add_argument("--dry-run", action="store_true", help="Show what would happen; change nothing")
        sp.add_argument("--only", help="Comma-separated Test IDs, wildcards allowed (INST-001,SCH-*)")
        sp.add_argument("--area", help="Comma-separated area codes (INST,SCH)")
        sp.add_argument("--priority", help="Comma-separated priorities (P0,P1)")
        sp.add_argument("--player", help="Comma-separated players; keeps cases listing any of them")
        sp.add_argument("--limit", type=int, help="Only the first N selected cases (handy for a trial run)")
        sp.add_argument("--rename", action="append", metavar="OLD=NEW", help="Test ID rename (repeatable)")

    u = sub.add_parser("push", help="ADD new cases to Testmo (never changes existing cases)")
    selection(u)
    u.add_argument("--add-suspected", action="store_true", help="Also add cases that look like renamed IDs")
    u.add_argument("--readd", action="store_true", help="Re-add cases that were imported before and deleted in Testmo")
    u.set_defaults(func=cmd_push)

    up = sub.add_parser("update", help="UPDATE existing cases from the docx (never creates cases)")
    selection(up)
    up.add_argument("--override", help="Apply docx changes even when the case was edited in Testmo (IDs, wildcards)")
    up.add_argument("--override-all", action="store_true", help="--override for every case")
    up.add_argument("--no-retire", action="store_true", help="Don't retire cases that were removed from the docx")
    up.add_argument("--retire-suspected", action="store_true", help="Also retire cases that look renamed")
    up.set_defaults(func=cmd_update)

    df = sub.add_parser("diff", help="Offline: compare two suite versions (.docx or JSON); never touches Testmo")
    df.add_argument("old")
    df.add_argument("new")
    df.add_argument("-v", "--verbose", action="store_true", help="Show old/new values")
    df.set_defaults(func=cmd_diff)

    args = ap.parse_args()
    args.func(args)


if __name__ == "__main__":
    main()
