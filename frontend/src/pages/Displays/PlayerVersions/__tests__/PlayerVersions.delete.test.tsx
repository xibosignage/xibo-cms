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
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  MULTIPLE_PLAYER_VERSIONS,
  mockPlayerVersion,
  SINGLE_PLAYER_VERSION,
} from './fixtures/playerVersion';
import { renderPlayerVersionsPage } from './helpers/renderPlayerVersionsPage';
import { mockFetchPlayerVersions } from './mocks/playerVersionApi';

import { deletePlayerVersion, fetchPlayerVersions } from '@/services/playerVersionApi';
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

vi.mock('../components/AddPlayerVersionModal', () => ({ default: () => null }));
vi.mock('../components/EditPlayerVersionModal', () => ({ default: () => null }));

// =============================================================================
// Helpers
// =============================================================================

const openRowDeleteModal = async (user: UserEvent) => {
  await screen.findByText(mockPlayerVersion.playerShowVersion);
  await user.click(screen.getByRole('button', { name: /more actions/i }));

  const deleteButton = await waitFor(() => screen.getByRole('button', { name: /^delete$/i }), {
    timeout: 5000,
  });
  await user.click(deleteButton);
};

const selectAllRows = async (user: UserEvent) => {
  // The first checkbox is the column header's "select all" toggle.
  const checkboxes = screen.getAllByRole('checkbox', { name: /select row/i });
  await user.click(checkboxes[0]!);
};

// =============================================================================
// Tests — Single delete
// =============================================================================

describe('Player Versions page - single delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchPlayerVersions(SINGLE_PLAYER_VERSION);
  });

  test('Delete row action opens the confirmation modal showing the version name', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();

    await openRowDeleteModal(user);

    expect(await screen.findByText('Delete Player Version?')).toBeInTheDocument();
    expect(
      screen.getByText(mockPlayerVersion.playerShowVersion, { selector: 'strong' }),
    ).toBeInTheDocument();
    // Routing: the Delete action opens ONLY the Delete modal — not the Edit modal.
    expect(screen.getAllByRole('dialog')).toHaveLength(1);
  }, 20_000);

  test('clicking Cancel closes the modal without deleting', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(screen.queryByText('Delete Player Version?')).not.toBeInTheDocument();
    expect(deletePlayerVersion).not.toHaveBeenCalled();
  }, 20_000);

  test('clicking Yes, Delete removes the version and closes the modal', async () => {
    const user = userEvent.setup();
    vi.mocked(deletePlayerVersion).mockResolvedValue(undefined);
    renderPlayerVersionsPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitFor(() => {
      expect(deletePlayerVersion).toHaveBeenCalledWith(mockPlayerVersion.versionId);
    });
    await waitFor(() => {
      expect(screen.queryByText('Delete Player Version?')).not.toBeInTheDocument();
    });
  }, 20_000);

  test('the table refreshes after a successful delete', async () => {
    const user = userEvent.setup();
    vi.mocked(deletePlayerVersion).mockResolvedValue(undefined);
    renderPlayerVersionsPage();

    await openRowDeleteModal(user);
    const fetchCountBeforeDelete = vi.mocked(fetchPlayerVersions).mock.calls.length;
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitFor(() => {
      expect(vi.mocked(fetchPlayerVersions).mock.calls.length).toBeGreaterThan(
        fetchCountBeforeDelete,
      );
    });
  }, 20_000);

  test('Delete button shows "Deleting…" while the request is in progress', async () => {
    const user = userEvent.setup();
    let resolveDelete: () => void = () => {};
    vi.mocked(deletePlayerVersion).mockReturnValueOnce(
      new Promise<void>((resolve) => {
        resolveDelete = resolve;
      }),
    );
    renderPlayerVersionsPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    expect(await screen.findByRole('button', { name: /deleting/i })).toBeDisabled();

    resolveDelete();
  }, 20_000);

  test('a failed delete keeps the modal open and shows the error', async () => {
    const user = userEvent.setup();
    vi.mocked(deletePlayerVersion).mockRejectedValueOnce({
      isAxiosError: true,
      response: { data: { message: 'Cannot delete — version is in use.' } },
    });
    renderPlayerVersionsPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    expect(await screen.findByText('Cannot delete — version is in use.')).toBeInTheDocument();
    expect(screen.getByText('Delete Player Version?')).toBeInTheDocument();
  }, 20_000);

  test('single item: heading is singular', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();

    await openRowDeleteModal(user);

    expect(await screen.findByText('Delete Player Version?')).toBeInTheDocument();
    expect(screen.queryByText('Delete Player Versions?')).not.toBeInTheDocument();
  }, 20_000);
});

// =============================================================================
// Tests — Bulk delete
// =============================================================================

describe('Player Versions page - bulk delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchPlayerVersions(MULTIPLE_PLAYER_VERSIONS);
  });

  test('selecting rows reveals the bulk delete action', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await screen.findByText('Android v4');

    expect(screen.queryByRole('button', { name: /delete selected/i })).not.toBeInTheDocument();

    await selectAllRows(user);

    expect(await screen.findByRole('button', { name: /delete selected/i })).toBeInTheDocument();
  });

  test('clicking Cancel on a bulk delete closes the modal without deleting', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await screen.findByText('Android v4');

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));
    await screen.findByText('Delete Player Versions?');

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(screen.queryByText('Delete Player Versions?')).not.toBeInTheDocument();
    expect(deletePlayerVersion).not.toHaveBeenCalled();
  });

  test('multiple items: heading is plural with the count', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await screen.findByText('Android v4');

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));

    expect(await screen.findByText('Delete Player Versions?')).toBeInTheDocument();
    expect(screen.getByText('2', { selector: 'strong' })).toBeInTheDocument();
  });

  test('bulk confirm deletes every selected item', async () => {
    const user = userEvent.setup();
    vi.mocked(deletePlayerVersion).mockResolvedValue(undefined);
    renderPlayerVersionsPage();
    await screen.findByText('Android v4');

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));
    await screen.findByText('Delete Player Versions?');
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitFor(() => {
      expect(deletePlayerVersion).toHaveBeenCalledTimes(2);
    });
    const calledIds = vi
      .mocked(deletePlayerVersion)
      .mock.calls.map((c) => c[0])
      .sort();
    expect(calledIds).toEqual([1, 2]);
  });

  test('a partial bulk delete failure shows the error and refreshes the table', async () => {
    const user = userEvent.setup();
    vi.mocked(deletePlayerVersion).mockImplementation((id) => {
      if (id === 2) {
        return Promise.reject({
          isAxiosError: true,
          response: { data: { message: 'Version 2 is in use.' } },
        });
      }
      return Promise.resolve(undefined);
    });
    renderPlayerVersionsPage();
    await screen.findByText('Android v4');
    const initialFetchCount = vi.mocked(fetchPlayerVersions).mock.calls.length;

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));
    await screen.findByText('Delete Player Versions?');
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    // Error surfaces and the modal stays open.
    expect(await screen.findByText('Version 2 is in use.')).toBeInTheDocument();
    expect(screen.getByText('Delete Player Versions?')).toBeInTheDocument();

    // Both rows were attempted and the table was refreshed.
    expect(deletePlayerVersion).toHaveBeenCalledTimes(2);
    await waitFor(() => {
      expect(vi.mocked(fetchPlayerVersions).mock.calls.length).toBeGreaterThan(initialFetchCount);
    });
  });
});
