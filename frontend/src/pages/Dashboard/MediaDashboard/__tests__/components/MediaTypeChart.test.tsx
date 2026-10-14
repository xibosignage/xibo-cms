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
import type * as RechartsModule from 'recharts';
import { vi, describe, test, expect } from 'vitest';

import MediaTypeChart, { buildChartItems } from '../../components/MediaTypeChart';
import { buildLibraryType } from '../fixtures/mediaDashboard';

import { formatFileSize } from '@/utils/formatters';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('recharts', async (importOriginal) => {
  const actual = await importOriginal<typeof RechartsModule>();
  const { cloneElement } = await import('react');
  return {
    ...actual,
    ResponsiveContainer: ({ children }: { children: React.ReactElement }) =>
      cloneElement(children, { width: 200, height: 200 } as Record<string, unknown>),
  };
});

// =============================================================================
// Helpers local to this file
// =============================================================================

const KNOWN_TYPES = [
  'Widget cache',
  'PowerPoint',
  'Image',
  'Flash',
  'PDF',
  'HTML Package',
  'Audio',
  'Generic File',
  'Video',
];

const valueOf = (items: ReturnType<typeof buildChartItems>, name: string) =>
  items.find((item) => item.name === name);

const renderChart = (items: ReturnType<typeof buildChartItems>, isLoading = false) =>
  render(
    <MediaTypeChart
      title="No. of Media Items"
      items={items}
      centerValue="4"
      centerLabel="Total Items"
      isLoading={isLoading}
    />,
  );

// Pie slices have no role or name. Recharts marks each with this class, and it is also what
// usePieTooltip hit-tests, so it is the only way to hover a slice.
const slices = (container: HTMLElement) =>
  Array.from(container.querySelectorAll('.recharts-pie-sector'));

// =============================================================================
// Tests — buildChartItems (pure function, no DOM)
// =============================================================================

describe('buildChartItems', () => {
  test('always returns the nine known media types in a fixed order', () => {
    const items = buildChartItems([], 'count');

    expect(items.map((item) => item.name)).toEqual(KNOWN_TYPES);
    expect(items.every((item) => item.value === 0)).toBe(true);
  });

  test('in count mode, a type shows its number of items', () => {
    const items = buildChartItems(
      [buildLibraryType({ title: 'Image', count: 4, size: 999 })],
      'count',
    );

    expect(valueOf(items, 'Image')).toEqual(
      expect.objectContaining({ value: 4, displayValue: '4' }),
    );
  });

  test('in size mode, a type shows its size in bytes, displayed in readable units', () => {
    const items = buildChartItems(
      [buildLibraryType({ title: 'Video', count: 1, size: 1048576 })],
      'size',
    );

    expect(valueOf(items, 'Video')).toEqual(
      expect.objectContaining({ value: 1048576, displayValue: formatFileSize(1048576) }),
    );
  });

  test('a known type the library does not contain is 0', () => {
    const items = buildChartItems([buildLibraryType({ title: 'Image', count: 2 })], 'count');

    expect(valueOf(items, 'Audio')?.value).toBe(0);
  });

  // Pins current behaviour (K3): types outside the nine are dropped, so the legend can add up
  // to less than the centre total.
  test('a type the chart does not know about is left out', () => {
    const items = buildChartItems(
      [
        buildLibraryType({ title: 'Image', count: 2 }),
        buildLibraryType({ title: 'Font', count: 5 }),
      ],
      'count',
    );

    expect(items).toHaveLength(9);
    expect(valueOf(items, 'Font')).toBeUndefined();
    expect(items.reduce((sum, item) => sum + item.value, 0)).toBe(2);
  });

  // The server's title for module files (MediaManager.php).
  test('"Widget cache" gets its own slice', () => {
    const items = buildChartItems([buildLibraryType({ title: 'Widget cache', count: 7 })], 'count');

    expect(valueOf(items, 'Widget cache')?.value).toBe(7);
  });
});

// =============================================================================
// Tests — MediaTypeChart component
// =============================================================================

describe('MediaTypeChart', () => {
  const IMAGE_3_VIDEO_1 = buildChartItems(
    [
      buildLibraryType({ title: 'Image', count: 3 }),
      buildLibraryType({ title: 'Video', count: 1 }),
    ],
    'count',
  );

  test('while loading, the chart says Loading and shows no legend', () => {
    renderChart(IMAGE_3_VIDEO_1, true);

    expect(screen.getByText('Loading...')).toBeInTheDocument();
    expect(screen.queryByText('Image: 3')).not.toBeInTheDocument();
  });

  test('the legend lists every type as "Type: value"', () => {
    renderChart(IMAGE_3_VIDEO_1);

    expect(screen.getByText('Image: 3')).toBeInTheDocument();
    expect(screen.getByText('Video: 1')).toBeInTheDocument();
    expect(screen.getByText('Audio: 0')).toBeInTheDocument();
  });

  // Low priority. Image is the third known type; with only Image and Video non-zero, the
  // first drawn slice is Image.
  test('hovering a slice shows its share of the total', async () => {
    const user = userEvent.setup();
    const { container } = renderChart(IMAGE_3_VIDEO_1);

    await user.hover(slices(container)[0]!);

    await screen.findByText('Image: 75.0%');
  });

  // Low priority. The tooltip's divide-by-zero guard can't be reached from here: with every
  // value at 0 no slice is drawn, so there is nothing to hover. This checks the empty chart
  // itself renders cleanly.
  test('when every type is zero, the chart renders every type at 0 and no NaN', () => {
    const { container } = renderChart(buildChartItems([], 'count'));

    expect(container.textContent).not.toMatch(/NaN/);
    expect(screen.getByText('Image: 0')).toBeInTheDocument();
  });
});
