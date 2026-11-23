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

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  mockFont,
  mockFontManagerUser,
  mockNoFeatureUser,
  mockUser,
  SINGLE_FONT,
  TWO_FONTS,
} from './fixtures/font';
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

// =============================================================================
// Helpers
// =============================================================================

const waitForPageReady = () => screen.findByText(mockFont.fileName);

// =============================================================================
// Tests — column picker
// =============================================================================

describe('Fonts page - column visibility', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchFonts(SINGLE_FONT);
  });

  test('"ID" column header is not visible by default', async () => {
    renderFontsPage();
    await waitForPageReady();

    expect(screen.queryByRole('columnheader', { name: /^id$/i })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Name has enableHiding: false, so it has no checkbox in the picker.
  // ---------------------------------------------------------------------------
  test('clicking the Columns button opens the column picker with toggleable columns', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    for (const label of [
      /^id$/i,
      /^file name$/i,
      /^family name$/i,
      /^created$/i,
      /^modified$/i,
      /^modified by$/i,
      /^size$/i,
    ]) {
      expect(screen.getByRole('checkbox', { name: label })).toBeInTheDocument();
    }
    expect(screen.queryByRole('checkbox', { name: /^name$/i })).not.toBeInTheDocument();
  });

  test('checking a hidden column checkbox shows that column in the table', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    const checkbox = screen.getByRole('checkbox', { name: /^id$/i });
    expect(checkbox).not.toBeChecked();

    await user.click(checkbox);

    await screen.findByRole('columnheader', { name: /^id$/i });
  });

  test('unchecking a visible column checkbox hides that column from the table', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await screen.findByRole('columnheader', { name: /^family name$/i });

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    const checkbox = screen.getByRole('checkbox', { name: /^family name$/i });
    expect(checkbox).toBeChecked();

    await user.click(checkbox);

    expect(screen.queryByRole('columnheader', { name: /^family name$/i })).not.toBeInTheDocument();
  });
});

// =============================================================================
// Tests — permission gates
// Upload Font: hasFeature(user, 'font.add')
// Delete row action + Delete Selected: hasFeature(user, 'font.delete')
// Details / Download: always present
// =============================================================================

describe('Fonts page — actions (superAdmin)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchFonts(SINGLE_FONT);
  });

  test('Upload Font button is visible', async () => {
    renderFontsPage(mockUser);

    expect(await screen.findByRole('button', { name: /upload font/i })).toBeEnabled();
  });

  test('Delete action is visible in the more-actions dropdown', async () => {
    const user = userEvent.setup();
    renderFontsPage(mockUser);
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /more actions/i }));

    await screen.findByRole('button', { name: /^delete$/i });
  });
});

describe('Fonts page — actions (user with font.add and font.delete)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchFonts(SINGLE_FONT);
  });

  test('Upload Font button is visible', async () => {
    renderFontsPage(mockFontManagerUser);

    expect(await screen.findByRole('button', { name: /upload font/i })).toBeEnabled();
  });

  test('Delete action is visible in the more-actions dropdown', async () => {
    const user = userEvent.setup();
    renderFontsPage(mockFontManagerUser);
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /more actions/i }));

    await screen.findByRole('button', { name: /^delete$/i });
  });
});

describe('Fonts page — actions (user without font features)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchFonts(SINGLE_FONT);
  });

  test('Upload Font button is hidden', async () => {
    renderFontsPage(mockNoFeatureUser);
    await waitForPageReady();

    expect(screen.queryByRole('button', { name: /upload font/i })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Before the dropdown opens, each row has exactly one Details and one
  // Download quick-action button.
  // Product Question: Details and Download appear twice per row (icon + More actions menu). Keep
  // both? This test relies on the duplicate; update it once decided.
  // ---------------------------------------------------------------------------
  test('Details and Download quick actions are visible', async () => {
    renderFontsPage(mockNoFeatureUser);
    await waitForPageReady();

    expect(screen.getByRole('button', { name: /^details$/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^download$/i })).toBeInTheDocument();
  });

  test('Delete action is hidden in the more-actions dropdown', async () => {
    const user = userEvent.setup();
    renderFontsPage(mockNoFeatureUser);
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /more actions/i }));

    // Anchor: dropdown adds a second Details button once it is open.
    // Product Question: Relies on Details appearing twice; update if the duplicate is removed.
    expect(await screen.findAllByRole('button', { name: /^details$/i })).toHaveLength(2);
    expect(screen.queryByRole('button', { name: /^delete$/i })).not.toBeInTheDocument();
  });
});

describe('Fonts page — bulk actions', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchFonts(TWO_FONTS);
  });

  test('Delete Selected appears for a user with font.delete after selecting a row', async () => {
    const user = userEvent.setup();
    renderFontsPage(mockFontManagerUser);
    await waitForPageReady();

    const checkboxes = screen.getAllByRole('checkbox', { name: /select row/i });
    await user.click(checkboxes[0]!);

    expect(await screen.findByRole('button', { name: /delete selected/i })).toBeEnabled();
  });

  test('Delete Selected is never shown for a user without font.delete', async () => {
    renderFontsPage(mockNoFeatureUser);
    await waitForPageReady();

    expect(screen.queryByRole('button', { name: /delete selected/i })).not.toBeInTheDocument();
  });
});
