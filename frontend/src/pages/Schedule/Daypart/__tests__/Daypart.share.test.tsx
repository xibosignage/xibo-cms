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
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockDaypart, MULTIPLE_DAYPARTS, SINGLE_DAYPART } from './fixtures/daypart';
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

vi.mock('@/components/ui/modals/ShareModal', () => ({
  default: ({
    title,
    entityId,
    entityType,
  }: {
    title?: string;
    entityId: number | number[] | null;
    entityType?: string;
  }) => (
    <div
      role="dialog"
      aria-label={title}
      data-testid="share-modal"
      data-entity-id={JSON.stringify(entityId)}
      data-entity-type={entityType}
    />
  ),
}));

const selectAllRows = async (user: UserEvent) => {
  const checkboxes = screen.getAllByRole('checkbox', { name: /select/i });
  await user.click(checkboxes[0]!);
};

// =============================================================================
// Tests — row Share
// =============================================================================

describe('Dayparting page - row share', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDaypart(SINGLE_DAYPART);
  });

  // Share lives in the row overflow menu; opening it shares that single daypart.
  test('the Share row action opens the Share modal for that daypart', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    await user.click(screen.getByRole('button', { name: /more actions/i }));
    await user.click(await screen.findByRole('button', { name: /^share$/i }));

    const modal = await screen.findByTestId('share-modal');
    expect(modal).toHaveAttribute('data-entity-id', String(mockDaypart.dayPartId));
    expect(modal).toHaveAttribute('aria-label', 'Share Daypart');
    expect(modal).toHaveAttribute('data-entity-type', 'DayPart');

    // Only the Share modal opens — not the Edit or Delete modal.
    expect(screen.queryByRole('dialog', { name: /edit daypart/i })).not.toBeInTheDocument();
    expect(screen.queryByText('Delete Daypart?')).not.toBeInTheDocument();
  }, 20_000);
});

// =============================================================================
// Tests — bulk Share
// =============================================================================

describe('Dayparting page - bulk share', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDaypart(MULTIPLE_DAYPARTS);
  });

  // "Share Selected" shares every selected daypart by passing the id array.
  test('"Share Selected" opens the Share modal for all selected dayparts', async () => {
    const user = userEvent.setup();
    renderDaypartPage();
    await screen.findByText('Daypart Alpha');

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /share selected/i }));

    const modal = await screen.findByTestId('share-modal');
    expect(modal).toHaveAttribute('data-entity-id', '[1,2]');
    expect(modal).toHaveAttribute('data-entity-type', 'DayPart');
  });
});
