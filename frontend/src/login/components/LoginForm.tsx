import { useState } from 'react';

import { submitLogin } from '../api';
import type { LoginResponse } from '../types';

import { ErrorBanner } from './ErrorBanner';
import { SubmitButton } from './SubmitButton';

interface Props {
  onSuccess: (priorRoute: string, passwordChangeRequired?: boolean) => void;
  onTfa: (priorRoute: string) => void;
  onForgot: () => void;
  passwordReminderEnabled: boolean;
  authCASEnabled: boolean;
  initialError?: string;
}

export function LoginForm({
  onSuccess,
  onTfa,
  onForgot,
  passwordReminderEnabled,
  authCASEnabled,
  initialError = '',
}: Props) {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState(initialError);
  const [loading, setLoading] = useState(false);
  const [rateLimited, setRateLimited] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (rateLimited) return;

    setError('');
    setLoading(true);

    try {
      const res: LoginResponse = await submitLogin(username, password);
      if (res.status === 'ok') {
        onSuccess(res.priorRoute ?? '', res.isPasswordChangeRequired);
      } else if (res.status === '2fa_required') {
        onTfa(res.priorRoute ?? '');
      } else if (res.status === 'rate_limited') {
        setRateLimited(true);
        setError(res.message ?? 'Too many attempts. Please wait before trying again.');
      } else {
        setError(res.message ?? 'Username or password incorrect.');
      }
    } catch {
      setError('An unexpected error occurred. Please try again.');
    } finally {
      setLoading(false);
    }
  }

  if (authCASEnabled) {
    return (
      <div className="text-center">
        <p>Connect with the Central Authentication Server</p>
        <form action="/cas/login" method="post">
          <SubmitButton label="CAS Login" />
        </form>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} noValidate>
      <p className="mb-3 text-sm text-gray-600">Please provide your credentials</p>

      <input
        id="username"
        type="text"
        name="username"
        value={username}
        onChange={(e) => setUsername(e.target.value)}
        placeholder="Username"
        autoComplete="username"
        autoFocus
        required
        className="form-control mb-3"
      />
      <input
        id="password"
        type="password"
        name="password"
        value={password}
        onChange={(e) => setPassword(e.target.value)}
        placeholder="Password"
        autoComplete="current-password"
        required
        className="form-control mb-3"
      />

      {error && <ErrorBanner message={error} />}

      <SubmitButton label="Login" loading={loading} disabled={rateLimited} />

      {passwordReminderEnabled && (
        <p className="mt-3 text-center text-sm">
          <button type="button" onClick={onForgot} className="btn-link">
            Forgotten your password?
          </button>
        </p>
      )}
    </form>
  );
}
