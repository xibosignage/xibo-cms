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
