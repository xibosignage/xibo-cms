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

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { REGION_SPECIFIC_MODULE, SINGLE_MODULE, mockModule } from './fixtures/module';
import { renderModulesPage } from './helpers/renderModulesPage';
import { mockFetchModules } from './mocks/moduleApi';

import { clearModuleCache, fetchModules } from '@/services/moduleApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/moduleApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  autoSubmitPrefQueryKey: (formId: string) => ['userPref', `autoSubmit.${formId}`],
  fetchAutoSubmitPreference: vi.fn().mockResolvedValue(false),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/useModuleFilterOptions', () => ({
  useModuleFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

const REGION_TABLE = { rows: [REGION_SPECIFIC_MODULE], totalCount: 1 };

// Clear Cache is a menu-only action, so the "More actions" menu must be opened first.
const openRowMenu = async (user: UserEvent) => {
  await user.click(screen.getByRole('button', { name: /more actions/i }));
};

// =============================================================================
// Tests — Clear Cache wiring
// =============================================================================

describe('Modules page - clear cache wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('Clear Cache is not offered for a library-media module', async () => {
    const user = userEvent.setup();
    mockFetchModules(SINGLE_MODULE); // regionSpecific === 0
    renderModulesPage();
    await screen.findByText(mockModule.name);

    await openRowMenu(user);
    await waitFor(() =>
      expect(screen.getAllByRole('button', { name: /^configure$/i }).length).toBeGreaterThan(1),
    );
    expect(screen.queryByRole('button', { name: /clear cache/i })).not.toBeInTheDocument();
  }, 20000);

  test('Clear Cache is offered for a region-specific module', async () => {
    const user = userEvent.setup();
    mockFetchModules(REGION_TABLE);
    renderModulesPage();
    await screen.findByText(REGION_SPECIFIC_MODULE.name);

    await openRowMenu(user);

    expect(await screen.findByRole('button', { name: /clear cache/i })).toBeInTheDocument();
  }, 20000);

  test('clicking Clear Cache opens the confirmation modal', async () => {
    const user = userEvent.setup();
    mockFetchModules(REGION_TABLE);
    renderModulesPage();
    await screen.findByText(REGION_SPECIFIC_MODULE.name);

    await openRowMenu(user);
    await user.click(await screen.findByRole('button', { name: /clear cache/i }));

    expect(await screen.findByText('Clear Cache?')).toBeInTheDocument();
    expect(screen.getAllByRole('dialog')).toHaveLength(1);
  }, 20000);

  test('confirming calls clearModuleCache, then closes and refreshes', async () => {
    const user = userEvent.setup();
    vi.mocked(clearModuleCache).mockResolvedValue(undefined);
    mockFetchModules(REGION_TABLE);
    renderModulesPage();
    await screen.findByText(REGION_SPECIFIC_MODULE.name);
    const fetchCountBefore = vi.mocked(fetchModules).mock.calls.length;

    await openRowMenu(user);
    await user.click(await screen.findByRole('button', { name: /clear cache/i }));
    const dialog = await screen.findByRole('dialog');
    await user.click(within(dialog).getByRole('button', { name: /^clear cache$/i }));

    await waitFor(() => {
      expect(clearModuleCache).toHaveBeenCalledWith(REGION_SPECIFIC_MODULE.moduleId);
    });
    await waitFor(() => {
      expect(screen.queryByText('Clear Cache?')).not.toBeInTheDocument();
    });
    await waitFor(() => {
      expect(vi.mocked(fetchModules).mock.calls.length).toBeGreaterThan(fetchCountBefore);
    });
  }, 20000);

  test('clicking Cancel closes the modal without clearing the cache', async () => {
    const user = userEvent.setup();
    mockFetchModules(REGION_TABLE);
    renderModulesPage();
    await screen.findByText(REGION_SPECIFIC_MODULE.name);

    await openRowMenu(user);
    await user.click(await screen.findByRole('button', { name: /clear cache/i }));
    const dialog = await screen.findByRole('dialog');
    await user.click(within(dialog).getByRole('button', { name: /^cancel$/i }));

    expect(screen.queryByText('Clear Cache?')).not.toBeInTheDocument();
    expect(clearModuleCache).not.toHaveBeenCalled();
  }, 20000);

  test('a failed clear keeps the modal open and shows the error', async () => {
    const user = userEvent.setup();
    vi.mocked(clearModuleCache).mockRejectedValueOnce({
      isAxiosError: true,
      response: { data: { message: 'Cache could not be cleared.' } },
    });
    mockFetchModules(REGION_TABLE);
    renderModulesPage();
    await screen.findByText(REGION_SPECIFIC_MODULE.name);

    await openRowMenu(user);
    await user.click(await screen.findByRole('button', { name: /clear cache/i }));
    const dialog = await screen.findByRole('dialog');
    await user.click(within(dialog).getByRole('button', { name: /^clear cache$/i }));

    expect(await screen.findByText('Cache could not be cleared.')).toBeInTheDocument();
    expect(screen.getByText('Clear Cache?')).toBeInTheDocument();
  }, 20000);
});
