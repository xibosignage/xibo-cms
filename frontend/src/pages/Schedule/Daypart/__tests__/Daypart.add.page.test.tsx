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

// =============================================================================
// The page with the real Add modal and real time pickers. Daypart.add.test.tsx
// stubs the modal to cover the page wiring; this file drives the add end to end.
//
// Time picker accessibility fallbacks (xibosignage/xibo#4070, see
// components/ui/__tests__/TimePickerInput.test.tsx):
// - the time fields have no accessible name, so they are found by their
//   "Select time" placeholder, Start Time first
// - the hour and minute columns share "00"–"23"; the hour column comes first
// JSDOM does not implement scrollIntoView, which the picker calls on open.
// =============================================================================

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, afterAll, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { buildDaypart, mockDaypart, SINGLE_DAYPART } from './fixtures/daypart';
import { renderDaypartPage } from './helpers/renderDaypartPage';
import { mockFetchDaypart } from './mocks/daypartApi';

import { createDaypart } from '@/services/daypartApi';
import { testQueryClient } from '@/setupTests';
import { escapeRegExp } from '@/testUtils/escapeRegExp';
import { findRow } from '@/testUtils/rowActions';

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
// Helpers
// =============================================================================

const pickTime = async (user: UserEvent, field: HTMLElement, hh: string, mm: string) => {
  await user.click(field);
  const apply = await screen.findByRole('button', { name: 'Apply' });
  await user.click(screen.getAllByRole('button', { name: hh })[0]!);
  const minutes = screen.getAllByRole('button', { name: mm });
  await user.click(minutes[minutes.length - 1]!);
  await user.click(apply);
  await waitFor(() =>
    expect(screen.queryByRole('button', { name: 'Apply' })).not.toBeInTheDocument(),
  );
};

const created = buildDaypart({
  dayPartId: 2,
  name: 'Breakfast',
  description: 'Weekday breakfast service',
  startTime: '08:30:00',
  endTime: '11:45:00',
});

// =============================================================================
// Tests
// =============================================================================

describe('Dayparting page - add with the real modal', () => {
  // Saved so a real implementation (if the environment ever has one) is restored.
  const originalScrollIntoView = Object.getOwnPropertyDescriptor(
    Element.prototype,
    'scrollIntoView',
  );
  beforeAll(() => {
    Element.prototype.scrollIntoView = vi.fn();
  });
  afterAll(() => {
    if (originalScrollIntoView) {
      Object.defineProperty(Element.prototype, 'scrollIntoView', originalScrollIntoView);
    } else {
      delete (Element.prototype as Partial<Element>).scrollIntoView;
    }
  });
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDaypart(SINGLE_DAYPART);
  });

  test(
    'adding a daypart from the page sends the entered values and shows the new daypart',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      vi.mocked(createDaypart).mockResolvedValueOnce(created);

      renderDaypartPage();
      await screen.findByRole('row', { name: new RegExp(escapeRegExp(mockDaypart.name)) });

      await user.click(screen.getByRole('button', { name: 'Add Daypart' }));
      const dialog = await screen.findByRole('dialog', { name: 'Add Daypart' });

      // Once saved, the refreshed list includes the new daypart.
      mockFetchDaypart({ rows: [mockDaypart, created], totalCount: 2 });

      await user.type(within(dialog).getByRole('textbox', { name: 'Name' }), 'Breakfast');
      const [startField, endField] = within(dialog).getAllByPlaceholderText('Select time');
      await pickTime(user, startField!, '08', '30');
      await pickTime(user, endField!, '11', '45');
      expect(startField).toHaveValue('08:30:00');
      expect(endField).toHaveValue('11:45:00');

      await user.click(within(dialog).getByRole('button', { name: 'Description' }));
      await user.type(
        within(dialog).getByRole('textbox', { name: 'Description' }),
        'Weekday breakfast service',
      );
      await user.click(within(dialog).getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(createDaypart).toHaveBeenCalledWith({
          name: 'Breakfast',
          description: 'Weekday breakfast service',
          isRetired: 0,
          startTime: '08:30:00',
          endTime: '11:45:00',
          exceptionDays: [],
          exceptionStartTimes: [],
          exceptionEndTimes: [],
        });
      });
      expect(createDaypart).toHaveBeenCalledTimes(1);

      await waitFor(() => expect(dialog).not.toBeInTheDocument());
      await findRow('Breakfast');
    },
  );
});
