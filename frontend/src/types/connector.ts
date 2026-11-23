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
