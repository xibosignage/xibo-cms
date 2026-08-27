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

import { Info, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

// Placeholder for the mockup's "player update available" banner —
// deliberately static, with no version-comparison logic, no data hook, and
// no "Upgrade now"/"Enable auto-upgrade" actions (no aggregate comparing
// clientVersion/clientCode against PlayerVersion/versionMediaId exists yet).
export default function PlayerUpdateBanner() {
  const { t } = useTranslation();
  const [isDismissed, setIsDismissed] = useState(false);

  if (isDismissed) {
    return null;
  }

  return (
    <div className="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
      <Info className="size-4 shrink-0 text-gray-400" aria-hidden="true" />
      <p className="flex-1 text-sm text-gray-500">
        {t('Player update notifications are coming soon.')}
      </p>
      <button
        type="button"
        aria-label={t('Dismiss')}
        onClick={() => setIsDismissed(true)}
        className="flex size-6 shrink-0 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-500 cursor-pointer transition-colors"
      >
        <X className="size-4" aria-hidden="true" />
      </button>
    </div>
  );
}
