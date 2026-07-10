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
import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

import Settings from '../../Settings';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

// The current user for Settings page tests — Settings reads/updates this via
// useUserContext() to refresh the bootstrap payload after a settings save.
export const mockUser: User = {
  userId: 1,
  userName: 'TestUser',
  userTypeId: 1,
};

// Call testQueryClient.clear() and vi.clearAllMocks() in beforeEach.
export const renderSettingsPage = () =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter>
          <Settings />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
