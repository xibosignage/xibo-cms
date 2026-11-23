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

type FilterConfigLike<T> = {
  name: keyof T & string;
  type?: string;
  compareToDefault?: boolean;
};

type FilterFieldRef<T> = FilterConfigLike<T> | (keyof T & string);

const isEmptyValue = (value: unknown) => value === undefined || value === null || value === '';

export function countActiveFilters<T extends object>(
  values: Partial<T>,
  initialState: Partial<T>,
  fields: readonly FilterFieldRef<T>[],
): number {
  return fields.reduce((count, field) => {
    const key = typeof field === 'string' ? field : field.name;
    const compareToDefault = typeof field !== 'string' && field.compareToDefault;
    const value = values[key];

    if (Array.isArray(value)) {
      return count + (value.length > 0 ? 1 : 0);
    }

    if (compareToDefault) {
      const normalized = isEmptyValue(value) ? undefined : value;
      const normalizedDefault = isEmptyValue(initialState[key]) ? undefined : initialState[key];
      return count + (normalized !== normalizedDefault ? 1 : 0);
    }

    return count + (!isEmptyValue(value) && value !== initialState[key] ? 1 : 0);
  }, 0);
}
