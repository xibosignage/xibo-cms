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

import http from '@/lib/api';

export interface ScheduleCriteriaValue {
  id: string;
  title: string;
}

export interface ScheduleCriteriaValues {
  inputType: 'text' | 'number' | 'dropdown' | 'date';
  values: ScheduleCriteriaValue[];
}

export interface ScheduleCriteriaCondition {
  id: string;
  name: string;
}

export interface ScheduleCriteriaMetric {
  id: string;
  name: string;
  conditions: ScheduleCriteriaCondition[];
  values: ScheduleCriteriaValues | null;
}

export interface ScheduleCriteriaType {
  id: string;
  name: string;
  metrics: ScheduleCriteriaMetric[];
}

export interface ScheduleCriteriaResponse {
  types: ScheduleCriteriaType[];
  defaultCondition: ScheduleCriteriaCondition[];
}

export async function fetchScheduleCriteria(
  signal?: AbortSignal,
): Promise<ScheduleCriteriaResponse> {
  const response = await http.get('/schedule/criteria', { signal });

  return response.data;
}
