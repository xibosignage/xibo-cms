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

import { isAxiosError } from 'axios';
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
  /** A manual Player connected and the operator was told, so the toast isn't shown again. */
  announced?: boolean;
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

    return parsed.announced
      ? { code: parsed.code, mode: parsed.mode, announced: true }
      : { code: parsed.code, mode: parsed.mode };
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
 * A Manually Configure Player that connects here is not finished: its code carries no settings,
 * so the display is still as it registered, in the default folder. The code is kept, and the toast
 * offers to reopen the form, which picks the code back up with the Player connected and Save ready.
 *
 * @param enabled false while the Add Display form is mounted
 * @param onManage opens the finished display
 * @param onFinishSetup reopens the Add Display form
 */
export function usePendingConnectToast(
  enabled: boolean,
  onManage?: (display: Display) => void,
  onFinishSetup?: () => void,
): void {
  const { t } = useTranslation();

  // Keeps the latest handlers without restarting the poll every render.
  const onManageRef = useRef(onManage);
  onManageRef.current = onManage;
  const onFinishSetupRef = useRef(onFinishSetup);
  onFinishSetupRef.current = onFinishSetup;

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
          // The settings still have to be saved, so the code stays for the form to resume. A
          // Player that was already registered gets no settings, so it is finished either way.
          if (pending.mode === 'manual' && !status.existing) {
            // Told once, not on every visit to the page while the code lasts.
            if (pending.announced) {
              return;
            }

            writePendingCode({ ...pending, announced: true });
            notify.success(
              status.display
                ? t('{{display}} has connected. Finish setting it up.', { display: status.display })
                : t('Your display has connected. Finish setting it up.'),
              onFinishSetupRef.current
                ? {
                    action: {
                      label: t('Finish setup'),
                      onClick: () => onFinishSetupRef.current?.(),
                    },
                  }
                : undefined,
            );

            return;
          }

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
      } catch (err: unknown) {
        // A 403 or 404 will not change by asking again (e.g. this account can't add displays), so
        // forget the code. Anything else, including an expired session (401) or rate limiting
        // (429), is treated as transient and tried again on the next tick.
        const status = isAxiosError(err) ? err.response?.status : undefined;

        if (status === 403 || status === 404) {
          writePendingCode(null);
          return;
        }
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
