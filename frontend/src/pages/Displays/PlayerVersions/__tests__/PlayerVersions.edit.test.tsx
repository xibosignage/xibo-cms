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
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockPlayerVersion, SINGLE_PLAYER_VERSION } from './fixtures/playerVersion';
import { renderPlayerVersionsPage } from './helpers/renderPlayerVersionsPage';
import { mockFetchPlayerVersions } from './mocks/playerVersionApi';

import { fetchPlayerVersions } from '@/services/playerVersionApi';
import { testQueryClient } from '@/setupTests';
import type { PlayerVersion } from '@/types/playerVersion';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/playerVersionApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue([]),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/usePlayerVersionsFilterOptions', () => ({
  usePlayerVersionFilterOptions: () => ({ filterOptions: [] }),
}));

vi.mock('../components/EditPlayerVersionModal', () => ({
  default: ({
    data,
    onClose,
    onSave,
  }: {
    data: PlayerVersion | null;
    onClose: () => void;
    onSave: (updated: PlayerVersion) => void;
  }) => (
    <div role="dialog" aria-label="Edit Player Version" data-version-id={data?.versionId ?? ''}>
      <button
        onClick={() => {
          if (data) {
            onSave(data);
          }
          onClose();
        }}
      >
        stub-save
      </button>
      <button onClick={onClose}>stub-cancel</button>
    </div>
  ),
}));

// =============================================================================
// Tests — Edit wiring
// =============================================================================

describe('Player Versions page - edit wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchPlayerVersions(SINGLE_PLAYER_VERSION);
  });

  test('clicking Edit on a row opens the Edit modal for that player version', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await screen.findByText(mockPlayerVersion.playerShowVersion);

    await user.click(screen.getByRole('button', { name: /^edit$/i }));

    const dialog = await screen.findByRole('dialog', { name: /edit player version/i });
    expect(dialog).toHaveAttribute('data-version-id', String(mockPlayerVersion.versionId));
  });

  test('the Edit action opens the Edit modal (not Delete)', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await screen.findByText(mockPlayerVersion.playerShowVersion);

    await user.click(screen.getByRole('button', { name: /^edit$/i }));

    await screen.findByRole('dialog', { name: /edit player version/i });
    expect(screen.getAllByRole('dialog')).toHaveLength(1);
    expect(screen.queryByText('Delete Player Version?')).not.toBeInTheDocument();
  });

  test('the table is refreshed after saving an edit', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await screen.findByText(mockPlayerVersion.playerShowVersion);

    await user.click(screen.getByRole('button', { name: /^edit$/i }));
    await screen.findByRole('dialog', { name: /edit player version/i });
    const fetchCountBeforeSave = vi.mocked(fetchPlayerVersions).mock.calls.length;
    await user.click(screen.getByRole('button', { name: /stub-save/i }));

    await waitFor(() => {
      expect(vi.mocked(fetchPlayerVersions).mock.calls.length).toBeGreaterThan(
        fetchCountBeforeSave,
      );
    });
  });

  test('cancelling an edit leaves the row unchanged', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await screen.findByText(mockPlayerVersion.playerShowVersion);

    await user.click(screen.getByRole('button', { name: /^edit$/i }));
    await screen.findByRole('dialog', { name: /edit player version/i });

    await user.click(screen.getByRole('button', { name: /stub-cancel/i }));

    // The modal closes and the original row text is still shown (no mutation).
    await waitFor(() => {
      expect(
        screen.queryByRole('dialog', { name: /edit player version/i }),
      ).not.toBeInTheDocument();
    });
    expect(screen.getByText(mockPlayerVersion.playerShowVersion)).toBeInTheDocument();
  });
});
