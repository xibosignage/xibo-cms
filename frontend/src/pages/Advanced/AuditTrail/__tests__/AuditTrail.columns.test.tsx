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

import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { TFunction } from 'i18next';
import { describe, expect, it } from 'vitest';

import { INITIAL_FILTER_STATE, getAuditTrailColumns, getBaseFilterKeys } from '../AuditTrailConfig';

import type { AuditLog } from '@/types/auditTrail';

// Use the identity TFunction for simple label assertions
const t = ((key: string) => key) as TFunction;

// --- getAuditTrailColumns ---

describe('getAuditTrailColumns', () => {
  it('returns exactly 8 column definitions', () => {
    const columns = getAuditTrailColumns(t);
    expect(columns).toHaveLength(8);
  });

  it('has the correct accessorKey / id values in order', () => {
    const columns = getAuditTrailColumns(t);
    const keys = columns.map(
      (c) => (c as { accessorKey?: string; id?: string }).accessorKey ?? (c as { id?: string }).id,
    );
    expect(keys).toEqual([
      'logId',
      'logDate',
      'userName',
      'entity',
      'entityId',
      'ipAddress',
      'message',
      'objectAfter',
    ]);
  });

  it('has the correct header label strings in order', () => {
    const columns = getAuditTrailColumns(t);
    const headers = columns.map((c) => c.header);
    expect(headers).toEqual([
      'ID',
      'Date',
      'User',
      'Entity',
      'Entity ID',
      'IP Address',
      'Message',
      'Object',
    ]);
  });

  it('renders the logId cell as bold text', () => {
    const columns = getAuditTrailColumns(t);
    const logIdCol = columns.find((c) => (c as { accessorKey?: string }).accessorKey === 'logId')!;
    type CellFn = (ctx: { getValue: () => unknown }) => React.ReactNode;
    const cell = logIdCol.cell as CellFn;

    render(<>{cell({ getValue: () => 7 })}</>);

    expect(screen.getByText('7')).toBeInTheDocument();
  });

  it('logDate cell renders a formatted date string for a valid Unix timestamp', () => {
    const columns = getAuditTrailColumns(t);
    const logDateCol = columns.find(
      (c) => (c as { accessorKey?: string }).accessorKey === 'logDate',
    )!;
    type CellFn = (ctx: { getValue: () => unknown }) => React.ReactNode;
    const cell = logDateCol.cell as CellFn;

    // 1704067200 = 2024-01-01 00:00:00 UTC
    render(<>{cell({ getValue: () => 1704067200 })}</>);

    // The formatted date should be non-empty; exact format is locale-dependent
    const dateEl = screen.getByText(/2024/);
    expect(dateEl).toBeInTheDocument();
  });

  it('logDate cell renders empty string for a falsy (0) timestamp', () => {
    const columns = getAuditTrailColumns(t);
    const logDateCol = columns.find(
      (c) => (c as { accessorKey?: string }).accessorKey === 'logDate',
    )!;
    type CellFn = (ctx: { getValue: () => unknown }) => React.ReactNode;
    const cell = logDateCol.cell as CellFn;

    const { container } = render(<>{cell({ getValue: () => 0 })}</>);

    expect(container.textContent).toBe('');
  });

  it('entityId cell renders empty string for null', () => {
    const columns = getAuditTrailColumns(t);
    const entityIdCol = columns.find(
      (c) => (c as { accessorKey?: string }).accessorKey === 'entityId',
    )!;
    type CellFn = (ctx: { getValue: () => unknown }) => React.ReactNode;
    const cell = entityIdCol.cell as CellFn;

    const { container } = render(<>{cell({ getValue: () => null })}</>);

    expect(container.textContent).toBe('');
  });

  it('entityId cell renders empty string for 0', () => {
    const columns = getAuditTrailColumns(t);
    const entityIdCol = columns.find(
      (c) => (c as { accessorKey?: string }).accessorKey === 'entityId',
    )!;
    type CellFn = (ctx: { getValue: () => unknown }) => React.ReactNode;
    const cell = entityIdCol.cell as CellFn;

    const { container } = render(<>{cell({ getValue: () => 0 })}</>);

    expect(container.textContent).toBe('');
  });

  it('entityId cell renders the value as a string for non-zero numbers', () => {
    const columns = getAuditTrailColumns(t);
    const entityIdCol = columns.find(
      (c) => (c as { accessorKey?: string }).accessorKey === 'entityId',
    )!;
    type CellFn = (ctx: { getValue: () => unknown }) => React.ReactNode;
    const cell = entityIdCol.cell as CellFn;

    render(<>{cell({ getValue: () => 99 })}</>);

    expect(screen.getByText('99')).toBeInTheDocument();
  });

  // --- objectAfter accessorFn ---

  it('objectAfter accessorFn returns empty string when objectAfter is null', () => {
    const columns = getAuditTrailColumns(t);
    const objectAfterCol = columns.find((c) => (c as { id?: string }).id === 'objectAfter')!;
    type AccessorFn = (row: AuditLog) => unknown;
    const fn = objectAfterCol.accessorFn as AccessorFn;

    const row = {
      logId: 1,
      logDate: 0,
      userName: '',
      entity: '',
      entityId: null,
      ipAddress: '',
      message: '',
      objectAfter: null,
    };
    expect(fn(row)).toBe('');
  });

  it('objectAfter accessorFn returns empty string when objectAfter is an empty object', () => {
    const columns = getAuditTrailColumns(t);
    const objectAfterCol = columns.find((c) => (c as { id?: string }).id === 'objectAfter')!;
    type AccessorFn = (row: AuditLog) => unknown;
    const fn = objectAfterCol.accessorFn as AccessorFn;

    const row = {
      logId: 1,
      logDate: 0,
      userName: '',
      entity: '',
      entityId: null,
      ipAddress: '',
      message: '',
      objectAfter: {},
    };
    expect(fn(row)).toBe('');
  });

  it('objectAfter accessorFn returns JSON.stringify value for a non-empty object', () => {
    const columns = getAuditTrailColumns(t);
    const objectAfterCol = columns.find((c) => (c as { id?: string }).id === 'objectAfter')!;
    type AccessorFn = (row: AuditLog) => unknown;
    const fn = objectAfterCol.accessorFn as AccessorFn;

    const obj = { name: 'My Layout', status: 1 };
    const row = {
      logId: 1,
      logDate: 0,
      userName: '',
      entity: '',
      entityId: null,
      ipAddress: '',
      message: '',
      objectAfter: obj,
    };
    expect(fn(row)).toBe(JSON.stringify(obj));
  });

  // --- objectAfter cell ---

  it('objectAfter cell renders nothing when objectAfter is null', () => {
    const columns = getAuditTrailColumns(t);
    const objectAfterCol = columns.find((c) => (c as { id?: string }).id === 'objectAfter')!;
    type CellFn = (ctx: { row: { original: AuditLog } }) => React.ReactNode;
    const cell = objectAfterCol.cell as CellFn;

    const { container } = render(
      <>
        {cell({
          row: {
            original: {
              logId: 1,
              logDate: 0,
              userName: '',
              entity: '',
              entityId: null,
              ipAddress: '',
              message: '',
              objectAfter: null,
            },
          },
        })}
      </>,
    );

    expect(container.firstChild).toBeNull();
  });

  it('objectAfter cell renders nothing when objectAfter is an empty object', () => {
    const columns = getAuditTrailColumns(t);
    const objectAfterCol = columns.find((c) => (c as { id?: string }).id === 'objectAfter')!;
    type CellFn = (ctx: { row: { original: AuditLog } }) => React.ReactNode;
    const cell = objectAfterCol.cell as CellFn;

    const { container } = render(
      <>
        {cell({
          row: {
            original: {
              logId: 1,
              logDate: 0,
              userName: '',
              entity: '',
              entityId: null,
              ipAddress: '',
              message: '',
              objectAfter: {},
            },
          },
        })}
      </>,
    );

    expect(container.firstChild).toBeNull();
  });

  it('objectAfter cell renders a Search icon button when objectAfter has properties', () => {
    const columns = getAuditTrailColumns(t);
    const objectAfterCol = columns.find((c) => (c as { id?: string }).id === 'objectAfter')!;
    type CellFn = (ctx: { row: { original: AuditLog } }) => React.ReactNode;
    const cell = objectAfterCol.cell as CellFn;

    render(
      <>
        {cell({
          row: {
            original: {
              logId: 1,
              logDate: 0,
              userName: '',
              entity: '',
              entityId: null,
              ipAddress: '',
              message: '',
              objectAfter: { name: 'Layout A' },
            },
          },
        })}
      </>,
    );

    expect(screen.getByRole('button')).toBeInTheDocument();
  });

  it('clicking the Search button expands the property table', async () => {
    const user = userEvent.setup();
    const columns = getAuditTrailColumns(t);
    const objectAfterCol = columns.find((c) => (c as { id?: string }).id === 'objectAfter')!;
    type CellFn = (ctx: { row: { original: AuditLog } }) => React.ReactNode;
    const cell = objectAfterCol.cell as CellFn;

    render(
      <>
        {cell({
          row: {
            original: {
              logId: 1,
              logDate: 0,
              userName: '',
              entity: '',
              entityId: null,
              ipAddress: '',
              message: '',
              objectAfter: { name: 'Layout A' },
            },
          },
        })}
      </>,
    );

    await user.click(screen.getByRole('button'));

    expect(screen.getByRole('table')).toBeInTheDocument();
  });

  it('expanded table has "Property" and "Value" column headers', async () => {
    const user = userEvent.setup();
    const columns = getAuditTrailColumns(t);
    const objectAfterCol = columns.find((c) => (c as { id?: string }).id === 'objectAfter')!;
    type CellFn = (ctx: { row: { original: AuditLog } }) => React.ReactNode;
    const cell = objectAfterCol.cell as CellFn;

    render(
      <>
        {cell({
          row: {
            original: {
              logId: 1,
              logDate: 0,
              userName: '',
              entity: '',
              entityId: null,
              ipAddress: '',
              message: '',
              objectAfter: { name: 'Layout A' },
            },
          },
        })}
      </>,
    );

    await user.click(screen.getByRole('button'));

    expect(screen.getByText('Property')).toBeInTheDocument();
    expect(screen.getByText('Value')).toBeInTheDocument();
  });

  it('expanded table shows individual key/value pairs', async () => {
    const user = userEvent.setup();
    const columns = getAuditTrailColumns(t);
    const objectAfterCol = columns.find((c) => (c as { id?: string }).id === 'objectAfter')!;
    type CellFn = (ctx: { row: { original: AuditLog } }) => React.ReactNode;
    const cell = objectAfterCol.cell as CellFn;

    render(
      <>
        {cell({
          row: {
            original: {
              logId: 1,
              logDate: 0,
              userName: '',
              entity: '',
              entityId: null,
              ipAddress: '',
              message: '',
              objectAfter: { status: 'published', width: 1920 },
            },
          },
        })}
      </>,
    );

    await user.click(screen.getByRole('button'));

    expect(screen.getByText('status')).toBeInTheDocument();
    expect(screen.getByText('published')).toBeInTheDocument();
    expect(screen.getByText('width')).toBeInTheDocument();
    expect(screen.getByText('1920')).toBeInTheDocument();
  });

  it('nested object values render as JSON.stringify strings', async () => {
    const user = userEvent.setup();
    const columns = getAuditTrailColumns(t);
    const objectAfterCol = columns.find((c) => (c as { id?: string }).id === 'objectAfter')!;
    type CellFn = (ctx: { row: { original: AuditLog } }) => React.ReactNode;
    const cell = objectAfterCol.cell as CellFn;

    const nested = { tags: ['a', 'b'] };
    render(
      <>
        {cell({
          row: {
            original: {
              logId: 1,
              logDate: 0,
              userName: '',
              entity: '',
              entityId: null,
              ipAddress: '',
              message: '',
              objectAfter: nested,
            },
          },
        })}
      </>,
    );

    await user.click(screen.getByRole('button'));

    expect(screen.getByText(JSON.stringify(['a', 'b']))).toBeInTheDocument();
  });

  it('clicking the Search button a second time collapses the property table', async () => {
    const user = userEvent.setup();
    const columns = getAuditTrailColumns(t);
    const objectAfterCol = columns.find((c) => (c as { id?: string }).id === 'objectAfter')!;
    type CellFn = (ctx: { row: { original: AuditLog } }) => React.ReactNode;
    const cell = objectAfterCol.cell as CellFn;

    render(
      <>
        {cell({
          row: {
            original: {
              logId: 1,
              logDate: 0,
              userName: '',
              entity: '',
              entityId: null,
              ipAddress: '',
              message: '',
              objectAfter: { name: 'Layout A' },
            },
          },
        })}
      </>,
    );

    await user.click(screen.getByRole('button')); // expand
    await user.click(screen.getByRole('button')); // collapse

    expect(screen.queryByRole('table')).not.toBeInTheDocument();
  });
});

// --- INITIAL_FILTER_STATE ---

describe('INITIAL_FILTER_STATE', () => {
  it('contains exactly 7 keys', () => {
    const keys = Object.keys(INITIAL_FILTER_STATE);
    expect(keys).toHaveLength(7);
  });

  it('defaults all values to empty string', () => {
    for (const value of Object.values(INITIAL_FILTER_STATE)) {
      expect(value).toBe('');
    }
  });
});

// --- getBaseFilterKeys ---

describe('getBaseFilterKeys', () => {
  it('returns exactly 7 filter config items', () => {
    const keys = getBaseFilterKeys(t);
    expect(keys).toHaveLength(7);
  });

  it('fromDt filter has type "date"', () => {
    const keys = getBaseFilterKeys(t);
    const fromDt = keys.find((k) => k.name === 'fromDt');
    expect(fromDt?.type).toBe('date');
  });

  it('toDt filter has type "date"', () => {
    const keys = getBaseFilterKeys(t);
    const toDt = keys.find((k) => k.name === 'toDt');
    expect(toDt?.type).toBe('date');
  });

  it('user, entity, entityId, ipAddress, and message filters all have type "text"', () => {
    const keys = getBaseFilterKeys(t);
    const textFilters = ['user', 'entity', 'entityId', 'ipAddress', 'message'];
    for (const name of textFilters) {
      const filter = keys.find((k) => k.name === name);
      expect(filter?.type).toBe('text');
    }
  });
});
