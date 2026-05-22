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
  FloatingPortal,
  autoUpdate,
  flip,
  offset,
  shift,
  size,
  useDismiss,
  useFloating,
  useInteractions,
} from '@floating-ui/react';
import { ChevronDown, Search, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { fetchDisplayGroups } from '@/services/displayGroupApi';

interface DisplayGroupSelectProps {
  label: string;
  helpText?: string;
  value: number | null;
  valueLabel: string;
  onChange: (id: number | null, label: string) => void;
}

export default function DisplayGroupSelect({
  label,
  helpText,
  value,
  valueLabel,
  onChange,
}: DisplayGroupSelectProps) {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [options, setOptions] = useState<{ id: number; label: string }[]>([]);
  const [isLoading, setIsLoading] = useState(false);

  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: (open) => {
      setIsOpen(open);
      if (!open) {
        setSearch('');
      }
    },
    placement: 'bottom-start',
    whileElementsMounted: autoUpdate,
    middleware: [
      offset(4),
      flip(),
      shift(),
      size({
        apply({ rects, elements }) {
          Object.assign(elements.floating.style, { width: `${rects.reference.width}px` });
        },
      }),
    ],
  });

  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([dismiss]);

  async function loadOptions(term: string) {
    setIsLoading(true);
    try {
      const result = await fetchDisplayGroups({
        start: 0,
        length: 20,
        displayGroup: term || undefined,
      });
      setOptions(result.rows.map((g) => ({ id: g.displayGroupId, label: g.displayGroup })));
    } finally {
      setIsLoading(false);
    }
  }

  function handleOpenChange(open: boolean) {
    if (open) {
      void loadOptions('');
    }
    setIsOpen(open);
    if (!open) {
      setSearch('');
    }
  }

  function handleSearchChange(term: string) {
    setSearch(term);
    void loadOptions(term);
  }

  return (
    <div className="flex flex-col gap-1 w-full">
      <label className="text-sm font-semibold text-gray-500 leading-5">{label}</label>

      <div className="flex gap-1">
        <div
          ref={refs.setReference}
          {...getReferenceProps({ onClick: () => handleOpenChange(!isOpen) })}
          role="combobox"
          aria-expanded={isOpen}
          tabIndex={0}
          className="flex-1 min-w-0 h-11.25 border bg-white border-gray-200 rounded-lg flex items-center cursor-pointer hover:border-gray-400 focus:outline-none focus:border-xibo-blue-600 focus:ring-1 focus:ring-xibo-blue-600/25 transition-colors"
        >
          <span
            className={`py-2 px-3 flex-1 text-sm truncate ${value ? 'text-gray-800' : 'text-gray-400'}`}
          >
            {value ? valueLabel : t('None')}
          </span>
          <span className="p-3 text-gray-500 shrink-0">
            <ChevronDown size={14} />
          </span>
        </div>

        {value !== null && (
          <button
            type="button"
            onClick={() => onChange(null, '')}
            className="p-2 text-gray-400 hover:text-gray-600"
            aria-label={t('Clear')}
          >
            <X size={16} />
          </button>
        )}
      </div>

      <FloatingPortal>
        {isOpen && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className="z-9999 bg-white shadow-md rounded-lg overflow-clip border border-gray-200 flex flex-col"
          >
            <div className="p-2 border-b border-gray-100 flex items-center gap-2">
              <Search size={14} className="text-gray-400 shrink-0" />
              <input
                autoFocus
                type="text"
                className="flex-1 text-sm outline-none border-none bg-transparent"
                placeholder={t('Search display groups…')}
                value={search}
                onChange={(e) => handleSearchChange(e.target.value)}
                onClick={(e) => e.stopPropagation()}
              />
            </div>
            <div className="flex flex-col p-2 text-sm overflow-y-auto max-h-60">
              {isLoading ? (
                <p className="text-sm text-gray-400 text-center py-3">{t('Loading…')}</p>
              ) : options.length === 0 ? (
                <p className="text-sm text-gray-400 text-center py-3">{t('No results')}</p>
              ) : (
                options.map((opt) => (
                  <button
                    key={opt.id}
                    type="button"
                    className={`text-left px-3 py-2 rounded-lg hover:bg-gray-100 text-sm ${opt.id === value ? 'font-semibold text-xibo-blue-600' : 'text-gray-800'}`}
                    onClick={() => {
                      onChange(opt.id, opt.label);
                      setIsOpen(false);
                    }}
                  >
                    {opt.label}
                  </button>
                ))
              )}
            </div>
          </div>
        )}
      </FloatingPortal>

      {helpText && <span className="text-xs text-gray-400">{helpText}</span>}
    </div>
  );
}
