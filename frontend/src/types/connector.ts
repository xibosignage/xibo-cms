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

export interface Connector {
  connectorId: number | null;
  className: string;
  isEnabled: 0 | 1;
  isVisible: 0 | 1;
  isHidden?: number;
  isSystem: boolean;
  title: string;
  description: string;
  thumbnail: string;
}

export interface ConnectorField {
  name: string;
  type: 'text' | 'number' | 'checkbox' | 'select';
  label: string;
  helpText?: string;
  required?: boolean;
  providerOnly?: boolean;
  default?: string | number | boolean;
  options?: { value: string; label: string }[];
}

export interface ConnectorFormAlert {
  type: 'info' | 'warning' | 'danger';
  text: string;
}

export interface ConnectorFieldsResponse {
  fields: ConnectorField[];
  settings: Record<string, unknown>;
  formSubtitle: string;
  formDescriptionHtml: string;
  formAlerts: ConnectorFormAlert[];
  enabledLabel: string;
  enabledDescription: string;
  enabledMessage: string;
}

export interface DashboardService {
  type: string;
  name: string;
  isUrl?: boolean;
}

export interface DashboardCredential {
  id: string;
  userName: string;
  status: boolean;
}

export interface SspPartner {
  name: string;
  logo?: string;
  url?: string;
  description?: string;
  isWidgetSupported?: boolean;
}

export interface AudienceImpressionSource {
  value: string;
  name: string;
}

export interface AudienceOptions {
  error?: boolean;
  message?: string;
  displays?: number;
  impressionSource?: AudienceImpressionSource[];
}

export interface Dma {
  _id: string;
  name: string;
  costPerPlay: number;
  impressionsPerPlay: number;
  impressionSource: string;
  startDate?: string;
  endDate?: string;
  daysOfWeek?: number[];
  startTime?: string;
  endTime?: string;
  geoFence?: object;
  priority: number;
  displays?: unknown[];
  displayGroupId?: number;
}
