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

export type CommandType = 'freetext' | 'tpv_led' | 'rs232' | 'intent' | 'http';

export interface IntentExtra {
  name: string;
  type: 'string' | 'int' | 'bool' | 'intArray';
  value: string;
}

export interface Rs232Config {
  deviceName: string;
  baudRate: string;
  dataBits: string;
  parity: string;
  stopBits: string;
  handshake: string;
  hexSupport: string;
}

export interface HttpRequestOptions {
  method: string;
  headers?: string;
  body?: string;
}

export interface ParsedCommand {
  type: CommandType;
  freetext?: string;
  tpvLedColor?: string;
  rs232?: { cs: Rs232Config; command: string };
  intent?: { type: string; name: string; extras: IntentExtra[] };
  http?: {
    url: string;
    contenttype: string;
    requestOptions: HttpRequestOptions;
  };
}

export const DEFAULT_RS232: Rs232Config = {
  deviceName: '',
  baudRate: '',
  dataBits: '',
  parity: 'None',
  stopBits: 'None',
  handshake: 'None',
  hexSupport: '0',
};

export const DEFAULT_HTTP_REQUEST_OPTIONS: HttpRequestOptions = {
  method: 'GET',
  headers: '{}',
  body: '',
};

export function getDefaultParsedCommand(type: CommandType): ParsedCommand {
  switch (type) {
    case 'freetext':
      return { type, freetext: '' };
    case 'tpv_led':
      return { type, tpvLedColor: 'off' };
    case 'rs232':
      return { type, rs232: { cs: { ...DEFAULT_RS232 }, command: '' } };
    case 'intent':
      return { type, intent: { type: 'activity', name: '', extras: [] } };
    case 'http':
      return {
        type,
        http: {
          url: '',
          contenttype: 'application/x-www-form-urlencoded',
          requestOptions: { ...DEFAULT_HTTP_REQUEST_OPTIONS },
        },
      };
  }
}

export function parseCommandString(value: string | null | undefined): ParsedCommand {
  if (!value || value === '') {
    return { type: 'freetext', freetext: '' };
  }

  const splitValue = value.split('|');

  if (splitValue.length === 1) {
    return { type: 'freetext', freetext: value };
  }

  const typeStr = splitValue[0];

  switch (typeStr) {
    case 'intent': {
      let extras: IntentExtra[] = [];
      if (splitValue.length > 3) {
        try {
          extras = JSON.parse(splitValue[3]!) as IntentExtra[];
        } catch {
          extras = [];
        }
      }
      // Normalize extras to always have string values for the form
      extras = extras.map((e) => ({
        name: e.name,
        type: e.type,
        value: String(e.value),
      }));
      return {
        type: 'intent',
        intent: {
          type: splitValue[1] || 'activity',
          name: splitValue[2] || '',
          extras,
        },
      };
    }

    case 'rs232': {
      const connectionStringRaw = (splitValue[1] || '').split(',');
      return {
        type: 'rs232',
        rs232: {
          cs: {
            deviceName: connectionStringRaw[0] || '',
            baudRate: connectionStringRaw[1] || '',
            dataBits: connectionStringRaw[2] || '',
            parity: connectionStringRaw[3] || 'None',
            stopBits: connectionStringRaw[4] || 'None',
            handshake: connectionStringRaw[5] || 'None',
            hexSupport: connectionStringRaw[6] || '0',
          },
          command: splitValue[2] || '',
        },
      };
    }

    case 'tpv_led':
      return { type: 'tpv_led', tpvLedColor: splitValue[1] || 'off' };

    case 'http': {
      let requestOptions: HttpRequestOptions = { ...DEFAULT_HTTP_REQUEST_OPTIONS };

      if (splitValue[3]) {
        try {
          const parsed = JSON.parse(splitValue[3]) as Record<string, string>;
          requestOptions = {
            method: parsed.method || 'GET',
            headers: parsed.headers || '{}',
            body: parsed.body || '',
          };
        } catch {
          // Skip JSON parse failure
        }
      }

      return {
        type: 'http',
        http: {
          url: splitValue[1] || '',
          contenttype: splitValue[2] || 'application/x-www-form-urlencoded',
          requestOptions,
        },
      };
    }

    default:
      return { type: 'freetext', freetext: value };
  }
}

export function buildCommandString(parsed: ParsedCommand): string {
  switch (parsed.type) {
    case 'freetext':
      return parsed.freetext || '';

    case 'tpv_led':
      return `tpv_led|${parsed.tpvLedColor || 'off'}`;

    case 'rs232': {
      const rs = parsed.rs232;
      if (!rs) return '';
      const cs = rs.cs;
      const connectionString = [
        cs.deviceName,
        cs.baudRate,
        cs.dataBits,
        cs.parity,
        cs.stopBits,
        cs.handshake,
        cs.hexSupport,
      ].join(',');
      return `rs232|${connectionString}|${rs.command}`;
    }

    case 'intent': {
      const intent = parsed.intent;
      if (!intent) return '';

      const coercedExtras = intent.extras
        .filter((e) => e.name !== '' && e.value !== '')
        .map((e) => {
          let coercedValue: string | number | boolean | number[] = e.value;

          if (e.type === 'intArray') {
            coercedValue = String(e.value)
              .replace(/\s/g, '')
              .split(',')
              .map(Number)
              .filter((n) => !isNaN(n));
          } else if (e.type === 'int') {
            coercedValue = Number(e.value);
          } else if (e.type === 'bool') {
            coercedValue = e.value === 'true';
          }

          return { name: e.name, type: e.type, value: coercedValue };
        });

      let result = `intent|${intent.type}|${intent.name}`;
      if (coercedExtras.length > 0) {
        result += '|' + JSON.stringify(coercedExtras);
      }
      return result;
    }

    case 'http': {
      const httpData = parsed.http;
      if (!httpData) return '';

      const requestOptions = JSON.stringify({
        method: httpData.requestOptions.method || 'GET',
        headers: httpData.requestOptions.headers || '{}',
        body: httpData.requestOptions.body || '',
      });

      return `http|${httpData.url}|${httpData.contenttype}|${requestOptions}`;
    }
  }
}
