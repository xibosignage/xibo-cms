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
// Test type: Form — Export Statistics modal
// =============================================================================

import { render, screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, afterAll, afterEach, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { stubIntersectionObserver } from '../../../__tests__/helpers/domStubs';
import { pickOption } from '../../../__tests__/helpers/renderReportPage';
import { setupOptionServiceMocks } from '../../../__tests__/mocks/optionServices';
import ExportStatisticsModal from '../../components/ExportStatisticsModal';

import { fetchExportStatsCount } from '@/services/reportApi';
import { deferred } from '@/testUtils/deferred';
import { knownFailure } from '@/testUtils/knownFailure';
import { setTimezone } from '@/testUtils/timezone';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/reportApi', () => ({ fetchExportStatsCount: vi.fn() }));
vi.mock('@/services/displaysApi', async (o) => ({
  ...((await o()) as object),
  fetchDisplays: vi.fn(),
}));
vi.mock('@/services/displayGroupApi', async (o) => ({
  ...((await o()) as object),
  fetchDisplayGroups: vi.fn(),
}));
vi.mock('@/services/layoutsApi', async (o) => ({
  ...((await o()) as object),
  fetchLayouts: vi.fn(),
}));
vi.mock('@/services/mediaApi', async (o) => ({ ...((await o()) as object), fetchMedia: vi.fn() }));
vi.mock('@/services/campaignApi', async (o) => ({
  ...((await o()) as object),
  fetchCampaigns: vi.fn(),
}));
vi.mock('@/services/userGroupApi', async (o) => ({
  ...((await o()) as object),
  fetchUserGroups: vi.fn(),
}));
vi.mock('@/services/userApi', () => ({ fetchUsers: vi.fn() }));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/forms/DatePickerInput', () => ({
  default: ({
    label,
    value,
    onChange,
    error,
  }: {
    label: string;
    value: string;
    onChange: (v: string) => void;
    error?: string;
  }) => (
    <div>
      <input aria-label={label} value={value} onChange={(e) => onChange(e.target.value)} />
      {error && <span>{error}</span>}
    </div>
  ),
}));
// The install folder is read once at import, so pretend to be a /cms/ install.
vi.mock('@/config/publicPath', async (importOriginal) => ({
  ...((await importOriginal()) as object),
  withPublicPath: (path: string) => '/cms/' + path.replace(/^\//, ''),
}));

// =============================================================================
// Helpers
// =============================================================================

const onClose = vi.fn();
const realLocation = window.location;
const originalTz = process.env.TZ;

const renderModal = () => render(<ExportStatisticsModal isOpen onClose={onClose} />);
const fromInput = () => screen.getByRole('textbox', { name: 'From Date' });
const toInput = () => screen.getByRole('textbox', { name: 'To Date' });
const exportButton = () => screen.getByRole('button', { name: /^export$/i });

// What the real date-only picker emits for a calendar day: local midnight as ISO.
const pickedDay = (y: number, m: number, d: number) => new Date(y, m - 1, d).toISOString();

// Sets the date in one change, as the real picker does.
const setDate = async (user: UserEvent, input: HTMLElement, iso: string) => {
  await user.click(input);
  await user.paste(iso);
};

const exportedParams = () => new URLSearchParams(window.location.href.split('?')[1]);

// =============================================================================
// Tests
// =============================================================================

describe('Export Statistics modal', () => {
  beforeAll(() => {
    process.env.TZ = 'UTC';
    stubIntersectionObserver();
  });
  afterAll(() => {
    setTimezone(originalTz);
  });
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchExportStatsCount).mockReset().mockResolvedValue(1234);
    setupOptionServiceMocks();
    Object.defineProperty(window, 'location', {
      configurable: true,
      writable: true,
      value: { ...realLocation, href: '' },
    });
  });
  afterEach(() => {
    Object.defineProperty(window, 'location', { configurable: true, value: realLocation });
  });

  test('both dates are required, and each message clears once its date is filled', async () => {
    const user = userEvent.setup({ delay: null });
    renderModal();

    await user.click(exportButton());

    expect(screen.getAllByText('This field is required.')).toHaveLength(2);
    expect(window.location.href).toBe('');
    expect(onClose).not.toHaveBeenCalled();

    await setDate(user, fromInput(), pickedDay(2027, 1, 1));
    expect(screen.getAllByText('This field is required.')).toHaveLength(1);
  });

  test('with both dates, the number of records to export is shown', async () => {
    const user = userEvent.setup({ delay: null });
    const count = deferred<number>();
    vi.mocked(fetchExportStatsCount).mockReturnValueOnce(count.promise);
    renderModal();

    await setDate(user, fromInput(), pickedDay(2027, 1, 1));
    await setDate(user, toInput(), pickedDay(2027, 1, 7));

    await screen.findByText('Counting records...');
    expect(exportButton()).toBeDisabled();
    count.resolve(1234);

    await waitFor(() =>
      expect(screen.getByRole('dialog')).toHaveTextContent(
        'Total number of records to be exported: 1234',
      ),
    );
    expect(fetchExportStatsCount).toHaveBeenLastCalledWith(
      { fromDt: '2027-01-01 00:00:00', toDt: '2027-01-07 00:00:00' },
      expect.any(AbortSignal),
    );
  });

  test('a failed count says so', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(fetchExportStatsCount).mockRejectedValueOnce(new Error('Server error'));
    renderModal();

    await setDate(user, fromInput(), pickedDay(2027, 1, 1));
    await setDate(user, toInput(), pickedDay(2027, 1, 7));

    await screen.findByText('Failed to get record count.');
  });

  test('Export downloads with the dates, display and UTC choice, under the install folder', async () => {
    const user = userEvent.setup({ delay: null });
    renderModal();
    await setDate(user, fromInput(), pickedDay(2027, 1, 1));
    await setDate(user, toInput(), pickedDay(2027, 1, 7));
    // The field's name includes its "Optional" marker.
    await pickOption(user, /^display/i, 'Lobby Screen');
    await waitFor(() => expect(exportButton()).toBeEnabled());

    await user.click(exportButton());

    expect(window.location.href.startsWith('/cms/stats/export?')).toBe(true);
    expect(Object.fromEntries(exportedParams())).toEqual({
      fromDt: '2027-01-01 00:00:00',
      toDt: '2027-01-07 00:00:00',
      isOutputUtc: '1',
      displayId: '1',
    });
    expect(onClose).toHaveBeenCalled();
  });

  test('UTC output is ticked by default; unticking sends isOutputUtc=0', async () => {
    const user = userEvent.setup({ delay: null });
    renderModal();
    const utc = screen.getByRole('checkbox', { name: /output dates as utc/i });
    expect(utc).toBeChecked();

    await setDate(user, fromInput(), pickedDay(2027, 1, 1));
    await setDate(user, toInput(), pickedDay(2027, 1, 7));
    await user.click(utc);
    await waitFor(() => expect(exportButton()).toBeEnabled());
    await user.click(exportButton());

    expect(exportedParams().get('isOutputUtc')).toBe('0');
    expect(exportedParams().has('displayId')).toBe(false);
  });
});

// =============================================================================
// Timezone (Known issue 16, xibosignage/xibo#4097)
// =============================================================================

describe('Export Statistics modal - browser ahead of UTC', () => {
  beforeAll(() => {
    process.env.TZ = 'Pacific/Auckland'; // UTC+13 in January
    stubIntersectionObserver();
  });
  afterAll(() => {
    setTimezone(originalTz);
  });
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchExportStatsCount).mockReset().mockResolvedValue(1);
    setupOptionServiceMocks();
  });

  // Guard: if the TZ switch ever stops working, the check below passes vacuously.
  test('the environment really is running 13 hours ahead of UTC', () => {
    expect(new Date('2027-01-01T00:00:00Z').getTimezoneOffset()).toBe(-13 * 60);
  });

  // Known issue 16: xibosignage/xibo#4097 (export shifts the picked day)
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4097 is fixed', () => {
    knownFailure(
      'the export keeps the calendar day the user picked',
      async () => {
        const user = userEvent.setup({ delay: null });
        renderModal();
        await setDate(user, fromInput(), pickedDay(2027, 1, 1));
        await setDate(user, toInput(), pickedDay(2027, 1, 7));
        await waitFor(() => expect(fetchExportStatsCount).toHaveBeenCalled());
        const calls = vi.mocked(fetchExportStatsCount).mock.calls;
        return calls[calls.length - 1]![0];
      },
      (params) => expect(params.fromDt.startsWith('2027-01-01')).toBe(true),
    );
  });
});
