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
// Component: a saved Time Connected report
// =============================================================================

import { render, screen } from '@testing-library/react';
import { describe, test, expect } from 'vitest';

import TimeConnectedSavedReport from '../../SavedReportViewer/TimeConnectedSavedReport';
import { EMPTY_TIME_CONNECTED_TABLE, TIME_CONNECTED_TABLE } from '../fixtures/savedReportData';

import type { TimeConnectedTable } from '@/services/timeConnectedApi';

describe('Saved Time Connected report', () => {
  // Back Office is 50% then 0%, so 25% overall.
  test('each display becomes a row with its uptime', () => {
    render(
      <TimeConnectedSavedReport table={TIME_CONNECTED_TABLE as unknown as TimeConnectedTable} />,
    );

    expect(screen.getByText('Lobby Screen')).toBeInTheDocument();
    expect(screen.getByText('Back Office')).toBeInTheDocument();
    expect(screen.getByText('100.0%')).toBeInTheDocument();
    expect(screen.getByText('25.0%')).toBeInTheDocument();
  });

  test('saved data with no displays says so', () => {
    render(
      <TimeConnectedSavedReport
        table={EMPTY_TIME_CONNECTED_TABLE as unknown as TimeConnectedTable}
      />,
    );

    expect(screen.getByText('This report contains no data.')).toBeInTheDocument();
  });
});
