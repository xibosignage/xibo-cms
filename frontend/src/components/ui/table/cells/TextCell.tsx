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

import React from 'react';

interface TextProps extends React.HTMLAttributes<HTMLDivElement> {
  children: React.ReactNode;
  subtext?: string;
  weight?: 'normal' | 'bold';
  className?: string;
}

export function TextCell({
  children,
  subtext,
  className = '',
  weight = 'normal',
  ...props
}: TextProps) {
  return (
    <div className={`inline-flex gap-2 ${className}`} {...props}>
      <span className={`text-gray-800 text-sm ${weight === 'bold' ? 'font-semibold' : ''}`}>
        {children}
      </span>
      {subtext && <span className="text-gray-500 text-sm">{subtext}</span>}
    </div>
  );
}
