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

// The network stub the service tests rely on: it must settle, cancel and shape
// responses the way axios's own XHR adapter would, or a service test could pass
// against behaviour the browser never shows.

import axios, { AxiosError, isCancel } from 'axios';
import { afterEach, beforeEach, describe, expect, test } from 'vitest';

import http from '@/lib/api';
import {
  installHttpStub,
  lastRequest,
  respondWith,
  restoreHttpStub,
} from '@/testUtils/httpAdapter';

describe('the stub in place', () => {
  beforeEach(installHttpStub);
  afterEach(restoreHttpStub);

  test('a 2xx response resolves', async () => {
    respondWith({ status: 201, data: { id: 1 } });

    const res = await http.post('/thing', 'a=1');

    expect(res.status).toBe(201);
    expect(res.data).toEqual({ id: 1 });
  });

  test('a 4xx response rejects as a bad request, with the response attached', async () => {
    respondWith({ status: 422, data: { message: 'No' } });

    const error = await http.get('/thing').catch((e: unknown) => e);

    expect(error).toBeInstanceOf(AxiosError);
    expect((error as AxiosError).code).toBe(AxiosError.ERR_BAD_REQUEST);
    expect((error as AxiosError).response?.data).toEqual({ message: 'No' });
  });

  test('a 5xx response rejects as a bad response', async () => {
    respondWith({ status: 503 });

    const error = await http.get('/thing').catch((e: unknown) => e);

    expect((error as AxiosError).code).toBe(AxiosError.ERR_BAD_RESPONSE);
  });

  test("the request's own validateStatus decides, as in axios", async () => {
    respondWith({ status: 404, data: 'missing' });

    const res = await http.get('/thing', { validateStatus: (s) => s < 500 });

    expect(res.status).toBe(404);
  });

  test('an aborted request rejects as cancelled', async () => {
    const controller = new AbortController();
    controller.abort();

    const error = await http.get('/thing', { signal: controller.signal }).catch((e: unknown) => e);

    expect(isCancel(error)).toBe(true);
  });

  test("responseType 'blob' resolves with a Blob of the stubbed data", async () => {
    respondWith({ data: 'file contents' });

    const res = await http.get('/thing', { responseType: 'blob' });

    expect(res.data).toBeInstanceOf(Blob);
    expect(await (res.data as Blob).text()).toBe('file contents');
  });

  test('requests through the bare axios export are recorded too', async () => {
    await axios.get('/display/map', { params: { a: 1 } });

    expect(lastRequest().url).toBe('/display/map?a=1');
  });
});

describe('installing and restoring', () => {
  test('restore puts the real adapters back, even after installing twice', () => {
    const real = { http: http.defaults.adapter, axios: axios.defaults.adapter };

    installHttpStub();
    installHttpStub();
    restoreHttpStub();

    expect(http.defaults.adapter).toBe(real.http);
    expect(axios.defaults.adapter).toBe(real.axios);
  });
});
