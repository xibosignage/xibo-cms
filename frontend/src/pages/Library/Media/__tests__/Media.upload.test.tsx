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
import type React from 'react';
import { vi, beforeEach } from 'vitest';

import { mockMediaData, renderMediaPage } from './mediaTestUtils';

import { testQueryClient } from '@/setupTests';

// -----------------------------------------------------------------------------
// Module mocks
// -----------------------------------------------------------------------------

vi.mock('@/pages/Library/Media/hooks/useMediaFilterOptions', () => ({
  useMediaFilterOptions: vi.fn().mockReturnValue({ filterOptions: [], isLoading: false }),
}));
vi.mock('../hooks/useMediaData');

// -----------------------------------------------------------------------------
// Mock: @/services/mediaApi
//
// When a file is added to the queue, the hook immediately starts uploading it
// in the background.
//
// We replace each upload function with a promise that never settles
// (new Promise(() => {})).
// -----------------------------------------------------------------------------
vi.mock('@/services/mediaApi', () => ({
  uploadMedia: vi.fn().mockReturnValue(new Promise(() => {})),
  uploadMediaFromUrl: vi.fn().mockReturnValue(new Promise(() => {})),
  updateMedia: vi.fn().mockReturnValue(new Promise(() => {})),
  uploadThumbnail: vi.fn().mockReturnValue(new Promise(() => {})),
  deleteMedia: vi.fn().mockResolvedValue(undefined),
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

// The Media page saves and loads user preferences (column order, page size, etc.)
// from the server via /user/pref - fake to return "no saved preferences".
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

// =============================================================================
// Helpers
// =============================================================================

// openAddMediaModal: clicks the "Add Media" button and waits for the modal to
// appear. Returns the modal element so callers can assert against it if needed.
const openAddMediaModal = async () => {
  const btn = await screen.findByRole('button', { name: 'Add Media' });
  fireEvent.click(btn);
  return screen.findByRole('dialog', { name: 'Add Media' });
};

// getFileInput: finds the hidden <input type="file"> element.
// The file input is invisible in the real UI (the user clicks a styled button
// that triggers it), but in tests we interact with it directly via fireEvent.
const getFileInput = () => document.querySelector('input[type="file"]') as HTMLInputElement;

// makeFile: creates a fake File object for testing.
const makeFile = (name = 'chucknorris.png', type = 'image/png') =>
  new File(['content'], name, { type });

// =============================================================================
// Tests
// =============================================================================

describe('Media page — file upload', () => {
  // ---------------------------------------------------------------------------
  // beforeEach: runs before every test.
  // Clears cache, resets mocks, and sets the default empty-success data state.
  // ---------------------------------------------------------------------------
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockMediaData({
      data: { rows: [], totalCount: 0 },
      isFetching: false,
      isError: false,
      error: null,
    });
  });

  // ===========================================================================
  // Group 1: Modal open/close
  // ===========================================================================

  // ---------------------------------------------------------------------------
  // Clicking "Add Media" should show the upload modal.
  // ---------------------------------------------------------------------------
  test('Add Media button opens the upload modal', async () => {
    renderMediaPage();

    // openAddMediaModal clicks the button and waits for the dialog to appear
    const modal = await openAddMediaModal();

    expect(modal).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Done button is disabled when no files have been added
  // ---------------------------------------------------------------------------
  test('Done button is disabled when no files have been added', async () => {
    renderMediaPage();
    await openAddMediaModal();

    expect(screen.getByRole('button', { name: 'Done' })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Clicking Cancel should clear the queue and close the modal immediately.
  //
  // queryByRole returns null (instead of throwing) when the element is not
  // found — useful for asserting absence.
  // ---------------------------------------------------------------------------
  test('Cancel button closes the modal', async () => {
    renderMediaPage();
    await openAddMediaModal();

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

    // Modal should be gone
    expect(screen.queryByRole('dialog', { name: 'Add Media' })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Done button closes the modal when queue has files
  //
  // After adding a file, clicking Done should close the modal. The upload
  // continues in the background.
  // ---------------------------------------------------------------------------
  test('Done button closes the modal when queue has files', async () => {
    renderMediaPage();
    await openAddMediaModal();

    // Add a file to enable the Done button
    fireEvent.change(getFileInput(), { target: { files: [makeFile()] } });

    // Click Done — handleStartUpload
    fireEvent.click(await screen.findByRole('button', { name: 'Done' }));

    // Wait for the modal to close
    await waitFor(() => {
      expect(screen.queryByRole('dialog', { name: 'Add Media' })).not.toBeInTheDocument();
    });
  });

  // ===========================================================================
  // Group 2: File selection
  // ===========================================================================

  // ---------------------------------------------------------------------------
  // Adding a file enables the Done button
  //
  // The Done button is disabled when queue.length === 0. Adding a file should
  // make the queue non-empty and therefore enable the button.
  //   We fire a 'change' event directly on the hidden file input with a
  //   fake files array. The component's onChange handler reads event.target.files
  //   exactly the same way it would with a real picker.
  // ---------------------------------------------------------------------------
  test('Done button becomes enabled after a file is selected', async () => {
    renderMediaPage();
    await openAddMediaModal();

    // Trigger the file input change event with one fake file
    fireEvent.change(getFileInput(), { target: { files: [makeFile()] } });

    // Done button should now be enabled
    expect(await screen.findByRole('button', { name: 'Done' })).not.toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // After adding a file, the queue renders an editable name input for it,
  // pre-filled with the file's name. This lets the user rename before uploading.
  //
  // findByDisplayValue looks for an input whose current value matches the
  // given string.
  // ---------------------------------------------------------------------------
  test('file name appears as an editable input in the queue after selection', async () => {
    renderMediaPage();
    await openAddMediaModal();

    fireEvent.change(getFileInput(), { target: { files: [makeFile('chucknorris.png')] } });

    // The name input should be visible and pre-filled with the filename
    expect(await screen.findByDisplayValue('chucknorris.png')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Adding two files at once should create two separate queue rows, each with
  // its own name input.
  // ---------------------------------------------------------------------------
  test('all selected files appear as separate queue items', async () => {
    renderMediaPage();
    await openAddMediaModal();

    // Pass an array of two files to the file input
    fireEvent.change(getFileInput(), {
      target: { files: [makeFile('alpha.png'), makeFile('beta.mp4', 'video/mp4')] },
    });

    // Both file names should be present as separate editable inputs
    expect(await screen.findByDisplayValue('alpha.png')).toBeInTheDocument();
    expect(await screen.findByDisplayValue('beta.mp4')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // A Remove button appears for each queued item
  //
  // Each item in the queue has a small "Remove File" button (an X / minus icon)
  // that lets the user cancel a specific upload before it starts.  //
  //
  // ---------------------------------------------------------------------------
  test('each queued file has a Remove File button', async () => {
    renderMediaPage();
    await openAddMediaModal();

    fireEvent.change(getFileInput(), {
      target: { files: [makeFile('a.png'), makeFile('b.png')] },
    });

    // There should be exactly two Remove File buttons — one for each file
    // findAllByTitle looks for elements whose title attribute matches.
    // The Remove button has title="Remove File".
    const removeButtons = await screen.findAllByTitle('Remove File');

    // We add two files and expect exactly two Remove buttons.
    expect(removeButtons).toHaveLength(2);
  });

  // ---------------------------------------------------------------------------
  // Removing the only file disables Done again
  //
  // We add one file (enables Done), then click the Remove button (empties
  // the queue), and check Done is disabled again.
  // ---------------------------------------------------------------------------
  test('Done button is disabled again after removing the only queued file', async () => {
    renderMediaPage();
    await openAddMediaModal();

    // Add one file
    fireEvent.change(getFileInput(), { target: { files: [makeFile()] } });

    // Wait for the queue item (and its Remove button) to appear
    await screen.findByRole('button', { name: 'Done' });

    // Click the Remove button to delete the only queued file
    fireEvent.click(screen.getByTitle('Remove File'));

    // Done should be disabled again — queue is now empty
    expect(screen.getByRole('button', { name: 'Done' })).toBeDisabled();
  });

  // ===========================================================================
  // Group 3: URL upload
  // ===========================================================================

  test('submitting a URL via the modal enables the Done button', async () => {
    renderMediaPage();
    await openAddMediaModal();

    const urlInput = await screen.findByPlaceholderText('https://www.exampleurl.com/funnycat4364');
    fireEvent.change(urlInput, { target: { value: 'https://example.com/video.mp4' } });
    fireEvent.click(screen.getByRole('button', { name: 'Upload' }));

    // URL was added to the queue → Done should now be enabled
    expect(screen.getByRole('button', { name: 'Done' })).not.toBeDisabled();
  });
});
