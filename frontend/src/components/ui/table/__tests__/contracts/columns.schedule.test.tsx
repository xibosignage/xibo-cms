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

import { describe, expect, test } from 'vitest';

import { propsWith, statesOf, textOf } from './helpers';

import { getEventColumns } from '@/pages/Schedule/Schedule/EventsConfig';
import { buildEvent } from '@/pages/Schedule/Schedule/__tests__/fixtures/event';

const columns = getEventColumns(propsWith());
const event = (fields: object) => ({ ...buildEvent(), ...fields });

describe('Events grid', () => {
  test('the Priority column shows each priority level differently, not a cross for everything except 1', () => {
    const states = statesOf(columns, 'isPriority', {
      normal: event({ isPriority: 0 }),
      priority1: event({ isPriority: 1 }),
      priority2: event({ isPriority: 2 }),
      priority5: event({ isPriority: 5 }),
    });

    expect(states.priority2).not.toBe(states.normal);
    expect(states.priority2).not.toBe(states.priority1);
    expect(states.priority5).not.toBe(states.priority2);
  });

  // 1 Layout, 2 Command, 3 Overlay, 4 Interrupt, 5 Campaign, 6 Action, 7 Media, 8 Playlist, 9 Sync, 10 Data Connector.
  test('the Type column shows each of the ten event types distinctly', () => {
    const rows = Object.fromEntries(
      [1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map((type) => [`type${type}`, event({ eventTypeId: type })]),
    );

    expect(new Set(Object.values(statesOf(columns, 'eventTypeId', rows))).size).toBe(10);
  });

  test('the Start column shows "Always" for an Always event, and a CMS date otherwise', () => {
    expect(textOf(columns, 'fromDt', event({ isAlways: 1 }))).toBe('Always');
    expect(textOf(columns, 'fromDt', event({ isAlways: 0 }))).toMatch(/^formatted:/);
  });

  test('the Geo column shows a geo-aware event differently from one that is not', () => {
    const states = statesOf(columns, 'isGeoAware', {
      geo: event({ isGeoAware: 1 }),
      plain: event({ isGeoAware: 0 }),
    });

    expect(states.geo).not.toBe(states.plain);
  });

  test('the Criteria column shows an event with criteria differently from one without', () => {
    const states = statesOf(columns, 'criteria', {
      withCriteria: event({
        criteria: [{ metric: 'temp', condition: 'gt', type: 'number', value: '20' }],
      }),
      without: event({ criteria: [] }),
    });

    expect(states.withCriteria).not.toBe(states.without);
  });

  // 0 means "no limit" (ScheduleEventModal; Schedule.php default 0), but the cell prints
  // the number, so an unlimited event reads as "0 plays per hour".
  test.fails('the Max Plays per Hour column does not show an unlimited event as "0"', () => {
    expect(textOf(columns, 'maxPlaysPerHour', event({ maxPlaysPerHour: 0 }))).not.toBe('0');
  });

  // The API sends 0 for "no campaign" (command, sync and action events); the cell
  // was written for null, so the dash never appears.
  test.fails(
    'the Campaign ID column shows "no campaign" the same whether it arrives as 0 or null',
    () => {
      const states = statesOf(columns, 'campaignId', {
        zero: event({ campaignId: 0 }),
        none: event({ campaignId: null }),
      });

      expect(states.zero).toBe(states.none);
    },
  );

  // Same 0-for-null shape: share of voice only applies to Interrupt events.
  test.fails(
    'the Share of Voice column shows "not set" the same whether it arrives as 0 or null',
    () => {
      const states = statesOf(columns, 'shareOfVoice', {
        zero: event({ shareOfVoice: 0 }),
        none: event({ shareOfVoice: null }),
      });

      expect(states.zero).toBe(states.none);
    },
  );

  // A non-recurring event has recurrenceDetail 0.
  test.fails(
    'the Recurrence Interval column shows "not recurring" the same whether it arrives as 0 or null',
    () => {
      const states = statesOf(columns, 'recurrenceDetail', {
        zero: event({ recurrenceDetail: 0 }),
        none: event({ recurrenceDetail: null }),
      });

      expect(states.zero).toBe(states.none);
    },
  );
});
