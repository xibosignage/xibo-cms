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

import { render, screen } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { DateTime } from 'luxon';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, test, vi } from 'vitest';

vi.mock('@/context/UserContext', () => ({
  useUserContext: vi.fn(),
}));

import DateFilter from '../DateFilter';
import DatePicker from '../DatePicker';

import { useUserContext } from '@/context/UserContext';
import { setTimezone } from '@/testUtils/timezone';

const BROWSER_TZ = 'Pacific/Auckland';
const CMS_TZ = 'America/New_York';

const originalTz = process.env.TZ;
beforeAll(() => {
  process.env.TZ = BROWSER_TZ;
});
afterAll(() => {
  setTimezone(originalTz);
});

beforeEach(() => {
  vi.useFakeTimers({ shouldAdvanceTime: true });
  vi.setSystemTime(new Date('2026-03-15T12:00:00Z'));
  vi.mocked(useUserContext).mockReturnValue({
    user: { settings: { defaultTimezone: CMS_TZ } },
  } as unknown as ReturnType<typeof useUserContext>);
});

afterEach(() => {
  vi.useRealTimers();
});

const setupUser = () => userEvent.setup({ advanceTimers: vi.advanceTimersByTime });

const clickDay = (user: UserEvent, day: number) =>
  user.click(screen.getAllByRole('button').find((b) => b.textContent?.trim() === String(day))!);
const clickApply = (user: UserEvent) => user.click(screen.getByRole('button', { name: 'Apply' }));

/** The calendar day a date falls on, read the way the CMS reads it. */
const cmsDay = (date: Date) => DateTime.fromJSDate(date, { zone: CMS_TZ }).toISODate();

describe('date-only picks keep the calendar day when the browser and CMS timezones differ', () => {
  test('the environment really is running in a different timezone from the CMS', () => {
    // Guards the test itself: if TZ switching stopped working, every case below would pass vacuously.
    expect(new Date('2026-03-10T00:00:00Z').getTimezoneOffset()).toBe(-13 * 60);
  });

  test('picking 10 March in the date picker gives the CMS 10 March, not 9 March', async () => {
    const user = setupUser();
    const onApply = vi.fn();
    render(
      <DatePicker mode="single" onApply={onApply} onCancel={vi.fn()} showTimePicker={false} />,
    );

    await clickDay(user, 10);
    await clickApply(user);

    const picked = onApply.mock.calls[0]![0] as { type: 'single'; date: Date };
    expect(cmsDay(picked.date)).toBe('2026-03-10');
  });

  test('picking 10 to 12 March as a range gives the CMS 10 to 12 March', async () => {
    const user = setupUser();
    const onApply = vi.fn();
    render(<DatePicker mode="range" onApply={onApply} onCancel={vi.fn()} showTimePicker={false} />);

    await clickDay(user, 10);
    await clickDay(user, 12);
    await clickApply(user);

    const picked = onApply.mock.calls[0]![0] as { type: 'range'; from: Date; to: Date };
    expect([cmsDay(picked.from), cmsDay(picked.to)]).toEqual(['2026-03-10', '2026-03-12']);
  });

  test('filtering a grid by 10 March sends 10 March as the filter value', async () => {
    const user = setupUser();
    const onChange = vi.fn();
    render(
      <DateFilter label="From" name="fromDt" value="" onChange={onChange} showTimePicker={false} />,
    );

    await user.click(screen.getByRole('button', { name: /any time/i }));
    await clickDay(user, 10);
    await clickApply(user);

    expect(onChange).toHaveBeenCalledWith('fromDt', '2026-03-10');
  });
});
