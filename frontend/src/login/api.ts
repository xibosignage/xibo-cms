import type { ForgotResponse, LoginResponse } from './types';

function getCsrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="token"]')?.content ?? '';
}

async function postJson<T>(url: string, body: Record<string, string>): Promise<T> {
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-XSRF-TOKEN': getCsrfToken(),
    },
    body: JSON.stringify(body),
  });

  if (!res.ok && res.status !== 401 && res.status !== 429) {
    throw new Error(`Unexpected HTTP ${res.status}`);
  }

  return res.json() as Promise<T>;
}

export function submitLogin(username: string, password: string): Promise<LoginResponse> {
  return postJson<LoginResponse>('/login', { username, password });
}

export function submitTwoFactor(code: string): Promise<LoginResponse> {
  return postJson<LoginResponse>('/tfa', { code });
}

export function submitRecoveryCode(recoveryCode: string): Promise<LoginResponse> {
  return postJson<LoginResponse>('/tfa', { recoveryCode });
}

export function submitForgotPassword(username: string): Promise<ForgotResponse> {
  return postJson<ForgotResponse>('/login/forgotten', { username });
}
