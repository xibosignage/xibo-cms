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

// Every test here drives the slow path: lazy page render → open the row's
// floating actions menu → portal-rendered menu item → modal. Under parallel
// JSDOM contention that can exceed the 5s default, so the whole file gets a
// generous timeout. Each test still completes in ~1s in isolation.
vi.setConfig({ testTimeout: 20_000 });

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
