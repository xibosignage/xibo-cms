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

import { buildEvent } from '../../fixtures/event';

import type { Event, RecurrenceType } from '@/types/event';

export const CALENDAR_DATE = new Date('2026-04-01T00:00:00Z');
export const FEBRUARY_NON_LEAP_DATE = new Date('2026-02-01T00:00:00Z');
export const FEBRUARY_LEAP_DATE = new Date('2028-02-01T00:00:00Z');
export const DST_SPRING_FORWARD_DATE = new Date('2026-03-01T00:00:00Z');
export const DST_FALL_BACK_DATE = new Date('2026-11-01T00:00:00Z');

// 2026-04-01T00:00:00Z in Unix seconds
const APRIL_2026_START = 1743465600;

export function buildMultiDayEvent(
  startIso: string,
  endIso: string,
  overrides?: Partial<Event>,
): Event {
  const fromDt = Math.floor(new Date(startIso).getTime() / 1000);
  const toDt = Math.floor(new Date(endIso).getTime() / 1000);
  return buildEvent({ fromDt, toDt, ...overrides });
}

export function buildAlwaysEvent(overrides?: Partial<Event>): Event {
  return buildEvent({ isAlways: 1, fromDt: 0, toDt: 0, ...overrides });
}

export function buildRecurringEvent(
  type: RecurrenceType,
  detail: number,
  overrides?: Partial<Event>,
): Event {
  return buildEvent({
    recurringEvent: true,
    recurrenceType: type,
    recurrenceDetail: detail,
    fromDt: APRIL_2026_START,
    toDt: APRIL_2026_START + 3600,
    ...overrides,
  });
}

export function buildEventWithExclusion(excludedFromDt: number, overrides?: Partial<Event>): Event {
  const fromDt = APRIL_2026_START;
  const toDt = fromDt + 3600;
  return buildEvent({
    recurringEvent: true,
    recurrenceType: 'Day',
    recurrenceDetail: 1,
    fromDt,
    toDt,
    scheduleExclusions: [{ fromDt: excludedFromDt, toDt: excludedFromDt + 3600 }],
    ...overrides,
  });
}
