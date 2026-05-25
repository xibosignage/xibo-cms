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
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';

import DisplayProfile from '../DisplayProfile';
import type { useDisplayProfileActions } from '../hooks/useDisplayProfileActions';
import { useDisplayProfileData } from '../hooks/useDisplayProfileData';

import { UserProvider } from '@/context/UserContext';
import { fetchDisplayProfile } from '@/services/displayProfileApi';
import type { FetchDisplayProfileResponse } from '@/services/displayProfileApi';
import { testQueryClient } from '@/setupTests';
import type { DisplayProfile as DisplayProfileType } from '@/types/displayProfile';
import type { User } from '@/types/user';

// -----------------------------------------------------------------------------
// One realistic display profile row.
// displayProfileId: 1 is asserted in delete tests.
// name: 'Android Profile' is asserted in render and modal tests.
// type: 'android', isDefault: 1
// -----------------------------------------------------------------------------
export const mockDisplayProfile: DisplayProfileType = {
  displayProfileId: 1,
  name: 'Android Profile',
  type: 'android',
  isDefault: 1,
};

export const SINGLE_DISPLAY_PROFILE: FetchDisplayProfileResponse = {
  rows: [mockDisplayProfile],
  totalCount: 1,
};

export const EMPTY_DISPLAY_PROFILE_TABLE: FetchDisplayProfileResponse = {
  rows: [],
  totalCount: 0,
};

// -----------------------------------------------------------------------------
// The default logged-in user for display profile tests.
// -----------------------------------------------------------------------------
export const mockUser: User = {
  userId: 1,
  userName: 'TestUser',
  userTypeId: 1,
  groupId: 1,
  features: { 'folder.view': true },
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

// -----------------------------------------------------------------------------
// Typed mock helpers
// -----------------------------------------------------------------------------
export type UseDisplayProfileReturn = ReturnType<typeof useDisplayProfileData>;
export type UseDisplayProfileActionsReturn = ReturnType<typeof useDisplayProfileActions>;

// Mocks useDisplayProfileData directly (e.g. for search/pagination tests).
export const mockDisplayProfileData = (rawData: FetchDisplayProfileResponse) => {
  vi.mocked(useDisplayProfileData).mockReturnValue({
    data: rawData,
    isFetching: false,
    isError: false,
    error: null,
  } as UseDisplayProfileReturn);
};

// Makes fetchDisplayProfile return the data you provide (for integration-style tests).
export const mockFetchDisplayProfile = (rawData: FetchDisplayProfileResponse) => {
  vi.mocked(fetchDisplayProfile).mockResolvedValue(rawData);
};

// Returns a fresh useDisplayProfileActions mock value.
export const defaultDisplayProfileActions = (
  overrides: Partial<UseDisplayProfileActionsReturn> = {},
): UseDisplayProfileActionsReturn =>
  ({
    isDeleting: false,
    deleteError: null,
    setDeleteError: vi.fn(),
    confirmDelete: vi.fn(),
    isCopying: false,
    confirmCopy: vi.fn(),
    ...overrides,
  }) as UseDisplayProfileActionsReturn;

// -----------------------------------------------------------------------------
// Opens the Edit Display Profile modal for mockDisplayProfile.
// Waits for the row, clicks Edit, then waits for the form to finish loading
// (past the "Loading settings…" spinner) before returning.
// -----------------------------------------------------------------------------
export const openEditModal = async () => {
  await screen.findByText(mockDisplayProfile.name);
  fireEvent.click(screen.getByTitle('Edit'));
  await screen.findByPlaceholderText('Enter name');
};

// -----------------------------------------------------------------------------
// Render wrapper - provides all required context providers.
// -----------------------------------------------------------------------------
export const renderDisplayProfilePage = () => {
  testQueryClient.setQueryData(['userPref', 'displayProfile_page'], null);
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter>
          <DisplayProfile />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};
