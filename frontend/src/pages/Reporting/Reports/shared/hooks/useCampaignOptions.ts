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

import { useEffect, useState } from 'react';

import type { MultiSelectOption } from '@/components/ui/forms/MultiSelectDropdown';
import { fetchCampaigns } from '@/services/campaignApi';

const MAX_CAMPAIGNS = 100;

export function useCampaignOptions(enabled = true): MultiSelectOption[] {
  const [options, setOptions] = useState<MultiSelectOption[]>([]);

  useEffect(() => {
    if (!enabled) {
      return;
    }
    const controller = new AbortController();
    fetchCampaigns({
      start: 0,
      length: MAX_CAMPAIGNS,
      signal: controller.signal,
    })
      .then(({ rows }) => {
        setOptions(rows.map((c) => ({ value: String(c.campaignId), label: c.campaign })));
      })
      .catch(() => {});
    return () => controller.abort();
  }, [enabled]);

  return options;
}
