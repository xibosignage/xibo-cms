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

export interface DatasetColumn {
  dataSetColumnId: number;
  dataSetId: number;
  heading: string;
  listType: string;
  columnOrder: number;
  dataType: string;
  dataTypeId: DataTypeId;
  dataSetColumnType: string;
  dataSetColumnTypeId: DataSetColumnTypeId;
  listContent?: string;
  remoteField?: string;
  tooltip?: string;
  formula?: string;
  showFilter?: boolean;
  dateFormat?: string;
  showSort?: boolean;
  isRequired?: boolean;
}

// 1 - Value | 2 - Formula | 3 - Remote
export type DataSetColumnTypeId = 1 | 2 | 3;
// 1 - String | 2 - Number | 3 - Date | 4 - External Image | 5 - Library Image | 6 - HTML |
export type DataTypeId = 1 | 2 | 3 | 4 | 5 | 6;
