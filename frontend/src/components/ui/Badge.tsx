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
import { twMerge } from 'tailwind-merge';

import { type UIStatus } from '@/types/uiStatus';

interface BadgeProps {
  type?: UIStatus;
  variation?: 'soft' | 'outline';
  children: React.ReactNode;
  className?: string;
}

const BASE_CLASSES =
  'inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium border border-gray-800 text-gray-800 dark:border-neutral-200 dark:text-white';

const STYLE_VARIANTS = {
  soft: {
    success: 'text-teal-800 bg-teal-100 border-transparent',
    warning: 'text-yellow-800 bg-yellow-100 border-transparent',
    danger: 'text-red-800 bg-red-100 border-transparent',
    info: 'text-blue-800 bg-blue-100 border-transparent',
    neutral: 'text-gray-800 bg-gray-50 border-transparent',
    light: 'text-gray-800 bg-white border border-gray-200 border-transparent',
    dark: 'text-white bg-gray-900 border-transparent',
  },
  outline: {
    success: 'bg-white border text-teal-500 border-teal-400',
    warning: 'bg-white border text-yellow-500 border-yellow-400',
    danger: 'bg-white border text-red-500 border-red-400',
    info: 'bg-white border text-xibo-blue-600 border-blue-400',
    neutral: 'bg-white border text-gray-800 border-gray-200',
    light: 'bg-white border text-gray-800 border-gray-200',
    dark: 'bg-white border text-gray-800 border-gray-400',
  },
};

export default function Badge({
  children,
  type = 'neutral',
  variation = 'soft',
  className,
}: BadgeProps) {
  const variantClasses = STYLE_VARIANTS[variation][type];

  return <span className={twMerge(BASE_CLASSES, variantClasses, className)}>{children}</span>;
}
