/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import { QueryClientProvider } from '@tanstack/react-query';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';

import { mockFont, mockFontDetails } from '../fixtures/font';
import { mockFetchFontBlob, mockFetchFontDetails } from '../mocks/fontApi';

import FontDetailsModal from '@/pages/Administration/Fonts/components/FontDetailsModal';
import { fetchFontBlob, fetchFontDetails } from '@/services/fontApi';
import { testQueryClient } from '@/setupTests';
import type { Font, FontDetails } from '@/types/font';
import { formatFileSize } from '@/utils/formatters';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/fontApi');
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Browser API stubs — jsdom has no FontFace, document.fonts or blob URLs
// =============================================================================

const fakeFace = { family: 'xibo-preview-1' };
const fontsAdd = vi.fn();
const fontsDelete = vi.fn();
const createObjectURL = vi.fn(() => 'blob:font');
const revokeObjectURL = vi.fn();

const originalFontFace = (globalThis as { FontFace?: unknown }).FontFace;
const originalCreateObjectURL = URL.createObjectURL;
const originalRevokeObjectURL = URL.revokeObjectURL;

// =============================================================================
// Helpers
// =============================================================================

const onClose = vi.fn();

const renderModal = (font: Font | null = mockFont) =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <FontDetailsModal onClose={onClose} fontId={mockFont.id} font={font} />
    </QueryClientProvider>,
  );

const waitForDetails = () => screen.findByText('Full Name');

// =============================================================================
// Tests
// =============================================================================

describe('FontDetailsModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();

    (globalThis as { FontFace?: unknown }).FontFace = vi.fn(function FontFace() {
      return { load: vi.fn().mockResolvedValue(fakeFace) };
    });
    Object.defineProperty(document, 'fonts', {
      value: { add: fontsAdd, delete: fontsDelete },
      configurable: true,
    });
    URL.createObjectURL = createObjectURL;
    URL.revokeObjectURL = revokeObjectURL;

    mockFetchFontDetails(mockFontDetails);
    mockFetchFontBlob();
  });

  afterEach(() => {
    // Unmount first: the modal's effect cleanup calls document.fonts.delete.
    cleanup();
    (globalThis as { FontFace?: unknown }).FontFace = originalFontFace;
    delete (document as { fonts?: unknown }).fonts;
    URL.createObjectURL = originalCreateObjectURL;
    URL.revokeObjectURL = originalRevokeObjectURL;
  });

  // ---------------------------------------------------------------------------
  // Title
  // ---------------------------------------------------------------------------

  test('title is the font name', async () => {
    renderModal();

    expect(screen.getByRole('heading', { name: mockFont.name })).toBeInTheDocument();
    await waitForDetails();
  });

  test('title falls back to "Font Details" when no font is supplied', async () => {
    renderModal(null);

    expect(screen.getByRole('heading', { name: 'Font Details' })).toBeInTheDocument();
    await waitForDetails();
  });

  // ---------------------------------------------------------------------------
  // Loading
  // ---------------------------------------------------------------------------

  test('shows a loading indicator while the details load', async () => {
    let resolveDetails!: (details: FontDetails) => void;
    vi.mocked(fetchFontDetails).mockReturnValue(
      new Promise<FontDetails>((resolve) => {
        resolveDetails = resolve;
      }),
    );
    renderModal();

    expect(screen.getByText('Loading...')).toBeInTheDocument();
    expect(fetchFontDetails).toHaveBeenCalledWith(mockFont.id);

    resolveDetails(mockFontDetails);
    await waitForDetails();
  });

  // ---------------------------------------------------------------------------
  // Content
  // ---------------------------------------------------------------------------

  test('lists every detail returned by the server, with "—" for empty values', async () => {
    renderModal();
    await waitForDetails();

    for (const key of Object.keys(mockFontDetails)) {
      expect(screen.getByText(key)).toBeInTheDocument();
    }
    expect(screen.getByText('Roboto Regular')).toBeInTheDocument();
    expect(screen.getByText('2.137')).toBeInTheDocument();
    expect(screen.getByText('Google')).toBeInTheDocument();
    // 'Subfamily ID' is '' in the fixture
    expect(screen.getByText('—')).toBeInTheDocument();
  });

  test('shows the file name and formatted size when the font is supplied', async () => {
    renderModal();
    await waitForDetails();

    expect(screen.getByText(mockFont.fileName)).toBeInTheDocument();
    expect(screen.getByText(formatFileSize(mockFont.size))).toBeInTheDocument();
  });

  test('shows the preview text once the font face loads', async () => {
    renderModal();

    await screen.findByText('The quick brown fox jumps over the lazy dog');
    expect(fetchFontBlob).toHaveBeenCalledWith(mockFont.id);
    expect(fontsAdd).toHaveBeenCalledWith(fakeFace);
  });

  test('shows "Preview not available" when the font file fails to load', async () => {
    vi.mocked(fetchFontBlob).mockRejectedValue(new Error('Network Error'));
    renderModal();

    await screen.findByText('Preview not available');
    expect(fetchFontBlob).toHaveBeenCalledWith(mockFont.id);
    expect(
      screen.queryByText('The quick brown fox jumps over the lazy dog'),
    ).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Error handling
  // ---------------------------------------------------------------------------

  test('shows an error message when the details fail to load', async () => {
    vi.mocked(fetchFontDetails).mockRejectedValue(new Error('Server error'));
    renderModal();

    await screen.findByText('Failed to load font details.');
    expect(fetchFontDetails).toHaveBeenCalledWith(mockFont.id);
  });

  // ---------------------------------------------------------------------------
  // Closing
  // ---------------------------------------------------------------------------

  test('Close calls onClose', async () => {
    const user = userEvent.setup();
    renderModal();
    await waitForDetails();

    await user.click(screen.getByRole('button', { name: 'Close' }));

    expect(onClose).toHaveBeenCalledTimes(1);
  });

  test('unmounting removes the font face and revokes the blob URL', async () => {
    const { unmount } = renderModal();
    await screen.findByText('The quick brown fox jumps over the lazy dog');

    unmount();

    await waitFor(() => {
      expect(fontsDelete).toHaveBeenCalledWith(fakeFace);
    });
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:font');
  });
});
