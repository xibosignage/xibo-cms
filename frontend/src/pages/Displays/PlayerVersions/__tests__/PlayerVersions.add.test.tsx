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

vi.mock('../components/AddPlayerVersionModal', () => ({
  default: ({ onSave }: { onClose: () => void; onSave: () => void }) => (
    <div role="dialog" aria-label="Upload Player Version">
      <button onClick={() => onSave()}>stub-save</button>
    </div>
  ),
}));

// =============================================================================
// Tests — Add (upload) wiring
// =============================================================================

describe('Player Versions page - add wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchPlayerVersions(SINGLE_PLAYER_VERSION);
  });

  test('"Add Version" button opens the upload modal', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await screen.findByText(mockPlayerVersion.playerShowVersion);

    await user.click(screen.getByRole('button', { name: /add version/i }));

    expect(
      await screen.findByRole('dialog', { name: /upload player version/i }),
    ).toBeInTheDocument();
  });

  test('the table is refreshed after a successful upload', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await screen.findByText(mockPlayerVersion.playerShowVersion);

    await user.click(screen.getByRole('button', { name: /add version/i }));
    const fetchCountBeforeSave = vi.mocked(fetchPlayerVersions).mock.calls.length;
    await user.click(screen.getByRole('button', { name: /stub-save/i }));

    await waitFor(() => {
      expect(vi.mocked(fetchPlayerVersions).mock.calls.length).toBeGreaterThan(
        fetchCountBeforeSave,
      );
    });
  });

  test('no second modal opens automatically after a successful upload', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await screen.findByText(mockPlayerVersion.playerShowVersion);

    await user.click(screen.getByRole('button', { name: /add version/i }));
    await user.click(screen.getByRole('button', { name: /stub-save/i }));

    // The upload modal stays open (the orchestrator only refreshes) and no other
    // dialog is routed in.
    await waitFor(() => {
      expect(screen.getAllByRole('dialog')).toHaveLength(1);
    });
    expect(screen.getByRole('dialog', { name: /upload player version/i })).toBeInTheDocument();
  });
});
