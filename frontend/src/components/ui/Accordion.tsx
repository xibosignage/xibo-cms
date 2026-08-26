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

import { ChevronRight, type LucideIcon } from 'lucide-react';
import { useId, useState } from 'react';
import { twMerge } from 'tailwind-merge';

export interface AccordionProps {
  /** Header label. */
  title: string;
  /** Optional topic icon shown before the title (e.g. a diagnostics/troubleshooting icon). */
  icon?: LucideIcon;
  /** Content revealed when expanded. */
  children: React.ReactNode;
  /** Whether the accordion starts expanded. Defaults to collapsed. Ignored if `open` is provided. */
  defaultOpen?: boolean;
  /**
   * Makes the accordion controlled — pass the current open state and listen for
   * changes via `onOpenChange`. Useful when a parent wants to know (or drive)
   * expanded state, e.g. to lazily fetch content only once expanded.
   */
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
  /** Optional trailing content in the header (e.g. a count badge). */
  badge?: React.ReactNode;
  className?: string;
  headerClassName?: string;
  contentClassName?: string;
}

export default function Accordion({
  title,
  icon: Icon,
  children,
  defaultOpen = false,
  open: openProp,
  onOpenChange,
  badge,
  className,
  headerClassName,
  contentClassName,
}: AccordionProps) {
  const [uncontrolledOpen, setUncontrolledOpen] = useState(defaultOpen);
  const isControlled = openProp !== undefined;
  const isOpen = isControlled ? openProp : uncontrolledOpen;
  const contentId = useId();

  const toggle = () => {
    const next = !isOpen;
    if (!isControlled) {
      setUncontrolledOpen(next);
    }
    onOpenChange?.(next);
  };

  return (
    <div className={twMerge('rounded-lg border border-gray-200 overflow-hidden', className)}>
      <button
        type="button"
        aria-expanded={isOpen}
        aria-controls={contentId}
        onClick={toggle}
        className={twMerge(
          'w-full flex items-center justify-between gap-3 bg-gray-50 px-4 py-2.5 text-left cursor-pointer hover:bg-gray-100 transition-colors',
          headerClassName,
        )}
      >
        <span className="flex items-center gap-2 text-sm font-semibold text-gray-700">
          <ChevronRight
            className={twMerge('w-4 h-4 text-gray-400 transition-transform', isOpen && 'rotate-90')}
            aria-hidden="true"
          />
          {Icon && <Icon className="size-3.5 shrink-0 text-gray-400" aria-hidden="true" />}
          {title}
        </span>
        {badge}
      </button>
      {isOpen && (
        <div id={contentId} className={contentClassName}>
          {children}
        </div>
      )}
    </div>
  );
}
