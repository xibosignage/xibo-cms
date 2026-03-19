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
import type React from 'react';
import { useDropzone } from 'react-dropzone';
import type * as ReactDropzone from 'react-dropzone';
import { vi, beforeEach } from 'vitest';

import { mockMediaData, renderMediaPage } from './mediaTestUtils';

import type * as FolderApi from '@/services/folderApi';
import { testQueryClient } from '@/setupTests';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/services/folderApi', async (importOriginal) => {
  const actual = await importOriginal<typeof FolderApi>();
  return {
    ...actual,
    fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
    selectFolder: vi.fn(),
    fetchFolderById: vi.fn().mockResolvedValue({
      id: 1,
      text: 'Root',
      type: 'root',
      parentId: 0,
      isRoot: 1,
      children: null,
      ownerId: 1,
      ownerName: 'MockUser',
    }),
    fetchFolderTree: vi.fn().mockResolvedValue([]),
    searchFolders: vi.fn().mockResolvedValue([]),
  };
});
vi.mock('@/hooks/useDebounce');
vi.mock('@/pages/Library/Media/hooks/useMediaFilterOptions');
vi.mock('../hooks/useMediaData');
vi.mock('@/services/mediaApi', () => ({
  uploadMedia: vi.fn().mockReturnValue(new Promise(() => {})),
  uploadMediaFromUrl: vi.fn().mockReturnValue(new Promise(() => {})),
  updateMedia: vi.fn().mockReturnValue(new Promise(() => {})),
  uploadThumbnail: vi.fn().mockReturnValue(new Promise(() => {})),
  deleteMedia: vi.fn().mockResolvedValue(undefined),
}));

// Replaces only useDropzone so each test can control isDragActive and onDrop.
// All other react-dropzone exports remain real.
vi.mock('react-dropzone', async (importOriginal) => {
  const actual = await importOriginal<typeof ReactDropzone>();
  return {
    ...actual,
    useDropzone: vi.fn(),
  };
});

const makeFile = (name = 'chucknorris.png', type = 'image/png') =>
  new File(['content'], name, { type });

// Sets up the useDropzone mock. Returns getOnDrop() to retrieve the captured
// onDrop callback after the component has rendered and registered it.
const mockDropzone = ({ isDragActive = false } = {}) => {
  let capturedOnDrop: ((files: File[]) => void) | undefined;
  vi.mocked(useDropzone).mockImplementation((options: Parameters<typeof useDropzone>[0]) => {
    if (options?.onDrop) capturedOnDrop = options.onDrop as (files: File[]) => void;
    return {
      getRootProps: () => ({}),
      getInputProps: () => ({ ref: { current: null } }),
      isDragActive,
      open: vi.fn(),
    } as unknown as ReturnType<typeof useDropzone>;
  });
  return { getOnDrop: () => capturedOnDrop };
};

// =============================================================================
// Tests
// =============================================================================

describe('Media page — global drag-and-drop', () => {
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

  test('drag indicator is visible when a file is dragged over the page', async () => {
    mockDropzone({ isDragActive: true });

    renderMediaPage();

    expect(await screen.findByText(/Upload files to/i)).toBeInTheDocument();
  });

  test('dropping files on the page opens the modal and adds files to the queue', async () => {
    const { getOnDrop } = mockDropzone();

    renderMediaPage();

    // Wait for the page to render — confirms the onDrop callback is captured.
    await screen.findByRole('button', { name: 'Add Media' });

    const onDrop = getOnDrop();
    expect(onDrop).toBeDefined();

    onDrop!([makeFile('dropped.png')]);

    expect(await screen.findByRole('dialog', { name: 'Add Media' })).toBeInTheDocument();

    // The dropped file's name appears as an input value in the upload queue.
    expect(await screen.findByDisplayValue('dropped.png')).toBeInTheDocument();
  });

  test('dropping no files does not open the modal', async () => {
    const { getOnDrop } = mockDropzone();

    renderMediaPage();

    await screen.findByRole('button', { name: 'Add Media' });

    const onDrop = getOnDrop();
    expect(onDrop).toBeDefined();

    onDrop!([]);

    // waitFor confirms the modal truly never appears, not just "not yet".
    await waitFor(() => {
      expect(screen.queryByRole('dialog', { name: 'Add Media' })).not.toBeInTheDocument();
    });
  });

  test('dropping multiple files adds all of them to the queue', async () => {
    const { getOnDrop } = mockDropzone();

    renderMediaPage();

    await screen.findByRole('button', { name: 'Add Media' });

    const onDrop = getOnDrop();
    expect(onDrop).toBeDefined();

    onDrop!([makeFile('file1.png'), makeFile('file2.png')]);

    expect(await screen.findByDisplayValue('file1.png')).toBeInTheDocument();
    expect(await screen.findByDisplayValue('file2.png')).toBeInTheDocument();
  });
});
