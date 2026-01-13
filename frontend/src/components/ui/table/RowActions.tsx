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

import { MoreVertical } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState, useRef, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';

export interface RowAction<TData> {
  label?: string;
  onClick?: (row: TData) => void;
  icon?: ReactNode;
  variant?: 'default' | 'danger';
  isSeparator?: boolean;
  isQuickAction?: boolean;
}

interface RowActionsProps<TData> {
  row: TData;
  actions: RowAction<TData>[];
}

export default function RowActions<TData>({ row, actions }: RowActionsProps<TData>) {
  const [open, setOpen] = useState(false);
  const [coords, setCoords] = useState({ top: 0, left: 0 });

  const buttonRef = useRef<HTMLButtonElement>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);

  const { t } = useTranslation();

  // Close on click outside or scroll
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      const target = event.target as Node;
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(target) &&
        buttonRef.current &&
        !buttonRef.current.contains(target)
      ) {
        setOpen(false);
      }
    }

    function handleScroll() {
      if (open) setOpen(false);
    }

    document.addEventListener('mousedown', handleClickOutside);
    window.addEventListener('scroll', handleScroll, true);
    window.addEventListener('resize', handleScroll);

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      window.removeEventListener('scroll', handleScroll, true);
      window.removeEventListener('resize', handleScroll);
    };
  }, [open]);

  const toggleOpen = (e: React.MouseEvent) => {
    e.stopPropagation();
    if (!open && buttonRef.current) {
      const rect = buttonRef.current.getBoundingClientRect();
      setCoords({
        top: rect.bottom + 2,
        left: rect.right - 180,
      });
    }
    setOpen(!open);
  };

  if (!actions || actions.length === 0) {
    return null;
  }

  return (
    <div className="relative inline-block text-left">
      <button
        ref={buttonRef}
        onClick={toggleOpen}
        className="p-1.5 text-gray-500 hover:bg-gray-100 focus:outline-none transition-colors"
        aria-label={t('More actions')}
      >
        <MoreVertical className="w-4 h-4" />
      </button>

      {open &&
        createPortal(
          <div
            ref={dropdownRef}
            style={{
              top: coords.top,
              left: coords.left,
            }}
            className="fixed w-[180px] bg-white shadow-xl z-9999 overflow-hidden animate-in fade-in zoom-in-95 duration-100"
          >
            <div className="py-1">
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
                    className={`flex items-center w-full text-left px-4 py-2 text-sm transition-colors text-gray-700 ${
                      action.variant === 'danger'
                        ? 'text-red-600 hover:bg-red-50'
                        : 'text-gray-700 hover:bg-gray-100'
                    }`}
                  >
                    {action.icon && (
                      <span className="mr-2 w-4 h-4 flex items-center justify-center">
                        {action.icon}
                      </span>
                    )}
                    {action.label}
                  </button>
                );
              })}
            </div>
          </div>,
          document.body,
        )}
    </div>
  );
}
