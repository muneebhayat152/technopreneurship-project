import { useId } from "react";

/**
 * AI Complaint Doctor — shield (trust, governance) + waveform (signals) + focal gem (intelligence).
 * No typography inside the mark; reads clearly at favicon scale.
 */
export function LogoMark({ className = "" }) {
  const uid = useId().replace(/[^a-zA-Z0-9]/g, "");
  const bg = `acd-bg-${uid}`;
  const shine = `acd-shine-${uid}`;
  const inner = `acd-inner-${uid}`;

  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 64 64"
      width="64"
      height="64"
      fill="none"
      className={`block h-full w-full ${className}`}
      aria-hidden
    >
      <defs>
        <linearGradient id={bg} x1="8" y1="4" x2="56" y2="62" gradientUnits="userSpaceOnUse">
          <stop stopColor="#0f172a" />
          <stop offset="0.35" stopColor="#5b21b6" />
          <stop offset="0.68" stopColor="#7e22ce" />
          <stop offset="1" stopColor="#0e7490" />
        </linearGradient>
        <linearGradient id={shine} x1="32" y1="10" x2="32" y2="36" gradientUnits="userSpaceOnUse">
          <stop stopColor="#ffffff" stopOpacity="0.38" />
          <stop offset="1" stopColor="#ffffff" stopOpacity="0.02" />
        </linearGradient>
        <radialGradient id={inner} cx="32" cy="22" r="18" gradientUnits="userSpaceOnUse">
          <stop stopColor="#ffffff" stopOpacity="0.14" />
          <stop offset="1" stopColor="#ffffff" stopOpacity="0" />
        </radialGradient>
      </defs>

      {/* Tile */}
      <rect width="64" height="64" rx="18" fill={`url(#${bg})`} />
      <rect x="0.5" y="0.5" width="63" height="63" rx="17.5" stroke="#ffffff" strokeOpacity="0.14" />

      {/* Shield — operations / trust */}
      <path
        fill="#f8fafc"
        fillOpacity="0.96"
        d="M32 13.5 46 20v13.5c0 8.8-5.8 17.2-14 21.5-8.2-4.3-14-12.7-14-21.5V20l14-6.5Z"
      />
      <path fill={`url(#${shine})`} d="M32 13.5 46 20v13.5c0 8.8-5.8 17.2-14 21.5-8.2-4.3-14-12.7-14-21.5V20l14-6.5Z" />
      <ellipse cx="32" cy="23" rx="14" ry="10" fill={`url(#${inner})`} />

      {/* Insight waveform */}
      <path
        stroke="#3730a3"
        strokeWidth="2.2"
        strokeLinecap="round"
        strokeLinejoin="round"
        fill="none"
        d="M21.5 36.5 25 31.5 28.8 37.2 32.4 29.5 36.2 35.8 40 33 43.5 36"
      />

      {/* Intelligence focal */}
      <circle cx="32" cy="26.5" r="2.35" fill="#06b6d4" />
      <circle cx="32" cy="26.5" r="3.6" stroke="#67e8f9" strokeOpacity="0.55" strokeWidth="1" fill="none" />

      {/* Cluster sparks */}
      <circle cx="40.5" cy="22" r="1.05" fill="#c4b5fd" />
      <circle cx="23.5" cy="24" r="0.85" fill="#a5f3fc" />
    </svg>
  );
}
