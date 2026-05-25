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

import { Info } from 'lucide-react';
import { twMerge } from 'tailwind-merge';

import { type UIStatus } from '@/types/uiStatus';

interface InfoBannerProps {
  type?: UIStatus;
  children: React.ReactNode;
  className?: string;
}

const STYLE_VARIANTS: Record<UIStatus, string> = {
  success: 'bg-teal-100 text-teal-800',
  warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
  danger: 'bg-red-50 border-red-200 text-red-800',
  info: 'bg-blue-50 border-blue-200 text-blue-800',
  neutral: 'bg-gray-50 border-gray-200 text-gray-800',
  light: 'bg-gray-50 border-gray-200 text-gray-800',
  dark: 'bg-gray-800 border-gray-700 text-gray-100',
};

export default function InfoBanner({ type = 'info', children, className }: InfoBannerProps) {
  return (
    <div
      className={twMerge(
        'flex items-start gap-3 rounded-lg p-4 text-sm w-fit mx-auto',
        STYLE_VARIANTS[type],
        className,
      )}
      role="status"
    >
      <Info size={18} className="shrink-0 mt-0.5" />
      <div>{children}</div>
    </div>
  );
}
