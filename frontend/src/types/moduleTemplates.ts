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

export interface ModuleTemplateStencil {
  twig: string | null;
  hbs: string | null;
  style: string | null;
  head: string | null;
}

export interface ModuleTemplateProperty {
  type: string;
  id: string;
  title?: string;
  helpText?: string;
  default?: string;
  variant?: string;
  format?: string;
  mode?: string;
  target?: string;
  propertyGroupId?: string;
  dependsOn?: string;
  customPopOver?: string;
  allowLibraryRefs?: boolean;
  allowAssetRefs?: boolean;
  parseTranslations?: boolean;
  includeInXlf?: boolean;
  options?: Array<{ title: string; name: string }>;
  visibility?: Array<{
    type: string;
    message: string;
    conditions: Array<{ field: string; type: string; value: string }>;
  }>;
  validation?: {
    onSave?: boolean;
    onStatus?: boolean;
    tests?: Array<{
      type: string;
      message: string;
      conditions: Array<{ field: string; type: string; value: string }>;
    }>;
  };
  playerCompatibility?: {
    windows?: string;
    android?: string;
    linux?: string;
    webos?: string;
    tizen?: string;
  };
}

export interface ModuleTemplate {
  id: number;
  templateId: string;
  title: string;
  type: string;
  dataType: string;
  description: string | null;
  showIn: 'none' | 'layout' | 'playlist' | 'both';
  isEnabled: boolean;
  isVisible: boolean;
  ownership: 'system' | 'custom' | 'user';
  ownerId: number;
  groupsWithPermissions: string;
  onTemplateRender: string | null;
  onTemplateVisible: string | null;
  stencil: ModuleTemplateStencil | null;
  properties: ModuleTemplateProperty[] | null;
}

export interface DataType {
  id: string;
  name: string;
}

export interface ModuleTemplateEditFormValues {
  templateId: string;
  title: string;
  dataType: string;
  showIn: string;
  enabled: boolean;
  twig: string;
  hbs: string;
  style: string;
  head: string;
  onTemplateRender: string;
  onTemplateVisible: string;
  properties: ModuleTemplateProperty[];
}
