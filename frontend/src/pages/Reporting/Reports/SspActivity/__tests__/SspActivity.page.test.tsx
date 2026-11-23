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
// Test type: Page integration — SSP Activity: connector, filters and request
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, afterAll, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { SSP_CONNECTOR_ID, SSP_PARTNERS, SSP_RESPONSE } from '../../__tests__/fixtures/reportRows';
import { stubIntersectionObserver } from '../../__tests__/helpers/domStubs';
import {
  applyButton,
  pickOption,
  renderReportPage,
} from '../../__tests__/helpers/renderReportPage';
import { setupOptionServiceMocks } from '../../__tests__/mocks/optionServices';
import SspActivity from '../SspActivity';
import type { SspActivityFilter } from '../SspActivityConfig';

import { fetchConnectors } from '@/services/connectorApi';
import { fetchSspActivity, fetchSspPartners } from '@/services/sspActivityApi';
import type { SspActivityResponse } from '@/services/sspActivityApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { setTimezone } from '@/testUtils/timezone';
import type { Connector } from '@/types/connector';

// =============================================================================
// Module mocks
// =============================================================================

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => ({
  ...((await importOriginal()) as object),
  useNavigate: () => mockNavigate,
}));
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
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn(),
}));
vi.mock('@/services/reportApi', () => ({
  fetchReports: vi.fn().mockResolvedValue({}),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/services/connectorApi', async (o) => ({
  ...((await o()) as object),
  fetchConnectors: vi.fn(),
}));
vi.mock('@/services/sspActivityApi', async (o) => ({
  ...((await o()) as object),
  fetchSspActivity: vi.fn(),
  fetchSspPartners: vi.fn(),
}));
// Plain inputs so a test can type the ISO string the real date picker emits.
vi.mock('@/components/ui/forms/DatePickerInput', () => ({
  default: ({
    label,
    value,
    onChange,
  }: {
    label: string;
    value: string;
    onChange: (v: string) => void;
  }) => <input aria-label={label} value={value} onChange={(e) => onChange(e.target.value)} />,
}));

// =============================================================================
// Helpers
// =============================================================================

const SSP_CONNECTOR = {
  connectorId: SSP_CONNECTOR_ID,
  className: 'Xibo\\\\Connector\\\\XiboSspConnector',
  isEnabled: 1,
  isVisible: 1,
  isSystem: false,
  title: 'Xibo SSP',
  description: '',
  thumbnail: '',
} as unknown as Connector;

const serveSsp = (activity: SspActivityResponse) => {
  vi.mocked(fetchSspPartners).mockResolvedValue(SSP_PARTNERS);
  vi.mocked(fetchSspActivity).mockResolvedValue(activity);
};

const renderPage = () =>
  renderReportPage(SspActivity, {
    route: '/reporting/ssp-activity',
    settingsKey: 'ssp_activity_report_page',
  });

const activityRequest = () => {
  const calls = vi.mocked(fetchSspActivity).mock.calls;
  return calls[calls.length - 1]![0];
};

const setDates = async (user: UserEvent, from: string, to: string) => {
  const fromInput = screen.getByRole('textbox', { name: 'From Date' });
  const toInput = screen.getByRole('textbox', { name: 'To Date' });
  await user.clear(fromInput);
  await user.type(fromInput, from);
  await user.clear(toInput);
  await user.type(toInput, to);
};

const originalTz = process.env.TZ;

// =============================================================================
// Tests
// =============================================================================

describe('SSP Activity - connector, filters and request', () => {
  beforeAll(() => {
    stubIntersectionObserver();
    process.env.TZ = 'UTC';
  });
  afterAll(() => {
    setTimezone(originalTz);
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // clearAllMocks keeps behaviour set by earlier tests; reset what this file overrides.
    vi.mocked(fetchConnectors).mockReset().mockResolvedValue([SSP_CONNECTOR]);
    vi.mocked(fetchSspPartners).mockReset();
    vi.mocked(fetchSspActivity).mockReset();
    serveSsp(SSP_RESPONSE);
    setupOptionServiceMocks();
  });

  test('without the SSP connector the page says so and hides the filters', async () => {
    vi.mocked(fetchConnectors).mockResolvedValueOnce([]);
    renderPage();

    await screen.findByText('The Xibo SSP Connector is not installed or enabled.');
    expect(screen.queryByRole('button', { name: /^apply$/i })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: /filters/i })).toBeDisabled();
  });

  test('Apply stays disabled until a display is chosen', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();

    await screen.findByText('Please select a display');
    expect(applyButton()).toBeDisabled();

    await pickOption(user, /^display$/i, 'Lobby Screen');

    await waitFor(() => expect(applyButton()).toBeEnabled());
    expect(screen.queryByText('Please select a display')).not.toBeInTheDocument();
  });

  test('partners load from the connector into the Partner list', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });

    await user.click(screen.getByRole('combobox', { name: /^partner/i }));

    await screen.findByRole('option', { name: 'Partner A' });
    expect(screen.getByRole('option', { name: 'Partner B' })).toBeInTheDocument();
    expect(fetchSspPartners).toHaveBeenCalledWith(SSP_CONNECTOR_ID, expect.anything());
  });

  test('display, partner and dates reach the connector request', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });

    await pickOption(user, /^display$/i, 'Lobby Screen');
    await pickOption(user, /^partner/i, 'Partner A');
    await setDates(user, '2027-01-01T00:00:00.000Z', '2027-01-01T23:59:00.000Z');
    await user.click(applyButton());

    await waitFor(() =>
      expect(fetchSspActivity).toHaveBeenCalledWith(
        expect.objectContaining({
          connectorId: SSP_CONNECTOR_ID,
          displayId: 1,
          partnerId: 'partnerA',
          activityFromDt: '2027-01-01 00:00:00',
          activityToDt: '2027-01-01 23:59:00',
        }),
      ),
    );
  });

  test('with no partner chosen, none is sent', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });

    await pickOption(user, /^display$/i, 'Lobby Screen');
    await user.click(applyButton());

    await waitFor(() =>
      expect(fetchSspActivity).toHaveBeenCalledWith(expect.objectContaining({ displayId: 1 })),
    );
    // The service drops an empty partner (sspActivityApi.test.ts).
    expect(['', undefined]).toContain(activityRequest().partnerId);
  });

  test('a failure reported by the SSP service shows its message', async () => {
    const user = userEvent.setup({ delay: null });
    serveSsp({ success: false, message: 'Partner offline' });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });

    await pickOption(user, /^display$/i, 'Lobby Screen');
    await user.click(applyButton());

    await screen.findByText('Partner offline');
    expect(screen.getByRole('button', { name: /retry/i })).toBeInTheDocument();
  });

  // Known issue 10: xibosignage/xibo#4101 (lookup ignores whether the connector is enabled)
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4101 is fixed', () => {
    knownFailure(
      'a disabled SSP connector is reported as not installed or enabled',
      async () => {
        vi.mocked(fetchConnectors).mockResolvedValueOnce([
          { ...SSP_CONNECTOR, isEnabled: 0 } as Connector,
        ]);
        renderPage();
        // Control: the page finished looking the connector up.
        await screen.findByRole('button', { name: /filters/i });
        await waitFor(() => expect(fetchConnectors).toHaveBeenCalled());
        await waitFor(() =>
          expect(screen.queryByText('Please select a display')).toBeInTheDocument(),
        );
      },
      () =>
        expect(
          screen.getByText('The Xibo SSP Connector is not installed or enabled.'),
        ).toBeInTheDocument(),
    );
  });

  // Known issue 10: xibosignage/xibo#4101 (failed lookup shown as not installed)
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4101 is fixed', () => {
    knownFailure(
      'a failed connector lookup is not reported as a missing connector',
      async () => {
        vi.mocked(fetchConnectors).mockRejectedValueOnce(new Error('Network Error'));
        renderPage();
        // Control: the lookup was attempted and the page settled.
        await waitFor(() => expect(fetchConnectors).toHaveBeenCalled());
        await waitFor(() =>
          expect(screen.queryByRole('button', { name: /^apply$/i })).not.toBeInTheDocument(),
        );
      },
      () =>
        expect(
          screen.queryByText('The Xibo SSP Connector is not installed or enabled.'),
        ).not.toBeInTheDocument(),
    );
  });

  // Known issue 8: xibosignage/xibo#4100 (default dates fixed at config load)
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4100 is fixed', () => {
    knownFailure(
      "the default From Date is today's, not the day the page loaded",
      async () => {
        vi.useFakeTimers({ toFake: ['Date'] });
        let defaults: SspActivityFilter;
        try {
          vi.setSystemTime(new Date('2027-01-01T10:00:00Z'));
          vi.resetModules();
          const config = await import('../SspActivityConfig');
          vi.setSystemTime(new Date('2027-01-02T10:00:00Z'));
          defaults = config.INITIAL_FILTER_STATE;
        } finally {
          vi.useRealTimers();
        }
        // Control: the defaults hold a start-of-day date.
        expect(defaults.activityFromDt).toMatch(/T00:00:00\.000Z$/);
        return defaults;
      },
      (defaults) => expect(defaults.activityFromDt).toBe('2027-01-02T00:00:00.000Z'),
    );
  });
});

describe('SSP Activity - request dates (browser 13 hours ahead of the CMS)', () => {
  beforeAll(() => {
    stubIntersectionObserver();
    process.env.TZ = 'Pacific/Auckland'; // UTC+13 in January; the CMS (superAdmin) is UTC
  });
  afterAll(() => {
    setTimezone(originalTz);
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchConnectors).mockReset().mockResolvedValue([SSP_CONNECTOR]);
    vi.mocked(fetchSspPartners).mockReset();
    vi.mocked(fetchSspActivity).mockReset();
    serveSsp(SSP_RESPONSE);
    setupOptionServiceMocks();
  });

  // Guard: if the TZ switch ever stops working, the pin below passes vacuously.
  test('the environment really is running 13 hours ahead of the CMS', () => {
    expect(new Date('2027-01-01T00:00:00Z').getTimezoneOffset()).toBe(-13 * 60);
  });

  // Known issue 10: xibosignage/xibo#4102 (request dates use the browser's zone)
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4102 is fixed', () => {
    knownFailure(
      'a From Date of midnight in the CMS is sent as midnight',
      async () => {
        const user = userEvent.setup({ delay: null });
        renderPage();
        await screen.findByRole('button', { name: /^apply$/i });
        await pickOption(user, /^display$/i, 'Lobby Screen');
        await setDates(user, '2027-01-01T00:00:00.000Z', '2027-01-01T23:59:00.000Z');
        await user.click(applyButton());
        await waitFor(() =>
          expect(fetchSspActivity).toHaveBeenCalledWith(expect.objectContaining({ displayId: 1 })),
        );
        return activityRequest();
      },
      (params) => expect(params.activityFromDt).toBe('2027-01-01 00:00:00'),
    );
  });
});
