import { render, screen } from '@testing-library/react';
import type { TFunction } from 'i18next';
import { describe, expect, it } from 'vitest';

import { INITIAL_FILTER_STATE, getBaseFilterKeys, getLogsColumns } from '../LogsConfig';

// Use the identity TFunction for simple label assertions
const t = ((key: string) => key) as TFunction;

// --- getLogsColumns ---

describe('getLogsColumns', () => {
  it('returns exactly 9 column definitions', () => {
    const columns = getLogsColumns(t);
    expect(columns).toHaveLength(9);
  });

  it('has the correct accessorKey values in order', () => {
    const columns = getLogsColumns(t);
    const keys = columns.map((c) => (c as { accessorKey?: string }).accessorKey);
    expect(keys).toEqual([
      'logId',
      'runNo',
      'logDate',
      'channel',
      'function',
      'type',
      'display',
      'page',
      'message',
    ]);
  });

  it('has the correct header labels', () => {
    const columns = getLogsColumns(t);
    const headers = columns.map((c) => c.header);
    expect(headers).toEqual([
      'ID',
      'Run No',
      'Date',
      'Channel',
      'Function',
      'Level',
      'Display',
      'Page',
      'Message',
    ]);
  });

  it('renders the logId cell as bold text', () => {
    const columns = getLogsColumns(t);
    const logIdCol = columns.find((c) => (c as { accessorKey?: string }).accessorKey === 'logId')!;
    type CellFn = (ctx: { getValue: () => unknown }) => React.ReactNode;
    const cell = logIdCol.cell as CellFn;

    render(<>{cell({ getValue: () => 42 })}</>);

    const el = screen.getByText('42');
    expect(el).toBeInTheDocument();
  });

  it('message cell decodes &amp; to the ampersand character', () => {
    const columns = getLogsColumns(t);
    const messageCol = columns.find(
      (c) => (c as { accessorKey?: string }).accessorKey === 'message',
    )!;
    type CellFn = (ctx: { getValue: () => unknown }) => React.ReactNode;
    const cell = messageCol.cell as CellFn;

    render(<>{cell({ getValue: () => '&amp;' })}</>);

    expect(screen.getByText('&')).toBeInTheDocument();
  });

  it('message cell decodes &lt; and &gt; angle brackets', () => {
    const columns = getLogsColumns(t);
    const messageCol = columns.find(
      (c) => (c as { accessorKey?: string }).accessorKey === 'message',
    )!;
    type CellFn = (ctx: { getValue: () => unknown }) => React.ReactNode;
    const cell = messageCol.cell as CellFn;

    render(<>{cell({ getValue: () => '&lt;b&gt;text&lt;/b&gt;' })}</>);

    expect(screen.getByText('<b>text</b>')).toBeInTheDocument();
  });

  it('message cell renders nothing for an empty string', () => {
    const columns = getLogsColumns(t);
    const messageCol = columns.find(
      (c) => (c as { accessorKey?: string }).accessorKey === 'message',
    )!;
    type CellFn = (ctx: { getValue: () => unknown }) => React.ReactNode;
    const cell = messageCol.cell as CellFn;

    const { container } = render(<>{cell({ getValue: () => '' })}</>);

    expect(container.textContent).toBe('');
  });

  it('message cell decodes multiple entities in a single string', () => {
    const columns = getLogsColumns(t);
    const messageCol = columns.find(
      (c) => (c as { accessorKey?: string }).accessorKey === 'message',
    )!;
    type CellFn = (ctx: { getValue: () => unknown }) => React.ReactNode;
    const cell = messageCol.cell as CellFn;

    render(<>{cell({ getValue: () => '&amp; &lt;br /&gt; &amp;' })}</>);

    expect(screen.getByText('& <br /> &')).toBeInTheDocument();
  });
});

// --- INITIAL_FILTER_STATE ---

describe('INITIAL_FILTER_STATE', () => {
  it('contains all 15 filter keys', () => {
    const keys = Object.keys(INITIAL_FILTER_STATE);
    expect(keys).toHaveLength(15);
  });

  it('defaults seconds to "120"', () => {
    expect(INITIAL_FILTER_STATE.seconds).toBe('120');
  });

  it('defaults intervalType to "1" (seconds)', () => {
    expect(INITIAL_FILTER_STATE.intervalType).toBe('1');
  });

  it('defaults excludeLog to true', () => {
    expect(INITIAL_FILTER_STATE.excludeLog).toBe(true);
  });

  it('defaults useRegexForName to false', () => {
    expect(INITIAL_FILTER_STATE.useRegexForName).toBe(false);
  });

  it('defaults all other string fields to empty string', () => {
    const skipKeys = new Set(['seconds', 'intervalType', 'excludeLog', 'useRegexForName']);
    for (const [key, value] of Object.entries(INITIAL_FILTER_STATE)) {
      if (!skipKeys.has(key)) {
        expect(value).toBe('');
      }
    }
  });
});

// --- getBaseFilterKeys ---

describe('getBaseFilterKeys', () => {
  it('returns exactly 14 filter config items', () => {
    const keys = getBaseFilterKeys(t);
    expect(keys).toHaveLength(14);
  });

  it('fromDt filter has type "date"', () => {
    const keys = getBaseFilterKeys(t);
    const fromDt = keys.find((k) => k.name === 'fromDt');
    expect(fromDt?.type).toBe('date');
  });

  it('seconds filter has type "number"', () => {
    const keys = getBaseFilterKeys(t);
    const seconds = keys.find((k) => k.name === 'seconds');
    expect(seconds?.type).toBe('number');
  });

  it('intervalType filter has type "select" with 3 options', () => {
    const keys = getBaseFilterKeys(t);
    const intervalType = keys.find((k) => k.name === 'intervalType');
    expect(intervalType?.type).toBe('select');
    expect(intervalType?.options).toHaveLength(3);
  });

  it('function filter has type "select" with 7 options (including All)', () => {
    const keys = getBaseFilterKeys(t);
    const fn = keys.find((k) => k.name === 'function');
    expect(fn?.type).toBe('select');
    expect(fn?.options).toHaveLength(7);
  });

  it('excludeLog filter has type "checkbox"', () => {
    const keys = getBaseFilterKeys(t);
    const excludeLog = keys.find((k) => k.name === 'excludeLog');
    expect(excludeLog?.type).toBe('checkbox');
  });

  it('display filter has showRegex enabled', () => {
    const keys = getBaseFilterKeys(t);
    const display = keys.find((k) => k.name === 'display');
    expect(display?.showRegex).toBe(true);
    expect(display?.regexKey).toBe('useRegexForName');
  });
});
