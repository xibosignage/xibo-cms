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
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import AuditTrail from '../AuditTrail';

import { createMockAuditLog, mockAuditLogList } from './fixtures/auditLog';

import { UserProvider } from '@/context/UserContext';
import { fetchAuditTrail, type FetchAuditTrailRequest } from '@/services/auditTrailApi';
import { testQueryClient } from '@/setupTests';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';

// Unlike the other AuditTrail page tests, the data hook and table state run for real here, so the
// filter values travel the whole way from the filter panel to the service call.
vi.mock('@/services/auditTrailApi', () => ({
  fetchAuditTrail: vi.fn(),
  exportAuditTrail: vi.fn(),
}));

vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Audit Trail', path: '/advanced/audit-trail' }]),
}));

// Setup.tsx is not imported: its own vi.mock of auditTrailApi replaces the one above, and the page
// then calls a different fetchAuditTrail from the one this test configures. The data comes from the
// side-effect-free fixtures module instead.
// A second 'User' entry whose message doesn't match, so only the message filter can hide it.
const USER_LOGGED_IN = createMockAuditLog({ logId: 4, entity: 'User', message: 'User logged in' });
const AUDIT_LOGS = [...mockAuditLogList, USER_LOGGED_IN];

const currentUser = buildCurrentUser(PERSONAS.superAdmin, {
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
});

const renderAuditTrailPage = () =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={currentUser}>
        <MemoryRouter>
          <AuditTrail />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );

describe('AuditTrail page - filtering the trail from the filter panel', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // The fake server honours the entity and message filters, so the grid shows what was asked for.
    vi.mocked(fetchAuditTrail).mockImplementation(async (request?: FetchAuditTrailRequest) => {
      const rows = AUDIT_LOGS.filter(
        (log) =>
          (!request?.entity || log.entity === request.entity) &&
          (!request?.message || log.message.includes(request.message)),
      );
      return { rows, totalCount: rows.length };
    });
  });

  test(
    'filtering by entity and message requests only matching entries and shows them',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      renderAuditTrailPage();
      await screen.findByText('Layout saved');

      await user.click(screen.getByRole('button', { name: /filters/i }));
      // The panel's text filters have a visible label that isn't linked to the input
      // (components/ui/InputFilter.tsx), so they have no accessible name; the placeholder is the only handle.
      await user.type(await screen.findByPlaceholderText('Entity'), 'User');
      await user.type(screen.getByPlaceholderText('Message'), 'updated');

      await waitFor(() => {
        expect(fetchAuditTrail).toHaveBeenLastCalledWith({
          start: 0,
          length: 10,
          sortBy: 'logId',
          sortDir: 'desc',
          signal: expect.any(AbortSignal),
          user: '',
          entity: 'User',
          entityId: '',
          ipAddress: '',
          message: 'updated',
        });
      });
      await screen.findByText('User updated');
      // The other 'User' entry only drops out because of the message filter.
      await waitFor(() => {
        expect(screen.queryByText(USER_LOGGED_IN.message)).not.toBeInTheDocument();
      });
      expect(screen.queryByText('Layout saved')).not.toBeInTheDocument();
    },
  );
});
