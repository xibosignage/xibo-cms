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
  size,
  useClick,
  useDismiss,
  useInteractions,
  FloatingPortal,
} from '@floating-ui/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

export type SelectOption = {
  label: string;
  value: string;
  disabled?: boolean;
};

interface SelectDropdownProps {
  label: string;
  value?: string;
  placeholder?: string;
  options: SelectOption[];
  searchable?: boolean;
  searchPlaceholder?: string;
  onSelect: (value: string) => void;
  helper?: string;
  addLeftLabel?: boolean;
  leftLabelContent?: ReactNode;
  optionLabel?: string;
  addOptionAvatar?: boolean;
  className?: string;
  error?: string;
}

export default function SelectDropdown({
  label,
  value,
  placeholder = 'Select',
  options,
  onSelect,
  helper,
  addLeftLabel,
  leftLabelContent,
  optionLabel,
  className,
  addOptionAvatar,
  error,
}: SelectDropdownProps) {
  const { t } = useTranslation();

  const [isOpen, setIsOpen] = useState(false);

  const selectedLabel = options.find((o) => o.value === value)?.label ?? '';

  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: setIsOpen,
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

  return (
    <div className={twMerge('relative overflow-visible', className)}>
      <label className="text-sm font-semibold text-gray-500">{t(label)}</label>

      <div
        ref={refs.setReference}
        {...getReferenceProps()}
        className="w-full border bg-white border-gray-200 rounded-lg flex items-center cursor-pointer h-11.25"
      >
        {addLeftLabel && leftLabelContent && (
          <div className="py-2 px-3 border-r text-sm border-gray-200 text-gray-500">
            {leftLabelContent}
          </div>
        )}
        <span className="py-2 px-3 flex-1 text-sm capitalize">
          {selectedLabel || t(placeholder)}
        </span>
        <span
          className={twMerge(
            'p-3 text-gray-500 transition-transform duration-300 ease-in-out',
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
            {optionLabel && (
              <span className="bg-gray-100 p-2 text-sm font-semibold text-gray-500 uppercase w-full flex">
                {t(optionLabel)}
              </span>
            )}
            <div className="flex flex-col p-2 text-sm overflow-y-auto max-h-75">
              {options.map((option) => (
                <button
                  key={option.value}
                  type="button"
                  disabled={option.disabled}
                  className={twMerge(
                    'text-left p-2 rounded-lg font-medium flex gap-2 items-center',
                    option.disabled
                      ? 'text-gray-300 cursor-not-allowed'
                      : 'hover:bg-gray-100 cursor-pointer',
                  )}
                  onClick={() => {
                    if (option.disabled) {
                      return;
                    }

                    onSelect(option.value);
                    setIsOpen(false);
                  }}
                >
                  {addOptionAvatar && (
                    <div className="bg-xibo-blue-100 h-6.5 w-6.5 text-[12px] center rounded-full text-xibo-blue-800 font-semibold flex items-center justify-center">
                      {option.label.slice(0, 1)}
                    </div>
                  )}
                  {t(option.label)}
                </button>
              ))}
            </div>
          </div>
        )}
      </FloatingPortal>

      {error ? (
        <p className="text-xs text-red-600 ml-2 mt-1">{error}</p>
      ) : (
        helper && (
          <span className="text-xs text-gray-400 leading-snug flex mt-1 whitespace-pre-line">
            {helper}
          </span>
        )
      )}
    </div>
  );
}
