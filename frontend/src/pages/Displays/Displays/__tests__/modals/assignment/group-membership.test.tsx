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
import { vi, describe, test, expect, beforeEach } from 'vitest';

import { mockDisplay } from '../../fixtures/display';
import ManageGroupMembershipModal from '../../../components/ManageGroupMembershipModal';

import { assignDisplayGroups, fetchDisplayGroupMembership } from '@/services/displaysApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next', () => {
  const t = (key: string) => key;
  return {
    useTranslation: () => ({ t, i18n: { changeLanguage: vi.fn() } }),
    Trans: ({ children }: { children: React.ReactNode }) => children,
  };
});

vi.mock('@/components/ui/modals/Modal');

vi.mock('@/services/displaysApi', () => ({
  fetchDisplayGroupMembership: vi.fn().mockResolvedValue([]),
  assignDisplayGroups: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/services/displayGroupApi', () => ({
  fetchDisplayGroups: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));

vi.mock('@/hooks/useDebounce', () => ({
  useDebounce: (v: unknown) => v,
}));

// SearchAssignPanel is a large composite component. Replace it with a minimal
// stub that exposes Add and Remove buttons so tests can drive the modal's
// internal toAdd/toRemove state without depending on table rendering.
vi.mock('@/components/ui/SearchAssignPanel', () => ({
  SearchAssignPanel: ({
    onAddItem,
    onRemoveItem,
    assignedItems,
  }: {
    onAddItem: (item: { displayGroupId: number; displayGroup: string }) => void;
    onRemoveItem: (item: { displayGroupId: number; displayGroup: string }) => void;
    assignedItems: { displayGroupId: number; displayGroup: string }[];
  }) => (
    <div data-testid="search-assign-panel">
      <span data-testid="assigned-count">{assignedItems.length}</span>
      <button
        onClick={() => onAddItem({ displayGroupId: 99, displayGroup: 'New Group' })}
      >
        Add Group
      </button>
      {assignedItems.map((g) => (
        <button key={g.displayGroupId} onClick={() => onRemoveItem(g)}>
          Remove {g.displayGroup}
        </button>
      ))}
    </div>
  ),
}));

// =============================================================================
// Helpers
// =============================================================================

const renderModal = (overrides: { onClose?: () => void; onSave?: () => void } = {}) =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <ManageGroupMembershipModal
        display={mockDisplay}
        onClose={overrides.onClose ?? vi.fn()}
        onSave={overrides.onSave ?? vi.fn()}
      />
    </QueryClientProvider>,
  );

// =============================================================================
// Tests
// =============================================================================

describe('ManageGroupMembershipModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchDisplayGroupMembership).mockResolvedValue([]);
  });

  // ---------------------------------------------------------------------------
  // The modal must open with a title that identifies which display's membership
  // is being managed.
  // ---------------------------------------------------------------------------
  test('renders the dialog', async () => {
    renderModal();

    await screen.findByRole('dialog', { name: /manage membership/i });
  });

  // ---------------------------------------------------------------------------
  // Cancel must call onClose to dismiss the modal.
  // ---------------------------------------------------------------------------
  test('Cancel button calls onClose', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();

    renderModal({ onClose });
    await screen.findByRole('dialog');

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(onClose).toHaveBeenCalledOnce();
  });

  // ---------------------------------------------------------------------------
  // When nothing has changed (toAdd and toRemove are both empty), Save skips
  // the API call and calls onClose directly.
  // ---------------------------------------------------------------------------
  test('Save with no changes calls onClose without calling the API', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();

    renderModal({ onClose });
    await screen.findByRole('dialog');

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(assignDisplayGroups).not.toHaveBeenCalled();
    expect(onClose).toHaveBeenCalledOnce();
  });

  // ---------------------------------------------------------------------------
  // Adding a group and then saving must call assignDisplayGroups with the
  // display id, the list of groups to add, and an empty list to remove.
  // ---------------------------------------------------------------------------
  test('Save calls assignDisplayGroups after adding a group', async () => {
    const onSave = vi.fn();
    const onClose = vi.fn();
    const user = userEvent.setup();

    renderModal({ onSave, onClose });
    await screen.findByRole('dialog');

    await user.click(screen.getByRole('button', { name: /add group/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(assignDisplayGroups).toHaveBeenCalledWith(mockDisplay.displayId, [99], []);
    expect(onSave).toHaveBeenCalledOnce();
    expect(onClose).toHaveBeenCalledOnce();
  });

  // ---------------------------------------------------------------------------
  // API errors must surface in the modal's error slot.
  // ---------------------------------------------------------------------------
  test('shows error when assignDisplayGroups rejects', async () => {
    vi.mocked(assignDisplayGroups).mockRejectedValueOnce(new Error('Network error'));
    const user = userEvent.setup();

    renderModal();
    await screen.findByRole('dialog');

    await user.click(screen.getByRole('button', { name: /add group/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Failed to update group membership.');
  });

  // ---------------------------------------------------------------------------
  // While saving the Save button must be disabled and show "Saving…" to prevent
  // double-submission. Use a controlled promise so we can assert the in-flight
  // state before letting the promise resolve.
  // ---------------------------------------------------------------------------
  test('Save button is disabled and shows pending label while saving', async () => {
    let resolve!: () => void;
    const pending = new Promise<void>((res) => { resolve = res; });
    vi.mocked(assignDisplayGroups).mockReturnValueOnce(pending);

    const user = userEvent.setup();
    renderModal();
    await screen.findByRole('dialog');

    await user.click(screen.getByRole('button', { name: /add group/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(screen.getByRole('button', { name: /saving/i })).toBeDisabled();

    resolve();
    await waitFor(() =>
      expect(screen.queryByRole('button', { name: /saving/i })).not.toBeInTheDocument(),
    );
  });
});
