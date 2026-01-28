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

import type { LucideIcon } from 'lucide-react';
import { Check, X } from 'lucide-react';
import { twMerge } from 'tailwind-merge';

const BASE_STYLE = 'inline-flex justify-center items-center size-6 rounded-lg';

export function CheckMarkCell({ active = true }) {
  const Icon = (active ? Check : X) as LucideIcon;

  return (
    <div className="flex items-center justify-center">
      <span
        className={twMerge(
          BASE_STYLE,
          active ? 'text-teal-800 bg-teal-100' : 'text-gray-500 bg-gray-50',
        )}
      >
        <Icon className="size-4"></Icon>
      </span>
    </div>
  );
}
