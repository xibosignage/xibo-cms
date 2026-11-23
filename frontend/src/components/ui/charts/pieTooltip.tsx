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

import { useState, type ReactElement } from 'react';
import type { TooltipContentProps } from 'recharts';

/**
 * Gates a pie/donut tooltip so it only shows while the cursor is genuinely over a slice.
 * Recharts otherwise keeps the tooltip active anywhere inside the chart (the donut hole and
 * empty margins). We hit-test the element under the cursor for the slice class rather than
 * tracking the cursor position in state, so hovering doesn't re-render the chart every frame —
 * recharts positions the tooltip itself.
 *
 * Wire `onMouseMove`/`onMouseLeave` to the chart wrapper and pass `active` to the Tooltip.
 */
export function usePieTooltip() {
  const [active, setActive] = useState(false);

  const onMouseMove = (e: React.MouseEvent) => {
    setActive(!!(e.target as Element | null)?.closest?.('.recharts-pie-sector'));
  };

  const onMouseLeave = () => setActive(false);

  return { active, onMouseMove, onMouseLeave };
}

export interface PieTooltipContent {
  /** Colour for the dot — usually the hovered slice's colour. */
  color: string;
  /** Text shown next to the dot, e.g. "Online: 5". */
  text: string;
}

/**
 * Build a recharts `content` component for a pie/donut tooltip: a dark chip with a colour dot
 * and a line of text. `format` receives the hovered slice's datum and returns the dot colour
 * and text. Call this at module scope (not inside render) so the component identity is stable.
 *
 * Note: this does not follow the cursor (no `position` prop) — recharts positions it. That keeps
 * hovering cheap (no per-frame re-render). The trade-off is no cursor-anchored arrow.
 */
export function makePieTooltip<T>(
  format: (entry: T) => PieTooltipContent,
): (props: TooltipContentProps) => ReactElement | null {
  return function PieTooltip({ active, payload }: TooltipContentProps): ReactElement | null {
    if (!active || !payload?.length) {
      return null;
    }
    const entry = payload[0]?.payload as T | undefined;
    if (!entry) {
      return null;
    }
    const { color, text } = format(entry);

    return (
      <div className="flex items-center gap-2 rounded-lg bg-gray-800 px-3 py-2 text-sm text-white shadow-lg">
        <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: color }} />
        <span>{text}</span>
      </div>
    );
  };
}
