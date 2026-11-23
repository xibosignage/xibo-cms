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

// =============================================================================
// Test type: Page integration — opening a report from its card
// =============================================================================

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, afterEach, beforeEach, describe, test, expect } from 'vitest';

import { ALL_REPORTS } from './fixtures/allReports';
import { findCard, renderAllReportsPage } from './helpers/renderAllReportsPage';
import { mockFetchReports } from './mocks/reportApi';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal();
  return { ...(actual as object), useNavigate: () => mockNavigate };
});
vi.mock('@/services/reportApi', () => ({
  fetchReports: vi.fn().mockResolvedValue({}),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
// The install folder is read at import; pretend to be a /cms/ install.
vi.mock('@/config/publicPath', async (importOriginal) => ({
  ...((await importOriginal()) as object),
  withPublicPath: (path: string) => '/cms/' + path.replace(/^\//, ''),
}));

// =============================================================================
// Tests
// =============================================================================

describe('All Reports page - navigation', () => {
  const realLocation = window.location;

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchReports(ALL_REPORTS);
    Object.defineProperty(window, 'location', {
      configurable: true,
      writable: true,
      value: { ...realLocation, href: '' },
    });
  });

  afterEach(() => {
    Object.defineProperty(window, 'location', { configurable: true, value: realLocation });
  });

  test('a report with a page in the app opens it', { tags: ['smoke'] }, async () => {
    const user = userEvent.setup({ delay: null });
    renderAllReportsPage();

    await user.click(await findCard('Proof of Play'));

    expect(mockNavigate).toHaveBeenCalledTimes(1);
    expect(mockNavigate).toHaveBeenCalledWith('/reporting/proof-of-play');
  });

  test('a report without one opens the classic form under the install folder', async () => {
    const user = userEvent.setup({ delay: null });
    renderAllReportsPage();

    await user.click(await findCard('Custom Legacy Report'));

    expect(window.location.href).toBe('/cms/report/form/customReport');
    expect(mockNavigate).not.toHaveBeenCalled();
  });

  test('Enter on a focused card opens it', async () => {
    const user = userEvent.setup({ delay: null });
    renderAllReportsPage();
    const card = await findCard('Session History');

    card.focus();
    await user.keyboard('{Enter}');

    expect(mockNavigate).toHaveBeenCalledWith('/reporting/session-history');
  });

  test('Space on a focused card opens it', async () => {
    const user = userEvent.setup({ delay: null });
    renderAllReportsPage();
    const card = await findCard('Session History');

    card.focus();
    await user.keyboard(' ');

    expect(mockNavigate).toHaveBeenCalledWith('/reporting/session-history');
  });

  // The button's click bubbles to the card; the report must open once.
  test('clicking the "View …" text opens the report once', async () => {
    const user = userEvent.setup({ delay: null });
    renderAllReportsPage();
    await findCard('Proof of Play');

    await user.click(screen.getAllByRole('button', { name: 'View Report' })[0]!);

    expect(mockNavigate).toHaveBeenCalledTimes(1);
  });
});
