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

import Switch from '@/components/ui/forms/Switch';

export interface SwitchRowProps {
  title: string;
  description?: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
  disabled?: boolean;
}

export default function SwitchRow({
  title,
  description,
  checked,
  onChange,
  disabled,
}: SwitchRowProps) {
  return (
    <div className="rounded-lg border border-gray-200 bg-white p-4 flex items-center justify-between gap-4">
      <div className="flex flex-col">
        <h4 className="font-bold text-gray-800 text-base">{title}</h4>
        {description && <p className="text-sm text-gray-500 mt-0.5">{description}</p>}
      </div>
      <div className="shrink-0">
        <Switch
          size="sm"
          ariaLabel={title}
          checked={checked}
          onChange={onChange}
          hideOnOff
          disabled={disabled}
        />
      </div>
    </div>
  );
}
