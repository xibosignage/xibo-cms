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

/** Anything that counts as the viewer still being there. */
const ACTIVITY_EVENTS = [
  'pointerdown',
  'pointermove',
  'keydown',
  'wheel',
  'scroll',
  'touchstart',
] as const;

/**
 * Whether the viewer has stopped interacting with the page.
 *
 * For work that is only worth doing while someone is watching. Returns false while there is
 * activity, true once `timeoutMs` has passed without any, and false again on the next
 * interaction, so a caller can stand down and pick straight back up.
 *
 * A hidden tab counts as idle immediately rather than waiting out the timeout: switching away is
 * itself the signal, and a background tab's timers are throttled unpredictably, so waiting is
 * both slower and less reliable than acting on it.
 *
 * @param timeoutMs How long without interaction counts as idle.
 * @param enabled Pass false to never report idle.
 */
export function useIdle(timeoutMs: number, enabled: boolean = true): boolean {
  const [isIdle, setIsIdle] = useState(false);

  useEffect(() => {
    if (!enabled) {
      setIsIdle(false);
      return;
    }

    let timer: ReturnType<typeof setTimeout>;

    const markActive = () => {
      // React bails out when the value is unchanged, so the common case of this firing on every
      // pointermove does not re-render.
      setIsIdle(false);
      clearTimeout(timer);
      timer = setTimeout(() => setIsIdle(true), timeoutMs);
    };

    const onVisibilityChange = () => {
      if (document.visibilityState === 'hidden') {
        clearTimeout(timer);
        setIsIdle(true);
      } else {
        markActive();
      }
    };

    ACTIVITY_EVENTS.forEach((event) =>
      window.addEventListener(event, markActive, { passive: true }),
    );
    document.addEventListener('visibilitychange', onVisibilityChange);

    // Starts the clock, so a page that is opened and then left alone still goes idle.
    markActive();

    return () => {
      clearTimeout(timer);
      ACTIVITY_EVENTS.forEach((event) => window.removeEventListener(event, markActive));
      document.removeEventListener('visibilitychange', onVisibilityChange);
    };
  }, [timeoutMs, enabled]);

  return isIdle;
}
