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

import { PanelCard } from './PanelCard';

import { useUserContext } from '@/context/UserContext';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useIdle } from '@/hooks/useIdle';
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

// Screenshot still trusted for 30min after going offline; older is misleading.
const OFFLINE_SCREENSHOT_TTL_MS = 30 * 60 * 1000;

/** The format getCurrentScreenShotTime() is cached in, in CMS time. */
const CMS_TIME_FORMAT = 'yyyy-MM-dd HH:mm:ss';

// formatRelative() reads anything under a minute as "Just now" — too coarse for a card
// polling every few seconds, so seconds are counted out instead until this threshold.
const SECONDS_LABEL_FROM = 30;

/** A minute in, formatRelative() changes wording by the minute, so counting seconds can stop. */
const SECONDS_LABEL_UNTIL_MS = 60 * 1000;

/**
 * How long the page goes untouched before it stops asking for screenshots.
 *
 * Asking costs the player a capture and an upload each time, and the CMS an upload to decode and
 * count against the display's bandwidth allowance, so it is only worth doing while someone is
 * actually watching. A hidden tab counts as idle at once, which is what stops a page left open in
 * a background tab from asking all day.
 *
 * Generous on purpose. Watching a screenshot update is passive, so a cursor can sit still for a
 * long time while someone is genuinely reading the card, and pausing on them would be worse than
 * the requests saved. The costly case, a page left open in a background tab, is already caught
 * the moment the tab is hidden, so this timeout only has to cover a tab left in front of an empty
 * chair.
 */
const IDLE_PAUSE_MS = 15 * 60 * 1000;

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

  // Matches getDisplayStatusBucket()'s loggedIn check — only an offline display stops updating.
  const isOnline = display.loggedIn === 1;

  // Kept separate from isOnline: an offline display has nothing running, but the viewer may not
  // have paused it. Live by default, so the control offered is Pause.
  const [isPaused, setIsPaused] = useState(false);

  // Kept apart from isPaused so the two cannot overwrite each other: going idle must not clear a
  // deliberate pause, and coming back to the page must not undo one either.
  const isIdle = useIdle(IDLE_PAUSE_MS, isOnline && !isPaused);
  const isActive = isOnline && !isPaused && !isIdle;

  // Same field the page header reads for "last seen", so the two can't disagree.
  const lastSeenMs = display.lastAccessed ? Number(display.lastAccessed) * 1000 : null;
  const isStaleOffline =
    !isOnline && lastSeenMs !== null && Date.now() - lastSeenMs > OFFLINE_SCREENSHOT_TTL_MS;

  // Stand down (no request, no poll) when not active: an offline display can't answer, so asking
  // would only write the display row and push unread XMR messages; pausing just freezes the image.
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

  // Fetch when the reported capture time isn't one already shown/held — the only signal
  // available, since the image endpoint always answers with something.
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

  // Unused value — just a pulse to force a re-render each second while age is read from Date.now().
  const [tick, setTick] = useState(0);

  useEffect(() => {
    if (takenAtMs === null || Date.now() - takenAtMs >= SECONDS_LABEL_UNTIL_MS) {
      return;
    }

    // Chained, not an interval, so it self-stops once the capture ages past the window.
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

  // The button reports whether anything is running, not just whether it was pressed, so going
  // idle flips it to Resume rather than being announced separately.
  const isStopped = isPaused || isIdle;

  const pauseLabel = !isOnline
    ? t('Display is offline')
    : isStopped
      ? t('Resume live screenshots')
      : t('Pause live screenshots');

  const neverCaptured = !isPending && !isError && !latestTime;

  // No spinner while offline (nothing is coming); prefer showing a recent capture over this,
  // and read isStaleOffline directly so the swap happens on the same render, no flash first.
  const showOffline = !isOnline && (shownUrl === null || isStaleOffline);

  const failed = (isError || loadFailed) && !showOffline;
  const isWaitingForFirst = shownUrl === null && !neverCaptured && !failed && !showOffline;

  return (
    <PanelCard
      title={t('Screenshot')}
      icon={Camera}
      bodyClassName="relative min-h-[190px] bg-gradient-to-br from-gray-50 to-gray-100 items-center justify-center overflow-visible"
      headerActions={
        <button
          type="button"
          // Reads the combined state rather than flipping isPaused blindly: pressing this while
          // idle has to resume, and a blind flip would pause it instead.
          onClick={() => setIsPaused(!isStopped)}
          disabled={!isOnline}
          aria-pressed={isStopped}
          aria-label={pauseLabel}
          title={pauseLabel}
          className="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-200/70 disabled:cursor-not-allowed disabled:text-gray-300 disabled:hover:bg-transparent"
        >
          {isStopped ? (
            <Play className="size-3.5 shrink-0" aria-hidden="true" />
          ) : (
            <Pause className="size-3.5 shrink-0" aria-hidden="true" />
          )}
          {isStopped ? t('Resume') : t('Pause')}
        </button>
      }
    >
      <>
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
      </>
    </PanelCard>
  );
}
