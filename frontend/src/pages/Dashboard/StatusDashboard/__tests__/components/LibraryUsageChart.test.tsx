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
import type * as ReactI18nextModule from 'react-i18next';
import type * as RechartsModule from 'recharts';
import { vi, describe, test, expect } from 'vitest';

import LibraryUsageChart from '../../components/LibraryUsageChart';
import { buildLibraryWidget } from '../fixtures/statusDashboard';

import { knownFailure } from '@/testUtils/knownFailure';

// =============================================================================
// Module mocks
// =============================================================================

// LibraryUsageChart is the one chart that leaves the Pie's entry animation on. In JSDOM the
// animation never advances, so a multi-slice donut draws no slices at all. Turning it off
// here renders the final state, which is what a user sees once the animation finishes.
vi.mock('recharts', async (importOriginal) => {
  const actual = await importOriginal<typeof RechartsModule>();
  const { cloneElement, createElement } = await import('react');
  return {
    ...actual,
    ResponsiveContainer: ({ children }: { children: React.ReactElement }) =>
      cloneElement(children, { width: 200, height: 200 } as Record<string, unknown>),
    Pie: (props: React.ComponentProps<typeof actual.Pie>) =>
      createElement(actual.Pie, { ...props, isAnimationActive: false }),
  };
});
// "Used of {{limit}}" would otherwise render as the raw key, which can't be asserted (§8).
vi.mock('react-i18next', async (importOriginal) => {
  const actual = await importOriginal<typeof ReactI18nextModule>();
  const t = (key: string, opts?: Record<string, unknown>) =>
    key.replace(/{{(\w+)}}/g, (_, name: string) => String(opts?.[name] ?? ''));
  return { ...actual, useTranslation: () => ({ t, i18n: { language: 'en' } }) };
});

// =============================================================================
// Helpers local to this file
// =============================================================================

interface ChartProps {
  rows: [label: string, value: number][];
  librarySize?: string;
  librarySuffix?: string;
  libraryLimit?: string;
  libraryLimitSet?: boolean;
}

// Labels must be server-shaped: ucfirst(type) + ' ' + suffix (StatusDashboard.php).
const renderChart = ({
  rows,
  librarySize = '3.2 GiB',
  librarySuffix = 'GiB',
  libraryLimit = '',
  libraryLimitSet = false,
}: ChartProps) => {
  const widget = buildLibraryWidget(rows);
  return render(
    <LibraryUsageChart
      libraryWidgetLabels={widget.libraryWidgetLabels}
      libraryWidgetData={widget.libraryWidgetData}
      librarySize={librarySize}
      librarySuffix={librarySuffix}
      libraryLimit={libraryLimit}
      libraryLimitSet={libraryLimitSet}
    />,
  );
};

// Every legend row reads "<Type>: <value> <suffix>".
const legendRows = () => screen.getAllByText(/^[A-Za-z ]+: [\d.]+ GiB$/);

// Donut slices have no role or name; recharts marks each with this class.
const donutSlices = (container: HTMLElement) => container.querySelectorAll('.recharts-pie-sector');

const ALL_TYPES = [
  'Audio',
  'HTML Package',
  'Image',
  'Generic File',
  'Flash',
  'Font',
  'Video',
  'Module',
  'PDF',
  'Player Software',
  'PowerPoint',
  'Saved Report',
];

// =============================================================================
// Tests
// =============================================================================

describe('LibraryUsageChart - centre of the donut', () => {
  test('the middle of the donut shows the total library size', () => {
    renderChart({ rows: [['Image GiB', 1]], librarySize: '3.2 GiB' });

    expect(screen.getByText('3.2 GiB')).toBeInTheDocument();
  });

  test('"Used of" the limit is shown when a limit is set and has a value', () => {
    renderChart({ rows: [['Image GiB', 1]], libraryLimitSet: true, libraryLimit: '10 GiB' });

    expect(screen.getByText('Used of 10 GiB')).toBeInTheDocument();
  });

  // The realistic "no limit" case: the server always sends libraryLimit as a string.
  test('"Used of" is hidden when no limit is set, even if a limit value is sent', () => {
    renderChart({ rows: [['Image GiB', 1]], libraryLimitSet: false, libraryLimit: '10 GiB' });

    expect(screen.queryByText(/^Used of/)).not.toBeInTheDocument();
  });

  test('"Used of" is hidden when the limit value is empty', () => {
    renderChart({ rows: [['Image GiB', 1]], libraryLimitSet: true, libraryLimit: '' });

    expect(screen.queryByText(/^Used of/)).not.toBeInTheDocument();
  });
});

describe('LibraryUsageChart - legend', () => {
  // Pdf and Powerpoint are matched to "PDF" and "PowerPoint" ignoring case.
  test('single-word media types show their size and unit', () => {
    renderChart({
      rows: [
        ['Image GiB', 1],
        ['Video GiB', 2],
        ['Pdf GiB', 0.5],
        ['Powerpoint GiB', 0.25],
      ],
    });

    expect(screen.getByText('Image: 1 GiB')).toBeInTheDocument();
    expect(screen.getByText('Video: 2 GiB')).toBeInTheDocument();
    expect(screen.getByText('PDF: 0.5 GiB')).toBeInTheDocument();
    expect(screen.getByText('PowerPoint: 0.25 GiB')).toBeInTheDocument();
  });

  test('the legend always lists all twelve known types, at 0 when absent', () => {
    renderChart({ rows: [['Image GiB', 1]] });

    expect(legendRows()).toHaveLength(12);
    for (const type of ALL_TYPES.filter((name) => name !== 'Image')) {
      expect(screen.getByText(`${type}: 0 GiB`)).toBeInTheDocument();
    }
  });

  test('the unit is removed from the labels regardless of letter case', () => {
    renderChart({ rows: [['Image gib', 1]], librarySuffix: 'GiB' });

    expect(screen.getByText('Image: 1 GiB')).toBeInTheDocument();
  });

  // The server sends ["Empty"] / [0] for an empty library with no limit.
  test('an empty library with no limit shows every type at 0', () => {
    renderChart({ rows: [['Empty', 0]] });

    expect(legendRows()).toHaveLength(12);
    for (const type of ALL_TYPES) {
      expect(screen.getByText(`${type}: 0 GiB`)).toBeInTheDocument();
    }
  });

  // xibosignage/xibo#4034: the server labels multi-word types as ucfirst(type)
  // with no space ("Genericfile", "Htmlpackage", "Playersoftware", "Savedreport"), and the chart
  // only knows "Generic File" etc., so these four always read 0.
  knownFailure(
    'multi-word media types show their real size',
    () => {
      renderChart({
        rows: [
          ['Image GiB', 1],
          ['Genericfile GiB', 2.5],
          ['Htmlpackage GiB', 1],
          ['Playersoftware GiB', 0.3],
          ['Savedreport GiB', 0.1],
        ],
      });
      // Control: the same render shows a single-word type's size, so the data did arrive.
      expect(screen.getByText('Image: 1 GiB')).toBeInTheDocument();
      return legendRows().map((row) => row.textContent);
    },
    (legend) =>
      expect(legend).toEqual(
        expect.arrayContaining([
          'Generic File: 2.5 GiB',
          'HTML Package: 1 GiB',
          'Player Software: 0.3 GiB',
          'Saved Report: 0.1 GiB',
        ]),
      ),
  );
});

describe('LibraryUsageChart - free space', () => {
  test('with a library limit, the donut has a separate free-space slice', () => {
    const { container } = renderChart({
      rows: [
        ['Image GiB', 1],
        ['Free GiB', 9],
      ],
      libraryLimitSet: true,
      libraryLimit: '10 GiB',
    });

    expect(donutSlices(container)).toHaveLength(2);
  });

  test('without a library limit, the donut has only the used slice', () => {
    const { container } = renderChart({ rows: [['Image GiB', 1]] });

    expect(donutSlices(container)).toHaveLength(1);
  });
});
