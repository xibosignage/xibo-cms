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

import type { Row } from '@tanstack/react-table';
import { twMerge } from 'tailwind-merge';

import DataTableRowActions from '../DataTableRowActions';
import type { DataTableRowAction } from '../DataTableRowActions';

interface ActionsProps<TData> {
  row: Row<TData>;
  actions: DataTableRowAction<TData>[];
}

export function ActionsCell<TData>({ row, actions }: ActionsProps<TData>) {
  // Quick actions
  const quickActions = actions.filter((a) => a.isQuickAction && !a.isSeparator);

  // Menu actions and separators
  const menuActions = actions.filter((a) => !a.isQuickAction);

  return (
    <div className="flex justify-end items-center gap-1 no-print">
      {/* Quick Actions */}
      {quickActions.map((action, index) => (
        <button
          key={index}
          onClick={(e) => {
            e.stopPropagation();
            if (action.onClick) action.onClick(row.original);
          }}
          className={twMerge(
            'cursor-pointer flex justify-center p-1 items-center text-sm font-medium rounded-lg border border-transparent focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none',
            action.variant === 'danger'
              ? 'text-red-600 hover:bg-red-50 focus:bg-red-100'
              : action.variant === 'primary'
                ? 'text-blue-600 hover:bg-blue-50 focus:bg-blue-100'
                : 'text-gray-600 hover:bg-gray-50 focus:bg-gray-100',
          )}
          title={action.label}
        >
          {action.icon && <action.icon className="w-4 h-4" />}
        </button>
      ))}

      {/* Menu Actions */}
      {menuActions.length > 0 && (
        <DataTableRowActions
          row={row.original}
          actions={menuActions as DataTableRowAction<TData>[]}
        />
      )}
    </div>
  );
}
