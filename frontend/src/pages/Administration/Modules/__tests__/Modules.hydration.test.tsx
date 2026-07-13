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

import { screen } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { SINGLE_MODULE, mockModule } from './fixtures/module';
import { renderModulesPage } from './helpers/renderModulesPage';
import { mockFetchModules } from './mocks/moduleApi';

import { fetchUserPreference } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

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
// Tests — Hydration gate
// =============================================================================

describe('Modules page - hydration', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchModules(SINGLE_MODULE);
  });

  test('shows the loading pulse and disables controls while preferences load', async () => {
    // Keep the preference request pending so the page never hydrates.
    vi.mocked(fetchUserPreference).mockReturnValueOnce(new Promise(() => {}));

    renderModulesPage({ hydrated: false });

    expect(await screen.findByText('Loading your preferences...')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('Search modules...')).toBeDisabled();
    expect(screen.getByRole('button', { name: /filters/i })).toBeDisabled();
  });

  test('renders the table once preferences have hydrated', async () => {
    renderModulesPage();

    expect(await screen.findByText(mockModule.name)).toBeInTheDocument();
    expect(screen.queryByText('Loading your preferences...')).not.toBeInTheDocument();
  });
});
