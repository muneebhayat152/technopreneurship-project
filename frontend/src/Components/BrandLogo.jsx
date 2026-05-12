import { LogoMark } from "./LogoMark";

/**
 * Brand lockup wrapper — shadow / ring tuned for light auth screens and dark sidebar.
 */
export function BrandLogo({
  className = "h-12 w-12 sm:h-14 sm:w-14",
  variant = "default",
}) {
  const ring =
    variant === "sidebar"
      ? "shadow-black/40 ring-white/15"
      : "shadow-indigo-900/25 ring-black/[0.06] dark:ring-white/10";

  return (
    <span
      className={`inline-flex shrink-0 overflow-hidden rounded-2xl shadow-lg ring-1 ${ring} ${className}`}
      style={{ aspectRatio: "1 / 1" }}
    >
      <LogoMark />
    </span>
  );
}
