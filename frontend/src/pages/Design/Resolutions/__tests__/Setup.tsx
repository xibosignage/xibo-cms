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

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import React from 'react';
import { MemoryRouter } from 'react-router-dom';

import Resolution from '../Resolutions';

import { UserProvider } from '@/context/UserContext';
import type { Resolution as ResolutionType } from '@/types/resolution';
import type { User } from '@/types/user';

const mockUser: User = {
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

// Provide a fresh QueryClient and Router for each test
export const renderWithClient = (ui: React.ReactElement = <Resolution />) => {
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
      <UserProvider initialUser={mockUser}>
        <MemoryRouter>{ui}</MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};

export const mockResolutions: ResolutionType[] = [
  { resolutionId: 1, resolution: '1080p', width: 1920, height: 1080, enabled: true },
  { resolutionId: 2, resolution: '720p', width: 1280, height: 720, enabled: true },
];
