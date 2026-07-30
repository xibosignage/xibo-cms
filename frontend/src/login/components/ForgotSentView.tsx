import { t } from '../i18n';

interface Props {
  onBack: () => void;
}

export function ForgotSentView({ onBack }: Props) {
  return (
    <div>
      <div role="status" className="login-banner login-banner-info">
        <svg
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
          aria-hidden="true"
        >
          <circle cx="12" cy="12" r="10" />
          <path d="M12 16v-4" />
          <path d="M12 8h.01" />
        </svg>
        <div>{t('forgotSentMessage')}</div>
      </div>
      <p className="login-alt">
        <button type="button" onClick={onBack} className="login-link">
          {t('forgotSentReturnLink')}
        </button>
      </p>
    </div>
  );
}
