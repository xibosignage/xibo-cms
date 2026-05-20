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
  useFloating,
  useHover,
  useInteractions,
} from '@floating-ui/react';
import { useState } from 'react';

interface DescriptionCellProps {
  value: string | undefined | null;
  isHtml?: boolean;
}

export function DescriptionCell({ value, isHtml = false }: DescriptionCellProps) {
  const [isOpen, setIsOpen] = useState(false);

  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: setIsOpen,
    placement: 'bottom-start',
    whileElementsMounted: autoUpdate,
    middleware: [offset(4), flip(), shift({ padding: 8 })],
  });

  const hover = useHover(context, { delay: { open: 300, close: 100 } });
  const { getReferenceProps, getFloatingProps } = useInteractions([hover]);

  if (!value) {
    return null;
  }

  return (
    <>
      <div
        ref={refs.setReference}
        {...getReferenceProps()}
        className="truncate text-gray-800 text-xs w-full cursor-default"
      >
        {/* isHtml is only true for showDescriptionId=3 (Widget List), which is CMS-generated HTML */}
        {isHtml ? <span dangerouslySetInnerHTML={{ __html: value }} /> : value}
      </div>

      <FloatingPortal>
        {isOpen && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className="z-9999 max-w-90 p-3 bg-white shadow-xl rounded-lg border border-gray-100 dark:bg-neutral-800 dark:border-neutral-700 text-xs text-gray-800 dark:text-gray-200 wrap-break-word"
          >
            {isHtml ? <span dangerouslySetInnerHTML={{ __html: value }} /> : value}
          </div>
        )}
      </FloatingPortal>
    </>
  );
}
