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
// Test type: Form — the Summary/Distribution schedule form
// =============================================================================

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { DISTRIBUTION_CONFIG } from '../../Distribution/DistributionConfig';
import { SUMMARY_CONFIG } from '../../Summary/SummaryConfig';
import StatsReportScheduleModal from '../../shared/StatsReportScheduleModal';
import type { StatsFilter, StatsReportConfig } from '../../shared/types';
import { stubIntersectionObserver } from '../helpers/domStubs';
import { pickOption } from '../helpers/renderReportPage';
import { setupOptionServiceMocks } from '../mocks/optionServices';

import { createReportSchedule } from '@/services/reportScheduleApi';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/displaysApi', () => ({ fetchDisplays: vi.fn() }));
vi.mock('@/services/displayGroupApi', () => ({ fetchDisplayGroups: vi.fn() }));
vi.mock('@/services/layoutsApi', () => ({ fetchLayouts: vi.fn() }));
vi.mock('@/services/mediaApi', () => ({ fetchMedia: vi.fn() }));
vi.mock('@/services/campaignApi', () => ({ fetchCampaigns: vi.fn() }));
vi.mock('@/services/userGroupApi', () => ({ fetchUserGroups: vi.fn() }));
vi.mock('@/services/userApi', () => ({
  fetchUsers: vi.fn(),
}));
vi.mock('@/services/reportScheduleApi', () => ({
  createReportSchedule: vi.fn().mockResolvedValue({}),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/components/ui/modals/Modal');
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

const LAYOUT_FILTER: StatsFilter = {
  ...SUMMARY_CONFIG.initialFilter,
  type: 'layout',
  layoutId: 301,
};

const renderModal = (config: StatsReportConfig, currentFilter: StatsFilter) =>
  render(
    <StatsReportScheduleModal
      config={config}
      isOpen
      currentFilter={currentFilter}
      onClose={vi.fn()}
      onSuccess={vi.fn()}
    />,
  );

const saveButton = () => screen.getByRole('button', { name: /^save$/i });

// =============================================================================
// Tests
// =============================================================================

describe('Summary/Distribution schedule form', () => {
  beforeAll(() => {
    stubIntersectionObserver();
  });

  beforeEach(() => {
    vi.clearAllMocks();
    setupOptionServiceMocks();
  });

  test('the title names the chosen layout', async () => {
    renderModal(SUMMARY_CONFIG, LAYOUT_FILTER);

    expect(
      await screen.findByRole('dialog', { name: 'Add Report Schedule for layout - Lobby Layout' }),
    ).toBeInTheDocument();
  });

  test('the title names the event tag', async () => {
    renderModal(SUMMARY_CONFIG, {
      ...SUMMARY_CONFIG.initialFilter,
      type: 'event',
      eventTag: 'promo',
    });

    await screen.findByRole('dialog', { name: 'Add Report Schedule for event - promo' });
  });

  test('without an item it warns and keeps Save disabled', async () => {
    const user = userEvent.setup({ delay: null });
    renderModal(SUMMARY_CONFIG, SUMMARY_CONFIG.initialFilter);

    await screen.findByRole('dialog', { name: 'Schedule Summary Report' });
    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'Weekly');

    expect(screen.getByText(/select a type and an item/i)).toBeInTheDocument();
    expect(saveButton()).toBeDisabled();
  });

  // A single display is more specific than a group, so the group is dropped.
  test('choosing a Display clears and hides Display Group', async () => {
    const user = userEvent.setup({ delay: null });
    renderModal(SUMMARY_CONFIG, { ...LAYOUT_FILTER, displayGroupId: [201] });
    await screen.findByRole('combobox', { name: /^display group$/i });

    await pickOption(user, /^display$/i, 'Lobby Screen');
    expect(screen.queryByRole('combobox', { name: /^display group$/i })).not.toBeInTheDocument();
    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'Weekly');
    await user.click(saveButton());

    await waitFor(() => expect(createReportSchedule).toHaveBeenCalled());
    expect(createReportSchedule).toHaveBeenCalledWith(
      expect.objectContaining({ displayId: 1, displayGroupId: [] }),
    );
  });

  test('saving sends the report, its item and the page filters', async () => {
    const user = userEvent.setup({ delay: null });
    renderModal(DISTRIBUTION_CONFIG, {
      ...DISTRIBUTION_CONFIG.initialFilter,
      type: 'layout',
      layoutId: 301,
    });
    await screen.findByRole('dialog', { name: /lobby layout/i });

    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'Hourly');
    await user.click(saveButton());

    await waitFor(() => expect(createReportSchedule).toHaveBeenCalled());
    expect(createReportSchedule).toHaveBeenCalledWith(
      expect.objectContaining({
        name: 'Hourly',
        reportName: 'distributionReport',
        groupByFilter: 'byhour',
        hiddenFields: { type: 'layout', selectedId: 301, eventTag: '' },
      }),
    );
    // The server needs the item id, however the payload is built.
    expect(vi.mocked(createReportSchedule).mock.calls[0]![0].hiddenFields!.selectedId).toBe(301);
  });

  test('Distribution offers its own Group By; Summary none', async () => {
    const { unmount } = renderModal(DISTRIBUTION_CONFIG, {
      ...DISTRIBUTION_CONFIG.initialFilter,
      layoutId: 301,
    });
    await screen.findByRole('combobox', { name: /^group by$/i });
    unmount();

    renderModal(SUMMARY_CONFIG, LAYOUT_FILTER);
    await screen.findByRole('dialog', { name: /lobby layout/i });
    expect(screen.queryByRole('combobox', { name: /^group by$/i })).not.toBeInTheDocument();
  });
});
