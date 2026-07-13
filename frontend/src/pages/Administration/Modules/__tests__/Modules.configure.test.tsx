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
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { SINGLE_MODULE, mockModule } from './fixtures/module';
import { renderModulesPage } from './helpers/renderModulesPage';
import { mockFetchModules } from './mocks/moduleApi';

import { fetchModules, updateModuleSettings } from '@/services/moduleApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================
// The real ConfigureModuleModal is used here (only Modal itself is stubbed) so
// the full page -> modal -> useModuleActions -> updateModuleSettings flow runs.

vi.mock('@/services/moduleApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/useModuleFilterOptions', () => ({
  useModuleFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Tests — Configure wiring
// =============================================================================

describe('Modules page - configure wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchModules(SINGLE_MODULE);
  });

  test('clicking Configure opens the "Edit Module" modal', async () => {
    const user = userEvent.setup();
    renderModulesPage();
    await screen.findByText(mockModule.name);

    await user.click(screen.getByRole('button', { name: /^configure$/i }));

    expect(await screen.findByRole('dialog', { name: /edit module/i })).toBeInTheDocument();
    expect(screen.getAllByRole('dialog')).toHaveLength(1);
  });

  test('a successful save calls updateModuleSettings, then closes and refreshes', async () => {
    const user = userEvent.setup();
    vi.mocked(updateModuleSettings).mockResolvedValue(mockModule);
    renderModulesPage();
    await screen.findByText(mockModule.name);
    const fetchCountBefore = vi.mocked(fetchModules).mock.calls.length;

    await user.click(screen.getByRole('button', { name: /^configure$/i }));
    const dialog = await screen.findByRole('dialog', { name: /edit module/i });
    await user.click(within(dialog).getByRole('button', { name: /^save$/i }));

    await waitFor(() => {
      expect(updateModuleSettings).toHaveBeenCalledWith(
        mockModule.moduleId,
        expect.objectContaining({ enabled: 1, previewEnabled: 1, defaultDuration: 10 }),
      );
    });
    // The modal closes...
    await waitFor(() => {
      expect(screen.queryByRole('dialog', { name: /edit module/i })).not.toBeInTheDocument();
    });
    // ...and the table is refreshed.
    await waitFor(() => {
      expect(vi.mocked(fetchModules).mock.calls.length).toBeGreaterThan(fetchCountBefore);
    });
  });

  test('a failed save keeps the modal open and shows the error', async () => {
    const user = userEvent.setup();
    vi.mocked(updateModuleSettings).mockRejectedValueOnce({
      isAxiosError: true,
      response: { data: { message: 'Settings could not be saved.' } },
    });
    renderModulesPage();
    await screen.findByText(mockModule.name);

    await user.click(screen.getByRole('button', { name: /^configure$/i }));
    const dialog = await screen.findByRole('dialog', { name: /edit module/i });
    await user.click(within(dialog).getByRole('button', { name: /^save$/i }));

    expect(await screen.findByText('Settings could not be saved.')).toBeInTheDocument();
    expect(screen.getByRole('dialog', { name: /edit module/i })).toBeInTheDocument();
  });
});
