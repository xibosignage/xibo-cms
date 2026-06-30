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

import { screen, waitFor, fireEvent, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  EMPTY_DISPLAY_PROFILE_TABLE,
  mockDisplayProfile,
  SINGLE_DISPLAY_PROFILE,
} from './fixtures/displayProfile';
import { renderDisplayProfilePage } from './helpers/renderDisplayProfilePage';
import { mockFetchDisplayProfile } from './mocks/displayProfileApi';

import { fetchDisplayProfile } from '@/services/displayProfileApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/displayProfileApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
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
    value?: string;
    options?: Array<{ value: string; label: string }>;
    onSelect?: (value: string) => void;
    placeholder?: string;
  }) => (
    <select aria-label={label} value={value ?? ''} onChange={(e) => onSelect?.(e.target.value)}>
      {placeholder !== undefined && <option value="">{placeholder}</option>}
      {options?.map((o) => (
        <option key={o.value} value={o.value}>
          {o.label}
        </option>
      ))}
    </select>
  ),
}));

// =============================================================================
// Helpers
// =============================================================================

const waitForPageReady = () => screen.findByText(mockDisplayProfile.name);

// =============================================================================
// Tests — DisplayProfile page filters
// =============================================================================

describe('DisplayProfile page - filters', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplayProfile(SINGLE_DISPLAY_PROFILE);
  });

  test('the filter panel is hidden by default', async () => {
    renderDisplayProfilePage();
    await waitForPageReady();

    expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
  });

  test('clicking Filters opens the panel with the Name and Type fields', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));

    expect(await screen.findByRole('textbox', { name: /^name$/i })).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: /^type$/i })).toBeInTheDocument();
    await screen.findByRole('button', { name: /reset/i });
  });

  test('the Name filter exposes the AND/OR and regex toggles', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await screen.findByRole('textbox', { name: /^name$/i });

    expect(screen.getAllByRole('button', { name: 'OR' }).length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByTitle('Use RegEx pattern matching').length).toBeGreaterThanOrEqual(1);
  });

  test('clicking Filters again closes the panel', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await waitForPageReady();

    const filtersButton = screen.getByRole('button', { name: /filters/i });
    await user.click(filtersButton);
    await screen.findByRole('button', { name: /reset/i });

    await user.click(filtersButton);

    await waitFor(() => {
      expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
    });
  });

  test('typing in the search box fetches results with that keyword', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await waitForPageReady();

    await user.type(screen.getByPlaceholderText('Search display profiles...'), 'Android');

    await waitFor(
      () => {
        expect(fetchDisplayProfile).toHaveBeenCalledWith(
          expect.objectContaining({ keyword: 'Android' }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('typing in the search box resets pagination to page 1', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await waitForPageReady();

    await user.type(screen.getByPlaceholderText('Search display profiles...'), 'screen');

    await waitFor(
      () => {
        expect(fetchDisplayProfile).toHaveBeenCalledWith(
          expect.objectContaining({ start: 0, keyword: 'screen' }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('entering a Name filter updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const nameInput = await screen.findByRole('textbox', { name: /^name$/i });
    await user.type(nameInput, 'Alpha');

    await waitFor(
      () => {
        expect(fetchDisplayProfile).toHaveBeenCalledWith(
          expect.objectContaining({ start: 0, displayProfile: 'Alpha' }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('selecting a Type filter updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const typeSelect = await screen.findByRole('combobox', { name: /^type$/i });
    fireEvent.change(typeSelect, { target: { value: 'android' } });

    await waitFor(
      () => {
        expect(fetchDisplayProfile).toHaveBeenCalledWith(
          expect.objectContaining({ start: 0, type: 'android' }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('clicking Reset clears the Name filter input', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const nameInput = (await screen.findByRole('textbox', {
      name: /^name$/i,
    })) as HTMLInputElement;
    await user.type(nameInput, 'Alpha');

    // Wait for the debounced value to reach the query before resetting, so the
    // input is actually synced to filterInputs when Reset fires.
    await waitFor(
      () => {
        expect(fetchDisplayProfile).toHaveBeenCalledWith(
          expect.objectContaining({ displayProfile: 'Alpha' }),
        );
      },
      { timeout: 2000 },
    );

    await user.click(screen.getByRole('button', { name: /reset/i }));

    await waitFor(() => expect(nameInput.value).toBe(''));
  });

  test('clicking Reset clears the Type filter', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const typeSelect = await screen.findByRole('combobox', { name: /^type$/i });
    fireEvent.change(typeSelect, { target: { value: 'android' } });

    // Wait for the selection to reach the query before resetting.
    await waitFor(
      () => {
        expect(fetchDisplayProfile).toHaveBeenCalledWith(
          expect.objectContaining({ type: 'android' }),
        );
      },
      { timeout: 2000 },
    );

    await user.click(screen.getByRole('button', { name: /reset/i }));

    await waitFor(() =>
      expect((screen.getByRole('combobox', { name: /^type$/i }) as HTMLSelectElement).value).toBe(
        '',
      ),
    );
  });

  test('Reset keeps the filter panel open', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await user.click(await screen.findByRole('button', { name: /reset/i }));

    expect(screen.getByRole('button', { name: /reset/i })).toBeInTheDocument();
  });

  test('clearing the search box restores the full list', async () => {
    const user = userEvent.setup();
    // Unfiltered returns the row; the 'Alpha' keyword returns nothing.
    vi.mocked(fetchDisplayProfile).mockImplementation(async (opts) =>
      opts?.keyword === 'Alpha' ? EMPTY_DISPLAY_PROFILE_TABLE : SINGLE_DISPLAY_PROFILE,
    );

    renderDisplayProfilePage();
    await waitForPageReady();

    const searchInput = screen.getByPlaceholderText(
      'Search display profiles...',
    ) as HTMLInputElement;

    // A keyword that matches no rows flips the table to its empty state.
    await user.type(searchInput, 'Alpha');
    expect(await screen.findByText('No results found.')).toBeInTheDocument();

    // Clearing the box brings the row back once the debounced filter clears.
    await user.type(searchInput, '{Backspace}{Backspace}{Backspace}{Backspace}{Backspace}');
    expect(searchInput.value).toBe('');
    expect(await screen.findByText(mockDisplayProfile.name)).toBeInTheDocument();
  });

  test('the Type filter lists all seven display types', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const typeSelect = await screen.findByRole('combobox', { name: /^type$/i });

    const labels = within(typeSelect)
      .getAllByRole('option')
      .filter((opt) => opt.getAttribute('value') !== '')
      .map((opt) => opt.textContent);

    expect(labels).toEqual([
      'Android',
      'Windows',
      'Linux',
      'webOS',
      'Tizen',
      'ChromeOS',
      'Hisense',
    ]);
  });
});
