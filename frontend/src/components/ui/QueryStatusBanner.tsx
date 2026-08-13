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

import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import InfoBanner from '@/components/ui/InfoBanner';

interface QueryStatusBannerProps {
  error?: string;
  isPaused?: boolean;
  className?: string;
}

export default function QueryStatusBanner({ error, isPaused, className }: QueryStatusBannerProps) {
  const { t } = useTranslation();

  return (
    <>
      {error && (
        <InfoBanner type="danger" className={twMerge('w-full! mt-2 items-center', className)}>
          {error}
        </InfoBanner>
      )}

      {isPaused && (
        <InfoBanner type="warning" className={twMerge('w-full! mt-2 items-center', className)}>
          {t(
            "You're offline. Showing previously loaded results. This will update automatically once your connection is restored.",
          )}
        </InfoBanner>
      )}
    </>
  );
}
