interface Props {
  logoUrl: string;
  supportUrl: string;
  children: React.ReactNode;
}

export function LoginCard({ logoUrl, supportUrl, children }: Props) {
  return (
    <div
      style={{
        maxWidth: 330,
        margin: '0 auto 20px',
        borderRadius: 5,
        overflow: 'hidden',
        border: '1px solid #e5e5e5',
        boxShadow: '0 2px 8px rgba(0,0,0,.15)',
        backgroundColor: 'var(--brand-primary, #3f7fff)',
        textAlign: 'center',
      }}
    >
      <p style={{ margin: '16px 0 8px' }}>
        <a href={supportUrl}>
          <img
            src={logoUrl}
            alt="Logo"
            style={{ width: 200, maxHeight: 80, objectFit: 'contain' }}
            onError={(e) => {
              const img = e.currentTarget;
              if (!img.src.endsWith('.png')) {
                img.src = img.src.replace(/\.[^.]+$/, '.png');
              }
            }}
          />
        </a>
      </p>
      <div
        style={{
          padding: '19px 29px 29px',
          backgroundColor: '#fff',
          textAlign: 'left',
        }}
      >
        {children}
      </div>
    </div>
  );
}
