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

import { Clock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

// A Layout which has been built, but displays are waiting for Regular Maintenance to notify them
// title: the CMS status description, which says when the next Regular Maintenance run is
export default function LayoutPendingNotifyIcon({
  className,
  title,
}: {
  className?: string;
  title?: string;
}) {
  const { t } = useTranslation();

  return (
    <span
      title={
        title ||
        t(
          'This Layout has been built and Displays will be updated at the next Regular Maintenance run',
        )
      }
      className={twMerge(
        'inline-flex items-center justify-center rounded-lg bg-yellow-100 text-yellow-800',
        className,
      )}
    >
      <Clock className="size-4" />
    </span>
  );
}
