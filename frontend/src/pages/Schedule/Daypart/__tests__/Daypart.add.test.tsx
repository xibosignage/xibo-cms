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

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockDaypart, SINGLE_DAYPART } from './fixtures/daypart';
import { renderDaypartPage } from './helpers/renderDaypartPage';
import { mockFetchDaypart } from './mocks/daypartApi';

import { fetchDaypart } from '@/services/daypartApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/daypartApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/useDaypartFilterOptions', () => ({
  useDaypartFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

vi.mock('../components/AddAndEditDaypartModal', () => ({
  default: ({
    type,
    onSave,
    onClose,
  }: {
    type: 'add' | 'edit';
    onSave: () => void;
    onClose: () => void;
  }) => (
    <div role="dialog" aria-label={type === 'add' ? 'Add Daypart' : 'Edit Daypart'}>
      <button type="button" onClick={() => onSave()}>
        Trigger save
      </button>
      <button type="button" onClick={onClose}>
        Stub cancel
      </button>
    </div>
  ),
}));

// =============================================================================
// Tests
// =============================================================================

describe('Dayparting page - add wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDaypart(SINGLE_DAYPART);
  });

  test('the "Add Daypart" button opens the Add modal', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    await user.click(screen.getByRole('button', { name: /add daypart/i }));

    expect(await screen.findByRole('dialog', { name: /add daypart/i })).toBeInTheDocument();
  });

  test('only one modal is open at a time', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    await user.click(screen.getByRole('button', { name: /add daypart/i }));
    await screen.findByRole('dialog', { name: /add daypart/i });

    expect(screen.getAllByRole('dialog')).toHaveLength(1);
  });

  test('the table is refreshed after a successful save', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    const callsBefore = vi.mocked(fetchDaypart).mock.calls.length;

    await user.click(screen.getByRole('button', { name: /add daypart/i }));
    await user.click(await screen.findByRole('button', { name: /trigger save/i }));

    await waitFor(() => {
      expect(vi.mocked(fetchDaypart).mock.calls.length).toBeGreaterThan(callsBefore);
    });
  });
});
