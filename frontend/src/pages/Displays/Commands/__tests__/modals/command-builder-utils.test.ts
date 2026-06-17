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

import { describe, test, expect } from 'vitest';

import {
  DEFAULT_HTTP_REQUEST_OPTIONS,
  DEFAULT_RS232,
  buildCommandString,
  getDefaultParsedCommand,
  parseCommandString,
  type ParsedCommand,
} from '../../components/CommandBuilder/commandStringUtils';

// =============================================================================
// Default constants
// =============================================================================

describe('command string utils - default constants', () => {
  test('RS232 defaults match the documented serial defaults', () => {
    expect(DEFAULT_RS232).toEqual({
      deviceName: '',
      baudRate: '',
      dataBits: '',
      parity: 'None',
      stopBits: 'None',
      handshake: 'None',
      hexSupport: '0',
    });
  });

  test('HTTP request-option defaults are a GET with empty headers and body', () => {
    expect(DEFAULT_HTTP_REQUEST_OPTIONS).toEqual({
      method: 'GET',
      headers: '{}',
      body: '',
    });
  });
});

// =============================================================================
// getDefaultParsedCommand — the starter shape for each command type
// =============================================================================

describe('getDefaultParsedCommand', () => {
  test('free text starts as an empty string', () => {
    expect(getDefaultParsedCommand('freetext')).toEqual({ type: 'freetext', freetext: '' });
  });

  test('Philips Android (LED) starts with the colour "off"', () => {
    expect(getDefaultParsedCommand('tpv_led')).toEqual({ type: 'tpv_led', tpvLedColor: 'off' });
  });

  test('RS232 starts with the default serial config and an empty command', () => {
    expect(getDefaultParsedCommand('rs232')).toEqual({
      type: 'rs232',
      rs232: { cs: { ...DEFAULT_RS232 }, command: '' },
    });
  });

  test('Android Intent starts as an activity with no name and no extras', () => {
    expect(getDefaultParsedCommand('intent')).toEqual({
      type: 'intent',
      intent: { type: 'activity', name: '', extras: [] },
    });
  });

  test('HTTP starts with a form-urlencoded content type and default options', () => {
    expect(getDefaultParsedCommand('http')).toEqual({
      type: 'http',
      http: {
        url: '',
        contenttype: 'application/x-www-form-urlencoded',
        requestOptions: { ...DEFAULT_HTTP_REQUEST_OPTIONS },
      },
    });
  });
});

// =============================================================================
// parseCommandString — raw string -> structured command
// =============================================================================

describe('parseCommandString', () => {
  test('an empty / missing value is treated as empty free text', () => {
    expect(parseCommandString('')).toEqual({ type: 'freetext', freetext: '' });
    expect(parseCommandString(null)).toEqual({ type: 'freetext', freetext: '' });
    expect(parseCommandString(undefined)).toEqual({ type: 'freetext', freetext: '' });
  });

  test('a plain string with no pipe is parsed as free text', () => {
    expect(parseCommandString('reboot')).toEqual({ type: 'freetext', freetext: 'reboot' });
  });

  test('a Philips Android LED command is parsed into its colour', () => {
    expect(parseCommandString('tpv_led|red')).toEqual({ type: 'tpv_led', tpvLedColor: 'red' });
  });

  test('an RS232 command is parsed into its serial config and command', () => {
    expect(parseCommandString('rs232|COM1,9600,8,None,None,None,0|AT')).toEqual({
      type: 'rs232',
      rs232: {
        cs: {
          deviceName: 'COM1',
          baudRate: '9600',
          dataBits: '8',
          parity: 'None',
          stopBits: 'None',
          handshake: 'None',
          hexSupport: '0',
        },
        command: 'AT',
      },
    });
  });

  test('an Android Intent command is parsed into type, name and extras (values as strings)', () => {
    expect(
      parseCommandString('intent|activity|com.example.Act|[{"name":"k","type":"int","value":5}]'),
    ).toEqual({
      type: 'intent',
      intent: {
        type: 'activity',
        name: 'com.example.Act',
        // The numeric 5 is normalised back to the string '5' for the form.
        extras: [{ name: 'k', type: 'int', value: '5' }],
      },
    });
  });

  test('an HTTP command is parsed into url, content type and request options', () => {
    expect(
      parseCommandString(
        'http|https://example.com/api|application/json|{"method":"POST","headers":"{}","body":""}',
      ),
    ).toEqual({
      type: 'http',
      http: {
        url: 'https://example.com/api',
        contenttype: 'application/json',
        requestOptions: { method: 'POST', headers: '{}', body: '' },
      },
    });
  });

  test('an unknown type prefix falls back to free text with the whole value', () => {
    expect(parseCommandString('mystery|foo|bar')).toEqual({
      type: 'freetext',
      freetext: 'mystery|foo|bar',
    });
  });
});

// =============================================================================
// buildCommandString — structured command -> raw string
// =============================================================================

describe('buildCommandString', () => {
  test('free text is returned as-is', () => {
    expect(buildCommandString({ type: 'freetext', freetext: 'reboot' })).toBe('reboot');
  });

  test('a Philips Android LED command is serialised with its colour', () => {
    expect(buildCommandString({ type: 'tpv_led', tpvLedColor: 'red' })).toBe('tpv_led|red');
  });

  test('an RS232 command is serialised as rs232|<config>|<command>', () => {
    expect(
      buildCommandString({
        type: 'rs232',
        rs232: {
          cs: {
            deviceName: 'COM1',
            baudRate: '9600',
            dataBits: '8',
            parity: 'None',
            stopBits: 'None',
            handshake: 'None',
            hexSupport: '0',
          },
          command: 'AT',
        },
      }),
    ).toBe('rs232|COM1,9600,8,None,None,None,0|AT');
  });

  test('an Android Intent command coerces extra values to their declared type', () => {
    expect(
      buildCommandString({
        type: 'intent',
        intent: {
          type: 'activity',
          name: 'com.example.Act',
          extras: [{ name: 'k', type: 'int', value: '5' }],
        },
      }),
    ).toBe('intent|activity|com.example.Act|[{"name":"k","type":"int","value":5}]');
  });

  test('an Android Intent with no extras omits the extras segment', () => {
    expect(
      buildCommandString({
        type: 'intent',
        intent: { type: 'activity', name: 'com.example.Act', extras: [] },
      }),
    ).toBe('intent|activity|com.example.Act');
  });

  test('an HTTP command is serialised as http|<url>|<contenttype>|<options>', () => {
    expect(
      buildCommandString({
        type: 'http',
        http: {
          url: 'https://example.com/api',
          contenttype: 'application/json',
          requestOptions: { method: 'POST', headers: '{}', body: '' },
        },
      }),
    ).toBe(
      'http|https://example.com/api|application/json|{"method":"POST","headers":"{}","body":""}',
    );
  });
});

// =============================================================================
// Round-trip — parse(build(x)) preserves a curated command of each type
// =============================================================================

describe('parse <-> build round-trip', () => {
  const cases: Array<{ name: string; parsed: ParsedCommand }> = [
    { name: 'free text', parsed: { type: 'freetext', freetext: 'reboot' } },
    { name: 'Philips Android LED', parsed: { type: 'tpv_led', tpvLedColor: 'red' } },
    {
      name: 'RS232',
      parsed: {
        type: 'rs232',
        rs232: {
          cs: {
            deviceName: 'COM1',
            baudRate: '9600',
            dataBits: '8',
            parity: 'None',
            stopBits: 'None',
            handshake: 'None',
            hexSupport: '0',
          },
          command: 'AT',
        },
      },
    },
    {
      name: 'Android Intent',
      parsed: {
        type: 'intent',
        intent: {
          type: 'activity',
          name: 'com.example.Act',
          extras: [{ name: 'k', type: 'int', value: '5' }],
        },
      },
    },
    {
      name: 'HTTP',
      parsed: {
        type: 'http',
        http: {
          url: 'https://example.com/api',
          contenttype: 'application/json',
          requestOptions: { method: 'POST', headers: '{}', body: '' },
        },
      },
    },
  ];

  test.each(cases)('a $name command survives build then parse unchanged', ({ parsed }) => {
    expect(parseCommandString(buildCommandString(parsed))).toEqual(parsed);
  });
});
