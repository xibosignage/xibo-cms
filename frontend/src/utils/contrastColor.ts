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

export type ContrastMode = 'light' | 'dark';

/** Returns whichever of black/white text gets the higher WCAG contrast ratio against cssColor. */
export function getContrastMode(cssColor: string): ContrastMode {
  const rgb = resolveToRgb(cssColor);
  if (!rgb) {
    return 'dark';
  }

  const luminance = relativeLuminance(rgb);
  const contrastWithWhiteText = 1.05 / (luminance + 0.05);
  const contrastWithBlackText = (luminance + 0.05) / 0.05;
  return contrastWithBlackText > contrastWithWhiteText ? 'light' : 'dark';
}

// WCAG 2.x relative luminance.
function relativeLuminance({ r, g, b }: { r: number; g: number; b: number }): number {
  const channel = (c: number) => {
    const v = c / 255;
    return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
  };
  return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

// Canvas fill normalizes any CSS colour notation to sRGB bytes, needed because
// getComputedStyle can return a resolved colour in its original notation (e.g.
// a literal "oklch(...)" string), not always rgb().
function resolveToRgb(cssColor: string): { r: number; g: number; b: number } | null {
  const canvas = document.createElement('canvas');
  canvas.width = 1;
  canvas.height = 1;
  const ctx = canvas.getContext('2d');
  if (!ctx) {
    return null;
  }

  ctx.fillStyle = cssColor;
  ctx.fillRect(0, 0, 1, 1);
  const [r, g, b] = ctx.getImageData(0, 0, 1, 1).data;
  if (r === undefined || g === undefined || b === undefined) {
    return null;
  }

  return { r, g, b };
}

// Probes --sidebar-bg's actual resolved colour (var() doesn't resolve directly
// via getComputedStyle) to decide the sidebar's text/icon contrast mode.
export function resolveSidebarContrastMode(): ContrastMode {
  const probe = document.createElement('div');
  probe.style.position = 'absolute';
  probe.style.visibility = 'hidden';
  probe.style.pointerEvents = 'none';
  probe.style.backgroundColor = 'var(--sidebar-bg)';
  document.body.appendChild(probe);

  const resolved = getComputedStyle(probe).backgroundColor;
  probe.remove();

  return getContrastMode(resolved);
}
