import { useState } from 'react';

import { submitForgotPassword } from '../api';
import { t } from '../i18n';

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

  async function handleSubmit(e: React.SyntheticEvent) {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      // PHP always returns 200 with the same message regardless of whether the user
      // exists, is rate-limited, or the email was sent — preserving constant-time behaviour.
      await submitForgotPassword(username);
      onSent();
    } catch {
      setError(t('unexpectedError'));
    } finally {
      setLoading(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} noValidate>
      <p className="login-prompt">{t('forgotPrompt')}</p>

      <div className="login-field">
        <label className="login-label" htmlFor="forgot-username">
          {t('username')}
        </label>
        <input
          id="forgot-username"
          type="text"
          value={username}
          onChange={(e) => setUsername(e.target.value)}
          placeholder={t('usernamePlaceholder')}
          autoComplete="username"
          autoFocus
          required
          className="login-input"
        />
      </div>

      {error && <ErrorBanner message={error} />}

      <SubmitButton label={t('forgotSendButton')} loading={loading} />

      <p className="login-alt">
        <button type="button" onClick={onBack} className="login-link login-link-muted">
          {t('loginInstead')}
        </button>
      </p>
    </form>
  );
}
