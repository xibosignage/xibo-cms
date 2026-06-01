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

import type { FilterConfigItem } from '@/components/ui/FilterInputs';

export const SSP_CLASS = '\\Xibo\\Connector\\XiboSspConnector';
export const AUDIENCE_CLASS = '\\Xibo\\Connector\\XiboAudienceReportingConnector';
export const DASHBOARD_CLASS = '\\Xibo\\Connector\\XiboDashboardConnector';

export interface ConnectorFilterInput {
  name: string;
  enabled: string;
}

export const INITIAL_FILTER_STATE: ConnectorFilterInput = {
  name: '',
  enabled: '',
};

export const getFilterKeys = (t: TFunction): FilterConfigItem<ConnectorFilterInput>[] => [
  {
    label: t('Name'),
    name: 'name',
    type: 'text',
    placeholder: ' ',
  },
  {
    label: t('Status'),
    name: 'enabled',
    type: 'select',
    options: [
      { label: t('All statuses'), value: '' },
      { label: t('Enabled'), value: 'enabled' },
      { label: t('Disabled'), value: 'disabled' },
    ],
  },
];
