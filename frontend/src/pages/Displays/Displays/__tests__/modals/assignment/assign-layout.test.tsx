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

import AssignLayoutModal from '../../../components/AssignLayoutModal';
import { mockDisplay } from '../../fixtures/display';

import { assignLayouts } from '@/services/displaysApi';
import { fetchLayouts } from '@/services/layoutsApi';
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
  assignLayouts: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/services/layoutsApi', () => ({
  fetchLayouts: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));

vi.mock('@/hooks/useDebounce', () => ({
  useDebounce: (v: unknown) => v,
}));

// SearchAssignPanel stub — exposes Add / Remove buttons to drive toAdd/toRemove
// state without depending on table rendering or IntersectionObserver.
vi.mock('@/components/ui/SearchAssignPanel', () => ({
  SearchAssignPanel: ({
    onAddItem,
    onRemoveItem,
    assignedItems,
  }: {
    onAddItem: (item: { layoutId: number; layout: string }) => void;
    onRemoveItem: (item: { layoutId: number; layout: string }) => void;
    assignedItems: { layoutId: number; layout: string }[];
  }) => (
    <div data-testid="search-assign-panel">
      <span data-testid="assigned-count">{assignedItems.length}</span>
      <button onClick={() => onAddItem({ layoutId: 42, layout: 'Promo Layout' })}>
        Add Layout
      </button>
      {assignedItems.map((l) => (
        <button key={l.layoutId} onClick={() => onRemoveItem(l)}>
          Remove {l.layout}
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
      <AssignLayoutModal
        display={{ displayGroupId: mockDisplay.displayGroupId }}
        onClose={overrides.onClose ?? vi.fn()}
        onSave={overrides.onSave ?? vi.fn()}
      />
    </QueryClientProvider>,
  );

// =============================================================================
// Tests
// =============================================================================

describe('AssignLayoutModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchLayouts).mockResolvedValue({ rows: [], totalCount: 0 });
  });

  // ---------------------------------------------------------------------------
  // The dialog must render so the user can interact with it.
  // ---------------------------------------------------------------------------
  test('renders the dialog with title "Assign Layouts"', async () => {
    renderModal();

    await screen.findByRole('dialog', { name: /assign layouts/i });
  });

  // ---------------------------------------------------------------------------
  // Cancel must call onClose to dismiss the modal.
  // ---------------------------------------------------------------------------
  test('Cancel button calls onClose', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();

    renderModal({ onClose });
    await screen.findByRole('dialog', { name: /assign layouts/i });

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(onClose).toHaveBeenCalledOnce();
  });

  // ---------------------------------------------------------------------------
  // With no changes in toAdd or toRemove, Save skips the API and calls onClose.
  // ---------------------------------------------------------------------------
  test('Save with no changes calls onClose without calling the API', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();

    renderModal({ onClose });
    await screen.findByRole('dialog', { name: /assign layouts/i });

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(assignLayouts).not.toHaveBeenCalled();
    expect(onClose).toHaveBeenCalledOnce();
  });

  // ---------------------------------------------------------------------------
  // Adding a layout and saving must call assignLayouts with the correct args.
  // ---------------------------------------------------------------------------
  test('Save calls assignLayouts after adding a layout', async () => {
    const onSave = vi.fn();
    const onClose = vi.fn();
    const user = userEvent.setup();

    renderModal({ onSave, onClose });
    await screen.findByRole('dialog', { name: /assign layouts/i });

    await user.click(screen.getByRole('button', { name: /add layout/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(assignLayouts).toHaveBeenCalledWith(mockDisplay.displayGroupId, [42], []);
    expect(onSave).toHaveBeenCalledOnce();
    expect(onClose).toHaveBeenCalledOnce();
  });

  // ---------------------------------------------------------------------------
  // API errors must surface in the modal's error slot.
  // ---------------------------------------------------------------------------
  test('shows error when assignLayouts rejects', async () => {
    vi.mocked(assignLayouts).mockRejectedValueOnce(new Error('Server error'));
    const user = userEvent.setup();

    renderModal();
    await screen.findByRole('dialog', { name: /assign layouts/i });

    await user.click(screen.getByRole('button', { name: /add layout/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Failed to assign layouts.');
  });

  // ---------------------------------------------------------------------------
  // While saving the Save button must be disabled and show "Saving…" to prevent
  // double-submission. Use a controlled promise to assert the in-flight state.
  // ---------------------------------------------------------------------------
  test('Save button is disabled and shows pending label while saving', async () => {
    let resolve!: () => void;
    const pending = new Promise<void>((res) => {
      resolve = res;
    });
    vi.mocked(assignLayouts).mockReturnValueOnce(pending);

    const user = userEvent.setup();
    renderModal();
    await screen.findByRole('dialog', { name: /assign layouts/i });

    await user.click(screen.getByRole('button', { name: /add layout/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(screen.getByRole('button', { name: /saving/i })).toBeDisabled();

    resolve();
    await waitFor(() =>
      expect(screen.queryByRole('button', { name: /saving/i })).not.toBeInTheDocument(),
    );
  });
});
