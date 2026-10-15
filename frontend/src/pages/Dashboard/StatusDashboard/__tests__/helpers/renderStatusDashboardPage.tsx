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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

import StatusDashboard from '../../StatusDashboard';
import { mockUser } from '../fixtures/statusDashboard';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

// Renders the Status Dashboard inside all required context providers.
// Call testQueryClient.clear() and vi.clearAllMocks() in beforeEach first.
export const renderStatusDashboardPage = (user: User = mockUser) =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={user}>
        <MemoryRouter>
          <StatusDashboard />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );

// Returns the card under a section heading. The page has no landmarks, and "Display Activity"
// is used twice (xibosignage/xibo#ISSUE_A11Y_LABELS; chart card = index 0, table card = index 1), so cards
// are reached through their heading. The card is the largest ancestor that holds no other heading of the same
// level or higher, which follows the page structure rather than its styling. Article titles
// inside the news card are a lower level, so they don't stop the walk. Scopes queries only.
export const getSection = (heading: string | RegExp, index = 0): HTMLElement => {
  const h = screen.getAllByRole('heading', { name: heading })[index]!;
  const level = Number(h.tagName.slice(1));
  const peers = Array.from({ length: level }, (_, i) => `h${i + 1}`).join(',');
  let section = h;
  while (section.parentElement && section.parentElement.querySelectorAll(peers).length === 1) {
    section = section.parentElement;
  }
  return section;
};

// Returns the stat card whose label matches ("Active Displays", "Library Size", "Active Users").
// The label and the value are siblings inside the card, so read the value from the parent.
export const getStatCardValue = (label: string): HTMLElement =>
  screen.getByText(label).previousElementSibling as HTMLElement;

// Returns the Display Activity table.
export const getDisplayTable = (): HTMLElement =>
  within(getSection('Display Activity', 1)).getByRole('table');

// DataTable's sort toggle is a bare <div onClick> inside the header, with no role, name or
// keyboard access, so it can't be selected by role (xibosignage/xibo#ISSUE_SORT_TOGGLE).
// It is the element that holds the header's sort icon, the only icon in the header.
export const getSortToggle = (columnName: string | RegExp): HTMLElement => {
  const header = within(getDisplayTable()).getByRole('columnheader', { name: columnName });
  const icon = header.querySelector('svg');
  if (!icon?.parentElement) throw new Error(`Column "${String(columnName)}" has no sort toggle`);
  return icon.parentElement;
};
