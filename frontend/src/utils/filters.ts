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

type FilterConfigLike<T> = {
  name: keyof T & string;
  type?: string;
};

type FilterFieldRef<T> = FilterConfigLike<T> | (keyof T & string);

export function countActiveFilters<T extends object>(
  values: Partial<T>,
  initialState: Partial<T>,
  fields: readonly FilterFieldRef<T>[],
): number {
  return fields.reduce((count, field) => {
    const key = typeof field === 'string' ? field : field.name;
    const value = values[key];

    if (Array.isArray(value)) {
      return count + (value.length > 0 ? 1 : 0);
    }

    const isEmpty = value === undefined || value === null || value === '';
    return count + (!isEmpty && value !== initialState[key] ? 1 : 0);
  }, 0);
}
