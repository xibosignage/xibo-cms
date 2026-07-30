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

import {
  buildDisplayProfile,
  mockDisplayProfile,
  SINGLE_DISPLAY_PROFILE,
} from './fixtures/displayProfile';
import { renderDisplayProfilePage } from './helpers/renderDisplayProfilePage';
import { mockFetchDisplayProfile } from './mocks/displayProfileApi';

import { fetchDisplayProfile } from '@/services/displayProfileApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/displayProfileApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/useDisplayProfileFilterOptions', () => ({
  useDisplayProfileFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

vi.mock('../components/AddDisplayProfileModal', () => ({
  default: ({
    onClose,
    onSave,
  }: {
    onClose: () => void;
    onSave: (created: ReturnType<typeof buildDisplayProfile>) => void;
  }) => (
    <div role="dialog" aria-label="Add Display Profile">
      <button
        onClick={() => {
          onSave(buildDisplayProfile({ displayProfileId: 999, name: 'Brand New Profile' }));
          onClose();
        }}
      >
        stub-save
      </button>
    </div>
  ),
}));

// =============================================================================
// Tests — Add Display Profile wiring
// =============================================================================

describe('DisplayProfile page - add wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplayProfile(SINGLE_DISPLAY_PROFILE);
  });

  test('the "Add Display Profile" button opens the Add modal', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await screen.findByText(mockDisplayProfile.name);

    await user.click(screen.getByRole('button', { name: /add display profile/i }));

    expect(await screen.findByRole('dialog', { name: /add display profile/i })).toBeInTheDocument();
  });

  test('the table is refreshed after a successful add', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await screen.findByText(mockDisplayProfile.name);

    await user.click(screen.getByRole('button', { name: /add display profile/i }));
    const fetchCountBeforeSave = vi.mocked(fetchDisplayProfile).mock.calls.length;
    await user.click(screen.getByRole('button', { name: /stub-save/i }));

    await waitFor(() => {
      expect(vi.mocked(fetchDisplayProfile).mock.calls.length).toBeGreaterThan(
        fetchCountBeforeSave,
      );
    });
  });

  test('no second modal opens automatically after add', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await screen.findByText(mockDisplayProfile.name);

    await user.click(screen.getByRole('button', { name: /add display profile/i }));
    await user.click(screen.getByRole('button', { name: /stub-save/i }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });

  test('only the Add modal is open when adding', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await screen.findByText(mockDisplayProfile.name);

    await user.click(screen.getByRole('button', { name: /add display profile/i }));

    expect(await screen.findByRole('dialog', { name: /add display profile/i })).toBeInTheDocument();
    expect(screen.getAllByRole('dialog')).toHaveLength(1);
  });
});
