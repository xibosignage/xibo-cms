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

import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import SpotRow from '../../components/SpotRow';
import type { SpotUploadState } from '../../hooks/usePlaylistDashboardActions';
import {
  buildSpotWidget,
  LOCKED_HIDDEN_WIDGET,
  makeFile,
  REGION_SPECIFIC_WIDGET,
  SUBPLAYLIST_WIDGET,
} from '../fixtures/playlistDashboard';
import { getFileInput } from '../helpers/renderPlaylistDashboardPage';

import type { SpotWidget } from '@/types/dashboard';

// =============================================================================
// Helpers local to this file
// =============================================================================

const onSelectFile = vi.fn();
const onDeleteWidget = vi.fn();
const onPreview = vi.fn();

const renderSpot = (
  props: { spotIndex?: number; widget?: SpotWidget; uploadState?: SpotUploadState } = {},
) =>
  render(
    <SpotRow
      spotIndex={props.spotIndex ?? 0}
      widget={props.widget}
      uploadState={props.uploadState}
      onSelectFile={onSelectFile}
      onDeleteWidget={onDeleteWidget}
      onPreview={onPreview}
    />,
  );

const uploading = (overrides: Partial<SpotUploadState> = {}): SpotUploadState => ({
  fileName: 'new.png',
  fileSize: 2048,
  progress: 40,
  status: 'uploading',
  ...overrides,
});

// Which of the spot's controls are on screen, as a comparable summary.
const controls = () => ({
  selectFile: screen.queryByRole('button', { name: /select file/i }) !== null,
  delete: screen.queryByRole('button', { name: 'Delete' }) !== null,
  locked: screen.queryByText('Locked') !== null,
  preview: screen.queryByRole('img') !== null,
});

// =============================================================================
// Tests
// =============================================================================

describe('SpotRow - empty and filled spots', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('shows its spot number, counting from 1', () => {
    renderSpot({ spotIndex: 2 });

    expect(screen.getByText('3')).toBeInTheDocument();
  });

  test('an empty spot accepts a chosen file and reports it with the spot index', async () => {
    const user = userEvent.setup();
    const { container } = renderSpot({ spotIndex: 0 });
    const file = makeFile('photo.png');

    expect(screen.getByRole('button', { name: /select file/i })).toBeInTheDocument();
    await user.upload(getFileInput(container), file);

    expect(onSelectFile).toHaveBeenCalledWith(0, file);
  });

  // The input is cleared after each pick; otherwise choosing the same file again does nothing.
  test('the same file can be chosen twice in a row', async () => {
    const user = userEvent.setup();
    const { container } = renderSpot();
    const file = makeFile('photo.png');

    await user.upload(getFileInput(container), file);
    await user.upload(getFileInput(container), file);

    expect(onSelectFile).toHaveBeenCalledTimes(2);
  });

  test('a filled spot shows its file name, size and widget badge', () => {
    renderSpot({
      widget: buildSpotWidget({
        name: 'Welcome',
        mediaFiles: [
          { mediaId: 101, fileName: 'welcome.png', mediaType: 'image', fileSize: '12 KiB' },
        ],
      }),
    });

    expect(screen.getByText('welcome.png')).toBeInTheDocument();
    expect(screen.getByText('12 KiB')).toBeInTheDocument();
    expect(screen.getByText('Welcome')).toBeInTheDocument();
  });

  test('without a file, the widget’s name is shown instead', () => {
    renderSpot({
      widget: buildSpotWidget({ type: 'clock', name: 'Clock', mediaIds: [], mediaFiles: [] }),
    });

    // Once as the title (in place of a file name) and once in the type badge.
    expect(screen.getAllByText('Clock')).toHaveLength(2);
  });

  test('a library image shows its thumbnail', () => {
    renderSpot({ widget: buildSpotWidget({ mediaIds: [9001] }) });

    expect(screen.getByRole('img')).toHaveAttribute(
      'src',
      expect.stringMatching(/library\/thumbnail\/9001$/),
    );
  });

  test('content that is not a library image shows an icon, not a thumbnail', () => {
    const { unmount } = renderSpot({ widget: REGION_SPECIFIC_WIDGET });
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
    unmount();

    renderSpot({ widget: buildSpotWidget({ type: 'video' }) });
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
  });

  test('Delete appears only when the widget may be deleted, and reports which widget', async () => {
    const user = userEvent.setup();
    const { unmount } = renderSpot({ widget: buildSpotWidget({ widgetId: 7, deletable: true }) });

    await user.click(screen.getByRole('button', { name: 'Delete' }));
    expect(onDeleteWidget).toHaveBeenCalledWith(7);
    unmount();

    renderSpot({ widget: buildSpotWidget({ deletable: false }) });
    expect(screen.queryByRole('button', { name: 'Delete' })).not.toBeInTheDocument();
  });

  // The preview button is named by its image's alt text, the file name.
  test('the preview opens only for a viewable widget with media, and reports the widget', async () => {
    const user = userEvent.setup();
    const widget = buildSpotWidget();
    const { unmount } = renderSpot({ widget });

    await user.click(screen.getByRole('button', { name: 'test-widget.png' }));
    expect(onPreview).toHaveBeenCalledWith(widget);
    unmount();

    renderSpot({ widget: buildSpotWidget({ mediaIds: [], mediaFiles: [] }) });
    expect(screen.queryByRole('button', { name: 'test-widget.png' })).not.toBeInTheDocument();
  });
});

describe('SpotRow - uploads', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('while uploading, it shows the file, its size and progress, and disables its buttons', () => {
    renderSpot({ widget: buildSpotWidget(), uploadState: uploading() });

    expect(screen.getByText('new.png')).toBeInTheDocument();
    expect(screen.getByText('2 KiB')).toBeInTheDocument();
    expect(screen.getByText('40%')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /select file/i })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Delete' })).toBeDisabled();
  });

  // On an empty spot the preview's alt text is empty (there is no widget name yet), so it has
  // no "img" role and is found as an element (xibosignage/xibo#ISSUE_A11Y_LABELS).
  test('an image being uploaded shows its temporary preview', () => {
    const { container } = renderSpot({ uploadState: uploading({ blobUrl: 'blob:preview-1' }) });

    expect(container.querySelector('img')).toHaveAttribute('src', 'blob:preview-1');
  });

  test('an upload error shows the reason and no progress', () => {
    renderSpot({ uploadState: uploading({ status: 'error', error: 'File too large' }) });

    expect(screen.getByText('File too large')).toBeInTheDocument();
    expect(screen.queryByText('40%')).not.toBeInTheDocument();
  });

  // userEvent can't drag files, so the drop is fired directly (§5.2 file-drop exception).
  test('dropping a file while the spot is uploading does nothing', () => {
    const { container } = renderSpot({ widget: buildSpotWidget(), uploadState: uploading() });

    fireEvent.drop(container.firstElementChild!, { dataTransfer: { files: [makeFile()] } });

    expect(onSelectFile).not.toHaveBeenCalled();
  });
});

describe('SpotRow - locked spots', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('a sub-playlist is locked: no file choice, no Delete, and drops are ignored', () => {
    const { container } = renderSpot({ widget: SUBPLAYLIST_WIDGET });

    expect(screen.getByText('Sub-Playlist')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /select file/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Delete' })).not.toBeInTheDocument();

    fireEvent.drop(container.firstElementChild!, { dataTransfer: { files: [makeFile()] } });
    expect(onSelectFile).not.toHaveBeenCalled();
  });

  test('a spot the user may not edit offers no file choice but can still be deleted', () => {
    const { container } = renderSpot({
      widget: buildSpotWidget({ editable: false, viewable: true, deletable: true }),
    });

    expect(screen.getByText('Locked')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /select file/i })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Delete' })).toBeInTheDocument();

    fireEvent.drop(container.firstElementChild!, { dataTransfer: { files: [makeFile()] } });
    expect(onSelectFile).not.toHaveBeenCalled();
  });

  // A preview without a thumbnail is an icon-only button with no name (xibosignage/xibo#ISSUE_A11Y_LABELS), so
  // "no preview" is checked as "no buttons at all": this spot also may not be deleted.
  test('a locked spot the user may not view shows no thumbnail and no preview', () => {
    renderSpot({ widget: LOCKED_HIDDEN_WIDGET });

    expect(screen.queryByRole('img')).not.toBeInTheDocument();
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  // Current behaviour: only image/video/audio spots narrow the picker; others accept any file.
  test('the file picker matches the spot’s type for images, and is open for PDFs', () => {
    const { container, unmount } = renderSpot({ widget: buildSpotWidget({ type: 'image' }) });
    const accept = getFileInput(container).getAttribute('accept') ?? '';
    expect(accept).toContain('.png');
    expect(accept).not.toContain('.mp4');
    unmount();

    const pdf = renderSpot({ widget: buildSpotWidget({ type: 'pdf' }) });
    expect(getFileInput(pdf.container)).not.toHaveAttribute('accept');
  });

  // Each edit/view/delete combination must offer a different set of controls (§4.6 spirit).
  test('each combination of edit, view and delete permission shows different controls', () => {
    const cases = [
      { editable: true, viewable: true, deletable: true },
      { editable: false, viewable: true, deletable: true },
      { editable: false, viewable: true, deletable: false },
      { editable: false, viewable: false, deletable: false },
    ];

    const summaries = cases.map((perms) => {
      const { unmount } = renderSpot({ widget: buildSpotWidget(perms) });
      const summary = JSON.stringify(controls());
      unmount();
      return summary;
    });

    expect(new Set(summaries).size).toBe(cases.length);
  });
});
