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

import { useEffect, useRef, useState } from 'react';

import { fetchDisplaysNewerThan } from '@/services/displaysApi';
import type { Display } from '@/types/display';

export type DetectState = 'idle' | 'waiting' | 'found' | 'ambiguous' | 'timedOut';

/** How often to look for the Player. */
const POLL_INTERVAL_MS = 2000;

/** Give up rather than poll forever. The Player normally arrives within seconds. */
const MAX_WAIT_MS = 5 * 60 * 1000;

/** Ceiling for the backoff applied when a poll request itself fails. */
const MAX_BACKOFF_MS = 15000;

/**
 * Watch for the display a Player creates when it registers.
 *
 * Submitting an activation code does not create a display - the Player does that moments later,
 * when it has picked up the CMS details. There is nothing in the response to identify it, so we
 * note the highest displayId we could see beforehand and treat anything above that watermark as
 * new.
 *
 * Usually exactly one display appears and we adopt it. If more than one does, another operator
 * added a display at the same moment (or an unrelated Player registered), and the caller asks
 * which one is theirs rather than guessing.
 *
 * @param highestSeenId Watermark captured before the code was submitted, or null to stay idle.
 */
export function useNewDisplayDetector(highestSeenId: number | null) {
  const [state, setState] = useState<DetectState>('idle');
  const [candidates, setCandidates] = useState<Display[]>([]);
  const startedAt = useRef(0);

  useEffect(() => {
    if (highestSeenId === null) {
      setState('idle');
      setCandidates([]);
      return;
    }

    setState('waiting');
    setCandidates([]);
    startedAt.current = Date.now();

    let cancelled = false;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let delay = POLL_INTERVAL_MS;

    const poll = async () => {
      if (cancelled) {
        return;
      }

      if (Date.now() - startedAt.current > MAX_WAIT_MS) {
        setState('timedOut');
        return;
      }

      try {
        const found = await fetchDisplaysNewerThan(highestSeenId);

        if (cancelled) {
          return;
        }

        if (found.length === 1) {
          setCandidates(found);
          setState('found');
          return;
        }

        if (found.length > 1) {
          setCandidates(found);
          setState('ambiguous');
          return;
        }

        // Nothing yet, and the CMS answered, so reset any backoff we had built up.
        delay = POLL_INTERVAL_MS;
      } catch {
        // Transient failure. Keep waiting, but ease off.
        delay = Math.min(delay * 2, MAX_BACKOFF_MS);
      }

      timer = setTimeout(poll, delay);
    };

    poll();

    // Without this, closing the modal leaves a timer polling for five minutes.
    return () => {
      cancelled = true;

      if (timer) {
        clearTimeout(timer);
      }
    };
  }, [highestSeenId]);

  return { state, candidates };
}
