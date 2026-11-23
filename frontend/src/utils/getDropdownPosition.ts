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

interface DropdownPositionParams {
  triggerRect: DOMRect;
  dropdownHeight: number;
  dropdownWidth: number;
  offset?: number;
}

export function getDropdownPosition({
  triggerRect,
  dropdownHeight,
  dropdownWidth,
  offset = 4,
}: DropdownPositionParams) {
  const viewportHeight = window.innerHeight;
  // const viewportWidth = window.innerWidth;

  const spaceBelow = viewportHeight - triggerRect.bottom;
  const spaceAbove = triggerRect.top;

  // Checks if there is no more space below
  const shouldOpenUp = spaceBelow < dropdownHeight && spaceAbove > dropdownHeight;

  const top = shouldOpenUp
    ? triggerRect.top - dropdownHeight - offset
    : triggerRect.bottom + offset;

  const left = triggerRect.right - dropdownWidth;

  return { top, left };
}
