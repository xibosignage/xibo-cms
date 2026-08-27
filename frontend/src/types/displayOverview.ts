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

// Response shape for GET /display/overview/summary — backs the five KPI tiles
// and quick-filter chips on the Display Overview page (Total/Logged In/
// Authorised/Up-to-date/Faults). loggedIn/authorised/upToDate/faults are
// independent, overlapping counts (a display can be all four at once) — see
// DisplayFactory::getSummary().
//
// faultsTrend is a 24-hour count sourced from the real `player_faults` history
// table. There's deliberately no trend for loggedIn/authorised/upToDate: none
// of those states are timestamped anywhere in the schema, so a trend for them
// would have to be fabricated.
export interface DisplayOverviewSummary {
  total: number;
  faults: number;
  loggedIn: number;
  authorised: number;
  upToDate: number;
  faultsTrend: number;
}

// The per-display health classification used for a single display's own
// status badge/dot (card, table row, Manage page) — see
// DisplayStatusConfig.ts's getDisplayStatusBucket(). Not used by the list's
// quick-filter chips, which filter on the concrete loggedIn/authorised/
// mediaInventoryStatus/faults fields directly instead of re-deriving a
// bucket.
export type DisplayOverviewBucket = 'online' | 'needsAttention' | 'offline' | 'faults';
