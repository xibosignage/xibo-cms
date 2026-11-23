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

import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildTag, mockTag, SINGLE_TAG } from './fixtures/tag';
import { renderTagsPage } from './helpers/renderTagsPage';
import { mockFetchTags } from './mocks/tagApi';

import { createTag, fetchTagUsage } from '@/services/tagApi';
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

  test(
    'adding a tag from the page sends its name, values and required flag, then lists it',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      const newTag = buildTag({
        tagId: 2,
        tag: 'store-region',
        options: '["north","south"]',
        isRequired: 1,
      });
      // Once the tag is created the grid's refresh returns it alongside the existing one.
      vi.mocked(createTag).mockImplementation(async () => {
        mockFetchTags({ rows: [mockTag, newTag], totalCount: 2 });
        return newTag;
      });
      renderTagsPage();
      await screen.findByText(mockTag.tag);

      await user.click(screen.getByRole('button', { name: /add tag/i }));
      const dialog = await screen.findByRole('dialog', { name: /add tag/i });
      await user.type(within(dialog).getByRole('textbox', { name: /^name/i }), 'store-region');
      await user.type(within(dialog).getByRole('textbox', { name: /^values/i }), 'north,south');
      await user.click(
        within(dialog).getByRole('checkbox', { name: /ensure that a value is selected/i }),
      );
      await user.click(within(dialog).getByRole('button', { name: /^save$/i }));

      await waitFor(() => {
        expect(createTag).toHaveBeenCalledWith({
          name: 'store-region',
          options: 'north,south',
          isRequired: 1,
        });
      });
      await screen.findByText('store-region');
      expect(screen.queryByRole('dialog', { name: /add tag/i })).not.toBeInTheDocument();
    },
  );
});
