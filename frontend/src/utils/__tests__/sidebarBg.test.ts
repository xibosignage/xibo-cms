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

import { readFileSync } from 'node:fs';

import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';

import { resolveSidebarBg } from '../contrastColor';
import { oklchToRgb, rgbToOklch } from '../oklch';

// jsdom has no canvas, and resolveToRgb uses one to read a colour back. This
// stand-in understands the #rrggbb colours the tests use.
const fakeCanvasContext = () => {
  let fill = '#000000';
  return {
    set fillStyle(value: string) {
      fill = value;
    },
    get fillStyle() {
      return fill;
    },
    fillRect: () => {},
    getImageData: () => ({
      data: [
        parseInt(fill.slice(1, 3), 16),
        parseInt(fill.slice(3, 5), 16),
        parseInt(fill.slice(5, 7), 16),
        255,
      ],
    }),
  };
};

// Read from disk; `import.meta.url` goes through a variable so Vite doesn't
// rewrite `new URL(path, import.meta.url)` into an asset import.
const thisFile = import.meta.url;

/** The --sidebar-bg curve as global.css defines it: pow(l, exponent), min(c, maxChroma). */
const cssCurve = () => {
  const css = readFileSync(new URL('../../styles/global.css', thisFile), 'utf8');
  const rule = css.match(/--sidebar-bg:\s*oklch\(from var\(--brand-primary\)([^;]+);/)?.[1] ?? '';
  return {
    exponent: Number(rule.match(/pow\(l,\s*([\d.]+)\)/)?.[1]),
    maxChroma: Number(rule.match(/min\(c,\s*([\d.]+)\)/)?.[1]),
  };
};

const hex = (value: string) => ({
  r: parseInt(value.slice(1, 3), 16),
  g: parseInt(value.slice(3, 5), 16),
  b: parseInt(value.slice(5, 7), 16),
});

const parseRgb = (value: string) => {
  const [r, g, b] = value.match(/\d+/g)!.map(Number);
  return { r: r!, g: g!, b: b! };
};

// Regression cover for xibosignage/xibo#3893. Firefox ESR 140 miscomputes the
// relative-colour CSS that derives the sidebar colour from the brand colour, so
// the app works it out in JS and sets it inline. The JS has to give the colour
// the CSS would.
describe('resolveSidebarBg', () => {
  beforeEach(() => {
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockImplementation(
      () => fakeCanvasContext() as unknown as CanvasRenderingContext2D,
    );
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  test('reads the curve from global.css', () => {
    // Guards the tests below: if the rule's shape changed, they would compare
    // against nothing.
    expect(cssCurve().exponent).toBeGreaterThan(0);
    expect(cssCurve().maxChroma).toBeGreaterThan(0);
  });

  test.each(['#0e70f6', '#ff0000', '#2e7d32', '#ffc107', '#ffffff', '#000000'])(
    'gives %s the sidebar colour the CSS would',
    (brand) => {
      const { exponent, maxChroma } = cssCurve();
      const { l, c, h } = rgbToOklch(hex(brand));
      const expected = oklchToRgb({ l: Math.pow(l, exponent), c: Math.min(c, maxChroma), h });

      expect(parseRgb(resolveSidebarBg(brand)!)).toEqual(expected);
    },
  );

  test('gives the default brand colour its known sidebar colour', () => {
    expect(resolveSidebarBg('#0e70f6')).toBe('rgb(0 55 142)');
  });

  test('never makes the sidebar lighter than the brand colour', () => {
    for (const brand of ['#0e70f6', '#ffc107', '#90caf9']) {
      const brandL = rgbToOklch(hex(brand)).l;
      const sidebarL = rgbToOklch(parseRgb(resolveSidebarBg(brand)!)).l;
      expect(sidebarL).toBeLessThanOrEqual(brandL + 1e-6);
    }
  });

  test('gives up, rather than guessing, when the browser cannot read the colour back', () => {
    vi.mocked(HTMLCanvasElement.prototype.getContext).mockReturnValue(null);

    expect(resolveSidebarBg('#0e70f6')).toBeNull();
  });
});
