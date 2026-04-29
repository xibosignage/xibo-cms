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

import type { Event } from '@/types/event';
import { EventTypeId } from '@/types/event';

let eventIdCounter = 1;

// 2026-04-01T10:00:00Z as a Unix timestamp in seconds
const BASE_FROM_DT = 1743501600;

export function buildEvent(overrides?: Partial<Event>): Event {
  const id = eventIdCounter++;
  const fromDt = BASE_FROM_DT;
  const toDt = fromDt + 3600;

  return {
    eventId: id,
    eventTypeId: EventTypeId.Layout,
    userId: 1,
    fromDt,
    toDt,
    isPriority: 0,
    displayOrder: 0,
    dayPartId: 0,
    isAlways: 0,
    isCustom: 0,
    syncEvent: 0,
    syncTimezone: 0,
    maxPlaysPerHour: 0,
    isGeoAware: 0,
    displayGroups: [{ displayGroupId: 1, displayGroup: 'Lobby Screens' }],
    scheduleReminders: [],
    criteria: [],
    scheduleExclusions: [],
    recurrenceType: 'None',
    recurrenceDetail: null,
    recurrenceRange: null,
    recurrenceRepeatsOn: null,
    recurrenceMonthlyRepeatsOn: null,
    recurringEvent: false,
    ...overrides,
  };
}

export const mockEvent = buildEvent();

export const SINGLE_EVENT = { rows: [mockEvent], totalCount: 1 };

export const EMPTY_EVENT_TABLE = { rows: [], totalCount: 0 };
