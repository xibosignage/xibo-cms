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

export interface ModuleSettingOption {
  name: string;
  title: string;
}

export interface ModuleSetting {
  id: string;
  type: string;
  title: string;
  helpText?: string;
  value?: string | number;
  options?: ModuleSettingOption[];
}

export interface Module {
  moduleId: string;
  name: string;
  description: string;
  author?: string;
  type: string;
  dataType?: string;
  regionSpecific: number;
  defaultDuration: number;
  previewEnabled: number;
  assignable: number;
  enabled: number;
  isError?: boolean;
  errors?: string[];
  settings?: ModuleSetting[];
  allowPreview?: number;
}
