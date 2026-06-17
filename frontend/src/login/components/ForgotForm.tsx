import { useState } from 'react';

import { submitForgotPassword } from '../api';

import { ErrorBanner } from './ErrorBanner';
import { SubmitButton } from './SubmitButton';

interface Props {
  onSent: () => void;
  onBack: () => void;
}

export function ForgotForm({ onSent, onBack }: Props) {
  const [username, setUsername] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      // PHP always returns 200 with the same message regardless of whether the user
      // exists, is rate-limited, or the email was sent — preserving constant-time behaviour.
      await submitForgotPassword(username);
      onSent();
    } catch {
      setError('An unexpected error occurred. Please try again.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} noValidate>
      <p className="mb-3 text-sm text-gray-600">
        Please provide your username and we will send a password reset link.
      </p>

      <input
        type="text"
        value={username}
        onChange={(e) => setUsername(e.target.value)}
        placeholder="Username"
        autoComplete="username"
        autoFocus
        required
        className="form-control mb-3"
      />

      {error && <ErrorBanner message={error} />}

      <SubmitButton label="Send Reset" loading={loading} />

      <p className="mt-3 text-center text-sm">
        <button type="button" onClick={onBack} className="btn-link btn-link-muted">
          Login instead?
        </button>
      </p>
    </form>
  );
}
