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

import { screen, fireEvent, waitFor } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockDisplayProfile, SINGLE_DISPLAY_PROFILE } from './fixtures/displayProfile';
import { clickRowMenuItem, renderDisplayProfilePage } from './helpers/renderDisplayProfilePage';
import { mockFetchDisplayProfile } from './mocks/displayProfileApi';

import { copyDisplayProfile } from '@/services/displayProfileApi';
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

vi.mock('../components/CopyDisplayProfileModal', () => ({
  default: ({ onConfirm }: { onConfirm: (newName: string) => void }) => (
    <div role="dialog" aria-label="Copy Display Profile">
      <button onClick={() => onConfirm('Copied Name')}>stub-confirm</button>
    </div>
  ),
}));

// =============================================================================
// Tests — Copy Display Profile wiring
// =============================================================================

describe('DisplayProfile page - copy wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplayProfile(SINGLE_DISPLAY_PROFILE);
    vi.mocked(copyDisplayProfile).mockResolvedValue(mockDisplayProfile);
  });

  test('choosing Copy from a row menu opens the Copy modal', async () => {
    renderDisplayProfilePage();

    await clickRowMenuItem(/^copy$/i);

    expect(
      await screen.findByRole('dialog', { name: /copy display profile/i }),
    ).toBeInTheDocument();
  });

  test('confirming a copy calls copyDisplayProfile with the profile ID and new name', async () => {
    renderDisplayProfilePage();

    await clickRowMenuItem(/^copy$/i);
    fireEvent.click(await screen.findByRole('button', { name: /stub-confirm/i }));

    await waitFor(() => {
      expect(copyDisplayProfile).toHaveBeenCalledWith(
        mockDisplayProfile.displayProfileId,
        'Copied Name',
      );
    });
  });

  test('only the Copy modal opens (not Add, Edit, or Delete)', async () => {
    renderDisplayProfilePage();

    await clickRowMenuItem(/^copy$/i);

    expect(
      await screen.findByRole('dialog', { name: /copy display profile/i }),
    ).toBeInTheDocument();
    expect(screen.getAllByRole('dialog')).toHaveLength(1);
  });
});
