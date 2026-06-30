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

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildDaypart, mockDaypart } from '../fixtures/daypart';

import { renderAddEditModal } from './helpers/renderAddEditModal';

import { createDaypart, updateDaypart } from '@/services/daypartApi';
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
    error,
  }: {
    label?: string;
    value?: string;
    onChange: (value: string) => void;
    error?: string;
  }) => (
    <>
      <input aria-label={label} value={value ?? ''} onChange={(e) => onChange(e.target.value)} />
      {error && <span>{error}</span>}
    </>
  ),
}));

// =============================================================================
// Tests — Add mode
// =============================================================================

describe('AddAndEditDaypartModal - add mode', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('the modal opens with the title "Add Daypart"', () => {
    renderAddEditModal({ type: 'add' });

    expect(screen.getByRole('dialog', { name: /add daypart/i })).toBeInTheDocument();
  });

  test('all three tabs are present', () => {
    renderAddEditModal({ type: 'add' });

    expect(screen.getByRole('button', { name: /^general$/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^description$/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^exceptions$/i })).toBeInTheDocument();
  });

  test('the General tab shows the Name, Retired, Start Time and End Time fields', () => {
    renderAddEditModal({ type: 'add' });

    expect(screen.getByRole('textbox', { name: /^name$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /retired/i })).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: /start time/i })).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: /end time/i })).toBeInTheDocument();
  });

  test('the Description tab shows the multiline Description field', async () => {
    const user = userEvent.setup();
    renderAddEditModal({ type: 'add' });

    await user.click(screen.getByRole('button', { name: /^description$/i }));

    expect(screen.getByRole('textbox', { name: /^description$/i })).toBeInTheDocument();
  });

  test('the in-use warning is not shown in add mode', () => {
    renderAddEditModal({ type: 'add' });

    expect(screen.queryByText(/if this daypart is already in use/i)).not.toBeInTheDocument();
  });

  test('the fields are empty / unchecked by default', () => {
    renderAddEditModal({ type: 'add' });

    expect(screen.getByRole('textbox', { name: /^name$/i })).toHaveValue('');
    expect(screen.getByRole('textbox', { name: /start time/i })).toHaveValue('');
    expect(screen.getByRole('textbox', { name: /end time/i })).toHaveValue('');
    expect(screen.getByRole('checkbox', { name: /retired/i })).not.toBeChecked();
  });

  test('saving with an empty Name shows a validation error and does not save', async () => {
    const user = userEvent.setup();
    renderAddEditModal({ type: 'add' });

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByText('Name is required')).toBeInTheDocument();
    expect(createDaypart).not.toHaveBeenCalled();
  });

  test('saving with empty Start/End times shows the time validation errors', async () => {
    const user = userEvent.setup();
    renderAddEditModal({ type: 'add' });

    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'Morning Slot');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByText('Start time is required')).toBeInTheDocument();
    expect(screen.getByText('End time is required')).toBeInTheDocument();
    expect(createDaypart).not.toHaveBeenCalled();
  });

  test('a validation error switches back to the General tab', async () => {
    const user = userEvent.setup();
    renderAddEditModal({ type: 'add' });

    // Move to the Description tab, then save with an empty name.
    await user.click(screen.getByRole('button', { name: /^description$/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    // The General tab is brought back into view with its error.
    expect(await screen.findByText('Name is required')).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: /^name$/i })).toBeInTheDocument();
  });

  test('a successful save sends the entered values to createDaypart and closes', async () => {
    const user = userEvent.setup();
    vi.mocked(createDaypart).mockResolvedValue(mockDaypart);
    const { onClose } = renderAddEditModal({ type: 'add' });

    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'Morning Slot');
    await user.type(screen.getByRole('textbox', { name: /start time/i }), '09:00:00');
    await user.type(screen.getByRole('textbox', { name: /end time/i }), '17:00:00');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => {
      expect(createDaypart).toHaveBeenCalledWith(
        expect.objectContaining({
          name: 'Morning Slot',
          startTime: '09:00:00',
          endTime: '17:00:00',
          isRetired: 0,
          exceptionDays: [],
          exceptionStartTimes: [],
          exceptionEndTimes: [],
        }),
      );
    });
    await waitFor(() => {
      expect(onClose).toHaveBeenCalled();
    });
  });

  test('clicking Cancel closes the modal without saving', async () => {
    const user = userEvent.setup();
    const { onClose } = renderAddEditModal({ type: 'add' });

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(createDaypart).not.toHaveBeenCalled();
    expect(onClose).toHaveBeenCalled();
  });

  test('the Save button shows "Saving…" while the request is in progress', async () => {
    const user = userEvent.setup();
    let resolveCreate: (value: typeof mockDaypart) => void = () => {};
    vi.mocked(createDaypart).mockReturnValueOnce(
      new Promise((resolve) => {
        resolveCreate = resolve;
      }),
    );
    renderAddEditModal({ type: 'add' });

    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'Morning Slot');
    await user.type(screen.getByRole('textbox', { name: /start time/i }), '09:00:00');
    await user.type(screen.getByRole('textbox', { name: /end time/i }), '17:00:00');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByRole('button', { name: /saving/i })).toBeDisabled();

    // Resolve so the test doesn't leak a pending promise.
    resolveCreate(mockDaypart);
  });

  test('an API error is displayed in the modal', async () => {
    const user = userEvent.setup();
    vi.mocked(createDaypart).mockRejectedValueOnce(new Error('Name already in use.'));
    renderAddEditModal({ type: 'add' });

    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'Existing');
    await user.type(screen.getByRole('textbox', { name: /start time/i }), '09:00:00');
    await user.type(screen.getByRole('textbox', { name: /end time/i }), '17:00:00');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Name already in use.');
  });
});

// =============================================================================
// Tests — Edit mode
// =============================================================================

describe('AddAndEditDaypartModal - edit mode', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('the modal opens with the title "Edit Daypart"', () => {
    renderAddEditModal({ type: 'edit' });

    expect(screen.getByRole('dialog', { name: /edit daypart/i })).toBeInTheDocument();
  });

  test('the fields are pre-filled with the daypart values', () => {
    renderAddEditModal({ type: 'edit' });

    expect(screen.getByRole('textbox', { name: /^name$/i })).toHaveValue(mockDaypart.name);
    expect(screen.getByRole('textbox', { name: /start time/i })).toHaveValue(mockDaypart.startTime);
    expect(screen.getByRole('textbox', { name: /end time/i })).toHaveValue(mockDaypart.endTime);
  });

  test('the Description and Retired fields are pre-filled in edit mode', async () => {
    const user = userEvent.setup();
    const daypart = buildDaypart({ description: 'Lunchtime slot', isRetired: 1 });
    renderAddEditModal({ type: 'edit', data: daypart });

    // Retired is on the General tab.
    expect(screen.getByRole('checkbox', { name: /retired/i })).toBeChecked();

    // Description is on its own tab.
    await user.click(screen.getByRole('button', { name: /^description$/i }));
    expect(screen.getByRole('textbox', { name: /^description$/i })).toHaveValue('Lunchtime slot');
  });

  test('the in-use warning is shown in edit mode', () => {
    renderAddEditModal({ type: 'edit' });

    expect(screen.getByText(/if this daypart is already in use/i)).toBeInTheDocument();
  });

  test('clicking Cancel in edit mode closes without calling updateDaypart', async () => {
    const user = userEvent.setup();
    const { onClose } = renderAddEditModal({ type: 'edit' });

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(updateDaypart).not.toHaveBeenCalled();
    expect(onClose).toHaveBeenCalled();
  });

  test('a successful save sends the updated values to updateDaypart', async () => {
    const user = userEvent.setup();
    vi.mocked(updateDaypart).mockResolvedValue(mockDaypart);
    renderAddEditModal({ type: 'edit' });

    const nameInput = screen.getByRole('textbox', { name: /^name$/i });
    await user.clear(nameInput);
    await user.type(nameInput, 'Renamed Daypart');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => {
      expect(updateDaypart).toHaveBeenCalledWith(
        mockDaypart.dayPartId,
        expect.objectContaining({ name: 'Renamed Daypart' }),
      );
    });
  });

  test('an API error is displayed in the modal', async () => {
    const user = userEvent.setup();
    vi.mocked(updateDaypart).mockRejectedValueOnce(new Error('Update failed.'));
    renderAddEditModal({ type: 'edit' });

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Update failed.');
  });
});
