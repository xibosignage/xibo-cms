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

import { useQueryClient } from '@tanstack/react-query';

import { autoSubmitPrefQueryKey, fetchAutoSubmitPreference } from '@/services/userApi';

export function useAutoSubmit() {
  const queryClient = useQueryClient();

  const guard = async (formId: string, onAutoSubmit: () => void, onConfirm: () => void) => {
    const queryKey = autoSubmitPrefQueryKey(formId);

    let shouldAutoSubmit = queryClient.getQueryData<boolean>(queryKey);
    if (shouldAutoSubmit === undefined) {
      shouldAutoSubmit = await queryClient.fetchQuery({
        queryKey,
        queryFn: () => fetchAutoSubmitPreference(formId),
        staleTime: Infinity,
      });
    }

    if (shouldAutoSubmit) {
      onAutoSubmit();
    } else {
      onConfirm();
    }
  };

  return { guard };
}
