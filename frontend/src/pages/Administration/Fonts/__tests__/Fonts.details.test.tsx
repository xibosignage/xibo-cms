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
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockFont, SINGLE_FONT } from './fixtures/font';
import { renderFontsPage } from './helpers/renderFontsPage';
import { mockFetchFonts } from './mocks/fontApi';

import { testQueryClient } from '@/setupTests';
import type { Font } from '@/types/font';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/fontApi');

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Fonts', path: '/administration/fonts' }]),
}));

vi.mock('@/components/ui/modals/Modal');

// The real modal uses FontFace / document.fonts, which jsdom lacks — its
// content is covered by the details modal tests. The stub exposes the props
// the page passes so the routing can be asserted.
vi.mock('../components/FontDetailsModal', () => ({
  default: ({
    onClose,
    fontId,
    font,
  }: {
    onClose: () => void;
    fontId: number;
    font: Font | null;
  }) => (
    <div role="dialog" aria-label={font?.name}>
      <p>Font id: {fontId}</p>
      <button onClick={onClose}>Close</button>
    </div>
  ),
}));

// =============================================================================
// Helpers
// =============================================================================

const waitForPageReady = () => screen.findByText(mockFont.fileName);

// =============================================================================
// Tests
// =============================================================================

describe('Fonts page - details', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchFonts(SINGLE_FONT);
  });

  test('Details quick action opens the details modal for the font', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await waitForPageReady();

    // Before the dropdown opens there is exactly one Details button (the quick action).
    await user.click(screen.getByRole('button', { name: /^details$/i }));

    const dialog = await screen.findByRole('dialog', { name: mockFont.name });
    expect(dialog).toHaveTextContent(`Font id: ${mockFont.id}`);
  });

  test('Details from the more-actions dropdown opens the same modal', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /more actions/i }));

    // The dropdown is portaled to the end of <body>, so its Details item is the
    // last match (the quick action renders inside the table first).
    // Product Question: Relies on Details appearing twice per row; update if the duplicate is
    // removed.
    const detailsButtons = await screen.findAllByRole('button', { name: /^details$/i });
    expect(detailsButtons).toHaveLength(2);
    await user.click(detailsButtons[detailsButtons.length - 1]!);

    await screen.findByRole('dialog', { name: mockFont.name });
  });

  test('Close shuts the details modal', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /^details$/i }));
    await screen.findByRole('dialog', { name: mockFont.name });

    await user.click(screen.getByRole('button', { name: /^close$/i }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog', { name: mockFont.name })).not.toBeInTheDocument();
    });
  });
});
