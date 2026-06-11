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

import L from 'leaflet';
import 'leaflet-draw';
import 'leaflet-draw/dist/leaflet.draw.css';
import 'leaflet/dist/leaflet.css';
import { GeoSearchControl, OpenStreetMapProvider } from 'leaflet-geosearch';
import 'leaflet-geosearch/dist/geosearch.css';
import { Minus, Plus } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { useTranslation } from 'react-i18next';
import { MapContainer, TileLayer, useMap } from 'react-leaflet';

import '@/styles/leaflet-overrides.css';

interface GeoScheduleMapProps {
  geoLocation: string;
  onChange: (json: string) => void;
  defaultLat: number;
  defaultLng: number;
}

interface DrawControlProps {
  geoLocation: string;
  onChange: (json: string) => void;
}

function InvalidateSize() {
  const map = useMap();
  useEffect(() => {
    const raf = requestAnimationFrame(() => map.invalidateSize());
    return () => cancelAnimationFrame(raf);
  }, [map]);
  return null;
}

function MapControls() {
  const map = useMap();
  const { t } = useTranslation();

  useEffect(() => {
    const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');

    function addBtn(classes: string, icon: React.ReactElement, label: string, onClick: () => void) {
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
    }

    const iconProps = { size: 16, strokeWidth: 1.5 };

    addBtn('leaflet-control-zoom-in', <Plus {...iconProps} />, t('Zoom in'), () => map.zoomIn());
    addBtn('leaflet-control-zoom-out', <Minus {...iconProps} />, t('Zoom out'), () =>
      map.zoomOut(),
    );

    const CustomZoomControl = L.Control.extend({
      options: { position: 'topleft' },
      onAdd: () => container,
    });

    const zoomControl = new CustomZoomControl();
    map.addControl(zoomControl);

    return () => {
      map.removeControl(zoomControl);
    };
  }, [map, t]);

  return null;
}

function DrawControl({ geoLocation, onChange }: DrawControlProps) {
  const map = useMap();
  const { t } = useTranslation();
  const drawnItemsRef = useRef<L.FeatureGroup | null>(null);
  const drawControlRef = useRef<L.Control.Draw | null>(null);
  const editControlRef = useRef<L.Control.Draw | null>(null);
  const initializedRef = useRef(false);

  useEffect(() => {
    if (initializedRef.current) {
      return;
    }
    initializedRef.current = true;

    const provider = new OpenStreetMapProvider();
    const searchControl = GeoSearchControl({
      provider,
      style: 'button',
      position: 'topleft',
      autoClose: true,
      autoType: false,
      keepResult: false,
      searchLabel: t('Search location…'),
    });
    map.addControl(searchControl);

    const drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);
    drawnItemsRef.current = drawnItems;

    const drawControl = new L.Control.Draw({
      position: 'topright',
      draw: {
        polyline: false,
        circle: false,
        marker: false,
        circlemarker: false,
        polygon: { showArea: false },
        rectangle: { showArea: false },
      },
      edit: {
        featureGroup: drawnItems,
      },
    });

    const editControl = new L.Control.Draw({
      position: 'topright',
      draw: {
        polyline: false,
        polygon: false,
        rectangle: false,
        circle: false,
        marker: false,
        circlemarker: false,
      },
      edit: {
        featureGroup: drawnItems,
      },
    });

    drawControlRef.current = drawControl;
    editControlRef.current = editControl;

    if (geoLocation) {
      try {
        const geoJSON = JSON.parse(geoLocation) as GeoJSON.GeoJsonObject;
        L.geoJSON(geoJSON, {
          onEachFeature: (_feature, layer) => {
            drawnItems.addLayer(layer);
          },
        });

        const bounds = drawnItems.getBounds();
        if (bounds.isValid()) {
          map.fitBounds(bounds);
        }

        map.addControl(editControl);
      } catch {
        map.addControl(drawControl);
      }
    } else {
      map.addControl(drawControl);
    }

    map.on('draw:created', (e: L.LeafletEvent) => {
      const event = e as L.DrawEvents.Created;
      const layer = event.layer;
      drawnItems.addLayer(layer);

      onChange(JSON.stringify(layer.toGeoJSON()));

      map.removeControl(drawControl);
      map.addControl(editControl);
    });

    map.on('draw:edited', (e: L.LeafletEvent) => {
      const event = e as L.DrawEvents.Edited;
      event.layers.eachLayer((layer) => {
        onChange(JSON.stringify((layer as L.Polygon | L.Rectangle).toGeoJSON()));
      });
    });

    map.on('draw:deleted', (e: L.LeafletEvent) => {
      const event = e as L.DrawEvents.Deleted;
      event.layers.eachLayer((layer) => {
        drawnItems.removeLayer(layer);
      });

      if (drawnItems.getLayers().length === 0) {
        onChange('');
        map.removeControl(editControl);
        map.addControl(drawControl);
      }
    });

    return () => {
      initializedRef.current = false;
      map.off('draw:created');
      map.off('draw:edited');
      map.off('draw:deleted');
      map.removeLayer(drawnItems);
      map.removeControl(searchControl);
      if (drawControlRef.current) {
        map.removeControl(drawControlRef.current);
      }
      if (editControlRef.current) {
        map.removeControl(editControlRef.current);
      }
    };
  }, [map, geoLocation, onChange, t]);

  return null;
}

export default function GeoScheduleMap({
  geoLocation,
  onChange,
  defaultLat,
  defaultLng,
}: GeoScheduleMapProps) {
  return (
    <div className="relative flex-1 min-h-0 w-full rounded-lg overflow-hidden border border-gray-200">
      <div className="absolute inset-0">
        <MapContainer
          center={[defaultLat, defaultLng]}
          zoom={13}
          zoomControl={false}
          style={{ height: '100%', width: '100%' }}
        >
          <TileLayer
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
          />
          <MapControls />
          <InvalidateSize />
          <DrawControl geoLocation={geoLocation} onChange={onChange} />
        </MapContainer>
      </div>
    </div>
  );
}
