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

import type { ColumnDef } from '@tanstack/react-table';
import { render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, test, expect, vi } from 'vitest';

import { DataTable } from '../DataTable';

type Row = { id: number; name: string };

const renderTable = (columns: ColumnDef<Row>[]) =>
  render(
    <MemoryRouter>
      <DataTable<Row, unknown>
        columns={columns}
        data={[{ id: 1, name: 'Lobby' }]}
        pageCount={1}
        pagination={{ pageIndex: 0, pageSize: 10 }}
        onPaginationChange={vi.fn()}
        sorting={[]}
        onSortingChange={vi.fn()}
        globalFilter=""
        onGlobalFilterChange={vi.fn()}
        rowSelection={{}}
        onRowSelectionChange={vi.fn()}
      />
    </MemoryRouter>,
  );

describe('DataTable sort control', () => {
  test('is named after the header text', () => {
    renderTable([{ accessorKey: 'name', header: 'Name' }]);

    const header = screen.getByRole('columnheader', { name: 'Name' });
    expect(within(header).getByRole('button', { name: 'Sort by Name' })).toBeInTheDocument();
  });

  // A header drawn by a function used to name the button after the column id.
  test('is named after what a custom header shows, not the column id', () => {
    renderTable([{ accessorKey: 'name', header: () => <span>Display Name</span> }]);

    const header = screen.getByRole('columnheader', { name: 'Display Name' });
    expect(
      within(header).getByRole('button', { name: 'Sort by Display Name' }),
    ).toBeInTheDocument();
  });
});
