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

import { type LucideIcon } from 'lucide-react';
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
    'text-white bg-xibo-blue-600 hover:bg-xibo-blue-700 focus:outline-4 focus:outline-blue-500/25 disabled:bg-blue-400 disabled:outline-0',
  secondary:
    'text-xibo-blue-600 border border-xibo-blue-600 bg-white hover:border-xibo-blue-800 hover:text-xibo-blue-800 focus:outline-4 focus:outline-blue-500/25 disabled:border-blue-200 disabled:text-blue-200 disabled:outline-0',
  tertiary:
    'p-2 text-xibo-blue-600 bg-gray-50 hover:bg-gray-100 hover:text-xibo-blue-800 focus:outline-4 focus:outline-blue-500/25 disabled:text-blue-200 disabled:outline-0',
  link: 'text-xibo-blue-600 hover:text-xibo-blue-800 bg-transparent focus:outline-blue-500/25 focus:outline-4',
  iconLink:
    'flex items-center justify-center rounded-lg hover:bg-black/5 cursor-pointer text-gray-500 bg-transparent disabled:text-gray-300 focus:outline-blue-500/25 focus:outline-4',
};

const baseClasses =
  'p-3 inline-flex items-center justify-center gap-x-2 text-sm font-medium truncate rounded-lg disabled:pointer-events-none focus:outline-2 focus:outline-gray-800/25! cursor-pointer';

export default function Button({
  variant = 'primary',
  leftIcon: LeftIcon,
  rightIcon: RightIcon,
  children,
  className,
  removeTextOnMobile = false,
  ariaLabel,
  disabled = false,
  ...props
}: ButtonProps) {
  const showText = Boolean(children);
  return (
    <button
      type="button"
      className={twMerge(baseClasses, buttonVariant[variant], className)}
      aria-label={!showText ? ariaLabel : undefined}
      disabled={disabled}
      {...props}
    >
      {LeftIcon && <LeftIcon className="shrink-0 size-4" aria-hidden="true" />}

      {showText && (
        <span className={removeTextOnMobile ? 'hidden sm:inline' : undefined}>{children}</span>
      )}

      {RightIcon && <RightIcon className="shrink-0 size-4" aria-hidden="true" />}
    </button>
  );
}
