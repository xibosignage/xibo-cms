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

import { oklchToRgb, rgbToOklch, type Rgb } from '../oklch';

const hex = (value: string): Rgb => ({
  r: parseInt(value.slice(1, 3), 16),
  g: parseInt(value.slice(3, 5), 16),
  b: parseInt(value.slice(5, 7), 16),
});

// The sidebar colour fallback (xibosignage/xibo#3893) converts the brand colour
// to OKLCH, adjusts it, and converts it back. These pin the two conversions.
describe('oklch', () => {
  test('converts red to the CSS Color 4 reference values', () => {
    const { l, c, h } = rgbToOklch({ r: 255, g: 0, b: 0 });

    expect(l).toBeCloseTo(0.62796, 4);
    expect(c).toBeCloseTo(0.25768, 4);
    expect(h).toBeCloseTo(29.2339, 3);
  });

  test.each(['#0e70f6', '#ff0000', '#00ff00', '#0000ff', '#ffffff', '#000000', '#808080'])(
    'round-trips %s exactly',
    (color) => {
      expect(oklchToRgb(rgbToOklch(hex(color)))).toEqual(hex(color));
    },
  );

  test('round-trips every colour on a coarse grid exactly', () => {
    const misses: string[] = [];
    for (let r = 0; r <= 255; r += 15) {
      for (let g = 0; g <= 255; g += 15) {
        for (let b = 0; b <= 255; b += 15) {
          const back = oklchToRgb(rgbToOklch({ r, g, b }));
          if (back.r !== r || back.g !== g || back.b !== b) misses.push(`${r},${g},${b}`);
        }
      }
    }

    expect(misses).toEqual([]);
  });
});
