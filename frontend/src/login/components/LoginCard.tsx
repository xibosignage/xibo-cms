interface Props {
  logoUrl: string;
  supportUrl: string;
  children: React.ReactNode;
}

export function LoginCard({ logoUrl, supportUrl, children }: Props) {
  return (
    <div className="login-card">
      <p className="login-card-logo">
        <a href={supportUrl}>
          <img
            src={logoUrl}
            alt="Logo"
            onError={(e) => {
              const img = e.currentTarget;
              if (!img.src.endsWith('.png')) {
                img.src = img.src.replace(/\.[^.]+$/, '.png');
              } else {
                // .png fallback also failed (e.g. a white-label install with no dark logo
                // asset) - collapse the whole fixed-height wrapper, not just the broken image,
                // so it doesn't leave an empty gap above the card content.
                img.closest<HTMLElement>('.login-card-logo')?.style.setProperty('display', 'none');
              }
            }}
          />
        </a>
      </p>
      {children}
    </div>
  );
}
