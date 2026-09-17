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

import { DateTime } from 'luxon';

import type {
  AgendaCampaign,
  AgendaDisplayGroup,
  AgendaLayout,
  AgendaScheduleEvent,
  FetchAgendaEventsResponse,
} from '@/services/eventApi';

// -----------------------------------------------------------------------------
// The day the agenda is opened for. Fixed so the date strings the modal sends to
// fetchAgendaEvents are predictable and can be asserted literally.
// -----------------------------------------------------------------------------
export const AGENDA_DATE = DateTime.fromISO('2026-03-17T00:00:00', { zone: 'utc' });

/** What the modal derives from AGENDA_DATE for a normal (whole-day) request. */
export const AGENDA_DAY_START = '2026-03-17 00:00:00';
export const AGENDA_DAY_END = '2026-03-17 23:59:59';

// -----------------------------------------------------------------------------
// Display groups, as the page hands them to the modal (its `displayGroups` prop).
// -----------------------------------------------------------------------------
export const FOYER_GROUP = { id: 10, name: 'Foyer Screens' };
export const ATRIUM_GROUP = { id: 20, name: 'Atrium Screens' };

export const ONE_DISPLAY_GROUP = [FOYER_GROUP];
export const TWO_DISPLAY_GROUPS = [FOYER_GROUP, ATRIUM_GROUP];

// -----------------------------------------------------------------------------
// Factories
// -----------------------------------------------------------------------------

let nextEventId = 900;

/**
 * A Layout-type agenda event. `fromDt`/`toDt` are unix seconds, which is what the
 * agenda endpoint returns (unlike the Events grid, which returns date strings).
 */
export const buildAgendaEvent = (
  overrides: Partial<AgendaScheduleEvent> = {},
): AgendaScheduleEvent => ({
  eventId: nextEventId++,
  eventTypeId: 1,
  layoutId: 100,
  displayGroupId: FOYER_GROUP.id,
  fromDt: Math.floor(Date.UTC(2026, 2, 17, 9, 0, 0) / 1000),
  toDt: Math.floor(Date.UTC(2026, 2, 17, 17, 0, 0) / 1000),
  isPriority: 0,
  intermediateDisplayGroupIds: [],
  isEditable: true,
  ...overrides,
});

export const buildAgendaLayout = (overrides: Partial<AgendaLayout> = {}): AgendaLayout => ({
  layoutId: 100,
  layout: 'Morning Welcome',
  status: 1,
  duration: 60,
  previewJwt: 'preview-jwt-token',
  ...overrides,
});

export const buildAgendaDisplayGroup = (
  overrides: Partial<AgendaDisplayGroup> = {},
): AgendaDisplayGroup => ({
  displayGroupId: FOYER_GROUP.id,
  displayGroup: FOYER_GROUP.name,
  isDisplaySpecific: 0,
  ...overrides,
});

export const buildAgendaCampaign = (overrides: Partial<AgendaCampaign> = {}): AgendaCampaign => ({
  campaignId: 55,
  campaign: 'Spring Campaign',
  cyclePlaybackEnabled: 0,
  ...overrides,
});

/** Assemble a full agenda response from its parts, keyed the way the API keys them. */
export const buildAgendaResponse = (
  events: AgendaScheduleEvent[],
  layouts: AgendaLayout[] = [],
  displayGroups: AgendaDisplayGroup[] = [buildAgendaDisplayGroup()],
  campaigns: AgendaCampaign[] = [],
): FetchAgendaEventsResponse => ({
  events,
  layouts: Object.fromEntries(layouts.map((l) => [String(l.layoutId), l])),
  displayGroups: Object.fromEntries(displayGroups.map((d) => [String(d.displayGroupId), d])),
  campaigns: Object.fromEntries(campaigns.map((c) => [String(c.campaignId), c])),
});

// -----------------------------------------------------------------------------
// Named response shapes
// -----------------------------------------------------------------------------

export const mockAgendaEvent = buildAgendaEvent({ eventId: 901, layoutId: 100 });
export const mockAgendaLayout = buildAgendaLayout({ layoutId: 100, layout: 'Morning Welcome' });

/** One Layout event whose layout is fully readable by this user. */
export const SINGLE_AGENDA_EVENT = buildAgendaResponse([mockAgendaEvent], [mockAgendaLayout]);

/** Nothing scheduled on this day. */
export const EMPTY_AGENDA = buildAgendaResponse([]);

// -----------------------------------------------------------------------------
// Geo-fencing.
//
// The agenda filters events client-side against a point the user picks: an event
// carrying a geoLocation only survives if the point falls inside its fence.
// Two fences far apart, so a point inside one is definitively outside the other.
// -----------------------------------------------------------------------------

// GeoJSON rings are [longitude, latitude] pairs. Both boxes sit in positive
// coordinate space on purpose: the lat/lng fields are number inputs, and typing
// a leading minus into one mid-keystroke yields NaN, which the input reports as
// a cleared field.

/** A box over East Anglia — longitude 0..2, latitude 51..53. Contains 52.2, 1.1. */
export const IPSWICH_FENCE =
  '{"type":"Polygon","coordinates":[[[0,51],[2,51],[2,53],[0,53],[0,51]]]}';

/** A box over Berlin — longitude 12..14, latitude 52..54. Disjoint from Ipswich. */
export const BERLIN_FENCE =
  '{"type":"Polygon","coordinates":[[[12,52],[14,52],[14,54],[12,54],[12,52]]]}';

/** The same East Anglia box, wrapped the way leaflet-draw exports a drawn fence. */
export const IPSWICH_FENCE_AS_FEATURE_COLLECTION = JSON.stringify({
  type: 'FeatureCollection',
  features: [{ type: 'Feature', geometry: JSON.parse(IPSWICH_FENCE) }],
});

/** A point inside IPSWICH_FENCE and outside BERLIN_FENCE. */
export const POINT_IN_IPSWICH = { lat: '52.2', lng: '1.1' };

/** A point inside BERLIN_FENCE and outside IPSWICH_FENCE. */
export const POINT_IN_BERLIN = { lat: '52.52', lng: '13.4' };

/**
 * Three events on one day: one fenced to East Anglia, one fenced to Berlin, and
 * one with no fence at all (which always shows, wherever the viewer is).
 */
export const GEO_DAY = buildAgendaResponse(
  [
    buildAgendaEvent({ eventId: 1, layoutId: 100, geoLocation: IPSWICH_FENCE }),
    buildAgendaEvent({ eventId: 2, layoutId: 200, geoLocation: BERLIN_FENCE }),
    buildAgendaEvent({ eventId: 3, layoutId: 300 }),
  ],
  [
    buildAgendaLayout({ layoutId: 100, layout: 'Ipswich Only' }),
    buildAgendaLayout({ layoutId: 200, layout: 'Berlin Only' }),
    buildAgendaLayout({ layoutId: 300, layout: 'Everywhere' }),
  ],
);
