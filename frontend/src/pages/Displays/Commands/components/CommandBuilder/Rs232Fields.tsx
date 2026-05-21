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

import { useTranslation } from 'react-i18next';

import type { Rs232Config } from './commandStringUtils';

import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';

const PARITY_OPTIONS = [
  { value: 'None', label: 'None' },
  { value: 'Odd', label: 'Odd' },
  { value: 'Even', label: 'Even' },
  { value: 'Mark', label: 'Mark' },
  { value: 'Space', label: 'Space' },
];

const STOP_BITS_OPTIONS = [
  { value: 'None', label: 'None' },
  { value: 'One', label: 'One' },
  { value: 'Two', label: 'Two' },
  { value: 'OnePointFive', label: 'OnePointFive' },
];

const HANDSHAKE_OPTIONS = [
  { value: 'None', label: 'None' },
  { value: 'XOnXOff', label: 'XOnXOff' },
  { value: 'RequestToSend', label: 'RequestToSend' },
  { value: 'RequestToSendXOnXOff', label: 'RequestToSendXOnXOff' },
];

const HEX_SUPPORT_OPTIONS = [
  { value: '0', label: '0' },
  { value: '1', label: '1' },
];

interface Rs232FieldsProps {
  config: Rs232Config;
  command: string;
  onConfigChange: (config: Rs232Config) => void;
  onCommandChange: (command: string) => void;
}

export default function Rs232Fields({
  config,
  command,
  onConfigChange,
  onCommandChange,
}: Rs232FieldsProps) {
  const { t } = useTranslation();

  const updateConfig = (field: keyof Rs232Config, value: string) => {
    onConfigChange({ ...config, [field]: value });
  };

  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-3 gap-4">
        <TextInput
          name="deviceName"
          label={t('Device Name (COM#)')}
          placeholder={' '}
          value={config.deviceName}
          onChange={(val) => updateConfig('deviceName', val)}
        />
        <TextInput
          name="baudRate"
          label={t('Baud Rate')}
          placeholder={' '}
          value={config.baudRate}
          onChange={(val) => updateConfig('baudRate', val)}
        />
        <TextInput
          name="dataBits"
          label={t('Data Bits')}
          placeholder={' '}
          value={config.dataBits}
          onChange={(val) => updateConfig('dataBits', val)}
        />
      </div>

      <div className="grid grid-cols-2 gap-4">
        <SelectDropdown
          label={t('Parity')}
          value={config.parity}
          options={PARITY_OPTIONS}
          onSelect={(val) => updateConfig('parity', val || 'None')}
        />
        <SelectDropdown
          label={t('Stop Bits')}
          value={config.stopBits}
          options={STOP_BITS_OPTIONS}
          onSelect={(val) => updateConfig('stopBits', val || 'None')}
        />
        <SelectDropdown
          label={t('Handshake')}
          value={config.handshake}
          options={HANDSHAKE_OPTIONS}
          onSelect={(val) => updateConfig('handshake', val || 'None')}
        />
        <SelectDropdown
          label={t('Hex Support')}
          value={config.hexSupport}
          options={HEX_SUPPORT_OPTIONS}
          onSelect={(val) => updateConfig('hexSupport', val || '0')}
        />
      </div>

      <TextInput
        name="rs232Command"
        label={t('Command')}
        placeholder={t('Enter command')}
        value={command}
        onChange={onCommandChange}
      />
    </div>
  );
}
