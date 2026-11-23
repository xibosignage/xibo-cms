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

import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { render, screen } from '@testing-library/react';
import type { TFunction } from 'i18next';
import { describe, test, expect, vi } from 'vitest';

import { getDisplayColumns, type DisplayActionsProps } from '../../DisplaysConfig';
import { buildDisplay } from '../fixtures/display';

import type { Display } from '@/types/display';

// The Commercial Licence column on Pro shows why the licensing server refused a display.

const t = ((key: string) => key) as unknown as TFunction;

function licenceColumn(): ColumnDef<Display> & {
  cell: (info: CellContext<Display, unknown>) => React.ReactNode;
  meta: { getExportValue: (row: Display) => string };
} {
  const columns = getDisplayColumns({
    t,
    formatDateTime: () => '',
    onDelete: vi.fn(),
    openEditModal: vi.fn(),
  } as unknown as DisplayActionsProps);

  const column = columns.find(
    (c) => (c as { accessorKey?: string }).accessorKey === 'commercialLicence',
  );
  if (!column) {
    throw new Error('No Commercial Licence column');
  }

  return column as ReturnType<typeof licenceColumn>;
}

function renderCell(display: Display) {
  const info = {
    row: { original: display },
    getValue: () => display.commercialLicence,
  } as unknown as CellContext<Display, unknown>;

  render(<>{licenceColumn().cell(info)}</>);
}

describe('Displays page - Commercial Licence column', () => {
  test('a refused display shows Not licenced and the reason', () => {
    renderCell(
      buildDisplay({
        commercialLicence: 1,
        licenceRefusal: 'cap_reached',
        licenceRefusalDescription: 'Every display licence is in use',
      }),
    );

    expect(screen.getByText('Not licenced')).toBeInTheDocument();
    expect(screen.getByText('Every display licence is in use')).toBeInTheDocument();
  });

  test('a display without a refusal shows what its player reported', () => {
    renderCell(buildDisplay({ commercialLicence: 1 }));

    expect(screen.getByText('Licensed fully')).toBeInTheDocument();
    expect(screen.queryByText('Every display licence is in use')).not.toBeInTheDocument();
  });

  test('the export includes the reason', () => {
    const { getExportValue } = licenceColumn().meta;

    expect(
      getExportValue(
        buildDisplay({ commercialLicence: 0, licenceRefusalDescription: 'Displays are disabled' }),
      ),
    ).toBe('Not licenced: Displays are disabled');
    expect(getExportValue(buildDisplay({ commercialLicence: 2 }))).toBe('Trial');
  });
});
