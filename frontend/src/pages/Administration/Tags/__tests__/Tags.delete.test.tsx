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

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildTag, mockTag, SINGLE_TAG } from './fixtures/tag';
import { renderTagsPage } from './helpers/renderTagsPage';
import { mockFetchTags } from './mocks/tagApi';

import { deleteTag } from '@/services/tagApi';
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
// Helpers
// =============================================================================

const openRowDeleteModal = async (user: UserEvent) => {
  await screen.findByText(mockTag.tag);
  await user.click(screen.getByRole('button', { name: /more actions/i }));

  const deleteButton = await waitFor(() => screen.getByRole('button', { name: /^delete$/i }), {
    timeout: 5000,
  });
  await user.click(deleteButton);
  return screen.findByRole('heading', { name: /delete tag\?/i });
};

const selectAllRows = async (user: UserEvent) => {
  await user.click(screen.getByRole('checkbox', { name: /select all rows/i }));
};

// =============================================================================
// Tests — single delete
// =============================================================================

describe('Tags page - single delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTags(SINGLE_TAG);
  });

  test('Delete row action opens the confirmation modal showing the tag name', async () => {
    const user = userEvent.setup();
    renderTagsPage();

    await openRowDeleteModal(user);

    // heading confirmed by helper's return value; check remaining content
    expect(screen.getByText(mockTag.tag, { selector: 'strong' })).toBeInTheDocument();
    expect(screen.queryByRole('dialog', { name: /edit tag/i })).not.toBeInTheDocument();
  }, 20_000);

  test('clicking Cancel closes the modal without deleting', async () => {
    const user = userEvent.setup();
    renderTagsPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(screen.queryByText('Delete Tag?')).not.toBeInTheDocument();
    expect(deleteTag).not.toHaveBeenCalled();
  }, 20_000);

  test('clicking Yes, Delete removes the tag and closes the modal', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteTag).mockResolvedValue(undefined);
    renderTagsPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitFor(() => {
      expect(deleteTag).toHaveBeenCalledWith(mockTag.tagId);
    });
    await waitFor(() => {
      expect(screen.queryByText('Delete Tag?')).not.toBeInTheDocument();
    });
  }, 20_000);

  test('Delete button shows "Deleting…" while the request is in progress', async () => {
    const user = userEvent.setup();
    let resolveDelete: () => void = () => {};
    vi.mocked(deleteTag).mockReturnValueOnce(
      new Promise<void>((resolve) => {
        resolveDelete = resolve;
      }),
    );
    renderTagsPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    expect(await screen.findByRole('button', { name: /deleting/i })).toBeDisabled();

    resolveDelete();
  }, 20_000);

  test('a failed delete keeps the modal open and shows the error', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteTag).mockRejectedValueOnce({
      isAxiosError: true,
      response: { data: { message: 'Cannot delete — tag is in use.' } },
    });
    renderTagsPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await screen.findByText('Cannot delete — tag is in use.');
    expect(screen.getByRole('heading', { name: /delete tag\?/i })).toBeInTheDocument();
  }, 20_000);

  test('single item: heading is singular', async () => {
    const user = userEvent.setup();
    renderTagsPage();

    await openRowDeleteModal(user);

    // singular heading confirmed by helper; just assert plural is absent
    expect(screen.queryByRole('heading', { name: /delete tags\?/i })).not.toBeInTheDocument();
  }, 20_000);
});

// =============================================================================
// Tests — bulk delete
// =============================================================================

describe('Tags page - bulk delete', () => {
  const TWO_TAGS = {
    rows: [buildTag({ tagId: 1, tag: 'location' }), buildTag({ tagId: 2, tag: 'region' })],
    totalCount: 2,
  };

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTags(TWO_TAGS);
  });

  test('selecting rows reveals the bulk Delete Selected button', async () => {
    const user = userEvent.setup();
    renderTagsPage();
    await screen.findByText('location');

    expect(screen.queryByRole('button', { name: /delete selected/i })).not.toBeInTheDocument();

    await selectAllRows(user);

    expect(await screen.findByRole('button', { name: /delete selected/i })).toBeEnabled();
  });

  test('multiple items: heading is plural with the count', async () => {
    const user = userEvent.setup();
    renderTagsPage();
    await screen.findByText('location');

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));

    await screen.findByRole('heading', { name: /delete tags\?/i });
    expect(screen.getByText(/are you sure you want to delete 2 tags/i)).toBeInTheDocument();
  });

  test('bulk confirm deletes every selected item', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteTag).mockResolvedValue(undefined);
    renderTagsPage();
    await screen.findByText('location');

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));
    await screen.findByText('Delete Tags?');
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitFor(() => {
      expect(deleteTag).toHaveBeenCalledTimes(2);
    });
    const calledIds = vi
      .mocked(deleteTag)
      .mock.calls.map((c) => c[0])
      .sort();
    expect(calledIds).toEqual([1, 2]);
  });

  test('a partial bulk delete failure shows the error and keeps the modal open', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteTag).mockImplementation((id: number) => {
      if (id === 2) {
        return Promise.reject({
          isAxiosError: true,
          response: { data: { message: 'Tag 2 is in use.' } },
        });
      }
      return Promise.resolve(undefined);
    });
    renderTagsPage();
    await screen.findByText('location');

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));
    await screen.findByRole('heading', { name: /delete tags\?/i });
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await screen.findByText('Tag 2 is in use.');
    expect(screen.getByRole('heading', { name: /delete tags\?/i })).toBeInTheDocument();
    expect(deleteTag).toHaveBeenCalledTimes(2);
  });
});
