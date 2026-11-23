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

import { screen, fireEvent, act } from '@testing-library/react';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { SINGLE_DISPLAY_PROFILE, mockDisplayProfile } from './fixtures/displayProfile';
import { renderDisplayProfilePage } from './helpers/renderDisplayProfilePage';
import { mockFetchDisplayProfile } from './mocks/displayProfileApi';

import { fetchDisplayProfile } from '@/services/displayProfileApi';
import { testQueryClient } from '@/setupTests';
import type { DisplayProfile } from '@/types/displayProfile';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/displayProfileApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/modals/Modal');

vi.mock('../components/EditDisplayProfileModal', () => ({
  default: ({
    isOpen = true,
    data,
    onSave,
  }: {
    isOpen?: boolean;
    data: DisplayProfile | null;
    onSave?: (profile: DisplayProfile) => void;
  }) =>
    isOpen ? (
      <div role="dialog" aria-label="Edit Display Profile">
        <button onClick={() => onSave?.({ ...data!, name: 'Android Profile - Edited' })}>
          Save Display Profile
        </button>
      </div>
    ) : null,
}));

// =============================================================================
// Fixtures
// =============================================================================

const updatedProfile = { ...mockDisplayProfile, name: 'Android Profile - Edited' };

// =============================================================================
// Tests
// =============================================================================

describe('DisplayProfile page - edit', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplayProfile(SINGLE_DISPLAY_PROFILE);
  });

  // ---------------------------------------------------------------------------
  // Clicking Edit on a row opens the Edit Display Profile modal.
  // ---------------------------------------------------------------------------
  test('opens the Edit modal when the Edit action is clicked on a row', async () => {
    renderDisplayProfilePage();

    fireEvent.click(await screen.findByTitle('Edit'));

    expect(await screen.findByRole('dialog', { name: 'Edit Display Profile' })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Saving an edit replaces the row's name in the table immediately.
  // ---------------------------------------------------------------------------
  test('saving an edit updates the row name in the table', async () => {
    renderDisplayProfilePage();

    expect(await screen.findByText(mockDisplayProfile.name)).toBeInTheDocument();

    // Data is already loaded above, so findByTitle resolves immediately inside act.
    // act() ensures state updates from the click are fully flushed before we proceed.
    await act(async () => {
      fireEvent.click(await screen.findByTitle('Edit'));
    });

    // When data is refreshed after save, return the updated profile.
    vi.mocked(fetchDisplayProfile).mockResolvedValueOnce({
      rows: [updatedProfile],
      totalCount: 1,
    });

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Save Display Profile' }));
    });

    expect(await screen.findByText(updatedProfile.name)).toBeInTheDocument();
  });
});
