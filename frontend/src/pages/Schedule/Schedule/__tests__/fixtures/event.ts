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

import type { FetchEventResponse } from '@/services/eventApi';
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
    displayGroups: [
      {
        displayGroupId: 1,
        displayGroup: 'Lobby Screens',
        isDisplaySpecific: 0,
        isDynamic: 0,
        userId: 1,
        tags: [],
        createdDt: '',
        modifiedDt: '',
        folderId: 1,
        permissionsFolderId: 1,
      },
    ],
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

// -----------------------------------------------------------------------------
// Canonical event fixtures shared by both test suites (table view and calendar
// view). Field values like eventId 1001 and the "Morning Promo - Spring
// Campaign" label are asserted on directly by the table-view tests, so they
// must be kept stable.
//
// Note: building these three fixtures eagerly at module load advances
// eventIdCounter by 3. Calendar tests don't rely on a specific starting
// counter value - only that subsequent buildEvent() calls produce unique IDs.
// -----------------------------------------------------------------------------

// One realistic non-recurring Layout-type event row.
export const mockEvent: Event = buildEvent({
  eventId: 1001,
  fromDt: 1735776000,
  toDt: 1735862400,
  dayPartId: 1,
  isCustom: 1,
  name: 'Morning Promo',
  campaignId: 42,
  campaign: 'Spring Campaign',
  displayGroups: [
    {
      displayGroupId: 5,
      displayGroup: 'Lobby Screens',
      isDisplaySpecific: 0,
      isDynamic: 0,
      userId: 1,
      tags: [],
      createdDt: '2026-01-01 00:00:00',
      modifiedDt: '2026-01-01 00:00:00',
      folderId: 1,
      permissionsFolderId: 1,
    },
  ],
  isEditable: true,
});

// A second event used for bulk-delete tests where we need >1 selectable row.
export const mockEvent2: Event = {
  ...mockEvent,
  eventId: 1002,
  name: 'Lunch Promo',
};

// A recurring event - drives the "Delete this instance / entire series" branch
// in the delete modal.
export const mockRecurringEvent: Event = {
  ...mockEvent,
  eventId: 1003,
  name: 'Weekly Special',
  recurrenceType: 'Week',
  recurrenceDetail: 1,
  recurringEvent: true,
  recurringEventDescription: 'Repeats every week',
};

// -----------------------------------------------------------------------------
// useEventData return shapes
// -----------------------------------------------------------------------------

export const SINGLE_EVENT: FetchEventResponse = {
  rows: [mockEvent],
  totalCount: 1,
};

export const TWO_EVENTS: FetchEventResponse = {
  rows: [mockEvent, mockEvent2],
  totalCount: 2,
};

export const SINGLE_RECURRING_EVENT: FetchEventResponse = {
  rows: [mockRecurringEvent],
  totalCount: 1,
};

export const EMPTY_EVENT_TABLE: FetchEventResponse = {
  rows: [],
  totalCount: 0,
};
