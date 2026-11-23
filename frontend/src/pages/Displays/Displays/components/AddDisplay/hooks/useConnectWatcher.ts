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

import { useEffect, useState } from 'react';

import { fetchConnectStatus } from '@/services/displaysApi';

export type ConnectWatchState = 'idle' | 'waiting' | 'connected' | 'expired';

/** How often to ask whether the coded Player has arrived. */
const POLL_INTERVAL_MS = 3000;

/** Ceiling for the backoff applied when a check itself fails. */
const MAX_BACKOFF_MS = 15000;

export interface ConnectWatchResult {
  state: ConnectWatchState;
  displayId: number | null;
  displayName: string | null;
  /** The Player was already registered before it presented the code, so it wasn't added. */
  existing: boolean;
}

/**
 * Watch for the Player holding a connection code.
 *
 * The code is either one the CMS issued for manual configuration or the activation code the
 * operator typed in; both are recorded against the display that presents them at registration.
 *
 * This asks a single question - "has the Player with *this* code registered?" - rather than
 * watching the display list for anything new. That distinction is the whole point: a list watch
 * cannot tell one operator's Player from another's when two are added at the same moment, whereas
 * a code is matched exactly by the CMS at registration.
 *
 * There is no separate timeout here. The code itself expires server side, and an expired code is
 * reported back as such, so the wait ends when the code does.
 *
 * @param code The issued code, or null to stay idle.
 */
export function useConnectWatcher(code: string | null): ConnectWatchResult {
  const [state, setState] = useState<ConnectWatchState>('idle');
  const [displayId, setDisplayId] = useState<number | null>(null);
  const [displayName, setDisplayName] = useState<string | null>(null);
  const [existing, setExisting] = useState(false);
  useEffect(() => {
    if (!code) {
      setState('idle');
      setDisplayId(null);
      setDisplayName(null);
      setExisting(false);
      return;
    }

    setState('waiting');
    setDisplayId(null);
    setDisplayName(null);
    setExisting(false);

    let cancelled = false;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let delay = POLL_INTERVAL_MS;

    const check = async () => {
      if (cancelled) {
        return;
      }

      try {
        const status = await fetchConnectStatus(code);

        if (cancelled) {
          return;
        }

        if (status.expired) {
          setState('expired');
          return;
        }

        if (status.connected && status.displayId) {
          setDisplayId(status.displayId);
          setDisplayName(status.display);
          setExisting(status.existing === true);
          setState('connected');
          return;
        }

        // The CMS answered, so drop any backoff we had built up.
        delay = POLL_INTERVAL_MS;
      } catch {
        // Transient failure. Keep waiting, but ease off.
        delay = Math.min(delay * 2, MAX_BACKOFF_MS);
      }

      timer = setTimeout(check, delay);
    };

    check();

    // Without this, closing the modal leaves a timer running until the code expires.
    return () => {
      cancelled = true;

      if (timer) {
        clearTimeout(timer);
      }
    };
  }, [code]);

  return { state, displayId, displayName, existing };
}
