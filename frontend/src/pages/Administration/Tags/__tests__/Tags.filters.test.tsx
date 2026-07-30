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
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockTag, SINGLE_TAG } from './fixtures/tag';
import { renderTagsPage } from './helpers/renderTagsPage';
import { mockFetchTags } from './mocks/tagApi';

import { fetchTags } from '@/services/tagApi';
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

vi.mock('@/components/ui/forms/SelectDropdown', () => ({
  default: ({
    label,
    value,
    options,
    onSelect,
    placeholder,
  }: {
    label?: string;
    value?: string | number | null;
    options?: Array<{ value: string | number | null; label: string }>;
    onSelect?: (value: string) => void;
    placeholder?: string;
  }) => (
    <select aria-label={label} value={value ?? ''} onChange={(e) => onSelect?.(e.target.value)}>
      {placeholder !== undefined && <option value="">{placeholder}</option>}
      {options?.map((o, i) => (
        <option key={i} value={String(o.value ?? '')}>
          {o.label}
        </option>
      ))}
    </select>
  ),
}));

const waitForPageReady = () => screen.findByText(mockTag.tag);

// =============================================================================
// Tests
// =============================================================================

describe('Tags page - search and filters', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTags(SINGLE_TAG);
  });

  test('the filter panel is hidden by default', async () => {
    renderTagsPage();
    await waitForPageReady();

    expect(screen.queryByRole('textbox', { name: /^name$/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
  });

  test('clicking Filters opens the panel', async () => {
    const user = userEvent.setup();
    renderTagsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));

    expect(await screen.findByRole('textbox', { name: /^name$/i })).toBeInTheDocument();
  });

  test('clicking Filters again closes the panel', async () => {
    const user = userEvent.setup();
    renderTagsPage();
    await waitForPageReady();

    const filtersButton = screen.getByRole('button', { name: /filters/i });
    await user.click(filtersButton);
    await screen.findByRole('textbox', { name: /^name$/i });

    await user.click(filtersButton);

    await waitFor(() => {
      expect(screen.queryByRole('textbox', { name: /^name$/i })).not.toBeInTheDocument();
    });
  });

  test('typing in the search box fetches results with that keyword', async () => {
    const user = userEvent.setup();
    renderTagsPage();
    await waitForPageReady();

    await user.type(screen.getByRole('textbox', { name: /search tags/i }), 'location');

    await waitFor(
      () => {
        expect(fetchTags).toHaveBeenCalledWith(expect.objectContaining({ tag: 'location' }));
      },
      { timeout: 2000 },
    );
  });

  test('typing in the search box resets to page 1', async () => {
    const user = userEvent.setup();
    renderTagsPage();
    await waitForPageReady();

    await user.type(screen.getByRole('textbox', { name: /search tags/i }), 'location');

    await waitFor(
      () => {
        expect(fetchTags).toHaveBeenCalledWith(
          expect.objectContaining({ tag: 'location', start: 0 }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('entering a Name filter updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderTagsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const nameInput = await screen.findByRole('textbox', { name: /^name$/i });
    await user.type(nameInput, 'location');

    await waitFor(
      () => {
        expect(fetchTags).toHaveBeenCalledWith(
          expect.objectContaining({ tag: 'location', start: 0 }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('clicking Reset clears the filter inputs', async () => {
    const user = userEvent.setup();
    renderTagsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const nameInput = (await screen.findByRole('textbox', { name: /^name$/i })) as HTMLInputElement;
    await user.type(nameInput, 'location');

    await waitFor(
      () => {
        expect(fetchTags).toHaveBeenCalledWith(expect.objectContaining({ tag: 'location' }));
      },
      { timeout: 2000 },
    );

    await user.click(screen.getByRole('button', { name: /reset/i }));

    await waitFor(() => {
      expect(screen.getByRole('textbox', { name: /^name$/i })).toHaveValue('');
    });
  });

  test('Reset keeps the filter panel open', async () => {
    const user = userEvent.setup();
    renderTagsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await screen.findByRole('textbox', { name: /^name$/i });

    await user.click(screen.getByRole('button', { name: /reset/i }));

    expect(screen.getByRole('textbox', { name: /^name$/i })).toBeInTheDocument();
  }, 20_000);

  test('clearing the search restores the unfiltered list', async () => {
    const user = userEvent.setup();
    renderTagsPage();
    await waitForPageReady();

    const search = screen.getByRole('textbox', { name: /search tags/i });
    await user.type(search, 'location');
    await waitFor(
      () => {
        expect(fetchTags).toHaveBeenCalledWith(expect.objectContaining({ tag: 'location' }));
      },
      { timeout: 2000 },
    );

    await user.clear(search);
    await waitFor(
      () => {
        expect(fetchTags).toHaveBeenCalledWith(expect.objectContaining({ tag: undefined }));
      },
      { timeout: 2000 },
    );
  });
});
