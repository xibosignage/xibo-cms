import React from 'react';

const useTranslation = () => ({
  t: (key: string, opts?: Record<string, unknown>) => {
    if (!opts) return key;
    return key.replace(/\{\{(\w+)\}\}/g, (_, k) => String(opts[k] ?? ''));
  },
  i18n: { changeLanguage: () => Promise.resolve() },
});

const Trans = ({
  i18nKey = '',
  values = {},
  children,
}: {
  i18nKey?: string;
  values?: Record<string, unknown>;
  components?: Record<string, unknown>;
  children?: React.ReactNode;
}) => {
  if (children !== undefined) return <>{children}</>;
  let s = String(i18nKey ?? '');
  for (const [k, v] of Object.entries(values as Record<string, unknown>)) {
    s = s.replace(new RegExp(`{{${k}}}`, 'g'), String(v ?? ''));
  }
  const parts = s.split(/(<\w+>.*?<\/\w+>)/g);
  return (
    <>
      {parts.map((p, i) => {
        const m = p.match(/^<(\w+)>(.*?)<\/\1>$/);
        if (!m) return p || null;
        const Tag = m[1] as keyof JSX.IntrinsicElements;
        return <Tag key={i}>{m[2]}</Tag>;
      })}
    </>
  );
};

export { useTranslation, Trans };
export default { useTranslation, Trans };
