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
import type { UserEvent } from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';

import MediaDashboard from '../../MediaDashboard';
import { mockUser } from '../fixtures/mediaDashboard';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

// Renders the Media Manager Dashboard inside all required context providers.
// Call testQueryClient.clear() and vi.clearAllMocks() in beforeEach first.
export const renderMediaDashboardPage = (user: User = mockUser) =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={user}>
        <MemoryRouter>
          <MediaDashboard />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );

// The value shown on a stat card ("Library Count", "Library Size"). The label and the value
// are siblings inside the card.
export const getStatCardValue = (label: string): HTMLElement =>
  screen.getByText(label).previousElementSibling as HTMLElement;

// The card under a heading ("No. of Media Items", "Unused Media", ...). The page has no
// landmarks, so a section is reached through its heading; this scopes queries and doesn't
// select the element under test.
export const getSection = (heading: string): HTMLElement =>
  screen.getByRole('heading', { name: heading }).parentElement as HTMLElement;

// The table under "Unused Media" or "Unreleased Media".
export const getTableUnder = (heading: 'Unused Media' | 'Unreleased Media'): HTMLElement =>
  within(getSection(heading)).getByRole('table');

// Opens the Tidy Library dialog from its card and returns the dialog. The dialog has no
// accessible name (TidyLibraryModal passes no title, xibosignage/xibo#ISSUE_DIALOG_NAME), so it is found by role
// alone; callers confirm it is the right one through its "Tidy Library" heading.
export const openTidyDialog = async (user: UserEvent): Promise<HTMLElement> => {
  await user.click(await screen.findByRole('button', { name: /tidy library/i }));
  return screen.findByRole('dialog');
};

// The dialog's confirm button. It is also labelled "Tidy Library" (and "Tidying…" while
// running), so it is always looked up inside the dialog, never on the page.
export const getTidyConfirm = (dialog: HTMLElement): HTMLElement =>
  within(dialog).getByRole('button', { name: /^(tidy library|tidying…)$/i });
