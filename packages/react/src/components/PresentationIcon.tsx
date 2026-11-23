const paths = {
  user: 'M18 21v-2a6 6 0 0 0-12 0v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8',
  shield: 'M12 3 3 7v5c0 5 9 9 9 9s9-4 9-9V7l-9-4Z',
  key: 'M15 14a5 5 0 1 0-5-5M10 9 3 16v5h5v-3h3v-3l2-2',
  sessions: 'M3 4h18v12H3zM8 21h8M12 16v5',
  dashboard: 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
  apiDocs: 'M7 3h8l4 4v14H7zM15 3v5h4M10 12l-2 2 2 2M16 12l2 2-2 2M14 11l-2 6',
  users:
    'M16 21v-2a5 5 0 0 0-10 0v2M11 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M17 4a4 4 0 0 1 0 8M22 21v-2a5 5 0 0 0-4-5',
  invite: 'M3 5h18v14H3zM3 5l9 8 9-8',
  elevatedInvite: 'M3 8h12v12H3zM3 8l6 5 6-5M19 2v8M15 6h8',
  invitations: 'M5 3h14v18H5zM8 7h8M8 12h8M8 17h5',
  logout: 'M9 3H3v18h6M10 12h11M16 7l5 5-5 5',
  sun: 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5l1.5 1.5M5 19l1.5-1.5M17.5 6.5l1.5-1.5',
  moon: 'M21 13A9 9 0 0 1 11 3a9 9 0 1 0 10 10Z',
  monitor: 'M3 3h18v13H3zM8 21h8M12 16v5',
  collapse: 'm11 6-6 6 6 6M19 6l-6 6 6 6',
  expand: 'm5 6 6 6-6 6M13 6l6 6-6 6'
} as const;

export type PresentationIconName = keyof typeof paths;

/** Supplies self-contained decorative line icons; the adjacent control or text owns meaning */
export function PresentationIcon({ name }: { name: PresentationIconName }) {
  return (
    <svg
      className="presentation-icon"
      width="20"
      height="20"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
    >
      <path d={paths[name]} />
    </svg>
  );
}
