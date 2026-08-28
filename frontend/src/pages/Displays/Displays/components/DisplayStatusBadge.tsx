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

import { twMerge } from 'tailwind-merge';

import { BUCKET_STATUS, type BucketColorClasses } from '../DisplayStatusConfig';

import Badge from '@/components/ui/Badge';
import type { DisplayOverviewBucket } from '@/types/displayOverview';

interface DisplayStatusBadgeProps {
  bucket: DisplayOverviewBucket;
  colors: BucketColorClasses;
  label: string;
  className?: string;
}

// The coloured-dot + label status pill, shared by DisplayCard and the Manage
// page's header so the markup can't drift between the two.
export default function DisplayStatusBadge({
  bucket,
  colors,
  label,
  className,
}: DisplayStatusBadgeProps) {
  return (
    <Badge type={BUCKET_STATUS[bucket]} className={className}>
      <span aria-hidden="true" className={twMerge('size-1.5 shrink-0 rounded-full', colors.dot)} />
      <span className="min-w-0 truncate">{label}</span>
    </Badge>
  );
}
