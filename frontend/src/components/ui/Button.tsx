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
.*/

import { type LucideIcon } from 'lucide-react';
import { twMerge } from 'tailwind-merge';

type ButtonProps = React.ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: 'primary' | 'secondary' | 'tertiary' | 'link';
  icon?: LucideIcon;
  removeTextOnMobile?: boolean;
  ariaLabel?: string;
};

const buttonVariant: Record<NonNullable<ButtonProps['variant']>, string> = {
  primary: 'text-white bg-xibo-blue-600 hover:bg-xibo-blue-700 focus:ring-4',
  secondary:
    'text-xibo-blue-600 border border-xibo-blue-600 bg-white hover:border-xibo-blue-800 hover:text-xibo-blue-800',
  tertiary: 'text-xibo-blue-600 bg-gray-50 hover:bg-gray-100 hover:text-xibo-blue-800',
  link: 'text-xibo-blue-600 underline hover:text-xibo-blue-800 bg-transparent',
};

const baseClasses =
  'p-2 rounded-lg gap-2 text-sm text-center tracking-[150%] flex items-center h-[45px] box-border cursor-pointer font-semibold focus:ring-blue-500/25';

export default function Button({
  variant = 'primary',
  icon: Icon,
  children,
  className,
  removeTextOnMobile = false,
  ariaLabel,
  ...props
}: ButtonProps) {
  const showText = Boolean(children);
  return (
    <button
      type="button"
      className={twMerge(baseClasses, buttonVariant[variant], className)}
      aria-label={!showText ? ariaLabel : undefined}
      {...props}
    >
      {Icon && <Icon size={21} />}
      {showText && (
        <span className={removeTextOnMobile ? 'hidden sm:inline' : undefined}>{children}</span>
      )}
    </button>
  );
}
