import { useEffect, useState } from "react";
import { Navigate } from "react-router-dom";
import toast from "react-hot-toast";
import { api } from "../lib/api";
import { getStoredUser } from "../lib/auth";
import { BarChart3 } from "lucide-react";

function PlatformAnalytics() {
  const me = getStoredUser();
  const [data, setData] = useState(null);

  useEffect(() => {
    if (me?.role !== "super_admin") return;
    (async () => {
      try {
        const { data: body } = await api.get("/admin/analytics");
        setData(body);
      } catch {
        toast.error("Could not load platform analytics.");
      }
    })();
  }, [me?.role]);

  if (me?.role !== "super_admin") {
    return <Navigate to="/" replace />;
  }

  if (!data?.success) {
    return (
      <div className="animate-pulse p-8 text-slate-500 dark:text-slate-400">
        Loading platform overview…
      </div>
    );
  }

  const t = data.tenants;
  const c = data.complaints;
  const u = data.users;
  const r = data.reliability;
  const g = data.governance;

  return (
    <div className="mx-auto max-w-5xl space-y-8 p-6">
      <div className="flex items-start gap-3">
        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md">
          <BarChart3 className="h-5 w-5" />
        </div>
        <div>
          <h1 className="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">
            Platform analytics
          </h1>
          <p className="mt-1 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            A simple “control tower” for the whole product: how many organizations are active, how much complaint
            traffic you have, and whether background jobs are failing. Think of it like a school attendance sheet for
            your servers.
          </p>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Active tenants
          </p>
          <p className="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{t.active}</p>
          <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Inactive: {t.inactive} · Premium orgs: {t.premium_subscriptions}
          </p>
        </div>
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Complaints (volume)
          </p>
          <p className="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{c.total}</p>
          <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Last 24h: {c.last_24_hours} · 7d: {c.last_7_days} · 30d: {c.last_30_days}
          </p>
        </div>
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            User accounts
          </p>
          <p className="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{u.total_accounts}</p>
          <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">Soft-deleted: {u.soft_deleted_accounts}</p>
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <div className="rounded-2xl border border-amber-200 bg-amber-50/90 p-5 dark:border-amber-900/50 dark:bg-amber-950/30">
          <p className="text-sm font-semibold text-amber-900 dark:text-amber-100">Reliability signal</p>
          <p className="mt-2 text-2xl font-bold text-amber-950 dark:text-amber-50">
            Failed queue jobs: {r.failed_queue_jobs}
          </p>
          <p className="mt-2 text-xs leading-relaxed text-amber-900/90 dark:text-amber-200/90">{r.note}</p>
        </div>
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <p className="text-sm font-semibold text-slate-900 dark:text-white">Governance</p>
          <p className="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
            Audit events (24h): {g.audit_events_last_24_hours}
          </p>
          <p className="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-400">{g.note}</p>
        </div>
      </div>
    </div>
  );
}

export default PlatformAnalytics;
