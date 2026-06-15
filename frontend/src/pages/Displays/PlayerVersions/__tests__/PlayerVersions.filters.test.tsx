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
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  EMPTY_PLAYER_VERSION_TABLE,
  mockPlayerVersion,
  SINGLE_PLAYER_VERSION,
} from './fixtures/playerVersion';
import { renderPlayerVersionsPage } from './helpers/renderPlayerVersionsPage';
import { mockFetchPlayerVersions } from './mocks/playerVersionApi';

import { fetchPlayerVersions } from '@/services/playerVersionApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/playerVersionApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue([]),
}));
vi.mock('@/components/ui/modals/Modal');

vi.mock('../hooks/usePlayerVersionsFilterOptions', () => ({
  usePlayerVersionFilterOptions: () => ({
    filterOptions: [
      {
        label: 'Type',
        name: 'type',
        options: [
          { label: 'Android', value: 'android' },
          { label: 'webOS', value: 'lg' },
          { label: 'Tizen', value: 'sssp' },
          { label: 'ChromeOS', value: 'chromeOS' },
        ],
      },
      {
        label: 'Version',
        name: 'version',
        options: [
          { label: '4.0.0', value: '4.0.0' },
          { label: '3.2.1', value: '3.2.1' },
        ],
      },
      { label: 'Code', name: 'code', type: 'text', placeholder: 'Code' },
    ],
  }),
}));

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

const waitForPageReady = () => screen.findByText(mockPlayerVersion.playerShowVersion);

// =============================================================================
// Tests — Player Versions page filters
// =============================================================================

describe('Player Versions page - filters', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchPlayerVersions(SINGLE_PLAYER_VERSION);
  });

  test('filter panel is hidden by default', async () => {
    renderPlayerVersionsPage();
    await waitForPageReady();

    expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
  });

  test('clicking Filters opens the panel', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));

    expect(await screen.findByRole('combobox', { name: /^type$/i })).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: /^version$/i })).toBeInTheDocument();
    expect(screen.getByPlaceholderText('Code')).toBeInTheDocument();
    await screen.findByRole('button', { name: /reset/i });
  });

  test('clicking Filters again closes the panel', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
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
    renderPlayerVersionsPage();
    await waitForPageReady();

    await user.type(screen.getByPlaceholderText('Search player versions...'), 'Alpha');

    await waitFor(
      () => {
        expect(fetchPlayerVersions).toHaveBeenCalledWith(
          expect.objectContaining({ keyword: 'Alpha' }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('typing in the search box resets pagination to page 1', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await waitForPageReady();

    await user.type(screen.getByPlaceholderText('Search player versions...'), 'screen');

    await waitFor(
      () => {
        expect(fetchPlayerVersions).toHaveBeenCalledWith(
          expect.objectContaining({ start: 0, keyword: 'screen' }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('clearing the search box restores the full list', async () => {
    const user = userEvent.setup();
    // Conditional mock: unfiltered returns the row, 'Alpha' returns empty.
    vi.mocked(fetchPlayerVersions).mockImplementation(async (opts) =>
      opts?.keyword === 'Alpha' ? EMPTY_PLAYER_VERSION_TABLE : SINGLE_PLAYER_VERSION,
    );

    renderPlayerVersionsPage();
    await waitForPageReady();

    const searchInput = screen.getByPlaceholderText(
      'Search player versions...',
    ) as HTMLInputElement;

    await user.type(searchInput, 'Alpha');
    expect(await screen.findByText('No results found.')).toBeInTheDocument();

    await user.type(searchInput, '{Backspace}{Backspace}{Backspace}{Backspace}{Backspace}');
    expect(searchInput.value).toBe('');

    expect(await screen.findByText(mockPlayerVersion.playerShowVersion)).toBeInTheDocument();
  });

  test('selecting a Type updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const typeSelect = await screen.findByRole('combobox', { name: /^type$/i });
    await user.selectOptions(typeSelect, 'android');

    await waitFor(
      () => {
        expect(fetchPlayerVersions).toHaveBeenCalledWith(
          expect.objectContaining({ start: 0, playerType: 'android' }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('selecting a Version updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const versionSelect = await screen.findByRole('combobox', { name: /^version$/i });
    await user.selectOptions(versionSelect, '4.0.0');

    await waitFor(
      () => {
        expect(fetchPlayerVersions).toHaveBeenCalledWith(
          expect.objectContaining({ start: 0, playerVersion: '4.0.0' }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('entering a Code updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const codeInput = await screen.findByPlaceholderText('Code');
    await user.type(codeInput, '400');

    await waitFor(
      () => {
        expect(fetchPlayerVersions).toHaveBeenCalledWith(
          expect.objectContaining({ start: 0, playerCode: 400 }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('clicking Reset clears all filter inputs', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const codeInput = (await screen.findByPlaceholderText('Code')) as HTMLInputElement;
    await user.type(codeInput, '400');

    await waitFor(
      () => {
        expect(fetchPlayerVersions).toHaveBeenCalledWith(
          expect.objectContaining({ playerCode: 400 }),
        );
      },
      { timeout: 2000 },
    );

    await user.click(screen.getByRole('button', { name: /reset/i }));

    await waitFor(() => expect(codeInput.value).toBe(''));
  });

  test('Reset keeps the filter panel open', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await user.click(await screen.findByRole('button', { name: /reset/i }));

    screen.getByRole('button', { name: /reset/i });
  });
});
