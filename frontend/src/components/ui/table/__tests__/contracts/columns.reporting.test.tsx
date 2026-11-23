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

import { describe, expect, test } from 'vitest';

import { EPOCH, propsWith, statesOf, textOf } from './helpers';

import { getNotificationColumns } from '@/pages/Notification/NotificationConfig';
import { getReportScheduleColumns } from '@/pages/Reporting/ReportSchedules/ReportSchedulesConfig';
import { getSavedReportColumns } from '@/pages/Reporting/SavedReports/SavedReportsConfig';

describe('Report Schedules grid', () => {
  const columns = getReportScheduleColumns(propsWith());
  const schedule = (fields: object) => ({
    id: 1,
    name: 'R',
    reportName: 'proofofplayReport',
    ...fields,
  });

  test('the Active column shows an active schedule differently from a paused one', () => {
    const states = statesOf(columns, 'isActive', {
      active: schedule({ isActive: 1 }),
      paused: schedule({ isActive: 0 }),
    });

    expect(states.active).not.toBe(states.paused);
  });

  // lastRunDt 0 means "never run".
  test('the Last Run column is empty for a schedule that never ran, and a CMS date otherwise', () => {
    expect(textOf(columns, 'lastRunDt', schedule({ lastRunDt: 0 }))).toBe('');
    expect(textOf(columns, 'lastRunDt', schedule({ lastRunDt: EPOCH }))).toMatch(/^formatted:/);
  });

  // No message and an empty message both mean "didn't fail", so both must show nothing.
  test('the Failed Message column is empty when a schedule has no failure message', () => {
    expect(textOf(columns, 'message', schedule({ message: null }))).toBe('');
    expect(textOf(columns, 'message', schedule({ message: '' }))).toBe('');
    expect(textOf(columns, 'message', schedule({ message: 'Timed out' }))).toBe('Timed out');
  });
});

describe('Saved Reports grid', () => {
  test('the Generated On column shows the date through the CMS formatter', () => {
    const columns = getSavedReportColumns(propsWith());

    expect(
      textOf(columns, 'generatedOn', { savedReportId: 1, reportName: 'r', generatedOn: EPOCH }),
    ).toMatch(/^formatted:/);
  });

  // 0 means the report was never generated, so the cell must be empty, not 1970.
  test('the Generated On column is empty when there is no generation time', () => {
    const columns = getSavedReportColumns(propsWith());

    expect(
      textOf(columns, 'generatedOn', { savedReportId: 1, reportName: 'r', generatedOn: 0 }),
    ).toBe('');
  });
});

describe('Notifications grid', () => {
  const columns = getNotificationColumns(propsWith());
  const notification = (fields: object) => ({ notificationId: 1, subject: 'S', ...fields });

  test('the Interrupt column shows an interrupting notification differently from a normal one', () => {
    const states = statesOf(columns, 'isInterrupt', {
      interrupt: notification({ isInterrupt: 1 }),
      normal: notification({ isInterrupt: 0 }),
    });

    expect(states.interrupt).not.toBe(states.normal);
  });

  test('the Release Date column shows the date through the CMS formatter', () => {
    expect(textOf(columns, 'releaseDt', notification({ releaseDt: EPOCH }))).toMatch(/^formatted:/);
  });

  test('the Type column shows each notification type distinctly', () => {
    const states = statesOf(columns, 'type', {
      custom: notification({ type: 'custom' }),
      dataset: notification({ type: 'dataset' }),
      schedule: notification({ type: 'schedule' }),
      report: notification({ type: 'report' }),
    });

    expect(new Set(Object.values(states)).size).toBe(4);
  });
});
