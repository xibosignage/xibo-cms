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
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildTag, mockTag, mockNonAdminUser, mockUser, SINGLE_TAG } from './fixtures/tag';
import { renderTagsPage } from './helpers/renderTagsPage';
import { mockFetchTags } from './mocks/tagApi';

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
// Tests — row actions: superAdmin + non-system tag
// The condition in getTagItemActions: isSuperAdmin && tag.isSystem === 0
// mockTag (isSystem: 0) + mockUser (userTypeId: 1) satisfies it.
// =============================================================================

describe('Tags page — row actions (superAdmin, non-system tag)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTags(SINGLE_TAG);
  });

  test('Edit quick action is visible', async () => {
    renderTagsPage(mockUser);

    await screen.findByText(mockTag.tag);

    expect(screen.getByRole('button', { name: /^edit$/i })).toBeInTheDocument();
  });

  test('Delete action is visible in the more-actions dropdown', async () => {
    const user = userEvent.setup();
    renderTagsPage(mockUser);
    await screen.findByText(mockTag.tag);

    await user.click(screen.getByRole('button', { name: /more actions/i }));

    expect(await screen.findByRole('button', { name: /^delete$/i })).toBeInTheDocument();
  });

  test('Usage action is visible in the more-actions dropdown', async () => {
    const user = userEvent.setup();
    renderTagsPage(mockUser);
    await screen.findByText(mockTag.tag);

    await user.click(screen.getByRole('button', { name: /more actions/i }));

    expect(await screen.findByRole('button', { name: /^usage$/i })).toBeInTheDocument();
  });
});

// =============================================================================
// Tests — row actions: non-superAdmin
// isSuperAdmin is false → only Usage is added.
// =============================================================================

describe('Tags page — row actions (non-superAdmin)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTags(SINGLE_TAG);
  });

  test('Edit quick action is hidden', async () => {
    renderTagsPage(mockNonAdminUser);

    await screen.findByText(mockTag.tag);

    expect(screen.queryByRole('button', { name: /^edit$/i })).not.toBeInTheDocument();
  });

  test('Delete action is hidden in the more-actions dropdown', async () => {
    const user = userEvent.setup();
    renderTagsPage(mockNonAdminUser);
    await screen.findByText(mockTag.tag);

    await user.click(screen.getByRole('button', { name: /more actions/i }));

    // Wait for Usage to confirm the dropdown is fully open, then assert Delete absent.
    await screen.findByRole('button', { name: /^usage$/i });
    expect(screen.queryByRole('button', { name: /^delete$/i })).not.toBeInTheDocument();
  });

  test('Usage action is still visible', async () => {
    const user = userEvent.setup();
    renderTagsPage(mockNonAdminUser);
    await screen.findByText(mockTag.tag);

    await user.click(screen.getByRole('button', { name: /more actions/i }));

    expect(await screen.findByRole('button', { name: /^usage$/i })).toBeInTheDocument();
  });
});

// =============================================================================
// Tests — row actions: superAdmin + system tag (isSystem: 1)
// Even though user is superAdmin, isSystem === 1 blocks Edit/Delete.
// =============================================================================

describe('Tags page — row actions (superAdmin, system tag)', () => {
  const SYSTEM_TAG_TABLE = {
    rows: [buildTag({ tagId: 10, tag: 'system-tag', isSystem: 1 })],
    totalCount: 1,
  };

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTags(SYSTEM_TAG_TABLE);
  });

  test('Edit quick action is hidden for a system tag', async () => {
    renderTagsPage(mockUser);

    await screen.findByText('system-tag');

    expect(screen.queryByRole('button', { name: /^edit$/i })).not.toBeInTheDocument();
  });

  test('Delete action is hidden for a system tag', async () => {
    const user = userEvent.setup();
    renderTagsPage(mockUser);
    await screen.findByText('system-tag');

    await user.click(screen.getByRole('button', { name: /more actions/i }));

    // Wait for Usage to confirm the dropdown is fully open, then assert Delete absent.
    await screen.findByRole('button', { name: /^usage$/i });
    expect(screen.queryByRole('button', { name: /^delete$/i })).not.toBeInTheDocument();
  }, 20_000);

  test('Usage action is still visible for a system tag', async () => {
    const user = userEvent.setup();
    renderTagsPage(mockUser);
    await screen.findByText('system-tag');

    await user.click(screen.getByRole('button', { name: /more actions/i }));

    expect(await screen.findByRole('button', { name: /^usage$/i })).toBeInTheDocument();
  });
});

// =============================================================================
// Tests — bulk actions
// getBulkActions returns [] for non-superAdmin; Delete Selected only for superAdmin.
// =============================================================================

describe('Tags page — bulk actions', () => {
  const TWO_TAGS = {
    rows: [buildTag({ tagId: 1, tag: 'location' }), buildTag({ tagId: 2, tag: 'region' })],
    totalCount: 2,
  };

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTags(TWO_TAGS);
  });

  test('Delete Selected button appears for superAdmin after selecting rows', async () => {
    const user = userEvent.setup();
    renderTagsPage(mockUser);
    await screen.findByText('location');

    const checkboxes = screen.getAllByRole('checkbox', { name: /select row/i });
    await user.click(checkboxes[0]!);

    expect(await screen.findByRole('button', { name: /delete selected/i })).toBeEnabled();
  });

  test('Delete Selected button is never shown for a non-superAdmin', async () => {
    renderTagsPage(mockNonAdminUser);

    await screen.findByText('location');

    expect(screen.queryByRole('button', { name: /delete selected/i })).not.toBeInTheDocument();
  });
});
