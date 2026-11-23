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

// mocks/userApi must be imported before anything that imports userApi.
import './mocks/userApi';

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { EMPTY_FONT_TABLE, mockFont, SINGLE_FONT } from './fixtures/font';
import { renderFontsPage } from './helpers/renderFontsPage';
import { mockFetchFonts } from './mocks/fontApi';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/fontApi');

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Fonts', path: '/administration/fonts' }]),
}));

vi.mock('@/components/ui/modals/Modal');

// The real dropzone is covered by the upload modal tests — here we only need
// the page wiring: FontModals passes onSuccess={handleRefresh} and onClose.
vi.mock('../components/UploadFontModal', () => ({
  default: ({ onClose, onSuccess }: { onClose: () => void; onSuccess: () => void }) => (
    <div role="dialog" aria-label="Upload Font">
      <button onClick={onSuccess}>Simulate success</button>
      <button onClick={onClose}>Close</button>
    </div>
  ),
}));

// =============================================================================
// Helpers
// =============================================================================

const openUploadModal = async (user: UserEvent) => {
  await user.click(await screen.findByRole('button', { name: /upload font/i }));
  return screen.findByRole('dialog', { name: /upload font/i });
};

// =============================================================================
// Tests
// =============================================================================

describe('Fonts page - upload font', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('clicking Upload Font opens the Upload Font modal', async () => {
    const user = userEvent.setup();
    mockFetchFonts(EMPTY_FONT_TABLE);
    renderFontsPage();

    await openUploadModal(user);
  });

  // ---------------------------------------------------------------------------
  // onSuccess only invalidates the ['font'] query — the modal stays open until
  // the user clicks Done/Cancel.
  // ---------------------------------------------------------------------------
  test('table is refreshed after a successful upload and the modal stays open', async () => {
    const user = userEvent.setup();
    mockFetchFonts(EMPTY_FONT_TABLE);
    renderFontsPage();
    await screen.findByText('No results found.');

    const dialog = await openUploadModal(user);

    mockFetchFonts(SINGLE_FONT);
    await user.click(screen.getByRole('button', { name: /simulate success/i }));

    await screen.findByText(mockFont.fileName);
    expect(dialog).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // No call-count assertion: React Query decides when to refetch. Refreshing
  // on success is covered by the test above.
  // ---------------------------------------------------------------------------
  test('closing the modal returns to the page with the font list intact', async () => {
    const user = userEvent.setup();
    mockFetchFonts(SINGLE_FONT);
    renderFontsPage();
    await screen.findByText(mockFont.fileName);

    await openUploadModal(user);

    await user.click(screen.getByRole('button', { name: /^close$/i }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog', { name: /upload font/i })).not.toBeInTheDocument();
    });
    expect(screen.getByText(mockFont.fileName)).toBeInTheDocument();
  });
});
