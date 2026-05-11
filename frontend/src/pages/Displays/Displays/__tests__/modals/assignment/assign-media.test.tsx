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
import AssignMediaModal from '../../../components/AssignMediaModal';

import { assignMedia } from '@/services/displaysApi';
import { fetchMedia } from '@/services/mediaApi';
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
  assignMedia: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/services/mediaApi', () => ({
  fetchMedia: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));

vi.mock('@/hooks/useDebounce', () => ({
  useDebounce: (v: unknown) => v,
}));

// SelectDropdown is used for the media-type filter. Replace with a native
// <select> so tests can drive filtering without custom combobox events.
vi.mock('@/components/ui/forms/SelectDropdown', () => ({
  default: ({
    value,
    options,
    onSelect,
  }: {
    value: string;
    options: { value: string; label: string }[];
    onSelect: (v: string) => void;
  }) => (
    <select
      data-testid="type-filter"
      value={value}
      onChange={(e) => onSelect(e.target.value)}
    >
      <option value="">All</option>
      {options.map((o) => (
        <option key={o.value} value={o.value}>
          {o.label}
        </option>
      ))}
    </select>
  ),
}));

// SearchAssignPanel stub — exposes Add / Remove buttons to drive toAdd/toRemove
// state without depending on table rendering or IntersectionObserver.
vi.mock('@/components/ui/SearchAssignPanel', () => ({
  SearchAssignPanel: ({
    onAddItem,
    onRemoveItem,
    assignedItems,
    extraFilters,
  }: {
    onAddItem: (item: { mediaId: number; name: string; mediaType: string }) => void;
    onRemoveItem: (item: { mediaId: number; name: string; mediaType: string }) => void;
    assignedItems: { mediaId: number; name: string; mediaType: string }[];
    extraFilters?: React.ReactNode;
  }) => (
    <div data-testid="search-assign-panel">
      {extraFilters}
      <span data-testid="assigned-count">{assignedItems.length}</span>
      <button
        onClick={() =>
          onAddItem({ mediaId: 77, name: 'Promo Video', mediaType: 'video' })
        }
      >
        Add Media
      </button>
      {assignedItems.map((m) => (
        <button key={m.mediaId} onClick={() => onRemoveItem(m)}>
          Remove {m.name}
        </button>
      ))}
    </div>
  ),
}));

vi.mock('@/pages/Library/Media/MediaConfig', () => ({
  getStatusTypeFromMediaType: vi.fn().mockReturnValue('info'),
}));

// =============================================================================
// Helpers
// =============================================================================

const renderModal = (overrides: { onClose?: () => void; onSave?: () => void } = {}) =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <AssignMediaModal
        display={{ displayGroupId: mockDisplay.displayGroupId }}
        onClose={overrides.onClose ?? vi.fn()}
        onSave={overrides.onSave ?? vi.fn()}
      />
    </QueryClientProvider>,
  );

// =============================================================================
// Tests
// =============================================================================

describe('AssignMediaModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchMedia).mockResolvedValue({ rows: [], totalCount: 0 });
  });

  // ---------------------------------------------------------------------------
  // The dialog must render with the title "Assign Files".
  // ---------------------------------------------------------------------------
  test('renders the dialog with title "Assign Files"', async () => {
    renderModal();

    await screen.findByRole('dialog', { name: /assign files/i });
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
  // With no changes, Save skips the API and calls onClose directly.
  // ---------------------------------------------------------------------------
  test('Save with no changes calls onClose without calling the API', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();

    renderModal({ onClose });
    await screen.findByRole('dialog');

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(assignMedia).not.toHaveBeenCalled();
    expect(onClose).toHaveBeenCalledOnce();
  });

  // ---------------------------------------------------------------------------
  // Adding a media item and saving must call assignMedia with the correct args.
  // ---------------------------------------------------------------------------
  test('Save calls assignMedia after adding a media item', async () => {
    const onSave = vi.fn();
    const onClose = vi.fn();
    const user = userEvent.setup();

    renderModal({ onSave, onClose });
    await screen.findByRole('dialog');

    await user.click(screen.getByRole('button', { name: /add media/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(assignMedia).toHaveBeenCalledWith(mockDisplay.displayGroupId, [77], []);
    expect(onSave).toHaveBeenCalledOnce();
    expect(onClose).toHaveBeenCalledOnce();
  });

  // ---------------------------------------------------------------------------
  // API errors must surface in the modal's error slot.
  // ---------------------------------------------------------------------------
  test('shows error when assignMedia rejects', async () => {
    vi.mocked(assignMedia).mockRejectedValueOnce(new Error('Upload error'));
    const user = userEvent.setup();

    renderModal();
    await screen.findByRole('dialog');

    await user.click(screen.getByRole('button', { name: /add media/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Failed to assign media.');
  });

  // ---------------------------------------------------------------------------
  // While saving the Save button must be disabled and show "Saving…" to prevent
  // double-submission. Use a controlled promise to assert the in-flight state.
  // ---------------------------------------------------------------------------
  test('Save button is disabled and shows pending label while saving', async () => {
    let resolve!: () => void;
    const pending = new Promise<void>((res) => { resolve = res; });
    vi.mocked(assignMedia).mockReturnValueOnce(pending);

    const user = userEvent.setup();
    renderModal();
    await screen.findByRole('dialog');

    await user.click(screen.getByRole('button', { name: /add media/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(screen.getByRole('button', { name: /saving/i })).toBeDisabled();

    resolve();
    await waitFor(() =>
      expect(screen.queryByRole('button', { name: /saving/i })).not.toBeInTheDocument(),
    );
  });
});
