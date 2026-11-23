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

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import React from 'react';
import { MemoryRouter } from 'react-router-dom';

import Resolution from '../Resolutions';

import { UserProvider } from '@/context/UserContext';
import type { Resolution as ResolutionType } from '@/types/resolution';
import type { User } from '@/types/user';

export const mockUser: User = {
  userId: 1,
  userName: 'TestUser',
  userTypeId: 1,
  groupId: 1,
  features: {},
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

// Provide a fresh QueryClient and Router for each test.
// Pass { user } to render as someone other than the default super admin.
export const renderWithClient = (
  ui: React.ReactElement = <Resolution />,
  { user = mockUser }: { user?: User } = {},
) => {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });

  // Pre-seed the user preference cache with null (no saved prefs).
  // This lets the real useTableState run its full loading flow: the query
  // resolves immediately as success, the hydration effect fires, and
  // isHydrated becomes true — so tests will catch regressions if that
  // flow ever breaks in production.
  queryClient.setQueryData(['userPref', 'resolution_page'], null);

  return render(
    <QueryClientProvider client={queryClient}>
      <UserProvider initialUser={user}>
        <MemoryRouter>{ui}</MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};

const fullPermissions = { view: 1, edit: 1, delete: 1, modifyPermissions: 1 } as const;

export const mockResolutions: ResolutionType[] = [
  {
    resolutionId: 1,
    resolution: '1080p',
    width: 1920,
    height: 1080,
    enabled: true,
    userPermissions: fullPermissions,
  },
  {
    resolutionId: 2,
    resolution: '720p',
    width: 1280,
    height: 720,
    enabled: true,
    userPermissions: fullPermissions,
  },
];
