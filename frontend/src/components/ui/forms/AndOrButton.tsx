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
  autoUpdate,
  flip,
  FloatingPortal,
  offset,
  shift,
  useClick,
  useDismiss,
  useFloating,
  useInteractions,
} from '@floating-ui/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

interface AndOrButtonProps {
  value: 'AND' | 'OR';
  onChange: (value: 'AND' | 'OR') => void;
}

export default function AndOrButton({ value, onChange }: AndOrButtonProps) {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);
  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: setIsOpen,
    placement: 'bottom-start',
    whileElementsMounted: autoUpdate,
    middleware: [offset(4), flip(), shift()],
  });
  const click = useClick(context);
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  return (
    <>
      <button
        type="button"
        ref={refs.setReference}
        {...getReferenceProps()}
        title={value === 'AND' ? t('Match ALL entered terms') : t('Match ANY entered terms')}
        className="flex items-center gap-2 p-3 h-full text-sm font-semibold text-gray-500 cursor-pointer bg-transparent border-none"
      >
        {value}
        <ChevronDown
          size={12}
          className={twMerge('transition-transform duration-200', isOpen && 'rotate-180')}
        />
      </button>

      <FloatingPortal>
        {isOpen && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className="z-9999 bg-white shadow-md rounded-lg border border-gray-200 flex flex-col overflow-clip"
          >
            {(['AND', 'OR'] as const).map((opt) => (
              <button
                key={opt}
                type="button"
                onClick={() => {
                  onChange(opt);
                  setIsOpen(false);
                }}
                className={twMerge(
                  'p-3 text-sm font-semibold text-left hover:bg-gray-100 cursor-pointer',
                  value === opt && 'text-xibo-blue-600',
                )}
              >
                {opt}
              </button>
            ))}
          </div>
        )}
      </FloatingPortal>
    </>
  );
}
