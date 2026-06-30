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
    data,
    onSave,
    onClose,
  }: {
    type: 'add' | 'edit';
    data: { name: string } | null;
    onSave: () => void;
    onClose: () => void;
  }) => (
    <div role="dialog" aria-label={type === 'edit' ? 'Edit Daypart' : 'Add Daypart'}>
      <span data-testid="bound-name">{data?.name ?? ''}</span>
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

describe('Dayparting page - edit wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDaypart(SINGLE_DAYPART);
  });

  // The row Edit quick action opens the modal in edit mode for that daypart.
  test('clicking Edit on a row opens the Edit modal for that daypart', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    await user.click(screen.getByRole('button', { name: /^edit$/i }));

    const dialog = await screen.findByRole('dialog', { name: /edit daypart/i });
    expect(within(dialog).getByTestId('bound-name')).toHaveTextContent(mockDaypart.name);

    // Only the Edit modal opens — not the Delete or Share modal.
    expect(screen.queryByText('Delete Daypart?')).not.toBeInTheDocument();
    expect(screen.queryByRole('dialog', { name: /share daypart/i })).not.toBeInTheDocument();
  });

  // A successful save fires the refresh, which re-fetches the table data.
  test('the table is refreshed after saving an edit', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    const callsBefore = vi.mocked(fetchDaypart).mock.calls.length;

    await user.click(screen.getByRole('button', { name: /^edit$/i }));
    await user.click(await screen.findByRole('button', { name: /trigger save/i }));

    await waitFor(() => {
      expect(vi.mocked(fetchDaypart).mock.calls.length).toBeGreaterThan(callsBefore);
    });
  });
});
