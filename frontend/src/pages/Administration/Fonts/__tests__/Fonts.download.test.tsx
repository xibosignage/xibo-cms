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

// notifyMock and mocks/userApi must be imported before anything that imports
// Notification or userApi.
import '@/testUtils/notifyMock';
import './mocks/userApi';

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockFont, SINGLE_FONT } from './fixtures/font';
import { renderFontsPage } from './helpers/renderFontsPage';
import { mockDownloadFont, mockFetchFonts } from './mocks/fontApi';

import { downloadFont } from '@/services/fontApi';
import { testQueryClient } from '@/setupTests';
import { mockNotifyError } from '@/testUtils/notifyMock';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/fontApi');

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Fonts', path: '/administration/fonts' }]),
}));

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

const waitForPageReady = () => screen.findByText(mockFont.fileName);

// =============================================================================
// Tests
// =============================================================================

describe('Fonts page - download', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchFonts(SINGLE_FONT);
    mockDownloadFont();
  });

  test('Download quick action downloads the font', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /^download$/i }));

    expect(downloadFont).toHaveBeenCalledWith(mockFont.id);
    expect(mockNotifyError).not.toHaveBeenCalled();
  });

  test('Download from the more-actions dropdown downloads the font', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /more actions/i }));

    // Dropdown is portaled after the table, so its Download item is the last match.
    // Product Question: Relies on Download appearing twice per row; update if the duplicate is
    // removed.
    const downloadButtons = await screen.findAllByRole('button', { name: /^download$/i });
    expect(downloadButtons).toHaveLength(2);
    await user.click(downloadButtons[downloadButtons.length - 1]!);

    expect(downloadFont).toHaveBeenCalledWith(mockFont.id);
  });
});
