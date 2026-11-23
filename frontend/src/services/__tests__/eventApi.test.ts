/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import { beforeEach, describe, expect, test, vi } from 'vitest';

import http from '@/lib/api';
import { updateEvent, type CreateEventRequest } from '@/services/eventApi';
import { EventTypeId } from '@/types/event';

vi.mock('@/lib/api', () => ({
  default: { put: vi.fn() },
}));

/** The form body of the last PUT the service sent. */
const sentBody = () => {
  const calls = vi.mocked(http.put).mock.calls;
  return new URLSearchParams(calls[calls.length - 1]![1] as string);
};

const reminder = (id: number | null) => ({
  reminder_scheduleReminderId: id,
  reminder_value: 15,
  reminder_type: 1,
  reminder_option: 1,
  reminder_isEmailHidden: 0,
});

const eventWithReminders = (...ids: (number | null)[]) =>
  ({
    eventTypeId: EventTypeId.Layout,
    dayPartId: 1,
    campaignId: 42,
    displayGroupIds: [5],
    scheduleReminders: ids.map(reminder),
  }) as unknown as CreateEventRequest;

// The modal half of xibosignage/xibo#3941 is covered in payload.test.tsx. This
// is the other half: before the fix, updateEvent wrote '0' for every reminder
// id whatever it was given, and the server reads 0 as "a new reminder".
describe('updateEvent - reminder ids', () => {
  beforeEach(() => {
    vi.mocked(http.put).mockReset().mockResolvedValue({ data: {} });
  });

  test('sends the id of an existing reminder', async () => {
    await updateEvent(77, eventWithReminders(55));

    expect(http.put).toHaveBeenCalledWith('/schedule/77', expect.any(String), expect.anything());
    expect(sentBody().get('reminder_scheduleReminderId[0]')).toBe('55');
  });

  test('sends each reminder its own id', async () => {
    await updateEvent(77, eventWithReminders(55, 56));

    expect(sentBody().getAll('reminder_scheduleReminderId[0]')).toEqual(['55']);
    expect(sentBody().getAll('reminder_scheduleReminderId[1]')).toEqual(['56']);
  });

  // 0 is how the server is told a reminder is new.
  test('sends 0 for a reminder that has no id yet', async () => {
    await updateEvent(77, eventWithReminders(null));

    expect(sentBody().get('reminder_scheduleReminderId[0]')).toBe('0');
  });
});
