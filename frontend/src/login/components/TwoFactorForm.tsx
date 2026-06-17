import { useRef, useState } from 'react';

import { submitRecoveryCode, submitTwoFactor } from '../api';
import type { LoginResponse } from '../types';

import { ErrorBanner } from './ErrorBanner';
import { SubmitButton } from './SubmitButton';

interface Props {
  mode: 'code' | 'recovery';
  onSuccess: (priorRoute: string) => void;
  onSwitchMode: (mode: 'code' | 'recovery') => void;
  onBack: () => void;
}

export function TwoFactorForm({ mode, onSuccess, onSwitchMode, onBack }: Props) {
  const [value, setValue] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [rateLimited, setRateLimited] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (rateLimited) return;

    setError('');
    setLoading(true);

    try {
      const res: LoginResponse =
        mode === 'code' ? await submitTwoFactor(value) : await submitRecoveryCode(value);

      if (res.status === 'ok') {
        onSuccess(res.priorRoute ?? '');
      } else if (res.status === 'rate_limited') {
        setRateLimited(true);
        setError(res.message ?? 'Too many attempts. Please wait before trying again.');
      } else {
        setError(res.message ?? 'Authentication code incorrect.');
        setValue('');
        inputRef.current?.focus();
      }
    } catch {
      setError('An unexpected error occurred. Please try again.');
    } finally {
      setLoading(false);
    }
  }

  const isCode = mode === 'code';

  return (
    <form onSubmit={handleSubmit} noValidate>
      <p className="mb-3 text-sm text-gray-600">
        {isCode
          ? 'Please provide your Two Factor Authorisation Code'
          : 'Please provide your Two Factor Recovery Code'}
      </p>

      <input
        ref={inputRef}
        type="text"
        value={value}
        onChange={(e) => setValue(e.target.value)}
        placeholder={isCode ? 'Code' : 'Recovery Code'}
        autoFocus
        autoComplete="one-time-code"
        required
        className="form-control mb-3"
      />

      {error && <ErrorBanner message={error} />}

      <SubmitButton label="Verify" loading={loading} disabled={rateLimited} />

      <p className="mt-3 text-center text-sm">
        <button
          type="button"
          onClick={() => {
            setValue('');
            setError('');
            onSwitchMode(isCode ? 'recovery' : 'code');
          }}
          className="btn-link"
        >
          {isCode ? 'Use Recovery Code instead?' : 'Use Two Factor Code instead?'}
        </button>
      </p>

      <p className="mt-1 text-center text-sm">
        <button type="button" onClick={onBack} className="btn-link btn-link-muted">
          Back to login
        </button>
      </p>
    </form>
  );
}
