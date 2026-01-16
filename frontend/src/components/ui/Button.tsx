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

type ButtonProps = React.ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: 'primary' | 'secondary' | 'tertiary' | 'link';
  icon?: LucideIcon;
  removeTextOnMobile?: boolean;
  ariaLabel?: string;
};

function Button({
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
      className={`button ${variant} ${className ?? ''}`}
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

export default Button;
