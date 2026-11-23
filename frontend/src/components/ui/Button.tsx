/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import { type LucideIcon } from 'lucide-react';
import React from 'react';
import { twMerge } from 'tailwind-merge';

export type ButtonProps = React.ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: 'primary' | 'secondary' | 'tertiary' | 'link' | 'iconLink';
  leftIcon?: LucideIcon;
  rightIcon?: LucideIcon;
  removeTextOnMobile?: boolean;
  ariaLabel?: string;
};

const buttonVariant: Record<NonNullable<ButtonProps['variant']>, string> = {
  primary:
    'text-white bg-xibo-blue-600 min-w-[100px] hover:bg-xibo-blue-700 focus:outline-4 focus:outline-xibo-blue-500/25 disabled:bg-xibo-blue-400 disabled:outline-0',
  secondary:
    'text-xibo-blue-600 border border-xibo-blue-600 bg-white hover:border-xibo-blue-800 hover:text-xibo-blue-800 focus:outline-4 focus:outline-xibo-blue-500/25 disabled:border-xibo-blue-200 disabled:text-xibo-blue-200 disabled:outline-0',
  tertiary:
    'p-2 text-xibo-blue-600 bg-gray-50 hover:bg-gray-100 hover:text-xibo-blue-800 focus:outline-4 focus:outline-xibo-blue-500/25 disabled:text-xibo-blue-200 disabled:outline-0',
  link: 'text-xibo-blue-600 hover:text-xibo-blue-800 bg-transparent focus:outline-xibo-blue-500/25 focus:outline-4',
  iconLink:
    'flex items-center justify-center rounded-lg hover:bg-black/5 cursor-pointer text-gray-500 bg-transparent disabled:text-gray-300 focus:outline-xibo-blue-500/25 focus:outline-4',
};

const baseClasses =
  'p-3 inline-flex items-center justify-center gap-x-2 text-sm font-medium truncate rounded-lg disabled:pointer-events-none focus:outline-2 focus:outline-gray-800/25! cursor-pointer';

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  {
    variant = 'primary',
    leftIcon: LeftIcon,
    rightIcon: RightIcon,
    children,
    className,
    removeTextOnMobile = false,
    ariaLabel,
    disabled = false,
    ...props
  },
  ref,
) {
  const showText = Boolean(children);
  return (
    <button
      type="button"
      className={twMerge(baseClasses, buttonVariant[variant], className)}
      aria-label={!showText ? ariaLabel : undefined}
      disabled={disabled}
      ref={ref}
      {...props}
    >
      {LeftIcon && <LeftIcon className="shrink-0 size-4" aria-hidden="true" />}

      {showText && (
        <span
          className={twMerge(
            'inline-flex items-center gap-x-2',
            removeTextOnMobile && 'hidden sm:inline-flex',
          )}
        >
          {children}
        </span>
      )}

      {RightIcon && <RightIcon className="shrink-0 size-4" aria-hidden="true" />}
    </button>
  );
});

Button.displayName = 'Button';

export default Button;
