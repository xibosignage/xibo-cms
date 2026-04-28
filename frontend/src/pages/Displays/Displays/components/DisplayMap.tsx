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

import { useQuery, keepPreviousData } from '@tanstack/react-query';
import L from 'leaflet';
import 'leaflet-easyprint';
import 'leaflet-fullscreen';
import 'leaflet-fullscreen/dist/leaflet.fullscreen.css';
import { Maximize2, Minus, Plus, Printer } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import 'leaflet/dist/leaflet.css';
import { useTranslation } from 'react-i18next';
import { MapContainer, TileLayer, Marker, Popup, useMap, useMapEvents } from 'react-leaflet';
import MarkerClusterGroup from 'react-leaflet-cluster';
import 'react-leaflet-cluster/dist/assets/MarkerCluster.Default.css';
import '@/styles/leaflet-overrides.css';
import 'react-leaflet-cluster/dist/assets/MarkerCluster.css';

import type { DisplayFilterInput } from '../DisplaysConfig';

import { useUserContext } from '@/context/UserContext';
import { fetchDisplaysMap } from '@/services/displaysApi';

interface DisplayMapProps {
  filters: DisplayFilterInput;
  folderId?: number | null;
}

const DEFAULT_LAT_FALLBACK = 51.5;
const DEFAULT_LNG_FALLBACK = -0.104;
const DEFAULT_ZOOM = 7;

const ICON_SIZE: [number, number] = [24, 40];
const ICON_ANCHOR: [number, number] = [12, 40];
const POPUP_ANCHOR: [number, number] = [0, -40];

const BOUNDS_DEBOUNCE_MS = 500;

function getIconUrl(mediaInventoryStatus: number, loggedIn: number): string {
  const colour =
    mediaInventoryStatus === 1 ? 'green' : mediaInventoryStatus === 3 ? 'yellow' : 'red';
  const state = loggedIn === 1 ? 'check' : 'cross';
  return `/dist/assets/map-marker-${colour}-${state}.png`;
}

function makeIcon(mediaInventoryStatus: number, loggedIn: number): L.Icon {
  const url = getIconUrl(mediaInventoryStatus, loggedIn);
  return L.icon({
    iconUrl: url,
    iconRetinaUrl: url.replace('.png', '-2x.png'),
    iconSize: ICON_SIZE,
    iconAnchor: ICON_ANCHOR,
    popupAnchor: POPUP_ANCHOR,
  });
}

function createPieChartHtml(
  counts: [number, number, number],
  colors: [string, string, string],
): string {
  const total = counts.reduce((a, b) => a + b, 0);
  const baseStyle =
    'width:30px;height:30px;border-radius:50%;display:flex;align-items:center;' +
    'justify-content:center;font-weight:bold;color:black;box-shadow:5px 5px 10px rgba(0,0,0,0.3);';

  if (total === 0) {
    return `<div style="${baseStyle}background:rgba(200,200,200,0.9);">0</div>`;
  }

  let gradient = 'conic-gradient(';
  let sum = 0;
  counts.forEach((count, i) => {
    const start = (sum / total) * 100;
    sum += count;
    const end = (sum / total) * 100;
    gradient += `${colors[i]} ${start}%,${colors[i]} ${end}%,`;
  });
  gradient += 'white 0%)';

  return `<div style="${baseStyle}background:${gradient};">${total}</div>`;
}

function createClusterIcon(cluster: L.MarkerCluster): L.DivIcon {
  let upToDate = 0;
  let outOfDate = 0;
  let downloading = 0;

  for (const marker of cluster.getAllChildMarkers()) {
    const m = marker as L.Marker & { mediaInventoryStatus?: number };
    switch (m.mediaInventoryStatus) {
      case 1:
        upToDate++;
        break;
      case 3:
        outOfDate++;
        break;
      default:
        downloading++;
        break;
    }
  }

  const html = createPieChartHtml(
    [upToDate, outOfDate, downloading],
    ['rgba(181,226,140,0.9)', 'rgba(243,194,18,0.9)', 'rgba(219,70,79,0.9)'],
  );

  return L.divIcon({ html, className: '', iconSize: L.point(40, 40) });
}

function MapControls({ tileLayer }: { tileLayer: L.TileLayer | null }) {
  const map = useMap();

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

    const zoomIn = makeBtn('leaflet-control-zoom-in', <Plus {...iconProps} />, 'Zoom in', () =>
      map.zoomIn(),
    );
    const zoomOut = makeBtn('leaflet-control-zoom-out', <Minus {...iconProps} />, 'Zoom out', () =>
      map.zoomOut(),
    );
    const fullscreen = makeBtn(
      'leaflet-control-fullscreen-button',
      <Maximize2 {...iconProps} />,
      'Toggle fullscreen',
      () => map.toggleFullscreen(),
    );

    zoomIn.addTo(map);
    zoomOut.addTo(map);
    fullscreen.addTo(map);

    return () => {
      zoomIn.remove();
      zoomOut.remove();
      fullscreen.remove();
    };
  }, [map]);

  useEffect(() => {
    if (!tileLayer) {
      return;
    }

    const print = L.easyPrint({
      tileLayer,
      sizeModes: ['Current', 'A4Landscape', 'A4Portrait'],
      filename: 'Displays on Map',
      hideControlContainer: true,
      position: 'topright',
    }).addTo(map);

    const printBtn = print.getContainer()?.querySelector('a');
    if (printBtn) {
      printBtn.innerHTML = renderToStaticMarkup(<Printer size={16} strokeWidth={1.5} />);
    }

    return () => {
      print.remove();
    };
  }, [map, tileLayer]);

  return null;
}

function BoundsTracker({ onBoundsChange }: { onBoundsChange: (bounds: string) => void }) {
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const map = useMapEvents({
    moveend: () => {
      if (timerRef.current) {
        clearTimeout(timerRef.current);
      }
      timerRef.current = setTimeout(() => {
        onBoundsChange(map.getBounds().toBBoxString());
      }, BOUNDS_DEBOUNCE_MS);
    },
  });

  useEffect(() => {
    onBoundsChange(map.getBounds().toBBoxString());
    return () => {
      if (timerRef.current) {
        clearTimeout(timerRef.current);
      }
    };
  }, [map, onBoundsChange]);

  return null;
}

function ClusterEventHandler({ clusterGroup }: { clusterGroup: L.MarkerClusterGroup | null }) {
  const map = useMap();
  const { t } = useTranslation();

  useEffect(() => {
    if (!clusterGroup) {
      return;
    }

    const handleMouseOver = (e: L.LeafletEvent) => {
      const event = e as L.LeafletEvent & { latlng: L.LatLng; layer: L.MarkerCluster };
      const markers = event.layer.getAllChildMarkers() as (L.Marker & {
        mediaInventoryStatus?: number;
      })[];

      let upToDate = 0;
      let outOfDate = 0;
      let downloading = 0;
      for (const m of markers) {
        switch (m.mediaInventoryStatus) {
          case 1:
            upToDate++;
            break;
          case 3:
            outOfDate++;
            break;
          default:
            downloading++;
            break;
        }
      }

      const total = upToDate + outOfDate + downloading;
      let content = `<div><strong>${t('Total displays')}: ${total}</strong>`;
      if (upToDate > 0) {
        content += `<div>${t('Up to date')}: ${upToDate}</div>`;
      }
      if (outOfDate > 0) {
        content += `<div>${t('Out of date')}: ${outOfDate}</div>`;
      }
      if (downloading > 0) {
        content += `<div>${t('Downloading')}: ${downloading}</div>`;
      }
      content += '</div>';

      L.popup().setLatLng(event.latlng).setContent(content).openOn(map);
    };

    const handleClose = () => map.closePopup();

    clusterGroup.on('clustermouseover', handleMouseOver);
    clusterGroup.on('clustermouseout', handleClose);
    clusterGroup.on('clusterclick', handleClose);

    return () => {
      clusterGroup.off('clustermouseover', handleMouseOver);
      clusterGroup.off('clustermouseout', handleClose);
      clusterGroup.off('clusterclick', handleClose);
    };
  }, [clusterGroup, map, t]);

  return null;
}

interface MarkerWithStatusProps {
  position: L.LatLngExpression;
  icon: L.Icon;
  mediaInventoryStatus: number;
  children: React.ReactNode;
}

function MarkerWithStatus({
  position,
  icon,
  mediaInventoryStatus,
  children,
}: MarkerWithStatusProps) {
  const markerRef = useRef<L.Marker | null>(null);

  useEffect(() => {
    if (markerRef.current) {
      (markerRef.current as L.Marker & { mediaInventoryStatus: number }).mediaInventoryStatus =
        mediaInventoryStatus;
    }
  }, [mediaInventoryStatus]);

  return (
    <Marker ref={markerRef} position={position} icon={icon}>
      {children}
    </Marker>
  );
}

type LegendHeader = { type: 'header'; label: string };
type LegendMarker = { colour: string; state: string; label: string };
type LegendItem = LegendHeader | LegendMarker;

const LEGEND_ITEMS: LegendItem[] = [
  { type: 'header', label: 'Logged in' },
  { colour: 'green', state: 'check', label: 'Up-to-date' },
  { colour: 'yellow', state: 'check', label: 'Out-of-date' },
  { colour: 'red', state: 'check', label: 'Downloading/Unknown' },
  { type: 'header', label: 'Logged out' },
  { colour: 'green', state: 'cross', label: 'Up-to-date' },
  { colour: 'yellow', state: 'cross', label: 'Out-of-date' },
  { colour: 'red', state: 'cross', label: 'Downloading/Unknown' },
];

export default function DisplayMap({ filters, folderId }: DisplayMapProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();

  const defaultLat = Number(user?.settings?.DEFAULT_LAT ?? DEFAULT_LAT_FALLBACK);
  const defaultLng = Number(user?.settings?.DEFAULT_LONG ?? DEFAULT_LNG_FALLBACK);

  const timezone = (user?.settings?.defaultTimezone as string | undefined) ?? 'UTC';

  const [tileLayer, setTileLayer] = useState<L.TileLayer | null>(null);
  const handleTileLayerRef = (instance: L.TileLayer | null) => {
    setTileLayer(instance);
  };

  const [clusterGroup, setClusterGroup] = useState<L.MarkerClusterGroup | null>(null);
  const handleClusterRef = (instance: L.MarkerClusterGroup | null) => {
    setClusterGroup(instance);
  };

  const [bounds, setBounds] = useState<string | null>(null);
  const handleBoundsChange = (b: string) => setBounds(b);

  const { data, isFetching, isError } = useQuery({
    queryKey: ['displayMap', filters, folderId, bounds],
    queryFn: () =>
      fetchDisplaysMap({
        ...(filters as unknown as Record<string, unknown>),
        ...(folderId ? { folderId } : {}),
        bounds: bounds ?? undefined,
      }),
    staleTime: 30_000,
    enabled: bounds !== null,
    placeholderData: keepPreviousData,
  });

  const features = data?.features ?? [];
  const center: [number, number] = [defaultLat, defaultLng];

  return (
    <div className="relative flex-1 min-h-0 rounded-lg overflow-hidden border border-gray-200">
      {isFetching && (
        <div className="absolute top-2 left-1/2 -translate-x-1/2 z-1000 bg-white text-xs px-3 py-1 rounded-full shadow border border-gray-200">
          {t('Loading map data...')}
        </div>
      )}

      {isError && (
        <div className="absolute top-2 left-1/2 -translate-x-1/2 z-1000 bg-red-50 text-red-700 text-xs px-3 py-1 rounded-full shadow border border-red-200">
          {t('Failed to load map data')}
        </div>
      )}

      <div className="absolute top-4 left-4 z-1000 bg-white/80 rounded-lg shadow border border-gray-200 p-2 text-xs font-semibold flex flex-col gap-2">
        {LEGEND_ITEMS.map((item) => {
          if ('type' in item) {
            return (
              <p
                key={item.label}
                className="text-gray-500 font-semibold uppercase tracking-wide pt-1 not-first:mt-1 first:pt-0"
              >
                {t(item.label)}
              </p>
            );
          }
          return (
            <div key={`${item.colour}-${item.state}`} className="flex items-center gap-2">
              <img
                src={`/dist/assets/map-marker-${item.colour}-${item.state}.png`}
                alt={item.label}
                style={{ width: 12, height: 20 }}
              />
              <span className="text-gray-600">{t(item.label)}</span>
            </div>
          );
        })}
      </div>

      <MapContainer
        center={center}
        zoom={DEFAULT_ZOOM}
        zoomControl={false}
        style={{ height: '100%', width: '100%' }}
      >
        <TileLayer
          ref={handleTileLayerRef}
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        />

        <MarkerClusterGroup
          ref={handleClusterRef}
          chunkedLoading
          iconCreateFunction={createClusterIcon}
          maxClusterRadius={(zoom: number) => (zoom > 9 ? 20 : 80)}
        >
          {features.map((feature) => {
            const [lng, lat] = feature.geometry.coordinates;
            const {
              displayId,
              display,
              status,
              mediaInventoryStatus,
              loggedIn,
              orientation,
              displayProfile,
              resolution,
              lastAccessed,
              thumbnail,
            } = feature.properties;

            const lastAccessedStr = lastAccessed
              ? new Date(lastAccessed * 1000).toLocaleString(undefined, { timeZone: timezone })
              : null;

            return (
              <MarkerWithStatus
                key={displayId}
                position={[lat, lng]}
                icon={makeIcon(mediaInventoryStatus, loggedIn)}
                mediaInventoryStatus={mediaInventoryStatus}
              >
                <Popup>
                  <div className="text-sm space-y-1 min-w-36">
                    <p className="font-semibold">{display}</p>
                    <p>
                      {t('Status')}: {status}{' '}
                      <span className="text-gray-500">
                        ({loggedIn === 1 ? t('Logged in') : t('Not logged in')})
                      </span>
                    </p>
                    {orientation && (
                      <p>
                        {t('Orientation')}: {orientation}
                      </p>
                    )}
                    {displayProfile && (
                      <p>
                        {t('Profile')}: {displayProfile}
                      </p>
                    )}
                    {resolution && (
                      <p>
                        {t('Resolution')}: {resolution}
                      </p>
                    )}
                    {lastAccessedStr && (
                      <p>
                        {t('Last Accessed')}: {lastAccessedStr}
                      </p>
                    )}
                    {thumbnail && (
                      <img
                        src={thumbnail}
                        alt={display}
                        className="w-full mt-1 rounded"
                        style={{ maxWidth: 180 }}
                      />
                    )}
                  </div>
                </Popup>
              </MarkerWithStatus>
            );
          })}
        </MarkerClusterGroup>

        <MapControls tileLayer={tileLayer} />
        <BoundsTracker onBoundsChange={handleBoundsChange} />
        <ClusterEventHandler clusterGroup={clusterGroup} />
      </MapContainer>
    </div>
  );
}
