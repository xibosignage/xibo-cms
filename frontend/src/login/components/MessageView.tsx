import type { ReactNode } from 'react';

import { t } from '../i18n';

import { LoginCard } from './LoginCard';

const config = window.__LOGIN_CONFIG__;

interface Props {
  icon: ReactNode;
  titleKey: string;
  messageKey: string;
  spinning?: boolean;
}

export function MessageView({ icon, titleKey, messageKey, spinning = false }: Props) {
  return (
    <div className="login-root">
      <LoginCard logoUrl={config.logoDarkUrl} supportUrl={config.supportUrl ?? ''}>
        <div className="login-message">
          <span
            className={
              spinning ? 'login-message-icon login-message-icon--spin' : 'login-message-icon'
            }
          >
            {icon}
          </span>
          <h1 className="login-message-title">{t(titleKey)}</h1>
          <p className="login-message-message">{t(messageKey)}</p>
        </div>
      </LoginCard>
    </div>
  );
}
