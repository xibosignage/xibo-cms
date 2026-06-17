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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import ManageMembersModal from '../../components/ManageMembersModal';
import { buildSyncGroup, buildSyncGroupDisplay, mockUser } from '../fixtures/syncGroup';

import { UserProvider } from '@/context/UserContext';
import { fetchDisplays } from '@/services/displaysApi';
import { assignSyncGroupMembers, fetchSyncGroupDisplays } from '@/services/syncGroupApi';
import { testQueryClient } from '@/setupTests';
import type { Display } from '@/types/display';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/components/ui/modals/Modal');

// useDebounce is identity in tests so keyword changes flow through immediately.
vi.mock('@/hooks/useDebounce', () => ({ useDebounce: (v: unknown) => v }));

vi.mock('@/services/syncGroupApi', () => ({
  fetchSyncGroupDisplays: vi.fn().mockResolvedValue([]),
  assignSyncGroupMembers: vi.fn(),
}));

vi.mock('@/services/displaysApi', () => ({
  fetchDisplays: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));

// SearchAssignPanel is replaced with a minimal stub that exposes plain
// buttons for adding/removing/clearing the items. This lets the test drive
// the modal's state transitions directly without rendering a TanStack table
// + IntersectionObserver.
vi.mock('@/components/ui/SearchAssignPanel', () => ({
  SearchAssignPanel: <TItem,>({
    assignedItems,
    onAddItem,
    onRemoveItem,
    onClearAll,
    noAssignedText,
    getItemId,
    getItemLabel,
    searchRows,
  }: {
    assignedItems: TItem[];
    onAddItem: (item: TItem) => void;
    onRemoveItem: (item: TItem) => void;
    onClearAll?: () => void;
    noAssignedText?: string;
    getItemId: (item: TItem) => number | string;
    getItemLabel: (item: TItem) => string;
    searchRows: TItem[];
  }) => (
    <div data-testid="search-assign-panel">
      <div data-testid="assigned-count">{assignedItems.length}</div>
      {assignedItems.length === 0 ? (
        <p data-testid="no-assigned">{noAssignedText}</p>
      ) : (
        assignedItems.map((item) => (
          <div key={getItemId(item)} data-testid={`assigned-${getItemId(item)}`}>
            <span>{getItemLabel(item)}</span>
            <button type="button" onClick={() => onRemoveItem(item)}>
              {`Remove ${getItemLabel(item)}`}
            </button>
          </div>
        ))
      )}
      {searchRows.map((item) => (
        <button key={getItemId(item)} type="button" onClick={() => onAddItem(item)}>
          {`Add ${getItemLabel(item)}`}
        </button>
      ))}
      {onClearAll !== undefined && assignedItems.length > 0 && (
        <button type="button" onClick={onClearAll}>
          Clear All
        </button>
      )}
    </div>
  ),
}));

// =============================================================================
// Helpers
// =============================================================================

interface RenderOptions {
  syncGroup?: ReturnType<typeof buildSyncGroup>;
  onClose?: () => void;
  onSuccess?: () => void;
}

const renderMembersModal = (options: RenderOptions = {}) => {
  const syncGroup = options.syncGroup ?? buildSyncGroup({ name: 'Group A' });
  const onClose = options.onClose ?? vi.fn();
  const onSuccess = options.onSuccess ?? vi.fn();

  return {
    onClose,
    onSuccess,
    syncGroup,
    ...render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <ManageMembersModal syncGroup={syncGroup} onClose={onClose} onSuccess={onSuccess} />
        </UserProvider>
      </QueryClientProvider>,
    ),
  };
};

// Stages a non-empty searchRows list so the stub renders "Add …" buttons.
const stageSearchResults = (displays: Display[]) => {
  vi.mocked(fetchDisplays).mockResolvedValue({ rows: displays, totalCount: displays.length });
};

// =============================================================================
// Tests — ManageMembersModal
// =============================================================================

describe('ManageMembersModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchSyncGroupDisplays).mockResolvedValue([]);
    vi.mocked(fetchDisplays).mockResolvedValue({ rows: [], totalCount: 0 });
  });

  // ---------------------------------------------------------------------------
  // The Modal mock renders the title as the dialog's accessible name. The
  // modal interpolates the sync group name into "Manage Members for {{name}}".
  // ---------------------------------------------------------------------------
  test('modal opens with the correct title', () => {
    renderMembersModal({ syncGroup: buildSyncGroup({ name: 'Group A' }) });

    expect(screen.getByRole('dialog', { name: /manage members for group a/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // When the sync group has no current members the assigned panel renders
  // the empty-state copy supplied by the modal ("No displays assigned.").
  // ---------------------------------------------------------------------------
  test('a sync group with no current members shows the empty-state copy', async () => {
    // Default mock already returns []; reassert here for clarity.
    vi.mocked(fetchSyncGroupDisplays).mockResolvedValueOnce([]);

    renderMembersModal();

    expect(await screen.findByText('No displays assigned.')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The hook fetches assigned displays on open. Once the query resolves the
  // assigned list shows the returned displays.
  // ---------------------------------------------------------------------------
  test('currently assigned displays are loaded on open', async () => {
    vi.mocked(fetchSyncGroupDisplays).mockResolvedValue([
      buildSyncGroupDisplay({ displayId: 100, display: 'Screen A' }),
    ]);

    renderMembersModal();

    expect(await screen.findByText('Screen A')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // `handleAddDisplay` has an early-return guard: if the display is already
  // in the assigned list, do nothing. We exercise that guard here by staging
  // the same display in both the assigned list AND the search results, then
  // clicking Add. The assigned count must stay at 1 and Save must trigger
  // no API call (proving no duplicate ended up in `displaysToAdd`).
  // ---------------------------------------------------------------------------
  test('adding a display that is already assigned does not add it again', async () => {
    const user = userEvent.setup();
    const screenA = buildSyncGroupDisplay({ displayId: 100, display: 'Screen A' });
    vi.mocked(fetchSyncGroupDisplays).mockResolvedValue([screenA]);
    // Same display appears as a search result.
    stageSearchResults([{ displayId: 100, display: 'Screen A' } as Display]);

    renderMembersModal();
    await screen.findByText('Screen A');
    expect(screen.getByTestId('assigned-count')).toHaveTextContent('1');

    // Click the "Add Screen A" search button. The dedupe guard should
    // prevent a second entry.
    await user.click(await screen.findByRole('button', { name: /add screen a/i }));
    expect(screen.getByTestId('assigned-count')).toHaveTextContent('1');

    // Save — with nothing added or removed, the modal should bypass the API.
    await user.click(screen.getByRole('button', { name: /^save$/i }));
    await waitFor(() => {
      expect(assignSyncGroupMembers).not.toHaveBeenCalled();
    });
  });

  // ---------------------------------------------------------------------------
  // Adding a display from the search results panel pushes it into the
  // assigned list.
  // ---------------------------------------------------------------------------
  test('adding a display adds it to the assigned list', async () => {
    const user = userEvent.setup();
    stageSearchResults([{ displayId: 200, display: 'New Screen' } as Display]);

    renderMembersModal();

    // Wait for the search query to land.
    await screen.findByRole('button', { name: /add new screen/i });
    await user.click(screen.getByRole('button', { name: /add new screen/i }));

    await waitFor(() => expect(screen.getByTestId('assigned-200')).toHaveTextContent('New Screen'));
  });

  // ---------------------------------------------------------------------------
  // The remove button for an assigned display removes it from the list.
  // ---------------------------------------------------------------------------
  test('removing a display removes it from the assigned list', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchSyncGroupDisplays).mockResolvedValue([
      buildSyncGroupDisplay({ displayId: 100, display: 'Screen A' }),
    ]);

    renderMembersModal();
    await screen.findByText('Screen A');

    await user.click(screen.getByRole('button', { name: /remove screen a/i }));

    await waitFor(() => {
      expect(screen.queryByText('Screen A')).not.toBeInTheDocument();
    });
  });

  // ---------------------------------------------------------------------------
  // Clear All clears every assigned display from the modal state.
  // ---------------------------------------------------------------------------
  test('Clear All removes every assigned display', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchSyncGroupDisplays).mockResolvedValue([
      buildSyncGroupDisplay({ displayId: 100, display: 'Screen A' }),
      buildSyncGroupDisplay({ displayId: 101, display: 'Screen B' }),
    ]);

    renderMembersModal();
    await screen.findByText('Screen A');
    await screen.findByText('Screen B');

    await user.click(screen.getByRole('button', { name: /clear all/i }));

    await waitFor(() => {
      expect(screen.getByTestId('assigned-count')).toHaveTextContent('0');
    });
  });

  // ---------------------------------------------------------------------------
  // Cancel calls onClose without firing any mutation.
  // ---------------------------------------------------------------------------
  test('clicking Cancel closes the modal without saving', async () => {
    const user = userEvent.setup();
    const { onClose } = renderMembersModal();

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(onClose).toHaveBeenCalled();
    expect(assignSyncGroupMembers).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // When neither toAdd nor toRemove has any entries the modal skips the API
  // call entirely and just closes.
  // ---------------------------------------------------------------------------
  test('saving with no changes closes the modal without calling the API', async () => {
    const user = userEvent.setup();
    const { onClose } = renderMembersModal();

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => expect(onClose).toHaveBeenCalled());
    expect(assignSyncGroupMembers).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Adding + removing displays then saving sends the correct arrays to the
  // members endpoint.
  // ---------------------------------------------------------------------------
  test('saving after changes calls the API with the correct add and remove lists', async () => {
    const user = userEvent.setup();
    const syncGroup = buildSyncGroup({ syncGroupId: 7, name: 'Group A' });
    // Initially Screen A is assigned; the search panel offers New Screen.
    vi.mocked(fetchSyncGroupDisplays).mockResolvedValue([
      buildSyncGroupDisplay({ displayId: 100, display: 'Screen A' }),
    ]);
    stageSearchResults([{ displayId: 200, display: 'New Screen' } as Display]);
    vi.mocked(assignSyncGroupMembers).mockResolvedValue(undefined);

    renderMembersModal({ syncGroup });

    await user.click(await screen.findByRole('button', { name: /remove screen a/i }));
    await user.click(await screen.findByRole('button', { name: /add new screen/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => {
      expect(assignSyncGroupMembers).toHaveBeenCalledWith(7, [200], [100]);
    });
  });

  // ---------------------------------------------------------------------------
  // While the assign request is in flight the Save button reads "Saving…" and
  // is disabled so the user can't double-click.
  // ---------------------------------------------------------------------------
  test('Save button shows "Saving…" while the request is in progress', async () => {
    const user = userEvent.setup();
    let resolveAssign: () => void = () => {};
    vi.mocked(assignSyncGroupMembers).mockReturnValueOnce(
      new Promise<void>((resolve) => {
        resolveAssign = resolve;
      }),
    );
    stageSearchResults([{ displayId: 200, display: 'New Screen' } as Display]);

    renderMembersModal();

    await user.click(await screen.findByRole('button', { name: /add new screen/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByRole('button', { name: /saving/i })).toBeDisabled();

    // Resolve so the test doesn't leak a pending promise.
    resolveAssign();
  });

  // ---------------------------------------------------------------------------
  // A failed save sets saveError, which the Modal mock surfaces via
  // role="alert".
  // ---------------------------------------------------------------------------
  test('an API error is displayed in the modal', async () => {
    const user = userEvent.setup();
    vi.mocked(assignSyncGroupMembers).mockRejectedValueOnce({
      isAxiosError: true,
      response: { data: { message: 'Assignment failed.' } },
    });
    stageSearchResults([{ displayId: 200, display: 'New Screen' } as Display]);

    renderMembersModal();
    await user.click(await screen.findByRole('button', { name: /add new screen/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Assignment failed.');
  });
});
