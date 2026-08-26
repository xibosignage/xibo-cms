# Display Overview — API Endpoint Changes

Summary of the backend API surface added/changed to support the React "Display Overview"
page (`frontend/src/pages/Displays/Overview/`). Covers two new endpoints and two existing
endpoints that gained new optional query parameters.

All endpoints below require a valid OAuth2 bearer token and the `displays.view` feature
permission (enforced by `FeatureAuth` middleware on the route group). None of these
endpoints have an endpoint-specific timeout configured — they run under the standard
PHP/webserver request timeout. The one caching behaviour that can look like a timeout is
called out explicitly under "Next Scheduled Layout" below.

---

## 1. Display Grid — new filter parameters

**`GET /display`** (existing endpoint, unchanged path/response shape)

Two new optional query parameters were added alongside the existing filters (`display`,
`loggedIn`, `licensed`, `mediaInventoryStatus`, etc.):

| Parameter | Type | Required | Description |
|---|---|---|---|
| `needsAttention` | integer (`0` or `1`) | No | Filter by whether the Display needs attention — its media inventory is not fully synced, it is not authorised, or its commercial licence is not "Licensed fully"/"Not applicable" (or is unset). `1` = only matching Displays, `0` = only non-matching. |
| `faults` | integer (`0` or `1`) | No | Filter by whether the Display has at least one active player fault, **excluding** Displays already matched by `needsAttention`. `1` = only matching Displays, `0` = only non-matching. |

Both filters are guarded by `loggedIn = 1` internally — an offline Display is never
double-counted under Needs Attention or Faults; it only ever counts as Offline. This
mirrors the bucket precedence used by the summary endpoint below (Offline → Needs
Attention → Faults → Online).

**Success** — `200 OK`, unchanged JSON array of Display objects (only the filtering
changes, not the shape of each row).

**Errors** — unchanged from the existing endpoint: `401`/`403` if the caller lacks a
valid token or the `displays.view` permission.

**Timeout** — none specific to these filters; they're plain `WHERE`/`AND` predicates
appended to the existing grid query.

---

## 2. Display Overview Summary (new endpoint)

**`GET /display/overview/summary`**

Aggregate health counts (Total/Online/Offline/Needs Attention/Faults) for the Displays
visible to the caller, computed as a single SQL aggregate query
(`DisplayFactory::getSummary()`) — not a fetch-all-then-count loop — scoped by the same
permission logic (`viewPermissionSql`) as the main Display grid.

**Parameters** — none.

**Success response** — `200 OK`

```json
{
  "total": 42,
  "online": 30,
  "offline": 5,
  "needsAttention": 4,
  "faults": 3,
  "offlineTrend": 1,
  "onlineTrend": 2,
  "faultsTrend": 0
}
```

| Field | Type | Description |
|---|---|---|
| `total` | integer | All Displays visible to the caller. |
| `online` | integer | Logged in, fully synced, authorised, licensed, and no active faults. |
| `offline` | integer | Not currently logged in to the CMS. |
| `needsAttention` | integer | Logged in, but not fully synced / not authorised / licence issue. |
| `faults` | integer | Logged in, no needsAttention issue, but has an active player fault. |
| `offlineTrend` | integer | Displays that went offline in the last 24 hours. |
| `onlineTrend` | integer | Displays that came back online in the last 24 hours. |
| `faultsTrend` | integer | Player faults newly reported in the last 24 hours. |

The four buckets (`online`/`offline`/`needsAttention`/`faults`) are mutually exclusive
and sum to `total`. There is deliberately **no** `needsAttentionTrend` — media
sync/licence/authorisation changes aren't timestamped anywhere, so a trend for that
bucket would have to be fabricated.

**Errors** — `401`/`403` if the caller lacks a valid token or the `displays.view`
permission. There is no per-resource lookup here (no path parameter), so there is no
`404` case — an empty/zero result set is still a `200` with all counts at `0`.

**Timeout** — none specific; this is one aggregate query with a `WITH ... AS` CTE, not a
loop over individual Displays.

---

## 3. Next Scheduled Layout (new endpoint)

**`GET /display/{id}/schedule/next`**

Gets the next scheduled Layout for a single Display, within a short look-ahead window
(4 hours). This is an **approximation**: it does not replicate the player-side
priority/shareOfVoice/interrupt resolution that only happens on the player at runtime.
Where several events overlap in the window, the occurrence with the earliest start time
is returned.

**Path parameter**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id` | integer | Yes | The Display ID. |

**Success response** — `200 OK`

Either `null` (nothing scheduled within the look-ahead window):

```json
null
```

...or an object describing the next occurrence:

```json
{
  "layoutId": 17,
  "layoutName": "Welcome Loop",
  "startsAt": "2026-08-26T15:30:00+08:00",
  "status": "downloading"
}
```

| Field | Type | Description |
|---|---|---|
| `layoutId` | integer | The Layout that will play next. |
| `layoutName` | string | The Layout's name. |
| `startsAt` | string (ISO 8601, with UTC offset) | When the occurrence starts, in the CMS's own timezone. Carries an explicit offset (not a naive `Y-m-d H:i:s` string) so the frontend's `Date()` parsing can't silently misinterpret it in the browser's local timezone. |
| `status` | string enum: `ready` \| `downloading` \| `pending` | Derived from the `RequiredFile` record for this Display/Layout pair: `complete == 1` → `ready`, `complete == 0` → `downloading`, no record yet → `pending` (schedule was created before the player's next RequiredFiles poll). **There is no `error` status** — that isn't modelled anywhere in this data, so none is invented. |

**Error responses**

| Status | When | Body shape |
|---|---|---|
| `403` | Caller can't view this Display (`AccessDeniedException`). | `{"error": 403, "message": "...", "help": null}` |
| `404` | `id` doesn't correspond to an existing Display (`NotFoundException` from `DisplayFactory::getById()`), or the resolved Layout has since been deleted. | `{"error": 404, "message": "Not Found", "property": null, "help": null}` |

**Timeout / caching** — the underlying lookup (`ScheduleFactory::getNextForDisplay()`) is
a short-lived cache **keyed per Display + look-ahead window + Display timezone**, with a
**30-second TTL**. This is not a request timeout — the HTTP call itself always completes
immediately — but it means a schedule change made less than 30 seconds ago may not be
reflected yet in the response. The query behind it is a "big join" plus recurrence-tree
walking (`Schedule::getEvents()`), which is why the result is cached at all: it's
noticeable when a grid renders "next scheduled" for many Displays at once.

---

## 4. Player Faults — new filter parameter

**`GET /display/faults`** and **`GET /display/faults/{displayId}`** (existing endpoints,
unchanged path/response shape)

One new optional query parameter:

| Parameter | Type | Required | Description |
|---|---|---|---|
| `activeOnly` | boolean | No | Only return faults which are currently active, excluding any which have already expired (`player_faults.expires IS NULL OR expires >= now`). Defaults to `false` (returns full fault history, matching prior behaviour). |

**Success response** — `200 OK`, unchanged JSON array of PlayerFault objects:

```json
[
  {
    "playerFaultId": 101,
    "code": 5001,
    "reason": "Unable to download required file",
    "incidentDt": "2026-08-25 09:12:00",
    "expires": null
  }
]
```

**Errors** — unchanged from the existing endpoint: `401`/`403` if the caller lacks a
valid token or the `displays.view` permission. No `404` case for the collection route;
`displayId` (path variant) filters rather than looks up a single resource, so an unknown
ID simply returns an empty array.

**Timeout** — none specific; `activeOnly` adds one `AND` clause to the existing query.

---

## Auth/permission summary (applies to all endpoints above)

| Status | Meaning |
|---|---|
| `401` | Missing/invalid OAuth2 bearer token. |
| `403` | Valid token, but the caller lacks the `displays.view` feature permission, or (for `schedule/next`) lacks view permission on the specific Display. |

All error bodies follow the CMS's standard exception envelope:

```json
{
  "error": 403,
  "message": "human readable message",
  "...": "additional fields depend on the exception type (e.g. help, property)"
}
```
