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

import { mockDaypart, SINGLE_DAYPART } from './fixtures/daypart';
import { renderDaypartPage } from './helpers/renderDaypartPage';
import { mockFetchDaypart } from './mocks/daypartApi';

import { fetchDaypart } from '@/services/daypartApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/daypartApi');
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
    options?: Array<{ value: string; label: string; disabled?: boolean }>;
    onSelect?: (value: string) => void;
    placeholder?: string;
  }) => (
    <select aria-label={label} value={value ?? ''} onChange={(e) => onSelect?.(e.target.value)}>
      {placeholder !== undefined && <option value="">{placeholder}</option>}
      {options?.map((o) => (
        <option key={o.value} value={o.value} disabled={o.disabled}>
          {o.label}
        </option>
      ))}
    </select>
  ),
}));

const waitForPageReady = () => screen.findByText(mockDaypart.name);

describe('Dayparting page - search and filters', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDaypart(SINGLE_DAYPART);
  });

  // With the panel closed its fields are hidden from the accessibility tree.
  test('the filter panel is hidden by default', async () => {
    renderDaypartPage();
    await waitForPageReady();

    expect(screen.queryByRole('textbox', { name: /^name$/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
  });

  // Clicking Filters reveals the Name / Retired fields.
  test('clicking Filters opens the panel', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));

    expect(await screen.findByRole('textbox', { name: /^name$/i })).toBeInTheDocument();
  });

  // Clicking Filters a second time hides the panel again.
  test('clicking Filters again closes the panel', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await waitForPageReady();

    const filtersButton = screen.getByRole('button', { name: /filters/i });
    await user.click(filtersButton);
    await screen.findByRole('textbox', { name: /^name$/i });

    await user.click(filtersButton);

    await waitFor(() => {
      expect(screen.queryByRole('textbox', { name: /^name$/i })).not.toBeInTheDocument();
    });
  });

  // Typing in the search box feeds a keyword into the daypart query.
  test('typing in the search box fetches results with that keyword', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await waitForPageReady();

    await user.type(screen.getByPlaceholderText('Search daypart...'), 'Alpha');

    await waitFor(
      () => {
        expect(fetchDaypart).toHaveBeenCalledWith(expect.objectContaining({ name: 'Alpha' }));
      },
      { timeout: 2000 },
    );
  });

  // Searching resets back to the first page of results.
  test('typing in the search box resets to page 1', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await waitForPageReady();

    await user.type(screen.getByPlaceholderText('Search daypart...'), 'Alpha');

    await waitFor(
      () => {
        expect(fetchDaypart).toHaveBeenCalledWith(
          expect.objectContaining({ name: 'Alpha', start: 0 }),
        );
      },
      { timeout: 2000 },
    );
  });

  // The Name filter feeds the `name` query param and resets to page 1.
  test('entering a Name filter updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const nameInput = await screen.findByRole('textbox', { name: /^name$/i });
    await user.type(nameInput, 'Alpha');

    await waitFor(
      () => {
        expect(fetchDaypart).toHaveBeenCalledWith(
          expect.objectContaining({ name: 'Alpha', start: 0 }),
        );
      },
      { timeout: 2000 },
    );
  });

  // The Retired filter feeds the `isRetired` query param and resets to page 1.
  test('changing the Retired filter updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const retiredSelect = await screen.findByRole('combobox', { name: /^retired$/i });
    await user.selectOptions(retiredSelect, '1');

    await waitFor(() => {
      expect(fetchDaypart).toHaveBeenCalledWith(
        expect.objectContaining({ isRetired: 1, start: 0 }),
      );
    });
  });

  // Clearing the search box drops the keyword and re-fetches the full list.
  test('clearing the search restores the unfiltered list', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await waitForPageReady();

    const search = screen.getByPlaceholderText('Search daypart...');
    await user.type(search, 'Alpha');
    await waitFor(
      () => {
        expect(fetchDaypart).toHaveBeenCalledWith(expect.objectContaining({ name: 'Alpha' }));
      },
      { timeout: 2000 },
    );

    await user.clear(search);
    await waitFor(
      () => {
        expect(fetchDaypart).toHaveBeenCalledWith(expect.objectContaining({ name: undefined }));
      },
      { timeout: 2000 },
    );
  });

  // The Name field's AND/OR control feeds `logicalOperatorName` into the query.
  test('toggling the Name AND/OR control updates the query operator', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await screen.findByRole('textbox', { name: /^name$/i });

    // The control shows "OR" by default; switch it to "AND".
    await user.click(screen.getByRole('button', { name: /^or$/i }));
    await user.click(await screen.findByRole('button', { name: /^and$/i }));

    await waitFor(() => {
      expect(fetchDaypart).toHaveBeenCalledWith(
        expect.objectContaining({ logicalOperatorName: 'AND' }),
      );
    });
  });

  // The regex control feeds `useRegexForName` once a (valid) Name filter is set.
  test('toggling the regex control sends useRegexForName when a Name filter is set', async () => {
    const user = userEvent.setup();
    const { container } = renderDaypartPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const nameInput = await screen.findByRole('textbox', { name: /^name$/i });
    await user.type(nameInput, 'Alpha');

    // The regex toggle is an icon-only control; click it via its wrapping title.
    await user.click(screen.getByTitle('Use RegEx pattern matching'));
    // (container is available if a class-based lookup is ever needed)
    expect(container).toBeTruthy();

    await waitFor(
      () => {
        expect(fetchDaypart).toHaveBeenCalledWith(
          expect.objectContaining({ useRegexForName: 1, name: 'Alpha' }),
        );
      },
      { timeout: 2000 },
    );
  });

  // Reset restores the filter inputs to their defaults (Name empty, Retired "No").
  test('clicking Reset clears the filter inputs', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const nameInput = (await screen.findByRole('textbox', { name: /^name$/i })) as HTMLInputElement;
    await user.type(nameInput, 'Alpha');
    expect(nameInput).toHaveValue('Alpha');
    await user.selectOptions(screen.getByRole('combobox', { name: /^retired$/i }), '1');

    // The Name field is debounced; wait for the value to commit to the filter
    // state (externalValue) before resetting, otherwise Reset can't re-sync it.
    await waitFor(
      () => {
        expect(fetchDaypart).toHaveBeenCalledWith(expect.objectContaining({ name: 'Alpha' }));
      },
      { timeout: 2000 },
    );

    await user.click(screen.getByRole('button', { name: /reset/i }));

    await waitFor(() => {
      expect(screen.getByRole('textbox', { name: /^name$/i })).toHaveValue('');
    });
    expect(screen.getByRole('combobox', { name: /^retired$/i })).toHaveValue('0');
  });

  // Reset clears the inputs but leaves the panel open.
  test('Reset keeps the filter panel open', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await screen.findByRole('textbox', { name: /^name$/i });

    await user.click(screen.getByRole('button', { name: /reset/i }));

    expect(screen.getByRole('textbox', { name: /^name$/i })).toBeInTheDocument();
  });
});
