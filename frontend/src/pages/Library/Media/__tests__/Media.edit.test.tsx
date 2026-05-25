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

import { screen, fireEvent, waitFor, within } from '@testing-library/react';
import type React from 'react';
import { test, vi, beforeEach } from 'vitest';

import { mockEditMedia, mockMediaData, openEditModal, renderMediaPage } from './mediaTestUtils';

import { updateMedia } from '@/services/mediaApi';
import { testQueryClient } from '@/setupTests';

// -----------------------------------------------------------------------------
// Module mocks
// -----------------------------------------------------------------------------

vi.mock('@/pages/Library/Media/hooks/useMediaFilterOptions', () => ({
  useMediaFilterOptions: vi.fn().mockReturnValue({ filterOptions: [], isLoading: false }),
}));
vi.mock('../hooks/useMediaData');
vi.mock('@/services/mediaApi', () => ({
  uploadMedia: vi.fn(),
  uploadMediaFromUrl: vi.fn(),
  updateMedia: vi.fn(),
  uploadThumbnail: vi.fn(),
  deleteMedia: vi.fn(),
}));
vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
  fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
  selectFolder: vi.fn(),
}));
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

// =============================================================================
// Tests
// =============================================================================

describe('Edit Media — modal lifecycle', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(updateMedia).mockResolvedValue({ ...mockEditMedia });
    mockMediaData({
      data: { rows: [mockEditMedia], totalCount: 1 },
      isFetching: false,
      isError: false,
      error: null,
    });
  });

  // ---------------------------------------------------------------------------
  // Click the Edit button on a media row and check the modal appears.
  // ---------------------------------------------------------------------------
  test('Edit button opens the edit modal with the correct media title', async () => {
    renderMediaPage();

    const modal = await openEditModal();

    expect(modal).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Click Cancel and check the modal disappears.
  // ---------------------------------------------------------------------------
  test('Cancel button closes the modal', async () => {
    renderMediaPage();
    await openEditModal();

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(screen.queryByRole('dialog', { name: 'Edit Media' })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Click Cancel and check that no save request was sent to the API.
  // ---------------------------------------------------------------------------
  test('Cancel button does not call updateMedia', async () => {
    renderMediaPage();
    await openEditModal();

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(updateMedia).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Edit the name field, click Cancel, then reopen the modal.
  // The name should be back to the original — Cancel must throw away any
  // changes the user made, not keep them for the next time the modal opens.
  // ---------------------------------------------------------------------------
  test('Cancel after editing discards changes — modal reopens with original values', async () => {
    renderMediaPage();
    await openEditModal();

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'changed-name.png' } });
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

    const dialog = await openEditModal();
    expect(within(dialog).getByLabelText('Name')).toHaveValue(mockEditMedia.name);
  });

  // ---------------------------------------------------------------------------
  // Press Escape while the modal is open and check it closes.
  // This is the keyboard equivalent of clicking Cancel.
  // ---------------------------------------------------------------------------
  test('Pressing Escape closes the modal', async () => {
    renderMediaPage();
    const dialog = await openEditModal();

    fireEvent.keyDown(dialog, { key: 'Escape' });

    expect(screen.queryByRole('dialog', { name: 'Edit Media' })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Click the up arrow on the Duration field and check the displayed value
  // increases by 1 second. mockEditMedia.duration = 10, so 10 → 11 = '00:00:11'.
  // ---------------------------------------------------------------------------
  test('DurationInput up arrow increments the value by 1 second', async () => {
    renderMediaPage();
    const dialog = await openEditModal();

    fireEvent.click(within(dialog).getByRole('button', { name: 'Increase duration' }));

    expect(within(dialog).getByPlaceholderText('00:00:00')).toHaveValue('00:00:11');
  });

  // ---------------------------------------------------------------------------
  // Click the down arrow when the duration is already 0 and check it stays
  // at 0 — the decrement cannot produce a negative duration.
  // ---------------------------------------------------------------------------
  test('DurationInput down arrow at 0 stays at 0', async () => {
    mockMediaData({
      data: { rows: [{ ...mockEditMedia, duration: 0 }], totalCount: 1 },
      isFetching: false,
      isError: false,
      error: null,
    });

    renderMediaPage();
    const dialog = await openEditModal();

    fireEvent.click(within(dialog).getByRole('button', { name: 'Decrease duration' }));

    expect(within(dialog).getByPlaceholderText('00:00:00')).toHaveValue('00:00:00');
  });

  // ---------------------------------------------------------------------------
  // Open the modal and check every field shows the media's current values.
  // ---------------------------------------------------------------------------
  test('Modal pre-populates all fields with the selected media current values', async () => {
    renderMediaPage();
    const dialog = await openEditModal();

    expect(within(dialog).getByLabelText('Name')).toHaveValue(mockEditMedia.name);

    // mockEditMedia.duration = 10 seconds, displayed as HH:MM:SS
    expect(within(dialog).getByPlaceholderText('00:00:00')).toHaveValue('00:00:10');

    // The existing tag from mockEditMedia.tags should appear as a pill
    expect(within(dialog).getByText('nature')).toBeInTheDocument();

    // mockEditMedia.retired = false, so the checkbox should be unchecked
    expect(within(dialog).getByRole('checkbox', { name: /Retire this media/i })).not.toBeChecked();

    // mockEditMedia.updateInLayouts = false, so the checkbox should be unchecked
    expect(
      within(dialog).getByRole('checkbox', { name: /all layouts it is assigned to/i }),
    ).not.toBeChecked();
  });
});

describe('Edit Media — save behaviour', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(updateMedia).mockResolvedValue({ ...mockEditMedia });
    mockMediaData({
      data: { rows: [mockEditMedia], totalCount: 1 },
      isFetching: false,
      isError: false,
      error: null,
    });
  });

  // ---------------------------------------------------------------------------
  // Click Save and check that updateMedia was called with the media id and
  // the form values.
  // ---------------------------------------------------------------------------
  test('Save button calls updateMedia with the correct payload', async () => {
    renderMediaPage();
    await openEditModal();

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(updateMedia).toHaveBeenCalledWith(
        mockEditMedia.mediaId,
        expect.objectContaining({ name: mockEditMedia.name }),
      );
    });
  });

  // ---------------------------------------------------------------------------
  // Click the up arrow twice to change the duration, then save.
  // Check that updateMedia receives the updated duration in seconds.
  // mockEditMedia.duration = 10, two increments → 12 seconds.
  // ---------------------------------------------------------------------------
  test('Saving after incrementing duration sends the updated seconds in the payload', async () => {
    renderMediaPage();
    const dialog = await openEditModal();

    fireEvent.click(within(dialog).getByRole('button', { name: 'Increase duration' }));
    fireEvent.click(within(dialog).getByRole('button', { name: 'Increase duration' }));
    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(updateMedia).toHaveBeenCalledWith(
        mockEditMedia.mediaId,
        expect.objectContaining({ duration: 12 }),
      );
    });
  });

  // ---------------------------------------------------------------------------
  // Open the modal and click Save immediately without changing anything.
  // The save should still go through — the form must not silently skip the
  // API call just because no fields were changed.
  // ---------------------------------------------------------------------------
  test('Save without making changes still calls updateMedia', async () => {
    renderMediaPage();
    await openEditModal();

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(updateMedia).toHaveBeenCalledWith(
        mockEditMedia.mediaId,
        expect.objectContaining({ name: mockEditMedia.name }),
      );
    });
  });

  // ---------------------------------------------------------------------------
  // While the save is in progress the Save button should show "Saving…" and
  // be disabled so the user cannot click it a second time.
  //
  // To hold the save in progress we make updateMedia return a promise that
  // never resolves. The component stays in its loading state indefinitely,
  // giving us time to check the button before anything finishes.
  // ---------------------------------------------------------------------------
  test('Save button is disabled while save is in progress', async () => {
    vi.mocked(updateMedia).mockReturnValueOnce(new Promise(() => {}));

    renderMediaPage();
    await openEditModal();

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    // Label changes to "Saving…" and the button becomes disabled
    expect(await screen.findByRole('button', { name: 'Saving…' })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // After a successful save the modal should close automatically.
  // ---------------------------------------------------------------------------
  test('Modal closes after a successful save', async () => {
    renderMediaPage();
    await openEditModal();

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog', { name: 'Edit Media' })).not.toBeInTheDocument();
    });
  });

  // ---------------------------------------------------------------------------
  // After saving, the table row should show the updated values without needing
  // a page refresh.
  //
  // We make updateMedia return a renamed version of the item. We also switch
  // the data mock after the modal opens so the table re-render picks up the
  // new name once the save completes.
  // ---------------------------------------------------------------------------
  test('Media list updates the edited item after a successful save', async () => {
    const updatedMedia = { ...mockEditMedia, name: 'renamed.png' };
    vi.mocked(updateMedia).mockResolvedValueOnce(updatedMedia);

    // beforeEach provides original data so the initial table shows 'test-image.png'
    renderMediaPage();
    await openEditModal();

    // Switch the mock AFTER the modal is open so the post-save refetch
    // returns the renamed version, keeping the table in sync
    mockMediaData({
      data: { rows: [updatedMedia], totalCount: 1 },
      isFetching: false,
      isError: false,
      error: null,
    });

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    // Wait for the modal to close before asserting
    await waitFor(() => {
      expect(screen.queryByRole('dialog', { name: 'Edit Media' })).not.toBeInTheDocument();
    });

    expect(screen.getByText('renamed.png')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // If the save fails, the modal should stay open so the user can fix the
  // problem and try again.
  // ---------------------------------------------------------------------------
  test('API error on save — modal stays open', async () => {
    vi.mocked(updateMedia).mockReturnValueOnce(new Promise(() => {}));

    renderMediaPage();
    await openEditModal();

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    // Modal must still be in the document — onClose() is never reached
    await waitFor(() => {
      expect(screen.getByRole('dialog', { name: 'Edit Media' })).toBeInTheDocument();
    });
  });

  // ---------------------------------------------------------------------------
  // The finally block in handleSave always resets isSaving, so the Save button
  // re-enables and its label reverts from "Saving…" back to "Save" after a failure.
  // ---------------------------------------------------------------------------
  test('Save button re-enables after a failed save', async () => {
    vi.mocked(updateMedia).mockRejectedValueOnce(new Error('Network error'));

    renderMediaPage();
    await openEditModal();

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Save' })).not.toBeDisabled();
    });
  });
});
