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

import {
  useFloating,
  autoUpdate,
  offset,
  flip,
  shift,
  useClick,
  useDismiss,
  useInteractions,
  FloatingPortal,
} from '@floating-ui/react';
import type { LucideIcon } from 'lucide-react';
import { ChevronRight, MoreVertical } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

export interface DataTableRowAction<TData> {
  label?: string;
  onClick?: (row: TData) => void;
  icon?: LucideIcon;
  isNavigation?: boolean;
  variant?: 'default' | 'primary' | 'danger';
  isSeparator?: boolean;
  isQuickAction?: boolean;
}

interface DataTableRowActionsProps<TData> {
  row: TData;
  actions: DataTableRowAction<TData>[];
}

export default function DataTableRowActions<TData>({
  row,
  actions,
}: DataTableRowActionsProps<TData>) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);

  const { refs, floatingStyles, context } = useFloating({
    open: open,
    onOpenChange: setOpen,
    placement: 'bottom-end',
    whileElementsMounted: autoUpdate,
    middleware: [offset(4), flip(), shift()],
  });

  const click = useClick(context);
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  return (
    <div className="relative inline-block text-left">
      <button
        ref={refs.setReference}
        {...getReferenceProps()}
        className="p-1.5 text-gray-500 hover:bg-gray-50 focus:bg-gray-100 rounded-lg focus:outline-none transition-colors cursor-pointer"
        aria-label={t('More actions')}
      >
        <MoreVertical className="w-4 h-4" />
      </button>

      <FloatingPortal>
        {open && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className="z-100 bg-white shadow-lg rounded-xl overflow-hidden min-w-40"
          >
            <div className="p-2">
              {actions.map((action, idx) => {
                if (action.isSeparator) {
                  return <div key={idx} className="my-1 h-px bg-gray-100" role="separator" />;
                }

                return (
                  <button
                    key={idx}
                    onClick={(e) => {
                      e.stopPropagation();
                      if (action.onClick) action.onClick(row);
                      setOpen(false);
                    }}
                    className={twMerge(
                      'flex items-center w-full gap-3 rounded-lg text-left px-3 py-2 text-sm transition-colors cursor-pointer',
                      action.variant === 'danger'
                        ? 'text-red-800 hover:bg-red-50 focus:bg-red-100'
                        : action.variant === 'primary'
                          ? 'text-blue-800 hover:bg-blue-50 focus:bg-blue-100'
                          : 'text-gray-800 hover:bg-gray-50 focus:bg-gray-100',
                    )}
                  >
                    {action.icon && (
                      <span className="w-4 h-4 flex items-center justify-center">
                        <action.icon />
                      </span>
                    )}
                    <span className="flex-1 text-left truncate">{action.label}</span>
                    {action.isNavigation && (
                      <span className="w-4 h-4 flex items-center justify-center shrink-0 text-gray-400">
                        <ChevronRight />
                      </span>
                    )}
                  </button>
                );
              })}
            </div>
          </div>
        )}
      </FloatingPortal>
    </div>
  );
}
