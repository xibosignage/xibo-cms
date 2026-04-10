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
  useClick,
  useDismiss,
  useFloating,
  useInteractions,
} from '@floating-ui/react';
import { ChevronDown, Search } from 'lucide-react';
import { useLayoutEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import Checkbox from './Checkbox';

export interface MultiSelectOption {
  value: string;
  label: string;
}

interface MultiSelectDropdownProps {
  label: string;
  value: string[];
  options: MultiSelectOption[];
  onChange: (values: string[]) => void;
  placeholder?: string;
  searchPlaceholder?: string;
  helpText?: string;
  selectAllOption?: boolean;
  selectAllText?: string;
  error?: string;
  className?: string;
}

export default function MultiSelectDropdown({
  label,
  value,
  options,
  onChange,
  placeholder,
  searchPlaceholder,
  helpText,
  selectAllOption = false,
  selectAllText,
  error,
  className,
}: MultiSelectDropdownProps) {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');

  const handleOpenChange = (open: boolean) => {
    setIsOpen(open);
    if (!open) {
      setSearchTerm('');
    }
  };

  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: handleOpenChange,
    placement: 'bottom-start',
    whileElementsMounted: autoUpdate,
    middleware: [
      offset(4),
      flip(),
      shift(),
      size({
        apply({ rects, elements }) {
          Object.assign(elements.floating.style, {
            width: `${rects.reference.width}px`,
          });
        },
      }),
    ],
  });

  const click = useClick(context);
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  const toggle = (optValue: string) => {
    if (value.includes(optValue)) {
      onChange(value.filter((v) => v !== optValue));
    } else {
      onChange([...value, optValue]);
    }
  };

  const visibleOptions = searchTerm
    ? options.filter((o) => o.label.toLowerCase().includes(searchTerm.toLowerCase()))
    : options;

  const labelSpanRef = useRef<HTMLSpanElement>(null);
  const [selectedLabel, setSelectedLabel] = useState(
    value.length === 0
      ? (placeholder ?? t('None selected'))
      : t('{{n}} selected', { n: value.length }),
  );

  useLayoutEffect(() => {
    const el = labelSpanRef.current;
    if (!el) {
      return;
    }

    const compute = () => {
      if (value.length === 0) {
        setSelectedLabel(placeholder ?? t('None selected'));
        return;
      }

      const labels = value.map((v) => options.find((o) => o.value === v)?.label ?? v);
      const style = getComputedStyle(el);
      const availableWidth =
        el.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);

      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      if (!ctx) {
        setSelectedLabel(labels.join(', '));
        return;
      }
      ctx.font = `${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;

      for (let fitted = labels.length; fitted > 0; fitted--) {
        const remaining = labels.length - fitted;
        const text =
          remaining > 0
            ? `${labels.slice(0, fitted).join(', ')} +${remaining} more`
            : labels.join(', ');

        if (ctx.measureText(text).width <= availableWidth) {
          setSelectedLabel(text);
          return;
        }
      }

      setSelectedLabel(t('{{n}} selected', { n: value.length }));
    };

    compute();

    const ro = new ResizeObserver(compute);
    ro.observe(el);
    return () => ro.disconnect();
  }, [value, options, placeholder, t]);

  return (
    <div className={twMerge('flex flex-col gap-1 relative w-full', className)}>
      <label className="text-sm font-semibold text-gray-500 leading-5">{t(label)}</label>

      <div
        ref={refs.setReference}
        {...getReferenceProps()}
        className="w-full border bg-white border-gray-200 rounded-lg flex items-center cursor-pointer h-11.25 hover:border-gray-400 focus-within:border-xibo-blue-600 focus-within:ring-1 focus-within:ring-xibo-blue-600/25 focus:outline-none transition-colors"
      >
        <span
          ref={labelSpanRef}
          className={twMerge(
            'py-2 px-3 flex-1 text-sm truncate',
            value.length === 0 ? 'text-gray-400' : 'text-gray-800',
          )}
        >
          {selectedLabel}
        </span>
        <span
          className={twMerge(
            'p-3 text-gray-500 transition-transform duration-300 ease-in-out shrink-0',
            isOpen ? 'rotate-180' : 'rotate-0',
          )}
        >
          <ChevronDown size={14} />
        </span>
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
                placeholder={searchPlaceholder ?? t('Search…')}
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                onClick={(e) => e.stopPropagation()}
              />
            </div>
            <div className="flex flex-col p-2 text-sm overflow-y-auto max-h-75">
              {(() => {
                const allChecked = options.length > 0 && value.length === options.length;
                const someChecked = value.length > 0 && value.length < options.length;

                return (
                  <>
                    {selectAllOption && (
                      <Checkbox
                        id="multi-select-all"
                        label={selectAllText ? selectAllText : t('All')}
                        checked={allChecked}
                        indeterminate={someChecked}
                        className="px-2 py-2 gap-4"
                        classNameLabel="m-0 font-semibold text-gray-800"
                        onChange={(e) => {
                          if (e.target.checked) {
                            onChange(options.map((o) => o.value));
                          } else {
                            onChange([]);
                          }
                        }}
                      />
                    )}
                    {visibleOptions.length === 0 && (
                      <p className="text-sm text-gray-400 text-center py-2">{t('No results')}</p>
                    )}
                    {visibleOptions.map((option) => {
                      const isSelected = value.includes(option.value);
                      return (
                        <Checkbox
                          key={option.value}
                          id={`multi-select-${option.value}`}
                          label={option.label}
                          checked={isSelected}
                          className="px-2 py-2 gap-4 rounded-lg hover:bg-gray-100"
                          classNameLabel="m-0 font-semibold text-gray-800"
                          onChange={() => toggle(option.value)}
                        />
                      );
                    })}
                  </>
                );
              })()}
            </div>
          </div>
        )}
      </FloatingPortal>

      {error ? (
        <p className="text-xs text-red-600 ml-2 mt-1">{error}</p>
      ) : (
        helpText && <span className="text-xs text-gray-400">{helpText}</span>
      )}
    </div>
  );
}
