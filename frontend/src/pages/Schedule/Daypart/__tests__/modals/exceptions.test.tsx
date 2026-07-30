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

import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockDaypart } from '../fixtures/daypart';

import { renderAddEditModal } from './helpers/renderAddEditModal';

import { createDaypart } from '@/services/daypartApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/components/ui/modals/Modal');
vi.mock('@/services/daypartApi', () => ({
  createDaypart: vi.fn(),
  updateDaypart: vi.fn(),
}));

vi.mock('@/components/ui/forms/TimePickerInput', () => ({
  default: ({
    label,
    value,
    onChange,
  }: {
    label?: string;
    value?: string;
    onChange: (value: string) => void;
  }) => (
    <input
      aria-label={label || undefined}
      value={value ?? ''}
      onChange={(e) => onChange(e.target.value)}
    />
  ),
}));

vi.mock('@/components/ui/forms/SelectDropdown', () => ({
  default: ({
    label,
    value,
    options,
    onSelect,
  }: {
    label?: string;
    value?: string;
    options?: Array<{ value: string; label: string; disabled?: boolean }>;
    onSelect?: (value: string) => void;
  }) => (
    <select
      aria-label={label || undefined}
      value={value ?? ''}
      onChange={(e) => onSelect?.(e.target.value)}
    >
      <option value="">Select a day</option>
      {options?.map((o) => (
        <option key={o.value} value={o.value} disabled={o.disabled}>
          {o.label}
        </option>
      ))}
    </select>
  ),
}));

const openExceptionsTab = async (user: ReturnType<typeof userEvent.setup>) => {
  await user.click(screen.getByRole('button', { name: /^exceptions$/i }));
};

// =============================================================================
// Tests
// =============================================================================

describe('AddAndEditDaypartModal - exceptions tab', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('the Exceptions tab shows the helper text', async () => {
    const user = userEvent.setup();
    renderAddEditModal({ type: 'add' });
    await openExceptionsTab(user);

    expect(screen.getByText(/if there are any exceptions/i)).toBeInTheDocument();
  });

  test('clicking "Add Exception" adds an exception row', async () => {
    const user = userEvent.setup();
    renderAddEditModal({ type: 'add' });
    await openExceptionsTab(user);

    expect(screen.queryAllByRole('combobox')).toHaveLength(0);

    await user.click(screen.getByRole('button', { name: /add exception/i }));

    expect(screen.getAllByRole('combobox')).toHaveLength(1);
  });

  test('the Day dropdown offers every day Monday through Sunday', async () => {
    const user = userEvent.setup();
    renderAddEditModal({ type: 'add' });
    await openExceptionsTab(user);

    await user.click(screen.getByRole('button', { name: /add exception/i }));
    const dayDropdown = screen.getByRole('combobox', { name: /^day$/i });

    for (const day of [
      'Monday',
      'Tuesday',
      'Wednesday',
      'Thursday',
      'Friday',
      'Saturday',
      'Sunday',
    ]) {
      expect(within(dayDropdown).getByRole('option', { name: day })).toBeInTheDocument();
    }
  });

  test('the remove button deletes an exception row', async () => {
    const user = userEvent.setup();
    const { container } = renderAddEditModal({ type: 'add' });
    await openExceptionsTab(user);

    await user.click(screen.getByRole('button', { name: /add exception/i }));
    expect(screen.getAllByRole('combobox')).toHaveLength(1);

    // The remove button is an icon-only button (no accessible name); find it via
    // its trash icon.
    const removeButton = container.querySelector('.lucide-trash-2')?.closest('button');
    expect(removeButton).not.toBeNull();
    await user.click(removeButton!);

    expect(screen.queryAllByRole('combobox')).toHaveLength(0);
  });

  test('a day chosen in one row is disabled in the other rows', async () => {
    const user = userEvent.setup();
    renderAddEditModal({ type: 'add' });
    await openExceptionsTab(user);

    await user.click(screen.getByRole('button', { name: /add exception/i }));
    await user.click(screen.getByRole('button', { name: /add exception/i }));

    // Pick Monday in the first row.
    const firstRowDay = screen.getByRole('combobox', { name: /^day$/i });
    await user.selectOptions(firstRowDay, 'Mon');

    // Monday should now be disabled in the second row's dropdown.
    const secondRowDay = screen.getAllByRole('combobox')[1]!;
    expect(within(secondRowDay).getByRole('option', { name: 'Monday' })).toBeDisabled();
  });

  test('"Add Exception" is disabled once all seven days are used', async () => {
    const user = userEvent.setup();
    renderAddEditModal({ type: 'add' });
    await openExceptionsTab(user);

    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    // Add seven rows.
    for (let i = 0; i < 7; i++) {
      await user.click(screen.getByRole('button', { name: /add exception/i }));
    }
    // Assign a distinct day to each.
    for (let i = 0; i < 7; i++) {
      await user.selectOptions(screen.getAllByRole('combobox')[i]!, days[i]!);
    }

    expect(screen.getByRole('button', { name: /add exception/i })).toBeDisabled();
  });

  test('exceptions are serialised into the three parallel payload arrays on save', async () => {
    const user = userEvent.setup();
    vi.mocked(createDaypart).mockResolvedValue(mockDaypart);
    renderAddEditModal({ type: 'add' });

    // Fill the General tab so the overall form is valid.
    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'With Exceptions');
    await user.type(screen.getByRole('textbox', { name: /start time/i }), '09:00:00');
    await user.type(screen.getByRole('textbox', { name: /end time/i }), '17:00:00');

    // Add one exception on the Exceptions tab.
    await openExceptionsTab(user);
    await user.click(screen.getByRole('button', { name: /add exception/i }));
    await user.selectOptions(screen.getByRole('combobox', { name: /^day$/i }), 'Mon');
    await user.type(screen.getByRole('textbox', { name: /start time/i }), '08:00:00');
    await user.type(screen.getByRole('textbox', { name: /end time/i }), '12:00:00');

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => {
      expect(createDaypart).toHaveBeenCalledWith(
        expect.objectContaining({
          exceptionDays: ['Mon'],
          exceptionStartTimes: ['08:00:00'],
          exceptionEndTimes: ['12:00:00'],
        }),
      );
    });
  });
});
