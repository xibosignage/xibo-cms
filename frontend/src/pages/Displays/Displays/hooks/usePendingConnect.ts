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

import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';

import { notify } from '@/components/ui/Notification';
import { fetchConnectStatus, fetchDisplays } from '@/services/displaysApi';
import type { Display } from '@/types/display';

/**
 * Where a pending connection code is remembered across a page load.
 *
 * A refresh while waiting for the Player would otherwise strand the code: it stays live on the CMS
 * until it expires, but the form has forgotten it and would issue another. The Player would then be
 * carrying a code nobody is listening for.
 */
const PENDING_CODE_KEY = 'xibo.addDisplay.pendingCode';

/** Which route a pending code belongs to. The two are not interchangeable. */
export interface PendingCode {
  code: string;
  mode: 'code' | 'manual';
}

export function readPendingCode(): PendingCode | null {
  try {
    const raw = window.localStorage.getItem(PENDING_CODE_KEY);

    if (!raw) {
      return null;
    }

    const parsed = JSON.parse(raw) as Partial<PendingCode>;

    // Anything unrecognised (an older format, hand-edited storage) is not worth resuming.
    if (!parsed?.code || (parsed.mode !== 'code' && parsed.mode !== 'manual')) {
      return null;
    }

    return { code: parsed.code, mode: parsed.mode };
  } catch {
    // Storage can be unavailable (private browsing, blocked cookies). Not being able to resume is
    // a lesser problem than not being able to add a display at all.
    return null;
  }
}

export function writePendingCode(pending: PendingCode | null): void {
  try {
    if (pending === null) {
      window.localStorage.removeItem(PENDING_CODE_KEY);
    } else {
      window.localStorage.setItem(PENDING_CODE_KEY, JSON.stringify(pending));
    }
  } catch {
    // As above - remembering is a convenience, never a requirement.
  }
}

/**
 * Checked far less often than the Add Display form checks, because nobody is watching a spinner
 * here - this only has to catch up within a reasonable time of the Player arriving.
 */
const BACKGROUND_POLL_MS = 20000;

/**
 * Tell the operator when a Player they were waiting on finally connects, after they have closed
 * the Add Display form.
 *
 * The CMS removes the "waiting for a Player" entry from the notifications bell as soon as the
 * Player registers, so without this the setup would complete silently: the notice would simply
 * disappear. This turns that into something the operator actually sees.
 *
 * Only runs while the Add Display form is closed - an open (or minimized) form is already watching
 * the same code and reports the outcome itself, and two watchers would announce it twice.
 *
 * @param enabled false while the Add Display form is mounted
 * @param onManage opens the finished display
 */
export function usePendingConnectToast(
  enabled: boolean,
  onManage?: (display: Display) => void,
): void {
  const { t } = useTranslation();

  // Keeps the latest handler without restarting the poll every render.
  const onManageRef = useRef(onManage);
  onManageRef.current = onManage;

  useEffect(() => {
    if (!enabled || !readPendingCode()) {
      return;
    }

    let cancelled = false;
    let timer: ReturnType<typeof setTimeout> | undefined;

    const check = async () => {
      if (cancelled) {
        return;
      }

      const pending = readPendingCode();

      // Cleared elsewhere - the form was reopened and took the code back.
      if (!pending) {
        return;
      }

      try {
        const status = await fetchConnectStatus(pending.code);

        if (cancelled) {
          return;
        }

        if (status.expired) {
          // The bell notification stays as the record of an abandoned setup, so say nothing here.
          writePendingCode(null);
          return;
        }

        if (status.connected && status.displayId) {
          writePendingCode(null);

          const { rows } = await fetchDisplays({
            start: 0,
            length: 1,
            displayId: status.displayId,
          });
          const display = rows[0];

          notify.success(
            t('{{display}} has connected.', { display: status.display ?? t('Your display') }),
            display && onManageRef.current
              ? {
                  action: {
                    label: t('Manage'),
                    onClick: () => onManageRef.current?.(display),
                  },
                }
              : undefined,
          );

          return;
        }
      } catch {
        // Transient failure. Try again on the next tick rather than giving up on the code.
      }

      timer = setTimeout(check, BACKGROUND_POLL_MS);
    };

    timer = setTimeout(check, BACKGROUND_POLL_MS);

    return () => {
      cancelled = true;

      if (timer) {
        clearTimeout(timer);
      }
    };
  }, [enabled]);
}
