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

import type { TFunction } from 'i18next';
import { AlertTriangle, OctagonAlert, Wifi, WifiOff, type LucideIcon } from 'lucide-react';

import type { FetchDisplaysRequest } from '@/services/displaysApi';
import type { Display } from '@/types/display';
import type { DisplayOverviewBucket } from '@/types/displayOverview';
import type { UIStatus } from '@/types/uiStatus';

export const CARD_PAGE_SIZE = 12;

// Shared between the card grid's own pagination bar and the table view's
// DataTable — both drive the same `pagination` state from Overview.tsx, so
// their page-size choices need to stay identical.
export const PAGE_SIZE_OPTIONS = [12, 24, 50];

export const BUCKET_ICON: Record<DisplayOverviewBucket, LucideIcon> = {
  online: Wifi,
  needsAttention: AlertTriangle,
  offline: WifiOff,
  faults: OctagonAlert,
};

// Maps each bucket onto the app's existing Badge/UIStatus semantic colours —
// the same success/warning/neutral/danger (teal/yellow/gray/red) tokens the
// Displays grid already uses for mediaInventoryStatus and commercialLicence
// (see DisplaysConfig.tsx's getInventoryStatusType/getCommercialLicenceStatus)
// — rather than a one-off palette invented for this page.
export const BUCKET_STATUS: Record<DisplayOverviewBucket, UIStatus> = {
  online: 'success',
  needsAttention: 'warning',
  offline: 'neutral',
  faults: 'danger',
};

// Icon/dot/chip colour classes matching BUCKET_STATUS above — Badge only
// gives us fill+text on a pill, so anywhere else a bucket needs colour (KPI
// tile icon, card status dot, card/row accent stripe, icon chip background)
// pulls from here instead. Single source of truth so none of these can drift
// from BUCKET_STATUS or from each other. The KPI tile and filter chip
// outlines are still deliberately NOT bucket-coloured — they share one
// uniform xibo-blue-600 outline/fill instead (see KpiRow.tsx / StatusChipRow.tsx).
export interface BucketColorClasses {
  /** Solid background utility — table row accent, and the KPI tile's icon chip fill (paired with a white icon). */
  dot: string;
  /** Border-color utility for a thin accent stripe (card top border, table row left border). */
  accentBorder: string;
  /** Very light background utility for a full-width tinted band (Manage modal hero). */
  tintBg: string;
  /** Border-color utility to pair with tintBg. */
  tintBorder: string;
}

export const BUCKET_COLORS: Record<DisplayOverviewBucket, BucketColorClasses> = {
  online: {
    dot: 'bg-teal-500',
    accentBorder: 'border-teal-500',
    tintBg: 'bg-teal-50',
    tintBorder: 'border-teal-100',
  },
  needsAttention: {
    dot: 'bg-yellow-500',
    accentBorder: 'border-yellow-500',
    tintBg: 'bg-yellow-50',
    tintBorder: 'border-yellow-100',
  },
  offline: {
    dot: 'bg-gray-400',
    accentBorder: 'border-gray-400',
    // One shade darker than the other buckets' tintBg (which are all X-50) —
    // Badge's neutral "soft" variant is also bg-gray-50 (see Badge.tsx), so
    // pairing it with a bg-gray-50 hero band made the offline pill in
    // ManageHeroStatus.tsx blend invisibly into its own background. Every
    // other bucket's Badge colour (teal/yellow/red-100) already reads
    // against its X-50 band, so only offline needed the bump.
    tintBg: 'bg-gray-100',
    tintBorder: 'border-gray-200',
  },
  faults: {
    dot: 'bg-red-500',
    accentBorder: 'border-red-500',
    tintBg: 'bg-red-50',
    tintBorder: 'border-red-100',
  },
};

export function getBucketLabel(t: TFunction, bucket: DisplayOverviewBucket): string {
  switch (bucket) {
    case 'online':
      return t('Online');
    case 'needsAttention':
      return t('Needs Attention');
    case 'offline':
      return t('Offline');
    case 'faults':
      return t('Faults');
    default:
      return '';
  }
}

// Maps the active KPI bucket to the list endpoint's filter params (row 7 of
// the backlog: "don't reimplement the bucket logic client-side, just pass the
// right filter param through to the existing list query"). The four buckets
// are mutually exclusive server-side (see DisplayFactory::getSummary()), so
// "online" has to explicitly rule out needsAttention/faults membership too —
// otherwise a display that's logged in but has e.g. a licence issue would
// show up under both Online and Needs Attention.
export function getBucketFilterParams(
  bucket: DisplayOverviewBucket | null,
): Partial<FetchDisplaysRequest> {
  switch (bucket) {
    case 'online':
      return { loggedIn: 1, needsAttention: 0, faults: 0 };
    case 'offline':
      return { loggedIn: 0 };
    case 'needsAttention':
      return { needsAttention: 1 };
    case 'faults':
      return { faults: 1 };
    default:
      return {};
  }
}

// Client-side approximation of the backend's bucket precedence, used only to
// colour the status swatch on a card — the authoritative bucket membership
// (and the KPI counts themselves) always comes from the server, which this
// page never re-derives for filtering purposes.
//
// A display lands in exactly one of the four buckets, in this precedence
// order: Offline first (not logged in, regardless of anything else), then
// Needs Attention, then Faults, else Online — matching
// DisplayFactory::getSummary()'s SQL exactly.
export function getDisplayStatusBucket(display: Display): DisplayOverviewBucket {
  if (display.loggedIn !== 1) {
    return 'offline';
  }

  const isNotFullySynced = display.mediaInventoryStatus !== 1;
  const isUnauthorised = display.licensed !== 1;
  // commercialLicence: 1 = Licensed fully, 3 = Not applicable — both are fine.
  // null (unset, e.g. a display that hasn't completed its licence-reporting
  // round-trip yet) is treated as a licence issue, matching the backend's
  // needsAttentionSql() (`commercialLicence IS NULL OR commercialLicence NOT
  // IN (1, 3)`) — keeping this in sync with that predicate is what avoids a
  // client/server bucket disagreement (a display shown as Online on its card
  // while the KPI count/backend filter already classes it as Needs Attention).
  const hasLicenceIssue =
    display.commercialLicence === null ||
    (display.commercialLicence !== 1 && display.commercialLicence !== 3);

  if (isNotFullySynced || isUnauthorised || hasLicenceIssue) {
    return 'needsAttention';
  }

  if (display.countFaults > 0) {
    return 'faults';
  }

  return 'online';
}

// Which specific condition put a display in the Needs Attention bucket — this
// is what the card's status badge actually shows for that bucket (in place of
// the generic "Needs Attention" label — see DisplayCard.tsx). Same precedence
// order as the bucket check above.
export function getNeedsAttentionReason(t: TFunction, display: Display): string | null {
  if (display.licensed !== 1) {
    return t('Unauthorised');
  }
  if (
    display.commercialLicence === null ||
    (display.commercialLicence !== 1 && display.commercialLicence !== 3)
  ) {
    return t('Unlicensed');
  }
  if (display.mediaInventoryStatus !== 1) {
    return t('Out of sync');
  }
  return null;
}

export interface DisplayStatusInfo {
  bucket: DisplayOverviewBucket;
  colors: BucketColorClasses;
  /** The specific Needs Attention reason where there is one, else the generic bucket label. */
  badgeLabel: string;
  lastSeenLabel: string;
}

// Single derivation of "how does this display's status render" — bucket,
// colour, badge label and last-seen text — shared by DisplayCard,
// ManageHeroStatus, and DisplayTableView's status/last-accessed columns so
// the same display can't show different wording depending on which view
// mode is showing it. Takes `t`/`formatRelative` as plain arguments (rather
// than being a hook itself) so it can also be called from DisplayTableView's
// column-definition cell renderers, which aren't React components.
export function getDisplayStatusInfo(
  display: Display,
  t: TFunction,
  formatRelative: (value: Date) => string,
): DisplayStatusInfo {
  const bucket = getDisplayStatusBucket(display);
  const attentionReason = bucket === 'needsAttention' ? getNeedsAttentionReason(t, display) : null;

  return {
    bucket,
    colors: BUCKET_COLORS[bucket],
    badgeLabel: attentionReason ?? getBucketLabel(t, bucket),
    lastSeenLabel: display.lastAccessed
      ? formatRelative(new Date(Number(display.lastAccessed) * 1000))
      : t('Never'),
  };
}
