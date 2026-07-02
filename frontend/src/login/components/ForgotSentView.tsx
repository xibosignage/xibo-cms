import { t } from '../i18n';

interface Props {
  onBack: () => void;
}

export function ForgotSentView({ onBack }: Props) {
  return (
    <div>
      <p className="mb-3 text-sm text-gray-600">{t('forgotSentMessage')}</p>
      <p className="text-center text-sm">
        <button
          type="button"
          onClick={onBack}
          className="text-blue-600 hover:underline bg-transparent border-0 p-0 cursor-pointer"
        >
          {t('forgotSentReturnLink')}
        </button>
      </p>
    </div>
  );
}
