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

// =============================================================================
// Test type: Page integration — Library Usage filters
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { LIBRARY_RESPONSE } from '../../__tests__/fixtures/reportRows';
import { stubIntersectionObserver } from '../../__tests__/helpers/domStubs';
import { applyButton, pickOption } from '../../__tests__/helpers/renderReportPage';
import { setupOptionServiceMocks } from '../../__tests__/mocks/optionServices';

import { renderLibraryUsage } from './helpers/renderLibraryUsage';

import { fetchLibraryUsage } from '@/services/libraryUsageApi';
import { fetchUsers } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => ({
  ...((await importOriginal()) as object),
  useNavigate: () => mockNavigate,
}));
vi.mock('@/services/displaysApi', async (o) => ({
  ...((await o()) as object),
  fetchDisplays: vi.fn(),
}));
vi.mock('@/services/displayGroupApi', async (o) => ({
  ...((await o()) as object),
  fetchDisplayGroups: vi.fn(),
}));
vi.mock('@/services/layoutsApi', async (o) => ({
  ...((await o()) as object),
  fetchLayouts: vi.fn(),
}));
vi.mock('@/services/mediaApi', async (o) => ({ ...((await o()) as object), fetchMedia: vi.fn() }));
vi.mock('@/services/campaignApi', async (o) => ({
  ...((await o()) as object),
  fetchCampaigns: vi.fn(),
}));
vi.mock('@/services/userGroupApi', async (o) => ({
  ...((await o()) as object),
  fetchUserGroups: vi.fn(),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn(),
}));
vi.mock('@/services/reportApi', () => ({
  fetchReports: vi.fn().mockResolvedValue({}),
}));
vi.mock('@/services/reportScheduleApi', () => ({
  createReportSchedule: vi.fn().mockResolvedValue({}),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/services/libraryUsageApi', () => ({ fetchLibraryUsage: vi.fn() }));

// =============================================================================
// Helpers
// =============================================================================

const waitForFilters = () => screen.findByRole('button', { name: /^apply$/i });

const apply = async (user: UserEvent) => {
  await user.click(applyButton());
  await waitFor(() => expect(fetchLibraryUsage).toHaveBeenCalled());
};

const lastRequest = () => {
  const calls = vi.mocked(fetchLibraryUsage).mock.calls;
  return calls[calls.length - 1]![0];
};

// =============================================================================
// Tests
// =============================================================================

describe('Library Usage - filters', () => {
  beforeAll(() => {
    stubIntersectionObserver();
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchLibraryUsage).mockReset().mockResolvedValue(LIBRARY_RESPONSE);
    setupOptionServiceMocks();
  });

  test('with no user or group chosen, everyone is requested', async () => {
    const user = userEvent.setup({ delay: null });
    renderLibraryUsage();
    await waitForFilters();

    await apply(user);

    expect(lastRequest()).toEqual(expect.objectContaining({ userId: null, groupId: null }));
  });

  test('a chosen User reaches the request as its id', async () => {
    const user = userEvent.setup({ delay: null });
    renderLibraryUsage();
    await waitForFilters();

    await pickOption(user, /^user$/i, 'alice');
    await apply(user);

    expect(lastRequest()).toEqual(expect.objectContaining({ userId: 1 }));
  });

  // User groups are asked for with isUser 0, so per-user groups aren't offered.
  test('a chosen User Group reaches the request as its id', async () => {
    const user = userEvent.setup({ delay: null });
    renderLibraryUsage();
    await waitForFilters();

    await pickOption(user, /^user group$/i, 'Editors');
    await apply(user);

    expect(lastRequest()).toEqual(expect.objectContaining({ groupId: 601 }));
  });

  test("a saved user's name is looked up by id", async () => {
    renderLibraryUsage({ userId: 2, groupId: null });

    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: /^user$/i })).toHaveTextContent('bob'),
    );
    expect(fetchUsers).toHaveBeenCalledWith(expect.objectContaining({ userId: 2, length: 1 }));
  });
});
