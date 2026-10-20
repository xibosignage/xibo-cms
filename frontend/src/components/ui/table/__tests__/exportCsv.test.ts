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

import type { ColumnDef } from '@tanstack/react-table';
import { describe, expect, test } from 'vitest';

import { isExportableColumn, toCsvField } from '../exportCsv';

import { exportColumnValue, exportedColumns } from '@/testUtils/columnContract';

/**
 * The CSV export's per-cell rules, extracted from DataTable so the export contract
 * (contracts/export.test.tsx) runs exactly the code the CSV button runs.
 */

type Row = { id: number; name: string | null; tags: string[] | null; count: number };

const columns: ColumnDef<Row>[] = [
  { id: 'tableSelection', header: '' },
  { accessorKey: 'name', header: 'Name' },
  { accessorKey: 'tags', header: 'Tags' },
  { accessorKey: 'count', header: 'Count' },
  {
    id: 'label',
    header: 'Label',
    accessorFn: (row) => row.count,
    meta: { getExportValue: (row) => `${row.count} items` },
  },
  {
    id: 'hidden',
    header: 'Hidden',
    accessorFn: (row) => row.id,
    meta: { excludeFromExport: true },
  },
  { id: 'tableActions', header: '' },
];

const row: Row = { id: 1, name: 'Lobby', tags: ['a', 'b'], count: 3 };

describe('which columns the CSV export includes', () => {
  test('the selection, row-action and explicitly excluded columns are left out', () => {
    expect(exportedColumns(columns).map((c) => c.id)).toEqual(['name', 'tags', 'count', 'label']);
  });

  test('a column is exportable unless the table injects it or it opts out', () => {
    expect(isExportableColumn('name')).toBe(true);
    expect(isExportableColumn('tableSelection')).toBe(false);
    expect(isExportableColumn('tableActions')).toBe(false);
    expect(isExportableColumn('name', { excludeFromExport: true })).toBe(false);
  });
});

describe('what a cell exports', () => {
  test('a column that declares getExportValue exports that, not its raw value', () => {
    expect(exportColumnValue(columns, 'label', row)).toBe('3 items');
  });

  test('a plain value exports as text, null as empty, and an array or object as JSON', () => {
    expect(exportColumnValue(columns, 'count', row)).toBe('3');
    expect(exportColumnValue(columns, 'name', { ...row, name: null })).toBe('');
    expect(exportColumnValue(columns, 'tags', row)).toBe('["a","b"]');
  });

  test('every field is quoted, with quotes inside it doubled', () => {
    expect(toCsvField('plain')).toBe('"plain"');
    expect(toCsvField('say "hi"')).toBe('"say ""hi"""');
  });
});
