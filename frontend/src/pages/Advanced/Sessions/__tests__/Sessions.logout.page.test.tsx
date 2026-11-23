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
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import Sessions from '../Sessions';

import { mockSessionsList } from './fixtures/session';

import { UserProvider } from '@/context/UserContext';
import { fetchSession, logoutSession } from '@/services/sessionApi';
import { testQueryClient } from '@/setupTests';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { Session } from '@/types/session';

// The session data hook, table state and logout action all run for real here; only the HTTP
// services are mocked. The service mock is declared here, not taken from Setup.tsx, so the page
// and this test share one logoutSession.
vi.mock('@/services/sessionApi', () => ({
  fetchSession: vi.fn(),
  logoutSession: vi.fn(),
}));

vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Sessions', path: '/advanced/sessions' }]),
}));

vi.mock('@/components/ui/modals/Modal');

// Setup.tsx is not imported (its vi.mock of sessionApi would replace the one above); the
// sessions come from the side-effect-free fixtures module instead.
const [ALICE, , CHARLIE] = mockSessionsList as [Session, Session, Session];

const currentUser = buildCurrentUser(PERSONAS.superAdmin, {
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
});

const renderSessionsPage = () =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={currentUser}>
        <MemoryRouter>
          <Sessions />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );

describe('Sessions page - logging out a session from its row', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchSession).mockResolvedValue({ rows: [ALICE, CHARLIE], totalCount: 2 });
  });

  test(
    'logging out a session from its row logs out that user and removes the session from the list',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      // Once the session is logged out the grid's refresh no longer returns it.
      vi.mocked(logoutSession).mockImplementation(async () => {
        vi.mocked(fetchSession).mockResolvedValue({ rows: [CHARLIE], totalCount: 1 });
      });
      renderSessionsPage();
      await screen.findByText(ALICE.userName!);

      const aliceRow = screen.getByRole('row', { name: new RegExp(ALICE.userName!) });
      await user.click(within(aliceRow).getByRole('button', { name: 'Logout' }));
      // The confirmation modal has no title, so its confirm button is the handle on it.
      await user.click(await screen.findByRole('button', { name: 'Yes, Logout' }));

      await waitFor(() => expect(logoutSession).toHaveBeenCalledTimes(1));
      expect(logoutSession).toHaveBeenCalledWith(ALICE.userId);
      await waitFor(() => {
        expect(screen.queryByText(ALICE.userName!)).not.toBeInTheDocument();
      });
      expect(screen.getByText(CHARLIE.userName!)).toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Yes, Logout' })).not.toBeInTheDocument();
    },
  );
});
