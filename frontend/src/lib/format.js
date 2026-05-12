/** Known role keys → display (product terminology). */
const ROLE_DISPLAY = {
  super_admin: "Super Admin",
  admin: "Admin",
  user: "Customer",
};

/**
 * Title-case each word (legacy short tokens only).
 */
export function tc(str) {
  if (str == null || str === "") return "";
  return String(str)
    .split(/\s+/)
    .map((w) => {
      if (!w) return w;
      return w.charAt(0).toUpperCase() + w.slice(1).toLowerCase();
    })
    .join(" ");
}

/**
 * Human-readable role (Super Admin, Admin, User).
 */
export function roleLabel(role) {
  if (role == null || role === "") return "";
  const normalized = String(role).trim().replace(/\s+/g, "_").toLowerCase();
  if (ROLE_DISPLAY[normalized]) return ROLE_DISPLAY[normalized];
  return String(role)
    .replace(/_/g, " ")
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase())
    .join(" ");
}

/** Complaint workflow status for badges and filters. */
export function complaintStatusLabel(status) {
  const s = String(status || "").toLowerCase();
  if (s === "open") return "Open";
  if (s === "in_progress") return "In progress";
  if (s === "resolved") return "Resolved";
  return tc(String(status || "").replace(/_/g, " "));
}

/** Sentiment pill text. */
export function sentimentLabel(value) {
  const s = String(value || "neutral").toLowerCase();
  if (s === "negative") return "Negative";
  if (s === "positive") return "Positive";
  return "Neutral";
}

/** Category pill (single word). */
export function categoryLabel(cat) {
  const s = String(cat || "general").toLowerCase();
  return s.charAt(0).toUpperCase() + s.slice(1);
}
