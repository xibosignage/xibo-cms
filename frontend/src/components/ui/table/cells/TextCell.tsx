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
  useHover,
  useDismiss,
  useInteractions,
  FloatingPortal,
  safePolygon,
} from '@floating-ui/react';
import React, { useRef, useState } from 'react';

interface TextProps extends React.HTMLAttributes<HTMLDivElement> {
  children: React.ReactNode;
  subtext?: string;
  weight?: 'normal' | 'bold';
  truncate?: boolean;
  wrap?: boolean;
  className?: string;
}

export function TextCell({
  children,
  subtext,
  className = '',
  weight = 'normal',
  truncate = false,
  wrap = false,
  ...props
}: TextProps) {
  const [isOpen, setIsOpen] = useState(false);
  const textRef = useRef<HTMLSpanElement>(null);

  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: (open) => {
      if (open && textRef.current) {
        if (textRef.current.scrollWidth <= textRef.current.clientWidth) {
          return;
        }
      }
      setIsOpen(open);
    },
    placement: 'bottom-start',
    whileElementsMounted: autoUpdate,
    middleware: [offset(4), flip(), shift()],
  });

  const hover = useHover(context, {
    delay: { open: 300, close: 0 },
    handleClose: safePolygon(),
  });
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([hover, dismiss]);

  const showTooltip = truncate && typeof children === 'string';

  return (
    <div
      className={`flex items-center w-full min-w-0 gap-2 ${className}`}
      ref={showTooltip ? refs.setReference : undefined}
      {...(showTooltip ? getReferenceProps() : {})}
      {...props}
    >
      <span
        ref={textRef}
        className={`text-gray-800 text-sm ${truncate ? 'truncate' : ''} ${wrap ? 'min-w-0 break-words whitespace-normal' : ''} ${weight === 'bold' ? 'font-semibold' : ''}`}
      >
        {children}
      </span>
      {subtext && <span className="text-gray-500 text-sm">{subtext}</span>}
      {showTooltip && (
        <FloatingPortal>
          {isOpen && (
            <div
              ref={refs.setFloating}
              style={floatingStyles}
              {...getFloatingProps()}
              className="z-9999 max-w-80 p-2 bg-white shadow-xl rounded-lg border border-gray-100 dark:bg-neutral-800 dark:border-neutral-700"
            >
              <p className="text-sm text-gray-800 whitespace-normal wrap-break-word">{children}</p>
            </div>
          )}
        </FloatingPortal>
      )}
    </div>
  );
}
