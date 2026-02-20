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
