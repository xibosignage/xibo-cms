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
// Test type: Form — the schedule form every report's Schedule button opens
// =============================================================================

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AxiosError, AxiosHeaders } from 'axios';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import ReportScheduleModalShell from '../../shared/ReportScheduleModalShell';
import type { BaseScheduleDraft } from '../../shared/ReportScheduleModalShell';
import { stubIntersectionObserver } from '../helpers/domStubs';

import { notify } from '@/components/ui/Notification';
import { createReportSchedule } from '@/services/reportScheduleApi';
import type { CreateReportSchedulePayload } from '@/services/reportScheduleApi';
import { deferred } from '@/testUtils/deferred';
import type { ReportSchedule } from '@/types/reportSchedule';

vi.mock('@/services/reportScheduleApi', () => ({ createReportSchedule: vi.fn() }));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
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

const onClose = vi.fn();
const onSuccess = vi.fn();
const buildPayload = vi.fn(
  (draft: BaseScheduleDraft): CreateReportSchedulePayload => ({
    name: draft.name,
    reportName: 'testReport',
    filter: draft.filter,
    groupByFilter: '',
    displayGroupIds: [],
    fromDt: draft.fromDt || undefined,
    toDt: draft.toDt || undefined,
    sendEmail: draft.sendEmail,
    nonusers: draft.nonusers,
  }),
);

const renderShell = (props: Partial<Parameters<typeof ReportScheduleModalShell>[0]> = {}) =>
  render(
    <ReportScheduleModalShell
      isOpen
      title="Schedule Test Report"
      onClose={onClose}
      onSuccess={onSuccess}
      buildPayload={buildPayload}
      {...props}
    />,
  );

const nameInput = () => screen.getByRole('textbox', { name: /^name$/i });
const saveButton = () => screen.getByRole('button', { name: /^save$/i });
const lastDraft = () => buildPayload.mock.calls[buildPayload.mock.calls.length - 1]![0];

const apiError = (message: string) =>
  new AxiosError('Request failed', '422', undefined, undefined, {
    status: 422,
    statusText: '',
    headers: {},
    config: { headers: new AxiosHeaders() },
    data: { message },
  });

describe('Report schedule form', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    stubIntersectionObserver();
    // clearAllMocks keeps earlier behaviour; reset the one this file overrides.
    vi.mocked(createReportSchedule)
      .mockReset()
      .mockResolvedValue({} as unknown as ReportSchedule);
  });

  test('Save needs a name, and spaces alone do not count', async () => {
    const user = userEvent.setup({ delay: null });
    renderShell();

    expect(saveButton()).toBeDisabled();
    await user.type(nameInput(), '   ');
    expect(saveButton()).toBeDisabled();
    await user.type(nameInput(), 'Weekly');
    expect(saveButton()).toBeEnabled();
  });

  test('a report that is not ready keeps Save disabled and shows its warning', async () => {
    const user = userEvent.setup({ delay: null });
    renderShell({ canSave: false, warning: <p>Pick an item first</p> });

    await user.type(nameInput(), 'Weekly');

    expect(screen.getByText('Pick an item first')).toBeInTheDocument();
    expect(saveButton()).toBeDisabled();
  });

  test('the first frequency is chosen by default', async () => {
    const user = userEvent.setup({ delay: null });
    renderShell();

    await user.type(nameInput(), 'Weekly');
    await user.click(saveButton());

    await waitFor(() => expect(createReportSchedule).toHaveBeenCalled());
    expect(lastDraft().filter).toBe('daily');
  });

  // Bandwidth passes its own list (Daily, Monthly, Yearly).
  test("a report's own frequency list starts on its first entry, and a choice is sent", async () => {
    const user = userEvent.setup({ delay: null });
    renderShell({
      frequencyOptions: [
        { value: 'monthly', label: 'Monthly' },
        { value: 'yearly', label: 'Yearly' },
      ],
    });
    await user.type(nameInput(), 'Monthly run');

    // The Frequency label isn't tied to its dropdown (Known issue 19).
    expect(screen.getByRole('combobox')).toHaveTextContent('Monthly');
    await user.click(screen.getByRole('combobox'));
    await user.click(await screen.findByRole('option', { name: 'Yearly' }));
    await user.click(saveButton());

    await waitFor(() => expect(createReportSchedule).toHaveBeenCalled());
    expect(lastDraft().filter).toBe('yearly');
  });

  test('the email addresses field appears only when email is ticked, and is sent', async () => {
    const user = userEvent.setup({ delay: null });
    renderShell();
    await user.type(nameInput(), 'Weekly');
    expect(screen.queryByRole('textbox', { name: /email addresses/i })).not.toBeInTheDocument();

    await user.click(screen.getByRole('checkbox', { name: /should an email be sent/i }));
    await user.type(screen.getByRole('textbox', { name: /email addresses/i }), 'a@b.c');
    await user.click(saveButton());

    await waitFor(() => expect(createReportSchedule).toHaveBeenCalled());
    expect(createReportSchedule).toHaveBeenCalledWith(
      expect.objectContaining({ sendEmail: true, nonusers: 'a@b.c', name: 'Weekly' }),
    );
  });

  test('the start and end times typed are handed to the report payload', async () => {
    const user = userEvent.setup({ delay: null });
    renderShell();
    await user.type(nameInput(), 'Weekly');

    await user.type(
      screen.getByRole('textbox', { name: 'Start Time' }),
      '2027-03-01T09:00:00.000Z',
    );
    await user.click(saveButton());

    await waitFor(() => expect(createReportSchedule).toHaveBeenCalled());
    expect(lastDraft()).toEqual(
      expect.objectContaining({ fromDt: '2027-03-01T09:00:00.000Z', toDt: '' }),
    );
  });

  test('saving shows Saving…, then confirms, tells the page and closes', async () => {
    const user = userEvent.setup({ delay: null });
    const pending = deferred<ReportSchedule>();
    vi.mocked(createReportSchedule).mockReturnValueOnce(pending.promise);
    renderShell();
    await user.type(nameInput(), 'Weekly');

    await user.click(saveButton());

    expect(await screen.findByRole('button', { name: 'Saving…' })).toBeDisabled();
    pending.resolve({} as unknown as ReportSchedule);
    await waitFor(() => expect(onClose).toHaveBeenCalled());
    expect(notify.success).toHaveBeenCalledWith('Report schedule created.');
    expect(vi.mocked(onSuccess).mock.invocationCallOrder[0]!).toBeLessThan(
      vi.mocked(onClose).mock.invocationCallOrder[0]!,
    );
  });

  test.each([
    ['the server message', apiError('Name already in use'), 'Name already in use'],
    ["the error's own message", new Error('Network Error'), 'Network Error'],
    ['a generic message', { unexpected: true }, 'An unexpected error occurred.'],
  ])('a failed save shows %s and keeps the form open', async (_label, rejection, shown) => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(createReportSchedule).mockRejectedValueOnce(rejection);
    renderShell();
    await user.type(nameInput(), 'Weekly');

    await user.click(saveButton());

    expect(await screen.findByRole('alert')).toHaveTextContent(shown);
    expect(createReportSchedule).toHaveBeenCalledTimes(1);
    expect(onClose).not.toHaveBeenCalled();
  });

  test('reopening the form starts blank', async () => {
    const user = userEvent.setup({ delay: null });
    const { rerender } = renderShell();
    await user.type(nameInput(), 'Weekly');

    rerender(
      <ReportScheduleModalShell
        isOpen={false}
        title="Schedule Test Report"
        onClose={onClose}
        onSuccess={onSuccess}
        buildPayload={buildPayload}
      />,
    );
    rerender(
      <ReportScheduleModalShell
        isOpen
        title="Schedule Test Report"
        onClose={onClose}
        onSuccess={onSuccess}
        buildPayload={buildPayload}
      />,
    );

    expect(nameInput()).toHaveValue('');
  });
});
