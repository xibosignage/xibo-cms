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

import { Camera, Loader2, Monitor, Pause, Play, WifiOff } from 'lucide-react';
import { DateTime } from 'luxon';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { useUserContext } from '@/context/UserContext';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import {
  useCurrentScreenshotTime,
  useScreenshotAutoRequest,
} from '@/pages/Displays/Displays/hooks/useDisplayManageData';
import { fetchDisplayScreenshotBlob } from '@/services/displaysApi';
import type { Display } from '@/types/display';

interface ScreenshotCardProps {
  display: Display;
  /** Opens the full-size viewer. Omitted, the image is not clickable. */
  onOpen?: () => void;
}

/** How recent a capture has to be for the card to call itself live. */
const LIVE_WINDOW_MS = 2 * 60 * 1000;

/** Seconds counted down in front of the viewer before a new capture takes over. */
const SWAP_COUNTDOWN_SECONDS = 3;

/**
 * How long a capture outlives its display going quiet.
 *
 * A screenshot from a few minutes ago is still worth showing: it is probably what is on the
 * screen. Half an hour on, it is only what the player last managed to send, and leaving it up
 * reads as current when it is not.
 */
const OFFLINE_SCREENSHOT_TTL_MS = 30 * 60 * 1000;

/** The format getCurrentScreenShotTime() is cached in, in CMS time. */
const CMS_TIME_FORMAT = 'yyyy-MM-dd HH:mm:ss';

/**
 * How long the label stays on the relative wording before it starts counting seconds out.
 *
 * formatRelative() reads anything under a minute as "Just now", which on this card is too coarse:
 * it is asking for a screenshot every few seconds, so "Just now" sitting there for a whole minute
 * makes a live card look stuck.
 */
const SECONDS_LABEL_FROM = 30;

/** A minute in, formatRelative() changes wording by the minute, so counting seconds can stop. */
const SECONDS_LABEL_UNTIL_MS = 60 * 1000;

// The Manage page's "Screenshot" card. While the page is open it asks the display for a new
// screenshot every few seconds and swaps each one in as it lands, so the card tracks the player
// rather than showing whatever was last captured.
//
// A new capture is not shown the moment it is fetched: it is held back for a short countdown
// first, so the change is announced instead of the image silently changing under the viewer.
// The image is already in hand by then, so the countdown costs nothing but the wait.
//
// Replaces the thumbnail half of the old ManageHeroStatus.tsx (the status pill/last-seen half
// moved into the page header instead).
export default function ScreenshotCard({ display, onOpen }: ScreenshotCardProps) {
  const { t } = useTranslation();
  const { formatRelative } = useDateFormatter();
  const { user } = useUserContext();
  const cmsTimeZone = user?.settings?.defaultTimezone ?? 'UTC';

  // Matches the first test getDisplayStatusBucket() makes. A display needing attention is still
  // checking in, so it keeps updating; only one that is not logged in stops.
  const isOnline = display.loggedIn === 1;

  // Live by default: opening the page starts asking, so the control offered is Pause. This
  // holds the viewer's intent, which is why it is kept separate from isOnline rather than folded
  // into one flag: an offline display has nothing running, but the viewer has not paused it.
  const [isPaused, setIsPaused] = useState(false);
  const isActive = isOnline && !isPaused;

  // Measured from the display's own last check-in, the same field the page header reads for its
  // "last seen", so the two cannot disagree about how long it has been gone.
  const lastSeenMs = display.lastAccessed ? Number(display.lastAccessed) * 1000 : null;
  const isStaleOffline =
    !isOnline && lastSeenMs !== null && Date.now() - lastSeenMs > OFFLINE_SCREENSHOT_TTL_MS;

  // Ask for a screenshot on a timer, and watch for one arriving. Both stop when this unmounts,
  // which is the reason the asking lives here rather than on the server, and both stand down for
  // a display that is not checking in: it cannot answer, so asking every few seconds only writes
  // the display row and pushes XMR messages nobody reads. Pausing stops them the same way, so
  // the image on screen simply stays put. The last capture is still fetched once and shown.
  useScreenshotAutoRequest(display.displayId, isActive);
  const {
    data: latestTime,
    isPending,
    isError,
  } = useCurrentScreenshotTime(display.displayId, isActive);

  const [shownUrl, setShownUrl] = useState<string | null>(null);
  const [shownTime, setShownTime] = useState<string | null>(null);
  const [countdown, setCountdown] = useState<number | null>(null);
  const [loadFailed, setLoadFailed] = useState(false);

  // Mirrored into refs so the fetch effect can check what is already on screen without listing
  // it as a dependency, which would restart the fetch every time a capture is promoted.
  const shownUrlRef = useRef<string | null>(null);
  const shownTimeRef = useRef<string | null>(null);
  const pendingRef = useRef<{ url: string; time: string } | null>(null);
  const fetchingForRef = useRef<string | null>(null);

  const promote = useCallback((url: string, time: string) => {
    if (shownUrlRef.current && shownUrlRef.current !== url) {
      window.URL.revokeObjectURL(shownUrlRef.current);
    }

    shownUrlRef.current = url;
    shownTimeRef.current = time;
    setShownUrl(url);
    setShownTime(time);
  }, []);

  // Fetch the image whenever the reported capture time is one we are not already showing or
  // holding. The time is the only signal available: the image endpoint always answers, so
  // without it there would be nothing to compare but the bytes.
  useEffect(() => {
    if (
      !latestTime ||
      // Otherwise clearing the capture below would just make this fetch it again.
      isStaleOffline ||
      latestTime === shownTimeRef.current ||
      latestTime === pendingRef.current?.time ||
      latestTime === fetchingForRef.current
    ) {
      return;
    }

    fetchingForRef.current = latestTime;
    const controller = new AbortController();
    let cancelled = false;

    fetchDisplayScreenshotBlob(display.displayId, controller.signal)
      .then((blob) => {
        if (cancelled) {
          return;
        }

        const url = window.URL.createObjectURL(blob);
        setLoadFailed(false);

        // Nothing on screen yet, so there is no change to announce.
        if (shownTimeRef.current === null) {
          promote(url, latestTime);
          return;
        }

        // A newer capture landed while one was still waiting its turn. The older one is dropped
        // rather than queued: the point is to show the player as it is now.
        if (pendingRef.current) {
          window.URL.revokeObjectURL(pendingRef.current.url);
        }

        pendingRef.current = { url, time: latestTime };
        setCountdown(SWAP_COUNTDOWN_SECONDS);
      })
      .catch(() => {
        if (!cancelled) {
          setLoadFailed(true);
        }
      })
      .finally(() => {
        if (fetchingForRef.current === latestTime) {
          fetchingForRef.current = null;
        }
      });

    return () => {
      cancelled = true;
      controller.abort();

      // Cleared here as well as in finally(). React runs an effect's cleanup before re-running
      // it, but finally() only lands a microtask later, so leaving it to finally() alone lets the
      // re-run see this still set, take the early return above, and leave the card waiting on a
      // fetch that was already aborted. Strict mode's double invoke does exactly that.
      if (fetchingForRef.current === latestTime) {
        fetchingForRef.current = null;
      }
    };
  }, [latestTime, display.displayId, isStaleOffline, promote]);

  // One second per tick, then hand over.
  useEffect(() => {
    if (countdown === null) {
      return;
    }

    if (countdown <= 0) {
      const pending = pendingRef.current;

      if (pending) {
        promote(pending.url, pending.time);
        pendingRef.current = null;
      }

      setCountdown(null);
      return;
    }

    const timer = setTimeout(() => {
      setCountdown((current) => (current === null ? null : current - 1));
    }, 1000);

    return () => clearTimeout(timer);
  }, [countdown, promote]);

  // Let go of a capture whose display has been quiet too long, rather than leaving the last
  // thing it sent on screen looking like what is playing now.
  useEffect(() => {
    if (!isStaleOffline) {
      return;
    }

    if (shownUrlRef.current) {
      window.URL.revokeObjectURL(shownUrlRef.current);
      shownUrlRef.current = null;
    }

    if (pendingRef.current) {
      window.URL.revokeObjectURL(pendingRef.current.url);
      pendingRef.current = null;
    }

    // Reset so the fetch above starts clean if the display comes back.
    shownTimeRef.current = null;
    setShownUrl(null);
    setShownTime(null);
    setCountdown(null);
  }, [isStaleOffline]);

  // Both images are object URLs, so leaving the page has to hand them back.
  useEffect(
    () => () => {
      if (shownUrlRef.current) {
        window.URL.revokeObjectURL(shownUrlRef.current);
      }

      if (pendingRef.current) {
        window.URL.revokeObjectURL(pendingRef.current.url);
      }
    },
    [],
  );

  const takenAt = shownTime
    ? DateTime.fromFormat(shownTime, CMS_TIME_FORMAT, { zone: cmsTimeZone })
    : null;
  const takenAtDate = takenAt?.isValid ? takenAt.toJSDate() : null;
  const takenAtMs = takenAtDate?.getTime() ?? null;

  // A pulse rather than a clock: the age below is read from Date.now() on each render, and this
  // only exists to cause those renders while the seconds are moving. Nothing reads its value, so
  // it cannot go stale when a new capture resets the age.
  const [tick, setTick] = useState(0);

  useEffect(() => {
    if (takenAtMs === null || Date.now() - takenAtMs >= SECONDS_LABEL_UNTIL_MS) {
      return;
    }

    // Chained rather than an interval, so depending on `tick` makes it stop itself once the
    // capture ages past the window instead of ticking for as long as the page is open.
    const timer = setTimeout(() => setTick((current) => current + 1), 1000);

    return () => clearTimeout(timer);
  }, [takenAtMs, tick]);

  const ageSeconds =
    takenAtMs === null ? null : Math.max(0, Math.floor((Date.now() - takenAtMs) / 1000));
  const isLive = takenAtMs !== null && Date.now() - takenAtMs < LIVE_WINDOW_MS;

  // Seconds only cover the gap formatRelative() leaves; either side of it, its wording is better
  // than anything spelled out here.
  let takenLabel = t('No recent capture');

  if (takenAtDate !== null && ageSeconds !== null) {
    takenLabel =
      ageSeconds >= SECONDS_LABEL_FROM && ageSeconds < SECONDS_LABEL_UNTIL_MS / 1000
        ? t('Taken {{seconds}} seconds ago', { seconds: ageSeconds })
        : t('Taken {{time}}', { time: formatRelative(takenAtDate) });
  }

  const pauseLabel = !isOnline
    ? t('Display is offline')
    : isPaused
      ? t('Resume live screenshots')
      : t('Pause live screenshots');

  const neverCaptured = !isPending && !isError && !latestTime;

  // Nothing is on its way while the display is not checking in, so a spinner would claim the card
  // is still working on something. A recent capture is shown in preference to this, since the
  // image and its "Taken ..." label tell the story better than any message; one too old to trust
  // is dropped by the effect above. isStaleOffline is read here as well so the swap happens on the
  // same render, rather than flashing the old image for one frame first.
  const showOffline = !isOnline && (shownUrl === null || isStaleOffline);
  const failed = (isError || loadFailed) && !showOffline;
  const isWaitingForFirst = shownUrl === null && !neverCaptured && !failed && !showOffline;

  return (
    <div className="flex flex-col rounded-lg border border-gray-200 overflow-hidden">
      <div className="bg-gray-50 px-4 py-2.5 border-b border-gray-200 flex items-center justify-between gap-3">
        <h3 className="flex items-center gap-1.5 text-sm font-semibold text-gray-700">
          <Camera className="size-3.5 shrink-0 text-gray-400" aria-hidden="true" />
          {t('Screenshot')}
        </h3>

        <button
          type="button"
          onClick={() => setIsPaused((current) => !current)}
          disabled={!isOnline}
          aria-pressed={isPaused}
          aria-label={pauseLabel}
          title={pauseLabel}
          className="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-200/70 disabled:cursor-not-allowed disabled:text-gray-300 disabled:hover:bg-transparent"
        >
          {isPaused ? (
            <Play className="size-3.5 shrink-0" aria-hidden="true" />
          ) : (
            <Pause className="size-3.5 shrink-0" aria-hidden="true" />
          )}
          {isPaused ? t('Resume') : t('Pause')}
        </button>
      </div>

      <div className="relative flex-1 min-h-[190px] bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center">
        {showOffline ? (
          <div className="flex flex-col items-center gap-2 px-4 text-center">
            <WifiOff className="size-8 text-gray-300" aria-hidden="true" />
            <p className="text-sm font-medium text-gray-500">{t('Display is offline')}</p>
            <p className="text-xs text-gray-400">{t('No screenshot to show')}</p>
          </div>
        ) : isWaitingForFirst ? (
          <Loader2 className="size-6 animate-spin text-gray-300" />
        ) : failed && shownUrl === null ? (
          <p className="px-4 text-center text-sm text-red-600">
            {t('Could not load the latest screenshot.')}
          </p>
        ) : shownUrl && onOpen ? (
          // Wraps the image alone, so the badges stay siblings layered over it rather than
          // becoming part of the control.
          <button
            type="button"
            onClick={onOpen}
            title={t('View full size')}
            className="size-full cursor-zoom-in focus:outline-none focus-visible:ring-2 focus-visible:ring-xibo-blue-500"
          >
            <img
              src={shownUrl}
              alt={t('Screenshot of {{name}}', { name: display.display })}
              className="h-full w-full object-contain"
            />
          </button>
        ) : shownUrl ? (
          <img
            src={shownUrl}
            alt={t('Screenshot of {{name}}', { name: display.display })}
            className="h-full w-full object-contain"
          />
        ) : (
          <Monitor className="size-10 text-gray-300" aria-hidden="true" />
        )}

        {/* Announces the swap that is already loaded and waiting. Sits over the old image, which
            stays put until the count reaches zero. */}
        {countdown !== null && countdown > 0 && (
          <div
            className="absolute inset-x-0 top-1/2 flex -translate-y-1/2 justify-center"
            role="status"
            aria-live="polite"
          >
            <span className="inline-flex items-center gap-2 rounded-full bg-black/70 px-3.5 py-1.5 text-xs font-semibold text-white">
              <span className="size-1.5 shrink-0 animate-pulse rounded-full bg-red-500" />
              {t('Updating screenshot in {{seconds}}', { seconds: countdown })}
            </span>
          </div>
        )}

        {!isWaitingForFirst && !showOffline && (
          <span className="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full bg-black/55 px-2.5 py-1 text-[11px] text-white">
            {isLive && <span className="size-1.5 shrink-0 rounded-full bg-red-500" />}
            {takenLabel}
          </span>
        )}

        {!isWaitingForFirst && !showOffline && (
          <span
            className="absolute bottom-3 left-3 max-w-[80%] truncate rounded-full bg-black/55 px-2.5 py-1 text-[11px] text-white"
            title={display.currentLayout ?? undefined}
          >
            {display.currentLayout || t('No layout playing')}
          </span>
        )}
      </div>
    </div>
  );
}
