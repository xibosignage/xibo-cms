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

import { isAxiosError } from 'axios';
import type { TFunction } from 'i18next';
import { useState } from 'react';

import { notify } from '@/components/ui/Notification';
import { purgeAll, requestScreenShot } from '@/services/displaysApi';
import type { Display } from '@/types/display';

interface UseManagePageActionsProps {
  t: TFunction;
}

// Single-display action wrappers for the Manage page, modeled on
// Displays/hooks/useDisplaysActions.ts's runAction pattern but without that
// hook's grid-refresh/modal-close coordination (there's no list/modal to
// coordinate with on this page) — a success/error toast is enough feedback
// here. Only wraps what this page actually wires today: Request screenshot
// and Clear cache. Wake on LAN/Send command/Collect now aren't included yet
// (see PowerRebootMenu and AllCommandsCard for why).
export function useManagePageActions({ t }: UseManagePageActionsProps) {
  const [isActionPending, setIsActionPending] = useState(false);

  const runAction = async (
    fn: () => Promise<unknown>,
    successMessage: string,
    errorMessage: string,
  ) => {
    try {
      setIsActionPending(true);
      await fn();
      notify.success(successMessage);
    } catch (error) {
      console.error(error);
      const message =
        isAxiosError(error) && error.response?.data?.message
          ? error.response.data.message
          : errorMessage;
      notify.error(message);
    } finally {
      setIsActionPending(false);
    }
  };

  const confirmRequestScreenShot = (display: Display) =>
    runAction(
      () => requestScreenShot(display.displayId),
      t('Screenshot requested.'),
      t('Failed to request screenshot.'),
    );

  const confirmPurgeAll = (display: Display) =>
    runAction(() => purgeAll(display.displayId), t('Cache cleared.'), t('Failed to clear cache.'));

  return { isActionPending, confirmRequestScreenShot, confirmPurgeAll };
}
