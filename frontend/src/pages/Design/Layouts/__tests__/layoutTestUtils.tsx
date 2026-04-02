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

import Layouts from '../Layouts';
import type { useLayoutActions } from '../hooks/useLayoutActions';
import { useLayoutData } from '../hooks/useLayoutData';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { Layout } from '@/types/layout';
import type { User } from '@/types/user';

// -----------------------------------------------------------------------------
// One realistic layout row.
// layoutId: 861001 is asserted in delete tests.
// name/layout: 'My Layout' is asserted in render and delete modal tests.
// publishedStatus: 'Published' - a published layout is the default state.
// userPermissions.delete: 1 - so the Delete button is visible.
// -----------------------------------------------------------------------------
export const mockLayout: Layout = {
  layoutId: 861001,
  campaignId: 10,
  layout: 'My Layout',
  publishedStatusId: 1,
  publishedStatus: 'Published',
  modifiedDt: '2026-03-01 10:00:00',
  status: 1,
  retired: 0,
  width: 1920,
  height: 1080,
  orientation: 'landscape',
  duration: 60,
  enableStat: true,
  tags: [],
  owner: 'TestUser',
  ownerId: 1,
  folderId: 1,
  permissionsFolderId: 1,
  userPermissions: { view: 1, edit: 1, delete: 1, modifyPermissions: 1 },
};

// A draft (non-published) layout - used for actions that only appear when a
// layout is NOT yet published (Publish, Discard).
export const mockDraftLayout: Layout = {
  ...mockLayout,
  layoutId: 2,
  publishedStatusId: 2,
  publishedStatus: 'Draft',
  layout: 'Draft Layout',
};

export const SINGLE_DRAFT_LAYOUT = {
  data: { rows: [mockDraftLayout], totalCount: 1 },
  isFetching: false,
  isError: false,
  error: null,
};

// -----------------------------------------------------------------------------
// The default logged-in user for most Layouts page tests.
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
// useLayoutData return shapes
// -----------------------------------------------------------------------------

// A table with one layout row.
export const SINGLE_LAYOUT = {
  data: { rows: [mockLayout], totalCount: 1 },
  isFetching: false,
  isError: false,
  error: null,
};

// An empty table - used for initial load and empty state tests.
export const EMPTY_LAYOUT_TABLE = {
  data: { rows: [], totalCount: 0 },
  isFetching: false,
  isError: false,
  error: null,
};

// -----------------------------------------------------------------------------
// Typed mock helpers - centralise casts so they don't repeat across tests.
// -----------------------------------------------------------------------------
export type UseLayoutReturn = ReturnType<typeof useLayoutData>;
export type UseLayoutActionsReturn = ReturnType<typeof useLayoutActions>;

// Returns a fresh useLayoutActions mock value for every beforeEach call.
export const defaultLayoutActions = (
  overrides: Partial<UseLayoutActionsReturn> = {},
): UseLayoutActionsReturn =>
  ({
    // Loading state
    isAssigning: false,
    isCloning: false,
    isDeleting: false,
    isDiscarding: false,
    isExporting: false,
    isPublishing: false,
    deleteError: null,
    setDeleteError: vi.fn(),
    // Action handlers
    confirmDelete: vi.fn(),
    confirmPublish: vi.fn(),
    handleCheckoutLayout: vi.fn(),
    handleConfirmAssign: vi.fn(),
    handleConfirmClone: vi.fn(),
    handleConfirmDiscard: vi.fn(),
    handleConfirmMove: vi.fn(),
    handleCreateLayout: vi.fn(),
    handleExportLayout: vi.fn(),
    handleJumpToCampaigns: vi.fn(),
    handleJumpToMedia: vi.fn(),
    handleJumpToPlaylists: vi.fn(),
    handleOpenLayout: vi.fn(),
    ...overrides,
  }) as UseLayoutActionsReturn;

export const mockLayoutData = (overrides: unknown) => {
  vi.mocked(useLayoutData).mockReturnValue(overrides as UseLayoutReturn);
};

// -----------------------------------------------------------------------------
// Opens the Edit Layout modal for mockLayout by clicking the Edit row action.
// Wait for the row text first - the table is behind an isHydrated guard and
// only renders after fetchUserPreference resolves.
// -----------------------------------------------------------------------------
export const openEditModal = async () => {
  await screen.findByText(mockLayout.layout);
  fireEvent.click(screen.getByTitle('Edit'));
  return screen.findByRole('dialog');
};

// -----------------------------------------------------------------------------
// Render wrapper - provides all required context providers.
// -----------------------------------------------------------------------------
export const renderLayoutsPage = () => {
  testQueryClient.setQueryData(['userPref', 'layout_page'], null);
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter>
          <Layouts />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};
