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

import Templates from '../Templates';
import type { useTemplateActions } from '../hooks/useTemplateActions';

import { UserProvider } from '@/context/UserContext';
import { fetchTemplates } from '@/services/templatesApi';
import type { FetchTemplateResponse } from '@/services/templatesApi';
import { testQueryClient } from '@/setupTests';
import type { Template } from '@/types/templates';
import type { User } from '@/types/user';

// -----------------------------------------------------------------------------
// One realistic template row used across all template tests.
// Integers (status, retired, enableStat) match the real API shape.
// The API uses `layout` for the template name — there is no `name` field.
export const mockTemplate: Template = {
  layoutId: 861001,
  campaignId: 10,
  parentId: null,
  publishedStatusId: 1,
  publishedStatus: 'Published',
  publishedDate: null,
  backgroundImageId: null,
  schemaVersion: 3,
  layout: 'Splash Screen Template',
  description: 'A branded splash screen for reception screens',
  backgroundColor: '#000',
  createdDt: '2026-01-01 10:00:00',
  modifiedDt: '2026-03-01 10:00:00',
  status: 1,
  retired: 0,
  backgroundzIndex: 0,
  width: 1920,
  height: 1080,
  orientation: 'landscape',
  displayOrder: null,
  duration: 30,
  statusMessage: null,
  enableStat: 1,
  autoApplyTransitions: 0,
  code: null,
  isLocked: null,
  tags: [{ tag: 'template', tagId: 1, value: '' }],
  owner: 'TestUser',
  ownerId: 1,
  groupsWithPermissions: null,
  folderId: 1,
  permissionsFolderId: 1,
  resolutionId: 0,
  userPermissions: { view: 1, edit: 1, delete: 1, modifyPermissions: 1 },
};

// A draft (non-published) template — used for Publish, Discard, and Checkout action tests.
export const mockDraftTemplate: Template = {
  ...mockTemplate,
  layoutId: 2,
  publishedStatusId: 2,
  publishedStatus: 'Draft',
  layout: 'Draft Template',
};

export const SINGLE_DRAFT_TEMPLATE_ROWS: FetchTemplateResponse = {
  rows: [mockDraftTemplate],
  totalCount: 1,
};

// -----------------------------------------------------------------------------
// The default logged-in user for most Templates page tests.
// -----------------------------------------------------------------------------
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

// -----------------------------------------------------------------------------
// useTemplateData return shapes
// -----------------------------------------------------------------------------

// A table with one template row.
export const SINGLE_TEMPLATE = {
  data: { rows: [mockTemplate], totalCount: 1 },
  isFetching: false,
  isError: false,
  error: null,
};

// An empty table — used for initial load and empty state tests.
export const EMPTY_TEMPLATES = {
  data: { rows: [], totalCount: 0 },
  isFetching: false,
  isError: false,
  error: null,
};

// -----------------------------------------------------------------------------
// Service-level response shapes (used when you want React Query to run for real).
// -----------------------------------------------------------------------------
export const SINGLE_TEMPLATE_ROWS: FetchTemplateResponse = { rows: [mockTemplate], totalCount: 1 };
export const EMPTY_TEMPLATE_ROWS: FetchTemplateResponse = { rows: [], totalCount: 0 };

// Makes fetchTemplates return the data you provide.
// Use this when you want the real useTemplateData and React Query to run in your test.
export const mockFetchTemplates = (rawData: FetchTemplateResponse = SINGLE_TEMPLATE_ROWS) => {
  vi.mocked(fetchTemplates).mockResolvedValue(rawData);
};

// -----------------------------------------------------------------------------
// useTemplateActions mock helpers
// -----------------------------------------------------------------------------
export type UseTemplateActionsReturn = ReturnType<typeof useTemplateActions>;

// Returns a fresh useTemplateActions mock value for every beforeEach call.
export const defaultTemplateActions = (
  overrides: Partial<UseTemplateActionsReturn> = {},
): UseTemplateActionsReturn =>
  ({
    isDeleting: false,
    deleteError: null,
    setDeleteError: vi.fn(),
    isCloning: false,
    confirmDelete: vi.fn(),
    handleConfirmClone: vi.fn(),
    handleConfirmMove: vi.fn(),
    handleAlterTemplate: vi.fn(),
    ...overrides,
  }) as UseTemplateActionsReturn;

// -----------------------------------------------------------------------------
// Opens the Edit Template modal for mockTemplate by clicking the Edit row action.
// Wait for the row text first — the table is behind an isHydrated guard and
// only renders after fetchUserPreference resolves.
// -----------------------------------------------------------------------------
export const openEditModal = async () => {
  await screen.findByText(mockTemplate.layout);
  fireEvent.click(screen.getByTitle('Edit'));
  return screen.findByRole('dialog');
};

// -----------------------------------------------------------------------------
// Render wrapper — provides all required context providers.
// -----------------------------------------------------------------------------
export const renderTemplatesPage = () => {
  testQueryClient.setQueryData(['userPref', 'template_page'], null);
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter>
          <Templates />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};
