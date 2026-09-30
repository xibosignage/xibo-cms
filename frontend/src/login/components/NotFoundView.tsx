import { SearchX } from 'lucide-react';

import { t } from '../i18n';
import { publicPath } from '../utils';

export function NotFoundView() {
  return (
    <div className="login-root">
      <div className="not-found-content">
        <span className="not-found-icon">
          <SearchX size={24} aria-hidden="true" />
        </span>
        <h1 className="not-found-title">{t('notFoundTitle')}</h1>
        <p className="not-found-message">{t('notFoundMessage')}</p>
        <a className="login-button not-found-button" href={publicPath}>
          {t('notFoundAction')}
        </a>
      </div>
    </div>
  );
}
