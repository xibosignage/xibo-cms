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
// Date pickers are stubbed as plain inputs so tests can type ISO strings.
// =============================================================================

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeAll, afterAll, beforeEach, describe, test, expect } from 'vitest';

import EditReportScheduleModal from '../../components/EditReportScheduleModal';
import { apiError, buildReportSchedule, mockReportSchedule } from '../fixtures/reportSchedule';

import { notify } from '@/components/ui/Notification';
import { updateReportSchedule } from '@/services/reportScheduleApi';
import { deferred } from '@/testUtils/deferred';
import { knownFailure } from '@/testUtils/knownFailure';
import { setTimezone } from '@/testUtils/timezone';
import type { ReportSchedule } from '@/types/reportSchedule';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/reportScheduleApi', () => ({
  updateReportSchedule: vi.fn().mockResolvedValue({}),
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
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));

// =============================================================================
// Fixtures and helpers
// =============================================================================

const JAN_1_2027 = 1798761600; // 2027-01-01 00:00:00 UTC
const FEB_1_2027 = 1801440000; // 2027-02-01 00:00:00 UTC
const JAN_1_2025 = 1735689600; // 2025-01-01 00:00:00 UTC, in the past

const DATED_SCHEDULE = buildReportSchedule({ fromDt: JAN_1_2027, toDt: FEB_1_2027 });

const onClose = vi.fn();
const onSuccess = vi.fn();

const renderModal = (schedule: ReportSchedule | null = mockReportSchedule) =>
  render(
    <EditReportScheduleModal isOpen schedule={schedule} onClose={onClose} onSuccess={onSuccess} />,
  );

const nameInput = () => screen.getByRole('textbox', { name: /schedule name/i });
const startInput = () => screen.getByRole('textbox', { name: 'Start Time' });
const endInput = () => screen.getByRole('textbox', { name: 'End Time' });
const saveButton = () => screen.getByRole('button', { name: /^save$/i });

const lastPayload = () => {
  const calls = vi.mocked(updateReportSchedule).mock.calls;
  return calls[calls.length - 1]!;
};

const originalTz = process.env.TZ;

// =============================================================================
// Tests
// =============================================================================

describe('Edit Report Schedule modal', () => {
  beforeAll(() => {
    process.env.TZ = 'UTC';
  });
  afterAll(() => {
    setTimezone(originalTz);
  });
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(updateReportSchedule).mockResolvedValue({} as unknown as ReportSchedule);
  });

  // Default state

  test('opens with the schedule name and dates filled in', () => {
    renderModal(DATED_SCHEDULE);

    expect(screen.getByRole('dialog', { name: 'Edit Report Schedule' })).toBeInTheDocument();
    expect(nameInput()).toHaveValue('Weekly PoP');
    expect(startInput()).toHaveValue('2027-01-01 00:00:00');
    expect(endInput()).toHaveValue('2027-02-01 00:00:00');
  });

  // 0 means "not set" on the server, so the fields must be empty rather than 1970.
  test('a schedule with no dates opens with empty date fields', () => {
    renderModal(mockReportSchedule);

    expect(startInput()).toHaveValue('');
    expect(endInput()).toHaveValue('');
  });

  test('reopening for another schedule shows its values and clears the error', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(updateReportSchedule).mockRejectedValueOnce(apiError('Name already in use'));
    const { rerender } = renderModal(DATED_SCHEDULE);
    await user.click(saveButton());
    await screen.findByRole('alert');

    const other = buildReportSchedule({ reportScheduleId: 2, name: 'Daily Alerts' });
    rerender(
      <EditReportScheduleModal
        isOpen={false}
        schedule={other}
        onClose={onClose}
        onSuccess={onSuccess}
      />,
    );
    rerender(
      <EditReportScheduleModal isOpen schedule={other} onClose={onClose} onSuccess={onSuccess} />,
    );

    // The form re-seeds in an effect when it reopens, so wait for it.
    expect(await screen.findByDisplayValue('Daily Alerts')).toBe(nameInput());
    expect(startInput()).toHaveValue('');
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  // Validation

  test('Save is disabled while the name is blank or only spaces', async () => {
    const user = userEvent.setup({ delay: null });
    renderModal();

    await user.clear(nameInput());
    expect(saveButton()).toBeDisabled();

    await user.type(nameInput(), '   ');
    expect(saveButton()).toBeDisabled();

    await user.type(nameInput(), 'x');
    expect(saveButton()).toBeEnabled();
  });

  // Save

  test('saving sends the edited name and dates for that schedule', async () => {
    const user = userEvent.setup({ delay: null });
    renderModal(mockReportSchedule);

    await user.clear(nameInput());
    await user.type(nameInput(), 'Monthly PoP');
    await user.type(startInput(), '2027-03-01T09:00:00.000Z');
    await user.click(saveButton());

    await waitFor(() =>
      expect(updateReportSchedule).toHaveBeenCalledWith(
        mockReportSchedule.reportScheduleId,
        expect.objectContaining({ name: 'Monthly PoP', fromDt: '2027-03-01T09:00:00.000Z' }),
      ),
    );
    // Only the three editable fields are sent.
    expect(Object.keys(lastPayload()[1]).sort()).toEqual(['fromDt', 'name', 'toDt']);
    // End Time stays empty rather than inheriting Start Time.
    expect(lastPayload()[1].toDt).toBe('');
  });

  // The service drops empty dates and the server treats a missing date as 0 (cleared).
  test('clearing a date sends it empty so the server removes it', async () => {
    const user = userEvent.setup({ delay: null });
    renderModal(DATED_SCHEDULE);

    await user.clear(startInput());
    await user.click(saveButton());

    await waitFor(() =>
      expect(updateReportSchedule).toHaveBeenCalledWith(
        DATED_SCHEDULE.reportScheduleId,
        expect.objectContaining({ fromDt: '', toDt: '2027-02-01 00:00:00' }),
      ),
    );
  });

  test('while saving, the button reads Saving… and both buttons are disabled', async () => {
    const user = userEvent.setup({ delay: null });
    const pending = deferred<ReportSchedule>();
    vi.mocked(updateReportSchedule).mockReturnValue(pending.promise);
    renderModal();

    await user.click(saveButton());

    expect(await screen.findByRole('button', { name: 'Saving…' })).toBeDisabled();
    expect(screen.getByRole('button', { name: /^cancel$/i })).toBeDisabled();

    pending.resolve({} as unknown as ReportSchedule);
    await waitFor(() => expect(onClose).toHaveBeenCalled());
  });

  // The page refreshes in onSuccess; closing first would drop that refresh.
  test('a successful save tells the page to refresh, then closes', async () => {
    const user = userEvent.setup({ delay: null });
    renderModal();

    await user.click(saveButton());

    await waitFor(() => expect(onClose).toHaveBeenCalled());
    expect(onSuccess).toHaveBeenCalledTimes(1);
    expect(vi.mocked(onSuccess).mock.invocationCallOrder[0]!).toBeLessThan(
      vi.mocked(onClose).mock.invocationCallOrder[0]!,
    );
  });

  test('Cancel closes without saving', async () => {
    const user = userEvent.setup({ delay: null });
    renderModal();

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(onClose).toHaveBeenCalled();
    expect(updateReportSchedule).not.toHaveBeenCalled();
  });

  // Known issue: xibosignage/xibo#4082
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4082 is fixed', () => {
    // Every other schedule action confirms success; Edit says nothing.
    knownFailure(
      'saving shows a success message like the other schedule actions',
      async () => {
        const user = userEvent.setup({ delay: null });
        renderModal();
        await user.click(saveButton());
        await waitFor(() => expect(onClose).toHaveBeenCalled());
      },
      () => expect(notify.success).toHaveBeenCalled(),
    );
  });

  // Error handling

  test('a server rejection shows its message and keeps the modal open', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(updateReportSchedule).mockRejectedValue(apiError('Name already in use'));
    renderModal();

    await user.click(saveButton());

    expect(await screen.findByRole('alert')).toHaveTextContent('Name already in use');
    expect(updateReportSchedule).toHaveBeenCalledTimes(1);
    expect(onClose).not.toHaveBeenCalled();
  });

  test('a rejection with no message shows the generic error', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(updateReportSchedule).mockRejectedValue({ unexpected: true });
    renderModal();

    await user.click(saveButton());

    expect(await screen.findByRole('alert')).toHaveTextContent('An unexpected error occurred.');
    expect(updateReportSchedule).toHaveBeenCalledTimes(1);
  });

  // Known issue: xibosignage/xibo#4081
  test('renaming a schedule whose start has passed re-sends the old start and shows the refusal', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(updateReportSchedule).mockRejectedValue(
      apiError('Start time cannot be earlier than today'),
    );
    renderModal(buildReportSchedule({ fromDt: JAN_1_2025 }));

    await user.type(nameInput(), ' v2');
    await user.click(saveButton());

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Start time cannot be earlier than today',
    );
    expect(lastPayload()[1]).toEqual(
      expect.objectContaining({ name: 'Weekly PoP v2', fromDt: '2025-01-01 00:00:00' }),
    );
  });
});

// =============================================================================
// Timezone: grid uses the CMS zone, modal the browser zone (xibosignage/xibo#4080)
// =============================================================================

describe('Edit Report Schedule modal - browser and CMS in different timezones', () => {
  beforeAll(() => {
    process.env.TZ = 'Pacific/Auckland'; // UTC+13 in January
  });
  afterAll(() => {
    setTimezone(originalTz);
  });
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // Known issue: xibosignage/xibo#4080
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4080 is fixed', () => {
    // Guard: if the TZ switch stops working, the check below passes vacuously.
    test('the environment really is running 13 hours ahead of the CMS', () => {
      expect(new Date('2027-01-01T00:00:00Z').getTimezoneOffset()).toBe(-13 * 60);
    });

    knownFailure(
      'the start time in the modal matches the time the grid shows',
      () => {
        renderModal(DATED_SCHEDULE);
        return startInput();
      },
      (input) => expect(input).toHaveValue('2027-01-01 00:00:00'),
    );
  });
});
