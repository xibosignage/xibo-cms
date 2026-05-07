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
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '@/styles/leaflet-overrides.css';
import {
  AlertTriangle,
  Check,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  ChevronUp,
  ChevronsUpDown,
  Cog,
  Map,
  MapPin,
  Minus,
  MonitorPlay,
  Plus,
  RefreshCw,
  Repeat2,
  X,
} from 'lucide-react';
import type { DateTime as DateTimeType } from 'luxon';
import { useEffect, useId, useRef, useState } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { useTranslation } from 'react-i18next';
import { MapContainer, Marker, TileLayer, useMap, useMapEvents } from 'react-leaflet';
import { twMerge } from 'tailwind-merge';

import { agendaQueryKeys, useAgendaData } from '../hooks/useAgendaData';

import Button from '@/components/ui/Button';
import NumberInput from '@/components/ui/forms/NumberInput';
import Modal from '@/components/ui/modals/Modal';
import ScheduleEventModal from '@/components/ui/modals/ScheduleEventModal';
import { StatusCell } from '@/components/ui/table/cells/StatusCell';
import { useUserContext } from '@/context/UserContext';
import { usePreline } from '@/hooks/usePreline';
import type {
  AgendaLayout,
  AgendaScheduleEvent,
  FetchAgendaEventsResponse,
} from '@/services/eventApi';
import type { Event } from '@/types/event';
import { formatDateTime } from '@/utils/date';

interface AgendaModalProps {
  date: DateTimeType;
  displayGroups: { id: number; name: string }[];
  onClose: () => void;
}

interface SelectedRow {
  type: 'layout' | 'displaygroup' | 'campaign';
  layoutId?: number;
  eventId?: number;
  displayGroupId?: number;
  campaignId?: number;
}

const EVENT_TYPE_LABELS: Record<number, string> = {
  1: 'Layouts',
  3: 'Overlay Layouts',
  4: 'Interrupt Layouts',
  5: 'Campaign Layouts',
  7: 'Fullscreen Video / Image',
  8: 'Fullscreen Playlist',
  9: 'Synchronised',
};

const DEFAULT_LAT_FALLBACK = 51.5;
const DEFAULT_LNG_FALLBACK = -0.104;

// Fix Leaflet default marker icons broken by vite asset bundling
delete (L.Icon.Default.prototype as unknown as Record<string, unknown>)._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
});

function isFullLayout(layout: AgendaLayout | { layout: string }): layout is AgendaLayout {
  return 'layoutId' in layout;
}

function formatDuration(seconds: number): string {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function minutesToTime(minutes: number): string {
  const h = Math.floor(minutes / 60)
    .toString()
    .padStart(2, '0');
  const m = (minutes % 60).toString().padStart(2, '0');
  return `${h}:${m}`;
}

function pointInRing(point: [number, number], ring: number[][]): boolean {
  const [px, py] = point;
  let inside = false;
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const [xi, yi] = ring[i] as [number, number];
    const [xj, yj] = ring[j] as [number, number];
    if (yi > py !== yj > py && px < ((xj - xi) * (py - yi)) / (yj - yi) + xi) {
      inside = !inside;
    }
  }
  return inside;
}

function isPointInGeoJSON(
  point: [number, number],
  geoJSON: { type: string; coordinates?: unknown; geometry?: unknown },
): boolean {
  if (geoJSON.type === 'Feature') {
    return isPointInGeoJSON(
      point,
      geoJSON.geometry as { type: string; coordinates?: unknown; geometry?: unknown },
    );
  }
  if (geoJSON.type === 'FeatureCollection') {
    const features = geoJSON.coordinates as { type: string; geometry?: unknown }[];
    return (features ?? []).some((f) => isPointInGeoJSON(point, f));
  }
  if (geoJSON.type === 'Polygon') {
    return pointInRing(point, (geoJSON.coordinates as number[][][])[0] ?? []);
  }
  if (geoJSON.type === 'MultiPolygon') {
    return (geoJSON.coordinates as number[][][][]).some((poly) =>
      pointInRing(point, poly[0] ?? []),
    );
  }
  return true;
}

function filterEventsByGeo(
  events: AgendaScheduleEvent[],
  lat: number,
  lng: number,
): AgendaScheduleEvent[] {
  return events.filter((ev) => {
    if (!ev.geoLocation) {
      return true;
    }
    try {
      return isPointInGeoJSON(
        [lng, lat],
        JSON.parse(ev.geoLocation) as { type: string; coordinates: unknown },
      );
    } catch {
      return true;
    }
  });
}

function getLinkedKeys(
  type: SelectedRow['type'],
  row: SelectedRow,
  events: AgendaScheduleEvent[],
): { layoutKeys: Set<string>; displayGroupIds: Set<number>; campaignIds: Set<number> } {
  const layoutKeys = new Set<string>();
  const displayGroupIds = new Set<number>();
  const campaignIds = new Set<number>();

  for (const ev of events) {
    let matches = false;
    if (type === 'layout' && ev.layoutId === row.layoutId && ev.eventId === row.eventId) {
      matches = true;
    } else if (type === 'displaygroup' && ev.displayGroupId === row.displayGroupId) {
      matches = true;
    } else if (type === 'campaign' && ev.campaignId === row.campaignId) {
      matches = true;
    }
    if (matches) {
      layoutKeys.add(`${ev.layoutId}-${ev.eventId}`);
      displayGroupIds.add(ev.displayGroupId);
      if (ev.campaignId != null) {
        campaignIds.add(ev.campaignId);
      }
    }
  }

  return { layoutKeys, displayGroupIds, campaignIds };
}

function MapZoomControls() {
  const map = useMap();
  const { t } = useTranslation();

  useEffect(() => {
    function makeBtn(
      classes: string,
      icon: React.ReactElement,
      label: string,
      onClick: () => void,
    ): L.Control {
      const ctrl = new L.Control({ position: 'topright' });
      ctrl.onAdd = () => {
        const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
        const btn = L.DomUtil.create('a', classes, container);
        btn.innerHTML = renderToStaticMarkup(icon);
        btn.title = label;
        btn.href = '#';
        btn.setAttribute('role', 'button');
        btn.setAttribute('aria-label', label);
        L.DomEvent.on(btn, 'click', (e) => {
          L.DomEvent.stop(e);
          onClick();
        });
        return container;
      };
      return ctrl;
    }

    const iconProps = { size: 16, strokeWidth: 1.5 };
    const zoomIn = makeBtn('leaflet-control-zoom-in', <Plus {...iconProps} />, t('Zoom in'), () =>
      map.zoomIn(),
    );
    const zoomOut = makeBtn(
      'leaflet-control-zoom-out',
      <Minus {...iconProps} />,
      t('Zoom out'),
      () => map.zoomOut(),
    );

    zoomIn.addTo(map);
    zoomOut.addTo(map);

    return () => {
      zoomIn.remove();
      zoomOut.remove();
    };
  }, [map, t]);

  return null;
}

interface MapClickHandlerProps {
  onChange: (lat: number, lng: number) => void;
}

function MapClickHandler({ onChange }: MapClickHandlerProps) {
  useMapEvents({
    click(e) {
      onChange(e.latlng.lat, e.latlng.lng);
    },
  });
  return null;
}

interface AgendaGeoMapProps {
  lat: string;
  lng: string;
  defaultLat: number;
  defaultLng: number;
  onChange: (lat: string, lng: string) => void;
}

function AgendaGeoMap({ lat, lng, defaultLat, defaultLng, onChange }: AgendaGeoMapProps) {
  const parsedLat = lat !== '' ? Number(lat) : null;
  const parsedLng = lng !== '' ? Number(lng) : null;
  const center: [number, number] = [parsedLat ?? defaultLat, parsedLng ?? defaultLng];

  return (
    <MapContainer
      center={center}
      zoom={7}
      zoomControl={false}
      style={{ height: '320px', width: '100%' }}
      className="rounded-lg border border-gray-200 z-0"
    >
      <TileLayer
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
      />
      <MapZoomControls />
      <MapClickHandler onChange={(clat, clng) => onChange(String(clat), String(clng))} />
      {parsedLat !== null && parsedLng !== null && <Marker position={[parsedLat, parsedLng]} />}
    </MapContainer>
  );
}

interface AgendaFilterBarProps {
  showTimeline: boolean;
  onToggleTimeline: () => void;
  timeMinutes: number;
  onTimeChange: (v: number) => void;
  geoLat: string;
  geoLng: string;
  onGeoLatChange: (v: string) => void;
  onGeoLngChange: (v: string) => void;
  onGetLocation: () => void;
  onClearLocation: () => void;
  showMap: boolean;
  onToggleMap: () => void;
  defaultLat: number;
  defaultLng: number;
}

function AgendaFilterBar({
  showTimeline,
  onToggleTimeline,
  timeMinutes,
  onTimeChange,
  geoLat,
  geoLng,
  onGeoLatChange,
  onGeoLngChange,
  onGetLocation,
  onClearLocation,
  showMap,
  onToggleMap,
  defaultLat,
  defaultLng,
}: AgendaFilterBarProps) {
  const { t } = useTranslation();
  const [localMinutes, setLocalMinutes] = useState(timeMinutes);
  const hasGeo = geoLat !== '' && geoLng !== '';
  const trackRef = useRef<HTMLDivElement>(null);

  const TICKS = [0, 120, 240, 360, 480, 600, 720, 840, 960, 1080, 1200, 1320, 1439];

  function minutesFromClientX(clientX: number): number {
    const rect = trackRef.current?.getBoundingClientRect();
    if (!rect) {
      return localMinutes;
    }
    return Math.round(Math.max(0, Math.min(1, (clientX - rect.left) / rect.width)) * 1439);
  }

  function stepMinutes(delta: number) {
    const next = Math.max(0, Math.min(1439, localMinutes + delta));
    setLocalMinutes(next);
    onTimeChange(next);
  }

  return (
    <div className="flex flex-col gap-3 pb-3">
      <div className="flex flex-row items-center justify-between">
        <Button
          variant="secondary"
          onClick={onToggleTimeline}
          rightIcon={showTimeline ? ChevronUp : ChevronDown}
        >
          {t('Specific point in time')}
        </Button>
        <div className="flex flex-row items-end gap-3 shrink-0">
          <NumberInput
            value={geoLat !== '' ? Number(geoLat) : undefined}
            onChange={(val) => onGeoLatChange(val != null ? String(val) : '')}
            label={t('Latitude')}
            placeholder=" "
            step="any"
            min={-90}
            max={90}
            className="text-xs border border-gray-300 rounded-md px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-xibo-blue-600"
          />
          <NumberInput
            value={geoLng !== '' ? Number(geoLng) : undefined}
            onChange={(val) => onGeoLngChange(val != null ? String(val) : '')}
            label={t('Longitude')}
            placeholder=" "
            step="any"
            min={-180}
            max={180}
            className="text-xs border border-gray-300 rounded-md px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-xibo-blue-600"
          />
          {hasGeo && (
            <Button
              variant="secondary"
              className="shrink-0 w-11.25 h-11.25"
              title={t('Clear location')}
              onClick={onClearLocation}
            >
              <X className="w-5 h-5" />
            </Button>
          )}
          <Button
            variant="secondary"
            className="shrink-0 w-11.25 h-11.25"
            title={t('Get browser location')}
            onClick={onGetLocation}
          >
            <MapPin className="w-5 h-5" />
          </Button>
          <Button
            variant="secondary"
            className="shrink-0 w-11.25 h-11.25"
            title={t('Toggle map')}
            onClick={onToggleMap}
          >
            <Map className="w-5 h-5" />
          </Button>
        </div>
      </div>

      {showTimeline && (
        <div className="relative pt-6 pb-5">
          <div className="flex items-center gap-2">
            <Button variant="tertiary" onClick={() => stepMinutes(-1)}>
              <ChevronLeft className="w-4 h-4" />
            </Button>

            <div
              ref={trackRef}
              className="relative flex-1 h-5 flex items-center cursor-pointer select-none"
              onPointerDown={(e) => {
                e.currentTarget.setPointerCapture(e.pointerId);
                setLocalMinutes(minutesFromClientX(e.clientX));
              }}
              onPointerMove={(e) => {
                if (e.buttons === 0) {
                  return;
                }
                setLocalMinutes(minutesFromClientX(e.clientX));
              }}
              onPointerUp={(e) => {
                onTimeChange(minutesFromClientX(e.clientX));
              }}
            >
              <span
                className="absolute -translate-x-1/2 pointer-events-none whitespace-nowrap z-30"
                style={{ left: `${(localMinutes / 1439) * 100}%`, bottom: 'calc(100% + 6px)' }}
              >
                <span className="relative block text-xs font-semibold bg-gray-800 text-white p-2 rounded">
                  {minutesToTime(localMinutes)}
                  <span className="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-gray-800" />
                </span>
              </span>
              <div className="absolute inset-x-0 top-1/2 -translate-y-1/2 h-1 bg-gray-200 rounded-full" />
              <div
                className="absolute left-0 top-1/2 -translate-y-1/2 h-1 bg-xibo-blue-600 rounded-full"
                style={{ width: `${(localMinutes / 1439) * 100}%` }}
              />
              {TICKS.map((v) => (
                <div
                  key={v}
                  className={twMerge(
                    'absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-2.5 h-2.5 rounded-full z-10 border-white border',
                    v <= localMinutes ? 'bg-xibo-blue-600' : 'bg-gray-300',
                  )}
                  style={{ left: `${(v / 1439) * 100}%` }}
                  onPointerDown={(e) => {
                    e.stopPropagation();
                    setLocalMinutes(v);
                    onTimeChange(v);
                  }}
                />
              ))}
              <div
                className="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-4 h-4 bg-white border-2 border-xibo-blue-600 rounded-full shadow z-20"
                style={{ left: `${(localMinutes / 1439) * 100}%` }}
              />
            </div>

            <Button variant="tertiary" onClick={() => stepMinutes(1)}>
              <ChevronRight className="w-4 h-4" />
            </Button>
          </div>

          <div className="flex justify-between mt-1 px-6">
            {TICKS.map((v) => (
              <span key={v} className="text-xs text-gray-500 font-semibold">
                {minutesToTime(v)}
              </span>
            ))}
          </div>
        </div>
      )}

      {showMap && (
        <AgendaGeoMap
          lat={geoLat}
          lng={geoLng}
          defaultLat={defaultLat}
          defaultLng={defaultLng}
          onChange={(newLat, newLng) => {
            onGeoLatChange(newLat);
            onGeoLngChange(newLng);
          }}
        />
      )}
    </div>
  );
}

const LAYOUT_EDITOR_TYPES = new Set([1, 3, 4]);

interface BreadcrumbPanelProps {
  row: SelectedRow;
  data: FetchAgendaEventsResponse;
  selectedGroupId: number;
  onEdit: (eventId: number) => void;
}

function BreadcrumbPanel({ row, data, selectedGroupId, onEdit }: BreadcrumbPanelProps) {
  const { t } = useTranslation();
  const event = data.events.find(
    (ev) => ev.layoutId === row.layoutId && ev.eventId === row.eventId,
  );
  if (!event) {
    return null;
  }

  const layoutEntry = data.layouts[String(event.layoutId)];
  const layout = layoutEntry && isFullLayout(layoutEntry) ? layoutEntry : null;
  const layoutName = layoutEntry?.layout ?? t('Private Item');
  const campaign = event.campaignId != null ? data.campaigns[String(event.campaignId)] : undefined;
  const showLayoutLink = LAYOUT_EDITOR_TYPES.has(event.eventTypeId) && layout !== null;

  // Chain: selected tab DG → intermediates reversed → directly assigned DG
  const dgChain: string[] = [];
  if (selectedGroupId !== event.displayGroupId) {
    const tabDg = data.displayGroups[String(selectedGroupId)];
    if (tabDg) {
      dgChain.push(tabDg.displayGroup);
    }
  }
  for (const dgId of [...(event.intermediateDisplayGroupIds ?? [])].reverse()) {
    const dg = data.displayGroups[String(dgId)];
    if (dg) {
      dgChain.push(dg.displayGroup);
    }
  }
  const assignedDg = data.displayGroups[String(event.displayGroupId)];
  if (assignedDg) {
    dgChain.push(assignedDg.displayGroup);
  }

  const sep = <ChevronRight className="w-4 h-4 text-gray-400 shrink-0 mx-0.5" />;

  return (
    <div className="rounded-lg px-4 py-3 text-sm text-gray-500 font-semibold flex flex-wrap items-center gap-y-1">
      {showLayoutLink ? (
        <a
          href={`/layout/designer/${layout.layoutId}`}
          className="px-3 py-2 text-xibo-blue-600 cursor-pointer hover:underline"
          target="_blank"
          rel="noreferrer"
        >
          {layoutName}
        </a>
      ) : (
        <span className="px-3 py-2">{layoutName}</span>
      )}
      {campaign && (
        <>
          {sep}
          <span className="px-3 py-2">{campaign.campaign}</span>
        </>
      )}
      {sep}
      <button
        type="button"
        className="px-3 py-2 text-xibo-blue-600 cursor-pointer hover:underline"
        onClick={() => onEdit(event.eventId)}
      >
        {t('Schedule')}
      </button>
      {dgChain.map((name, i) => (
        <span key={`${i}-${name}`} className="flex items-center gap-0.5">
          {sep}
          <span className="px-3 py-2">{name}</span>
        </span>
      ))}
    </div>
  );
}

const PAGINATION_BTN =
  'cursor-pointer w-full flex justify-center p-2 min-w-5 items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none';

function getPaginationItems(pageIndex: number, pageCount: number): (number | '...')[] {
  const current = pageIndex + 1;
  const total = pageCount;
  if (total <= 7) {
    return Array.from({ length: total }, (_, i) => i + 1);
  }
  const range: number[] = [1];
  for (let i = current - 1; i <= current + 1; i++) {
    if (i > 1 && i < total) {
      range.push(i);
    }
  }
  range.push(total);
  const result: (number | '...')[] = [];
  let prev: number | undefined;
  for (const i of range) {
    if (prev !== undefined) {
      if (i - prev === 2) {
        result.push(prev + 1);
      } else if (i - prev !== 1) {
        result.push('...');
      }
    }
    result.push(i);
    prev = i;
  }
  return result;
}

const PAGE_SIZE_OPTIONS = [5, 10, 25, 50];

interface AgendaTablePaginationProps {
  pageIndex: number;
  pageCount: number;
  pageSize: number;
  onPageChange: (page: number) => void;
  onPageSizeChange: (size: number) => void;
}

function AgendaTablePagination({
  pageIndex,
  pageCount,
  pageSize,
  onPageChange,
  onPageSizeChange,
}: AgendaTablePaginationProps) {
  usePreline();
  const { t } = useTranslation();
  const dropdownId = useId();

  if (pageCount <= 1 && pageSize === PAGE_SIZE_OPTIONS[0]) {
    return null;
  }

  return (
    <div className="flex gap-3 items-center p-2 pt-0">
      <div className="flex items-center gap-2">
        <div className="hs-dropdown relative inline-flex [--placement:top-left]">
          <button
            id={dropdownId}
            type="button"
            className={PAGINATION_BTN}
            aria-haspopup="menu"
            aria-expanded="false"
            aria-label="Select page size"
          >
            {t('{{pageSize}} / Page', { pageSize })}
            <ChevronUp className="hs-dropdown-open:rotate-180 size-4 text-gray-500" />
          </button>
          <div
            className="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 w-32 hidden z-60 bg-white shadow-md rounded-lg p-1 space-y-0.5 border border-gray-200 mb-2"
            role="menu"
            aria-orientation="vertical"
            aria-labelledby={dropdownId}
          >
            {PAGE_SIZE_OPTIONS.map((size) => (
              <button
                key={size}
                type="button"
                onClick={() => onPageSizeChange(size)}
                className={twMerge(
                  PAGINATION_BTN,
                  pageSize === size ? 'bg-gray-100 text-gray-800 font-medium' : '',
                )}
              >
                {size}
                {pageSize === size && <Check className="size-3.5 text-blue-600" />}
              </button>
            ))}
          </div>
        </div>
      </div>

      <div className="h-5 border-s border-gray-200" />

      {pageCount > 1 && (
        <nav className="flex items-center gap-x-1">
          <button
            type="button"
            className={PAGINATION_BTN}
            onClick={() => onPageChange(pageIndex - 1)}
            disabled={pageIndex === 0}
            aria-label="Previous"
          >
            <ChevronLeft className="h-5 w-5" />
          </button>
          <div className="flex items-center gap-x-1">
            {getPaginationItems(pageIndex, pageCount).map((page, index) => {
              if (page === '...') {
                return (
                  <span
                    key={`dots-${index}`}
                    className="p-2 min-h-8 min-w-8 flex justify-center items-end text-gray-800"
                  >
                    ...
                  </span>
                );
              }
              const isCurrent = page === pageIndex + 1;
              return (
                <div key={page} className="flex w-9 justify-center">
                  <button
                    type="button"
                    onClick={() => onPageChange((page as number) - 1)}
                    className={twMerge(
                      PAGINATION_BTN,
                      isCurrent ? 'bg-gray-200 focus:bg-gray-300' : '',
                    )}
                  >
                    {page}
                  </button>
                </div>
              );
            })}
          </div>
          <button
            type="button"
            className={PAGINATION_BTN}
            onClick={() => onPageChange(pageIndex + 1)}
            disabled={pageIndex >= pageCount - 1}
            aria-label="Next"
          >
            <ChevronRight className="h-5 w-5" />
          </button>
        </nav>
      )}
    </div>
  );
}

type SortCol =
  | 'layoutId'
  | 'name'
  | 'status'
  | 'fromDt'
  | 'toDt'
  | 'duration'
  | 'displayOrder'
  | 'isPriority';
type SortDir = 'asc' | 'desc';

function sortEventRows(
  events: AgendaScheduleEvent[],
  layouts: Record<string, AgendaLayout | { layout: string }>,
  col: SortCol,
  dir: SortDir,
): AgendaScheduleEvent[] {
  return [...events].sort((a, b) => {
    const la = layouts[String(a.layoutId)];
    const lb = layouts[String(b.layoutId)];
    const layoutA = la && isFullLayout(la) ? la : null;
    const layoutB = lb && isFullLayout(lb) ? lb : null;
    let cmp = 0;

    switch (col) {
      case 'layoutId':
        cmp = a.layoutId - b.layoutId;
        break;
      case 'name':
        cmp = (la?.layout ?? '').localeCompare(lb?.layout ?? '');
        break;
      case 'status':
        cmp = (layoutA?.status ?? -1) - (layoutB?.status ?? -1);
        break;
      case 'fromDt':
        cmp = a.fromDt - b.fromDt;
        break;
      case 'toDt':
        cmp = a.toDt - b.toDt;
        break;
      case 'duration':
        cmp = (layoutA?.duration ?? 0) - (layoutB?.duration ?? 0);
        break;
      case 'displayOrder':
        cmp = (a.displayOrder ?? 0) - (b.displayOrder ?? 0);
        break;
      case 'isPriority':
        cmp = a.isPriority - b.isPriority;
        break;
    }
    return dir === 'asc' ? cmp : -cmp;
  });
}

interface LayoutStatusIconProps {
  status?: number;
}

function LayoutStatusIcon({ status }: LayoutStatusIconProps) {
  const { t } = useTranslation();
  if (status === 1) {
    return (
      <span
        className="inline-flex items-center justify-center w-6.5 h-6.5 bg-teal-100 rounded-lg shrink-0"
        title={t('This Layout is ready to play')}
      >
        <Check className="w-4 h-4 text-teal-800" />
      </span>
    );
  }
  if (status === 0) {
    return (
      <span
        className="inline-flex items-center justify-center w-6.5 h-6.5 bg-gray-50 rounded-lg shrink-0"
        title={t('This Layout is invalid and should not be scheduled')}
      >
        <X className="w-4 h-4 text-gray-500" />
      </span>
    );
  }
  if (status === 2) {
    return (
      <span
        className="inline-flex items-center justify-center w-6.5 h-6.5 bg-yellow-100 rounded-lg shrink-0"
        title={t('There are items on this Layout that can only be assessed by the Display')}
      >
        <AlertTriangle className="w-4 h-4 text-yellow-800" />
      </span>
    );
  }
  if (status === 3) {
    return (
      <span
        className="inline-flex items-center justify-center w-6.5 h-6.5 bg-gray-100 rounded-lg shrink-0"
        title={t('This Layout has not been built yet')}
      >
        <Cog className="w-4 h-4 text-gray-500" />
      </span>
    );
  }
  return (
    <span
      className="inline-flex items-center justify-center w-6.5 h-6.5 bg-yellow-100 rounded-lg shrink-0"
      title={t('This Layout is invalid and should not be scheduled')}
    >
      <AlertTriangle className="w-4 h-4 text-yellow-800" />
    </span>
  );
}

interface SortableHeaderProps {
  col: SortCol;
  label: string;
  sortCol: SortCol;
  sortDir: SortDir;
  onSort: (col: SortCol) => void;
}

function SortableHeader({ col, label, sortCol, sortDir, onSort }: SortableHeaderProps) {
  const isActive = sortCol === col;
  return (
    <th className="relative">
      <div className="px-3 py-2 h-8 flex uppercase bg-gray-50 border-b border-gray-200 text-sm items-center justify-between text-gray-500">
        <div className="text-sm font-semibold text-nowrap overflow-hidden w-full text-left">
          {label}
        </div>
        <div
          className="flex justify-center items-center p-1 size-6 cursor-pointer select-none"
          onClick={() => onSort(col)}
        >
          {isActive ? (
            sortDir === 'asc' ? (
              <ChevronUp className="size-4" />
            ) : (
              <ChevronDown className="size-4" />
            )
          ) : (
            <ChevronsUpDown className="size-4" />
          )}
        </div>
      </div>
    </th>
  );
}

interface EventTypeTableProps {
  typeId: number;
  events: AgendaScheduleEvent[];
  layouts: Record<string, AgendaLayout | { layout: string }>;
  formatDt: (ts: number) => string;
  selectedRow: SelectedRow | null;
  linkedLayoutKeys: Set<string>;
  onRowClick: (row: SelectedRow) => void;
}

function EventTypeTable({
  typeId,
  events,
  layouts,
  formatDt,
  selectedRow,
  linkedLayoutKeys,
  onRowClick,
}: EventTypeTableProps) {
  const { t } = useTranslation();
  const label = EVENT_TYPE_LABELS[typeId];
  const [sortCol, setSortCol] = useState<SortCol>('fromDt');
  const [sortDir, setSortDir] = useState<SortDir>('asc');
  const [pageIndex, setPageIndex] = useState(0);
  const [pageSize, setPageSize] = useState(10);

  const handleSort = (col: SortCol) => {
    if (sortCol === col) {
      setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortCol(col);
      setSortDir('asc');
    }
    setPageIndex(0);
  };

  const handlePageSizeChange = (size: number) => {
    setPageSize(size);
    setPageIndex(0);
  };

  const sortedEvents = sortEventRows(events, layouts, sortCol, sortDir);
  const pageCount = Math.ceil(sortedEvents.length / pageSize);
  const pagedEvents = sortedEvents.slice(pageIndex * pageSize, (pageIndex + 1) * pageSize);

  return (
    <section>
      <div className="flex items-center gap-2 mb-2">
        <h3 className="font-semibold text-gray-700">{t(label ?? '')}</h3>
        <span className="text-xs text-gray-500 bg-gray-100 rounded-full px-2 py-0.5">
          {events.length}
        </span>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full text-xs">
          <thead>
            <tr className="bg-gray-50">
              <SortableHeader
                col="layoutId"
                label={t('ID')}
                sortCol={sortCol}
                sortDir={sortDir}
                onSort={handleSort}
              />
              <SortableHeader
                col="name"
                label={t('Name')}
                sortCol={sortCol}
                sortDir={sortDir}
                onSort={handleSort}
              />
              <SortableHeader
                col="status"
                label={t('Status')}
                sortCol={sortCol}
                sortDir={sortDir}
                onSort={handleSort}
              />
              <SortableHeader
                col="fromDt"
                label={t('From Date')}
                sortCol={sortCol}
                sortDir={sortDir}
                onSort={handleSort}
              />
              <SortableHeader
                col="toDt"
                label={t('To Date')}
                sortCol={sortCol}
                sortDir={sortDir}
                onSort={handleSort}
              />
              <SortableHeader
                col="duration"
                label={t('Duration')}
                sortCol={sortCol}
                sortDir={sortDir}
                onSort={handleSort}
              />
              <SortableHeader
                col="displayOrder"
                label={t('Display Order')}
                sortCol={sortCol}
                sortDir={sortDir}
                onSort={handleSort}
              />
              <SortableHeader
                col="isPriority"
                label={t('Priority')}
                sortCol={sortCol}
                sortDir={sortDir}
                onSort={handleSort}
              />
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {pagedEvents.map((event, i) => {
              const rowKey = `${event.layoutId}-${event.eventId}`;
              const isSelected =
                selectedRow?.type === 'layout' &&
                selectedRow.layoutId === event.layoutId &&
                selectedRow.eventId === event.eventId;
              const isLinked = !isSelected && linkedLayoutKeys.has(rowKey);
              const layoutEntry = layouts[String(event.layoutId)];
              const layout = layoutEntry && isFullLayout(layoutEntry) ? layoutEntry : null;
              const name = layoutEntry?.layout ?? t('Private Item');

              return (
                <tr
                  key={`${event.eventId}-${i}`}
                  className={twMerge(
                    'cursor-pointer transition-colors',
                    isSelected ? 'bg-blue-100' : isLinked ? 'bg-blue-50' : 'hover:bg-gray-50',
                  )}
                  onClick={() =>
                    onRowClick({ type: 'layout', layoutId: event.layoutId, eventId: event.eventId })
                  }
                >
                  <td className="px-3 py-2 text-gray-500">{event.layoutId}</td>
                  <td className="px-3 py-2 font-medium text-gray-800 max-w-48 truncate">{name}</td>
                  <td className="px-3 py-2">
                    <LayoutStatusIcon status={layout?.status} />
                  </td>
                  <td className="px-3 py-2 text-gray-600 whitespace-nowrap">
                    {event.isAlways ? (
                      <span className="flex items-center gap-1">
                        <StatusCell label={t('Always')} variation="outline" type="neutral" />
                      </span>
                    ) : (
                      formatDt(event.fromDt)
                    )}
                  </td>
                  <td className="px-3 py-2 text-gray-600 whitespace-nowrap">
                    {event.isAlways ? (
                      <span className="inline-flex items-center justify-center w-6.5 h-6.5 bg-teal-100 rounded-lg shrink-0">
                        <Repeat2 className="w-4 h-4 text-teal-800" />
                      </span>
                    ) : (
                      formatDt(event.toDt)
                    )}
                  </td>
                  <td className="px-3 py-2 text-gray-600 whitespace-nowrap">
                    {layout?.duration != null ? formatDuration(layout.duration) : '—'}
                  </td>
                  <td className="px-3 py-2 text-gray-600">{event.displayOrder ?? '—'}</td>
                  <td className="px-3 py-2 text-gray-600">{event.isPriority}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      <AgendaTablePagination
        pageIndex={pageIndex}
        pageCount={pageCount}
        pageSize={pageSize}
        onPageChange={setPageIndex}
        onPageSizeChange={handlePageSizeChange}
      />
    </section>
  );
}

interface SidebarListProps {
  title: string;
  items: { id: number; name: string; badge?: React.ReactNode }[];
  isSelected: (id: number) => boolean;
  isLinked: (id: number) => boolean;
  onRowClick: (id: number) => void;
}

function SidebarList({ title, items, isSelected, isLinked, onRowClick }: SidebarListProps) {
  return (
    <section>
      <h3 className="text-xs font-semibold text-gray-500 bg-gray-100 uppercase px-3 py-2">
        {title}
      </h3>
      <ul className="space-y-0.5 mt-2">
        {items.map((item) => (
          <li
            key={item.id}
            className={twMerge(
              'flex items-center gap-2.5 px-5 py-1.5 cursor-pointer text-xs transition-colors',
              isSelected(item.id)
                ? 'bg-blue-100'
                : isLinked(item.id)
                  ? 'bg-blue-50'
                  : 'hover:bg-gray-50',
            )}
            onClick={() => onRowClick(item.id)}
          >
            <span className="text-gray-400 font-mono shrink-0">{item.id}</span>
            <span className="flex-1 text-gray-800 font-semibold truncate min-w-0">{item.name}</span>
            {item.badge}
          </li>
        ))}
      </ul>
    </section>
  );
}

interface DisplayGroupScrollerProps {
  displayGroups: { id: number; name: string }[];
  selectedGroupId: number | null;
  onSelect: (id: number) => void;
}

function DisplayGroupScroller({
  displayGroups,
  selectedGroupId,
  onSelect,
}: DisplayGroupScrollerProps) {
  const scrollRef = useRef<HTMLDivElement>(null);

  const scroll = (dir: 'left' | 'right') => {
    if (scrollRef.current) {
      scrollRef.current.scrollBy({ left: dir === 'left' ? -200 : 200, behavior: 'smooth' });
    }
  };

  return (
    <div className="flex items-center gap-1">
      <Button variant="link" onClick={() => scroll('left')} leftIcon={ChevronLeft}></Button>

      <div
        ref={scrollRef}
        className="flex items-center flex-1 gap-2 overflow-x-auto scrollbar-none scroll-smooth"
      >
        {displayGroups.map((group) => {
          const isActive = selectedGroupId === group.id;
          return (
            <Button
              key={group.id}
              variant="link"
              leftIcon={MonitorPlay}
              onClick={() => onSelect(group.id)}
              className={twMerge(
                'focus:outline-2 -outline-offset-2',
                isActive ? 'text-xibo-blue-500' : ' text-gray-500 hover:text-gray-700',
              )}
            >
              {group.name}
            </Button>
          );
        })}
      </div>

      <Button variant="link" onClick={() => scroll('right')} leftIcon={ChevronRight}></Button>
    </div>
  );
}

export function AgendaModal({ date, displayGroups, onClose }: AgendaModalProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const timezone = user?.settings?.defaultTimezone ?? 'UTC';
  const defaultLat = Number(user?.settings?.DEFAULT_LAT ?? DEFAULT_LAT_FALLBACK);
  const defaultLng = Number(user?.settings?.DEFAULT_LONG ?? DEFAULT_LNG_FALLBACK);

  const queryClient = useQueryClient();

  const [selectedGroupId, setSelectedGroupId] = useState<number | null>(
    displayGroups[0]?.id ?? null,
  );
  const [selectedRow, setSelectedRow] = useState<SelectedRow | null>(null);
  const [showTimeline, setShowTimeline] = useState(false);
  const [timeMinutes, setTimeMinutes] = useState(0);
  const [geoLat, setGeoLat] = useState('');
  const [geoLng, setGeoLng] = useState('');
  const [showMap, setShowMap] = useState(false);
  const [editEventId, setEditEventId] = useState<number | null>(null);

  const queryParams = showTimeline
    ? {
        displayGroupId: selectedGroupId ?? 0,
        singlePointInTime: 1 as const,
        date: date.startOf('day').plus({ minutes: timeMinutes }).toFormat('yyyy-MM-dd HH:mm:ss'),
      }
    : {
        displayGroupId: selectedGroupId ?? 0,
        singlePointInTime: 0 as const,
        startDate: date.startOf('day').toFormat('yyyy-MM-dd HH:mm:ss'),
        endDate: date.endOf('day').toFormat('yyyy-MM-dd HH:mm:ss'),
      };

  const { data, isFetching, isError } = useAgendaData(queryParams, selectedGroupId !== null);

  const formatDt = (ts: number) => formatDateTime(new Date(ts * 1000), timezone);

  const handleRowClick = (row: SelectedRow) => {
    const isSame =
      selectedRow?.type === row.type &&
      selectedRow?.layoutId === row.layoutId &&
      selectedRow?.eventId === row.eventId &&
      selectedRow?.displayGroupId === row.displayGroupId &&
      selectedRow?.campaignId === row.campaignId;
    setSelectedRow(isSame ? null : row);
  };

  const handleGetLocation = () => {
    if (!navigator.geolocation) {
      return;
    }
    navigator.geolocation.getCurrentPosition((pos) => {
      setGeoLat(String(pos.coords.latitude));
      setGeoLng(String(pos.coords.longitude));
    });
  };

  const hasGeo = geoLat !== '' && geoLng !== '';
  const filteredEvents =
    data && hasGeo
      ? filterEventsByGeo(data.events, Number(geoLat), Number(geoLng))
      : (data?.events ?? []);

  const linked =
    selectedRow && filteredEvents.length > 0
      ? getLinkedKeys(selectedRow.type, selectedRow, filteredEvents)
      : {
          layoutKeys: new Set<string>(),
          displayGroupIds: new Set<number>(),
          campaignIds: new Set<number>(),
        };

  const eventsByType: Record<number, AgendaScheduleEvent[]> = {};
  for (const event of filteredEvents) {
    if (EVENT_TYPE_LABELS[event.eventTypeId]) {
      (eventsByType[event.eventTypeId] ??= []).push(event);
    }
  }

  const displayGroupsList = data ? Object.values(data.displayGroups) : [];
  const campaignsList = data ? Object.values(data.campaigns) : [];
  const hasContent = filteredEvents.length > 0;

  return (
    <Modal
      isOpen
      onClose={onClose}
      title={t('Agenda View') + ' — ' + date.toFormat('cccc, d LLL yyyy')}
      size="xl"
      align="top"
    >
      <div className="p-6 flex flex-col gap-3">
        <AgendaFilterBar
          showTimeline={showTimeline}
          onToggleTimeline={() => {
            setShowTimeline((prev) => !prev);
            setSelectedRow(null);
          }}
          timeMinutes={timeMinutes}
          onTimeChange={(v) => {
            setTimeMinutes(v);
            setSelectedRow(null);
          }}
          geoLat={geoLat}
          geoLng={geoLng}
          onGeoLatChange={setGeoLat}
          onGeoLngChange={setGeoLng}
          onGetLocation={handleGetLocation}
          onClearLocation={() => {
            setGeoLat('');
            setGeoLng('');
          }}
          showMap={showMap}
          onToggleMap={() => setShowMap((prev) => !prev)}
          defaultLat={defaultLat}
          defaultLng={defaultLng}
        />

        {displayGroups.length > 1 && (
          <DisplayGroupScroller
            displayGroups={displayGroups}
            selectedGroupId={selectedGroupId}
            onSelect={(id) => {
              setSelectedGroupId(id);
              setSelectedRow(null);
            }}
          />
        )}

        {isFetching && <p className="text-sm text-gray-500">{t('Loading...')}</p>}
        {isError && <p className="text-sm text-red-500">{t('Failed to load agenda events.')}</p>}
        {!isFetching && selectedGroupId === null && (
          <p className="text-sm text-gray-400">{t('No display group selected.')}</p>
        )}
        {!isFetching && data && filteredEvents.length === 0 && (
          <p className="text-sm text-gray-400 text-center py-8">{t('No events for this day.')}</p>
        )}

        {selectedRow?.type === 'layout' && selectedGroupId !== null && data && (
          <BreadcrumbPanel
            row={selectedRow}
            data={data}
            selectedGroupId={selectedGroupId}
            onEdit={setEditEventId}
          />
        )}

        {hasContent && (
          <div className="flex flex-col lg:flex-row gap-8">
            <div className="flex-1 flex flex-col gap-6">
              {Object.entries(eventsByType)
                .sort(([a], [b]) => Number(a) - Number(b))
                .map(([typeIdStr, events]) => (
                  <EventTypeTable
                    key={typeIdStr}
                    typeId={Number(typeIdStr)}
                    events={events}
                    layouts={data?.layouts ?? {}}
                    formatDt={formatDt}
                    selectedRow={selectedRow}
                    linkedLayoutKeys={linked.layoutKeys}
                    onRowClick={handleRowClick}
                  />
                ))}
            </div>

            <div className="flex flex-col gap-8 border-l border-gray-200 min-w-40 max-w-50">
              {displayGroupsList.length > 0 && (
                <SidebarList
                  title={t('Display Groups')}
                  items={displayGroupsList.map((dg) => ({
                    id: dg.displayGroupId,
                    name: dg.displayGroup,
                  }))}
                  isSelected={(id) =>
                    selectedRow?.type === 'displaygroup' && selectedRow.displayGroupId === id
                  }
                  isLinked={(id) => linked.displayGroupIds.has(id)}
                  onRowClick={(id) => handleRowClick({ type: 'displaygroup', displayGroupId: id })}
                />
              )}
              {campaignsList.length > 0 && (
                <SidebarList
                  title={t('Campaigns')}
                  items={campaignsList.map((c) => ({
                    id: c.campaignId,
                    name: c.campaign,
                    badge: c.cyclePlaybackEnabled ? (
                      <RefreshCw className="w-3 h-3 text-gray-400 shrink-0" />
                    ) : undefined,
                  }))}
                  isSelected={(id) =>
                    selectedRow?.type === 'campaign' && selectedRow.campaignId === id
                  }
                  isLinked={(id) => linked.campaignIds.has(id)}
                  onRowClick={(id) => handleRowClick({ type: 'campaign', campaignId: id })}
                />
              )}
            </div>
          </div>
        )}
      </div>

      {editEventId !== null && (
        <ScheduleEventModal
          isOpen
          mode="edit"
          event={{ eventId: editEventId } as Event}
          onClose={() => setEditEventId(null)}
          onSaved={() => {
            setEditEventId(null);
            void queryClient.invalidateQueries({ queryKey: agendaQueryKeys.all });
          }}
        />
      )}
    </Modal>
  );
}
