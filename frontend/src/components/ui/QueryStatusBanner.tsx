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
