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
