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

import { screen, within } from '@testing-library/react';
import type * as ReactI18nextModule from 'react-i18next';
import type * as RechartsModule from 'recharts';
import { vi, afterEach, beforeEach, describe, test, expect } from 'vitest';

import {
  buildNewsItem,
  buildStatusDashboardData,
  EMPTY_DISPLAY_TABLE,
  FIXED_NOW,
} from './fixtures/statusDashboard';
import { getSection, renderStatusDashboardPage } from './helpers/renderStatusDashboardPage';
import { mockDashboardDisplays, mockStatusDashboard } from './mocks/statusDashboardApi';

import { testQueryClient } from '@/setupTests';
import type { LatestNewsItem } from '@/types/dashboard';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/dashboardApi', () => ({
  fetchStatusDashboard: vi.fn(),
}));
vi.mock('@/services/displaysApi', () => ({
  fetchDisplays: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('recharts', async (importOriginal) => {
  const actual = await importOriginal<typeof RechartsModule>();
  const { cloneElement } = await import('react');
  return {
    ...actual,
    ResponsiveContainer: ({ children }: { children: React.ReactElement }) =>
      cloneElement(children, { width: 500, height: 300 } as Record<string, unknown>),
  };
});
// "{{count}} days ago" would otherwise reach the page as the raw key, which can't be
// asserted (§8). This t fills in the placeholders the way i18next does.
vi.mock('react-i18next', async (importOriginal) => {
  const actual = await importOriginal<typeof ReactI18nextModule>();
  const t = (key: string, opts?: Record<string, unknown>) =>
    key.replace(/{{(\w+)}}/g, (_, name: string) => String(opts?.[name] ?? ''));
  return { ...actual, useTranslation: () => ({ t, i18n: { language: 'en' } }) };
});

// =============================================================================
// Helpers local to this file
// =============================================================================

const renderWithNews = (latestNews: LatestNewsItem[]) => {
  mockStatusDashboard(buildStatusDashboardData({ latestNews }));
  return renderStatusDashboardPage();
};

const newsSection = () => within(getSection('Latest News'));

// =============================================================================
// Tests
// =============================================================================

describe('Status Dashboard - Latest News', () => {
  // "Today" / "N days ago" are worked out from the current time, so the clock is pinned.
  // The calculation uses milliseconds, not calendar dates, so the browser/CMS timezone gap
  // (§4.5) cannot move an article into a different bucket; no timezone case is needed.
  beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    vi.setSystemTime(FIXED_NOW);
    testQueryClient.clear();
    vi.clearAllMocks();
    mockDashboardDisplays(EMPTY_DISPLAY_TABLE);
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  test('each news item shows its title', async () => {
    renderWithNews([buildNewsItem({ title: 'CMS 4.5 released' })]);

    await screen.findByRole('heading', { name: 'CMS 4.5 released' });
  });

  test('HTML in a news description is shown as plain text', async () => {
    renderWithNews([buildNewsItem({ description: '<p>Hello <b>world</b></p>' })]);

    await screen.findByText('Hello world');
  });

  // Checks only that markup never reaches the page. It is NOT a proof against script
  // execution: JSDOM never loads images, so an onerror handler can't fire here even
  // though it would in a browser, so script execution needs a manual browser check.
  test('markup inside a news description never appears on the page as elements', async () => {
    renderWithNews([buildNewsItem({ description: '<img src="x"><b>Bold</b> text' })]);

    const description = await screen.findByText('Bold text');
    expect(description.innerHTML).toBe('Bold text');
  });

  test('"Full Article" opens the link in a new tab without access to the CMS window', async () => {
    renderWithNews([buildNewsItem({ link: 'https://xibosignage.com/news/1' })]);

    const link = await screen.findByRole('link', { name: /full article/i });
    expect(link).toHaveAttribute('href', 'https://xibosignage.com/news/1');
    expect(link).toHaveAttribute('target', '_blank');
    expect(link).toHaveAttribute('rel', 'noopener noreferrer');
  });

  test('a news item without a link has no "Full Article" link', async () => {
    renderWithNews([buildNewsItem({ title: 'No link here', link: '' })]);

    await screen.findByRole('heading', { name: 'No link here' });
    expect(screen.queryByRole('link', { name: /full article/i })).not.toBeInTheDocument();
  });

  // FIXED_NOW is 2026-10-14 12:00 UTC.
  test.each([
    ['2026-10-14T09:00:00Z', 'Today'],
    ['2026-10-13T09:00:00Z', '1 day ago'],
    ['2026-10-09T09:00:00Z', '5 days ago'],
    ['2026-09-14T09:00:00Z', '1 month ago'],
    ['2026-08-10T09:00:00Z', '2 months ago'],
  ])('an article dated %s shows "%s"', async (date, expected) => {
    renderWithNews([buildNewsItem({ title: 'Dated', date })]);

    await screen.findByRole('heading', { name: 'Dated' });
    expect(newsSection().getByText(expected)).toBeInTheDocument();
  });

  test('several news items appear in the order the server sent them', async () => {
    renderWithNews([
      buildNewsItem({ title: 'First', link: 'https://xibosignage.com/1' }),
      buildNewsItem({ title: 'Second', link: 'https://xibosignage.com/2' }),
      buildNewsItem({ title: 'Third', link: 'https://xibosignage.com/3' }),
    ]);

    await screen.findByRole('heading', { name: 'First' });
    const titles = newsSection()
      .getAllByRole('heading', { level: 4 })
      .map((h) => h.textContent);
    expect(titles).toEqual(['First', 'Second', 'Third']);
  });

  test('with no news, the section says "No news available."', async () => {
    renderWithNews([]);

    await screen.findByText('No news available.');
  });

  // Rows are keyed by link, so two empty links share a React key (xibosignage/xibo#4042).
  test('two news items without links both still appear', async () => {
    renderWithNews([
      buildNewsItem({ title: 'A', link: '' }),
      buildNewsItem({ title: 'B', link: '' }),
    ]);

    await screen.findByRole('heading', { name: 'A' });
    expect(screen.getByRole('heading', { name: 'B' })).toBeInTheDocument();
  });
});
