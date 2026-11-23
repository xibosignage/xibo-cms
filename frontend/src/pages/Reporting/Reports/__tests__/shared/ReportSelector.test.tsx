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
// Test type: Component — the report switcher on every report page
// =============================================================================

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { vi, afterEach, beforeEach, describe, test, expect } from 'vitest';

import { buildReport, mockReport } from '../../../__tests__/fixtures/report';
import ReportSelector from '../../shared/ReportSelector';

import { UserProvider } from '@/context/UserContext';
import { fetchReports } from '@/services/reportApi';
import { testQueryClient } from '@/setupTests';
import { deferred } from '@/testUtils/deferred';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { ReportsByCategory } from '@/types/report';

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => ({
  ...((await importOriginal()) as object),
  useNavigate: () => mockNavigate,
}));
vi.mock('@/services/reportApi', () => ({ fetchReports: vi.fn() }));
// The install folder is read at import; pretend to be a /cms/ install.
vi.mock('@/config/publicPath', async (importOriginal) => ({
  ...((await importOriginal()) as object),
  withPublicPath: (path: string) => '/cms/' + path.replace(/^\//, ''),
}));

const summary = buildReport({
  name: 'summaryReport',
  description: 'Summary Report',
  url: '/reporting/summary',
});
const hidden = buildReport({ name: 'hiddenPop', description: 'Hidden Report', hidden: 1 });
const legacy = buildReport({ name: 'customPop', description: 'Custom Report', url: undefined });
const bandwidth = buildReport({
  name: 'bandwidth',
  description: 'Bandwidth Report',
  category: 'Display',
});

const REPORTS: ReportsByCategory = {
  'Proof of Play': [summary, mockReport, hidden, legacy],
  Display: [bandwidth],
};

// A plain user, so the super-admin-only SSP card stays out of the list.
const plainUser = buildCurrentUser(PERSONAS.user, { features: { 'report.view': true } });

const renderSelector = (currentReportName = 'summaryReport') =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={plainUser}>
        <MemoryRouter>
          <ReportSelector currentReportName={currentReportName} fallbackLabel="Fallback Title" />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );

const openList = async (user: UserEvent, label: string) => {
  await user.click(await screen.findByRole('button', { name: label }));
};

describe('Report switcher', () => {
  const realLocation = window.location;

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchReports).mockReset().mockResolvedValue(REPORTS);
    Object.defineProperty(window, 'location', {
      configurable: true,
      writable: true,
      value: { ...realLocation, href: '' },
    });
  });

  afterEach(() => {
    Object.defineProperty(window, 'location', { configurable: true, value: realLocation });
  });

  test("shows the current report's name, and the fallback until the list loads", async () => {
    const pending = deferred<ReportsByCategory>();
    vi.mocked(fetchReports).mockReturnValueOnce(pending.promise);
    renderSelector();

    expect(screen.getByRole('button', { name: 'Fallback Title' })).toBeInTheDocument();
    pending.resolve(REPORTS);
    await screen.findByRole('button', { name: 'Summary Report' });
  });

  test('lists the visible reports in the same category only', async () => {
    const user = userEvent.setup({ delay: null });
    renderSelector();

    await openList(user, 'Summary Report');

    expect(screen.getByRole('button', { name: 'Proof of Play' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Custom Report' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Hidden Report' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Bandwidth Report' })).not.toBeInTheDocument();
  });

  test('a hidden current report is still listed', async () => {
    const user = userEvent.setup({ delay: null });
    renderSelector('hiddenPop');

    await openList(user, 'Hidden Report');

    expect(screen.getAllByRole('button', { name: 'Hidden Report' })).toHaveLength(2);
  });

  test('choosing a report with an app page opens it in the app', async () => {
    const user = userEvent.setup({ delay: null });
    renderSelector();
    await openList(user, 'Summary Report');

    await user.click(screen.getByRole('button', { name: 'Proof of Play' }));

    expect(mockNavigate).toHaveBeenCalledWith('/reporting/proof-of-play');
    expect(screen.queryByRole('button', { name: 'Custom Report' })).not.toBeInTheDocument();
  });

  test('choosing a report without one opens the classic page under the install folder', async () => {
    const user = userEvent.setup({ delay: null });
    renderSelector();
    await openList(user, 'Summary Report');

    await user.click(screen.getByRole('button', { name: 'Custom Report' }));

    expect(window.location.href).toBe('/cms/report/form/customPop');
    expect(mockNavigate).not.toHaveBeenCalled();
  });

  test('choosing the current report only closes the list', async () => {
    const user = userEvent.setup({ delay: null });
    renderSelector();
    await openList(user, 'Summary Report');

    const items = screen.getAllByRole('button', { name: 'Summary Report' });
    await user.click(items[items.length - 1]!);

    expect(mockNavigate).not.toHaveBeenCalled();
    await waitFor(() =>
      expect(screen.queryByRole('button', { name: 'Proof of Play' })).not.toBeInTheDocument(),
    );
  });

  test('clicking outside closes the list', async () => {
    const user = userEvent.setup({ delay: null });
    renderSelector();
    await openList(user, 'Summary Report');

    await user.click(document.body);

    await waitFor(() =>
      expect(screen.queryByRole('button', { name: 'Proof of Play' })).not.toBeInTheDocument(),
    );
  });
});
