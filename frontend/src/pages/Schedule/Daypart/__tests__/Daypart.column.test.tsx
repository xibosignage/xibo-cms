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

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockDaypart, SINGLE_DAYPART } from './fixtures/daypart';
import { renderDaypartPage } from './helpers/renderDaypartPage';
import { mockFetchDaypart } from './mocks/daypartApi';

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
vi.mock('../hooks/useDaypartFilterOptions', () => ({
  useDaypartFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Tests
// =============================================================================

describe('Dayparting page - column visibility', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDaypart(SINGLE_DAYPART);
  });

  // The Name column is always present (it cannot be hidden).
  test('the Name column header is visible', async () => {
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    expect(screen.getByRole('columnheader', { name: /^name$/i })).toBeInTheDocument();
  });

  // The ID column is part of the default visible set.
  test('the ID column header is visible by default', async () => {
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    expect(screen.getByRole('columnheader', { name: /^id$/i })).toBeInTheDocument();
  });

  // Opening the picker reveals a checkbox toggle per hideable column.
  test('the Columns button opens the column picker', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.getByRole('checkbox', { name: /^description$/i })).toBeInTheDocument();
  });

  // Every hideable column has a toggle; the always-on columns do not.
  test('the column picker lists every hideable column', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.getByRole('checkbox', { name: /^id$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^start time$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^end time$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^description$/i })).toBeInTheDocument();
  });

  // The Name column has enableHiding:false, so it has no toggle.
  test('the Name column cannot be hidden', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.queryByRole('checkbox', { name: /^name$/i })).not.toBeInTheDocument();
  });

  // Unchecking a column removes its header from the table.
  test('unchecking a column hides it from the table', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));
    const descriptionToggle = screen.getByRole('checkbox', { name: /^description$/i });
    expect(descriptionToggle).toBeChecked();

    await user.click(descriptionToggle);

    expect(screen.queryByRole('columnheader', { name: /^description$/i })).not.toBeInTheDocument();
  });

  // Re-checking a hidden column brings its header back.
  test('re-checking a hidden column brings it back', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));
    const descriptionToggle = screen.getByRole('checkbox', { name: /^description$/i });

    await user.click(descriptionToggle);
    expect(screen.queryByRole('columnheader', { name: /^description$/i })).not.toBeInTheDocument();

    await user.click(screen.getByRole('checkbox', { name: /^description$/i }));
    expect(screen.getByRole('columnheader', { name: /^description$/i })).toBeInTheDocument();
  });
});
