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
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test } from 'vitest';

import { mockTag, SINGLE_TAG } from './fixtures/tag';
import { renderTagsPage } from './helpers/renderTagsPage';
import { mockFetchTags } from './mocks/tagApi';

import { fetchTagUsage } from '@/services/tagApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/tagApi');

vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Tags', path: '/administration/tags' }]),
}));

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Tests
// =============================================================================

describe('Tags page - add / edit routing', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTags(SINGLE_TAG);
    vi.mocked(fetchTagUsage).mockResolvedValue({ rows: [], totalCount: 0 });
  });

  test('clicking "Add Tag" opens the Add Tag modal', async () => {
    const user = userEvent.setup();
    renderTagsPage();

    await user.click(await screen.findByRole('button', { name: /add tag/i }));

    await screen.findByRole('dialog', { name: /add tag/i });
  });

  test('clicking Edit quick action opens the Edit Tag modal for the correct tag', async () => {
    const user = userEvent.setup();
    renderTagsPage();
    await screen.findByText(mockTag.tag);

    await user.click(screen.getByRole('button', { name: /^edit$/i }));

    await screen.findByRole('dialog', { name: /edit tag/i });
  });

  test('clicking Usage opens the Usage modal for the tag', async () => {
    const user = userEvent.setup();
    renderTagsPage();
    await screen.findByText(mockTag.tag);

    await user.click(screen.getByRole('button', { name: /more actions/i }));

    const usageButton = await screen.findByRole('button', { name: /^usage$/i });
    await user.click(usageButton);

    await screen.findByRole('dialog', { name: new RegExp(mockTag.tag, 'i') });
  });
});
