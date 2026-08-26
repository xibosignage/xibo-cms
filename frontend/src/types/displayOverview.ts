/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

// Response shape for GET /display/overview/summary — backs the four KPI tiles
// on the Display Overview page. `total` is shown as every tile's denominator
// ("Online/Total", "Faults/Total", etc); it is never rendered as its own tile.
//
// offlineTrend/onlineTrend/faultsTrend are 24-hour counts sourced from real
// history tables (`displayevent`, `player_faults`) — see
// DisplayFactory::getSummary(). There's deliberately no needsAttentionTrend:
// media sync/licence/authorisation changes aren't timestamped anywhere in the
// schema, so a trend for that bucket would have to be fabricated.
export interface DisplayOverviewSummary {
  total: number;
  online: number;
  offline: number;
  needsAttention: number;
  faults: number;
  offlineTrend: number;
  onlineTrend: number;
  faultsTrend: number;
}

// The four KPI buckets a display can be filtered/grouped by on the Overview
// page. A display can belong to more than one of these conceptually (e.g. it
// could be both offline and have a stale licence), but the KPI counts and the
// server-side filters treat them as mutually exclusive buckets — Faults never
// double-counts a display already shown under Needs Attention.
export type DisplayOverviewBucket = 'online' | 'needsAttention' | 'offline' | 'faults';

// A display's readiness for its own next-scheduled-item lookup. There is no
// "error" state modeled anywhere in the backend today (requiredfile.complete
// is a plain 0/1, with no stuck-download indicator) — don't add one here
// without a corresponding backend change; a fabricated "error" state would be
// presenting a guess as fact.
export type DisplayNextScheduleStatus = 'ready' | 'downloading' | 'pending';

// Response shape for GET /display/{id}/schedule/next. `null` means nothing is
// scheduled within the lookahead window — a normal, expected state, not an
// error. Picking "next" is an earliest-start-time approximation: overlapping
// priority/shareOfVoice/interrupt schedules are resolved by the player at
// runtime, not here, so this can legitimately disagree with what's actually
// on screen.
export interface DisplayNextSchedule {
  layoutId: number;
  layoutName: string;
  startsAt: string;
  status: DisplayNextScheduleStatus;
}
