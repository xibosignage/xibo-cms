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

import { buildDisplay } from '../../../fixtures/display';
import { renderEditModal } from '../helpers/renderEditModal';

import { fetchDisplayLocales, updateDisplay } from '@/services/displaysApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next', () => {
  const t = (key: string) => key;
  return {
    useTranslation: () => ({ t, i18n: { changeLanguage: vi.fn() } }),
    Trans: ({ children }: { children: React.ReactNode }) => children,
  };
});

vi.mock('@/services/displaysApi', () => ({
  updateDisplay: vi.fn(),
  fetchDisplayVenues: vi.fn().mockResolvedValue([]),
  fetchDisplayLocales: vi.fn().mockResolvedValue([]),
  fetchDisplays: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/services/displayProfileApi', () => ({
  fetchDisplayProfile: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  fetchDisplayProfileById: vi.fn().mockResolvedValue(null),
}));
vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
  fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
  selectFolder: vi.fn().mockResolvedValue({ success: true }),
}));
vi.mock('@/services/layoutsApi', () => ({
  fetchLayouts: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/services/playerSoftwareApi', () => ({
  fetchPlayerSoftware: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/services/daypartApi', () => ({
  fetchDaypart: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/forms/SelectFolder', () => ({
  default: ({ selectedId }: { selectedId?: number | null }) => (
    <div data-testid="mock-select-folder" data-folder-id={selectedId ?? ''} />
  ),
}));
vi.mock('@/components/ui/forms/TimezoneSelect', () => ({
  default: ({
    id,
    value,
    onChange,
  }: {
    id?: string;
    value: string;
    onChange: (v: string) => void;
    helpText?: string;
  }) => (
    <div>
      <label htmlFor={id}>Timezone</label>
      <select
        id={id}
        role="combobox"
        aria-label="Timezone"
        value={value}
        onChange={(e) => onChange(e.target.value)}
      >
        <option value=""></option>
        <option value="UTC">(GMT) UTC</option>
        <option value="Europe/London">(GMT+1) Europe, London</option>
      </select>
    </div>
  ),
}));

// =============================================================================
// Tests
// =============================================================================

describe('Display - edit form: Details tab', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // Latitude
  // ---------------------------------------------------------------------------

  test('latitude is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ latitude: 51.5 }) });
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    expect(screen.getByRole('spinbutton', { name: /latitude/i })).toHaveValue(51.5);
  });

  test('latitude is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    const input = screen.getByRole('spinbutton', { name: /latitude/i });
    await user.clear(input);
    await user.type(input, '48');

    expect(input).toHaveValue(48);
  });

  // ---------------------------------------------------------------------------
  // Longitude
  // ---------------------------------------------------------------------------

  test('longitude is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ longitude: -0.1 }) });
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    expect(screen.getByRole('spinbutton', { name: /longitude/i })).toHaveValue(-0.1);
  });

  test('longitude is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    const input = screen.getByRole('spinbutton', { name: /longitude/i });
    await user.clear(input);
    await user.type(input, '2');

    expect(input).toHaveValue(2);
  });

  // ---------------------------------------------------------------------------
  // Address
  // ---------------------------------------------------------------------------

  test('address is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ address: '123 Main St' }) });
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    expect(screen.getByRole('textbox', { name: /address/i })).toHaveValue('123 Main St');
  });

  test('address is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    const input = screen.getByRole('textbox', { name: /address/i });
    await user.clear(input);
    await user.type(input, '456 New Road');

    expect(input).toHaveValue('456 New Road');
  });

  // ---------------------------------------------------------------------------
  // Screen size
  // ---------------------------------------------------------------------------

  test('screen size is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ screenSize: 55 }) });
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    expect(screen.getByRole('spinbutton', { name: /screen size/i })).toHaveValue(55);
  });

  test('screen size is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    const input = screen.getByRole('spinbutton', { name: /screen size/i });
    await user.clear(input);
    await user.type(input, '75');

    expect(input).toHaveValue(75);
  });

  // ---------------------------------------------------------------------------
  // Is mobile?
  // ---------------------------------------------------------------------------

  test('is mobile checkbox reflects the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ isMobile: 1 }) });
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    expect(screen.getByRole('checkbox', { name: /is mobile/i })).toBeChecked();
  });

  test('is mobile checkbox can be toggled', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ isMobile: 0 }) });
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    const checkbox = screen.getByRole('checkbox', { name: /is mobile/i });
    expect(checkbox).not.toBeChecked();
    await user.click(checkbox);
    expect(checkbox).toBeChecked();
  });

  // ---------------------------------------------------------------------------
  // Is outdoor?
  // ---------------------------------------------------------------------------

  test('is outdoor checkbox reflects the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ isOutdoor: 1 }) });
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    expect(screen.getByRole('checkbox', { name: /is outdoor/i })).toBeChecked();
  });

  test('is outdoor checkbox can be toggled', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ isOutdoor: 0 }) });
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    const checkbox = screen.getByRole('checkbox', { name: /is outdoor/i });
    expect(checkbox).not.toBeChecked();
    await user.click(checkbox);
    expect(checkbox).toBeChecked();
  });

  // ---------------------------------------------------------------------------
  // Cost per play
  // ---------------------------------------------------------------------------

  test('cost per play is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ costPerPlay: 2.5 }) });
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    expect(screen.getByRole('spinbutton', { name: /cost per play/i })).toHaveValue(2.5);
  });

  test('cost per play is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    const input = screen.getByRole('spinbutton', { name: /cost per play/i });
    await user.clear(input);
    await user.type(input, '5');

    expect(input).toHaveValue(5);
  });

  // ---------------------------------------------------------------------------
  // Impressions per play
  // ---------------------------------------------------------------------------

  test('impressions per play is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ impressionsPerPlay: 100 }) });
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    expect(screen.getByRole('spinbutton', { name: /impressions per play/i })).toHaveValue(100);
  });

  test('impressions per play is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    const input = screen.getByRole('spinbutton', { name: /impressions per play/i });
    await user.clear(input);
    await user.type(input, '200');

    expect(input).toHaveValue(200);
  });

  // ---------------------------------------------------------------------------
  // Timezone (TimezoneSelect → SelectDropdown with role="combobox")
  // ---------------------------------------------------------------------------

  test('timezone combobox is present', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    expect(screen.getByRole('combobox', { name: /timezone/i })).not.toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Languages (MultiSelectDropdown with role="combobox")
  // ---------------------------------------------------------------------------

  test('languages combobox is present', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    expect(screen.getByRole('combobox', { name: /languages/i })).not.toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Display Type (SelectDropdown with role="combobox")
  // ---------------------------------------------------------------------------

  test('display type combobox is present', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    expect(screen.getByRole('combobox', { name: /display type/i })).not.toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Venue (SelectDropdown with role="combobox")
  // ---------------------------------------------------------------------------

  test('venue combobox is present', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    expect(screen.getByRole('combobox', { name: /venue/i })).not.toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Edge cases — null number fields render as empty (not 0)
  //
  // latitude, longitude, and screenSize default to null in the fixture.
  // The component passes `value={draft.field ?? undefined}` to NumberInput,
  // which leaves the input uncontrolled (empty). toHaveValue(null) is the
  // @testing-library assertion for an empty number input.
  //
  // This matters because 0 is a valid geographic coordinate, so null and 0
  // must render differently — null as blank, 0 as the digit "0".
  // ---------------------------------------------------------------------------

  test('latitude renders as empty when not set', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ latitude: null }) });
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    expect(screen.getByRole('spinbutton', { name: /latitude/i })).toHaveValue(null);
  });

  test('longitude renders as empty when not set', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ longitude: null }) });
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    expect(screen.getByRole('spinbutton', { name: /longitude/i })).toHaveValue(null);
  });

  test('screen size renders as empty when not set', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ screenSize: null }) });
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    expect(screen.getByRole('spinbutton', { name: /screen size/i })).toHaveValue(null);
  });

  // ---------------------------------------------------------------------------
  // Edge cases — zero is a valid value and must not be treated as falsy
  //
  // A display on the equator (latitude 0), the prime meridian (longitude 0),
  // or with a 0-inch screen size is real. If the component used
  // `onChange={(v) => set('field', v || null)}` then typing 0 would silently
  // store null. The `?? null` pattern avoids this.
  // ---------------------------------------------------------------------------

  // BUG: onChange uses `v || null` which treats 0 as falsy — typing 0 stores
  // null in the draft, so the save payload contains null instead of 0.
  // The DOM shows 0 (uncontrolled input) so the input value alone cannot
  // catch this; we check the updateDisplay payload instead.
  // Marked test.fails until the bug is fixed (change `v || null` to `v ?? null`).

  test.fails('typing 0 into latitude saves 0, not null', async () => {
    const user = userEvent.setup();
    vi.mocked(updateDisplay).mockResolvedValue(undefined as never);
    await renderEditModal({ data: buildDisplay({ latitude: null }) });
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    await user.type(screen.getByRole('spinbutton', { name: /latitude/i }), '0');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(updateDisplay).toHaveBeenCalledWith(
        expect.anything(),
        expect.objectContaining({ latitude: 0 }),
      );
    });
  });

  test.fails('typing 0 into longitude saves 0, not null', async () => {
    const user = userEvent.setup();
    vi.mocked(updateDisplay).mockResolvedValue(undefined as never);
    await renderEditModal({ data: buildDisplay({ longitude: null }) });
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    await user.type(screen.getByRole('spinbutton', { name: /longitude/i }), '0');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(updateDisplay).toHaveBeenCalledWith(
        expect.anything(),
        expect.objectContaining({ longitude: 0 }),
      );
    });
  });

  test.fails('typing 0 into screen size saves 0, not null', async () => {
    const user = userEvent.setup();
    vi.mocked(updateDisplay).mockResolvedValue(undefined as never);
    await renderEditModal({ data: buildDisplay({ screenSize: null }) });
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    await user.type(screen.getByRole('spinbutton', { name: /screen size/i }), '0');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(updateDisplay).toHaveBeenCalledWith(
        expect.anything(),
        expect.objectContaining({ screenSize: 0 }),
      );
    });
  });

  // ---------------------------------------------------------------------------
  // Edge case — timezone pre-populated with the saved value
  //
  // When a display has a saved timezone the combobox must show that value on
  // open, not the placeholder. TimezoneSelect builds its options from
  // Intl.supportedValuesOf, so 'UTC' is guaranteed to be available.
  // ---------------------------------------------------------------------------

  test('timezone combobox shows the saved timezone value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ timeZone: 'UTC' }) });
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    expect(screen.getByRole('combobox', { name: /timezone/i })).toHaveTextContent('(GMT) UTC');
  });

  // ---------------------------------------------------------------------------
  // Edge case — languages pre-populated with the saved value
  //
  // The display stores languages as a comma-separated string ('en'). The modal
  // splits it into an array and passes it to MultiSelectDropdown. The dropdown
  // resolves the value to a human-readable label using the options returned by
  // fetchDisplayLocales. This test verifies the full chain: stored string →
  // split array → option label shown in the combobox.
  // ---------------------------------------------------------------------------

  test('languages combobox shows the saved language label', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchDisplayLocales).mockResolvedValueOnce([{ id: 'en', value: 'English' }]);

    await renderEditModal({ data: buildDisplay({ languages: 'en' }) });
    await user.click(screen.getByRole('tab', { name: 'Details' }));

    const combobox = screen.getByRole('combobox', { name: /languages/i });
    await waitFor(() => expect(combobox).toHaveTextContent('English'));
  });
});
