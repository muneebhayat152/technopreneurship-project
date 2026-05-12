import { Link, useLocation, useNavigate } from "react-router-dom";
import {
  LayoutDashboard,
  MessageSquareText,
  Sparkles,
  Bell,
  Users,
  Building2,
  ScrollText,
  ClipboardCheck,
  LogOut,
} from "lucide-react";
import { roleLabel } from "../lib/format";
import { BrandLogo } from "./BrandLogo";
import { clearSession, getStoredUser } from "../lib/auth";
import { api } from "../lib/api";

function NavItem({ to, icon, label, active }) {
  return (
    <Link
      to={to}
      className={`group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold tracking-tight transition ${
        active
          ? "bg-indigo-500/[0.12] text-white ring-1 ring-indigo-400/35 shadow-sm shadow-indigo-950/30"
          : "text-slate-300 hover:bg-white/[0.07] hover:text-white"
      }`}
    >
      <span
        className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition ${
          active
            ? "bg-indigo-500/25 text-indigo-50 ring-1 ring-indigo-400/25"
            : "bg-white/5 text-slate-300 group-hover:bg-white/10 group-hover:text-white"
        }`}
      >
        {icon}
      </span>
      <span className="truncate">{label}</span>
    </Link>
  );
}

function Sidebar() {
  const location = useLocation();
  const navigate = useNavigate();

  const user = getStoredUser();
  const roleTitle = roleLabel(user?.role);

  const handleLogout = async () => {
    try {
      await api.post("/auth/logout");
    } catch {
      // Ignore network/logout failures and clear local session anyway.
    }
    clearSession();
    navigate("/login");
  };

  return (
    <aside className="relative flex h-full min-h-0 w-72 shrink-0 flex-col border-r border-white/10 bg-gradient-to-b from-slate-950 via-slate-950 to-slate-900 text-white shadow-[8px_0_40px_-12px_rgba(0,0,0,0.65)]">
      <div
        className="pointer-events-none absolute inset-0 bg-[radial-gradient(90%_55%_at_50%_-10%,rgba(99,102,241,0.22),transparent_55%)]"
        aria-hidden
      />

      {/* Full-height inset panel: geometry does not depend on flex children, so no “cut” above Audit log */}
      <div
        className="pointer-events-none absolute inset-3 rounded-2xl bg-slate-900/45 ring-1 ring-white/[0.07] shadow-[inset_0_1px_0_rgba(255,255,255,0.06)]"
        aria-hidden
      />

      <div className="relative z-10 flex min-h-0 flex-1 flex-col px-4 pb-4 pt-5">
        <div className="mb-6 flex items-center gap-3 px-1">
          <BrandLogo variant="sidebar" className="h-11 w-11" />
          <div className="min-w-0">
            <p className="text-sm font-semibold tracking-tight text-white">
              AI Complaint Doctor
            </p>
            {roleTitle ? (
              <p className="truncate text-xs font-medium text-slate-400">
                {roleTitle}
                {user?.role === "super_admin" ? (
                  <span className="mt-0.5 block truncate text-[11px] font-normal text-slate-500">
                    Organizations & approvals · tenant data stays with each organization
                  </span>
                ) : null}
              </p>
            ) : null}
          </div>
        </div>

        <nav className="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto">
          <NavItem
            to="/"
            icon={<LayoutDashboard className="h-4 w-4 opacity-90" />}
            label={user?.role === "super_admin" ? "Platform" : "Dashboard"}
            active={location.pathname === "/"}
          />

          {user?.role !== "super_admin" && (
            <NavItem
              to="/complaints"
              icon={<MessageSquareText className="h-4 w-4 opacity-90" />}
              label="Complaints"
              active={location.pathname === "/complaints"}
            />
          )}

          {user?.role !== "super_admin" && (
            <NavItem
              to="/issues"
              icon={<Sparkles className="h-4 w-4 opacity-90" />}
              label="Issue patterns"
              active={
                location.pathname === "/issues" ||
                location.pathname.startsWith("/diagnosis")
              }
            />
          )}

          {user?.role !== "super_admin" && (
            <NavItem
              to="/alerts"
              icon={<Bell className="h-4 w-4 opacity-90" />}
              label="Smart alerts"
              active={location.pathname === "/alerts"}
            />
          )}

          {user?.role === "admin" && (
            <NavItem
              to="/admin"
              icon={<Users className="h-4 w-4 opacity-90" />}
              label="Users"
              active={location.pathname === "/admin"}
            />
          )}

          {(user?.role === "admin" || user?.role === "super_admin") && (
            <NavItem
              to="/approvals"
              icon={<ClipboardCheck className="h-4 w-4 opacity-90" />}
              label={user?.role === "super_admin" ? "Approval queue" : "My requests"}
              active={location.pathname === "/approvals"}
            />
          )}

          {user?.role === "super_admin" && (
            <NavItem
              to="/companies"
              icon={<Building2 className="h-4 w-4 opacity-90" />}
              label="Organizations"
              active={location.pathname === "/companies"}
            />
          )}

          {user?.role === "super_admin" && (
            <NavItem
              to="/platform/audit-log"
              icon={<ScrollText className="h-4 w-4 opacity-90" />}
              label="Audit log"
              active={location.pathname === "/platform/audit-log"}
            />
          )}
        </nav>

        <button
          type="button"
          onClick={handleLogout}
          className="mt-auto shrink-0 flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/[0.06] px-4 py-2.5 text-sm font-semibold text-slate-100 backdrop-blur-sm transition hover:bg-white/[0.11]"
        >
          <LogOut className="h-4 w-4 opacity-90" />
          Sign out
        </button>

        <div className="mt-3 shrink-0 text-center text-[11px] font-medium text-slate-500">
          © {new Date().getFullYear()} AI Complaint Doctor
        </div>
      </div>
    </aside>
  );
}

export default Sidebar;