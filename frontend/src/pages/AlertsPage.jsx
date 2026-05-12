/* eslint-disable react-hooks/set-state-in-effect */

import { useEffect, useState } from "react";
import { api } from "../lib/api";
import toast from "react-hot-toast";
import { Bell, Lock } from "lucide-react";

function AlertsPage() {
  const [alerts, setAlerts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [premiumLocked, setPremiumLocked] = useState(false);

  const load = async () => {
    try {
      setPremiumLocked(false);
      const { data } = await api.get("/alerts");
      setAlerts(data.alerts || []);
    } catch (e) {
      if (e.response?.status === 402) {
        setPremiumLocked(true);
        setAlerts([]);
      } else {
        toast.error(e.response?.data?.message || "Could not load alerts.");
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const markRead = async (id) => {
    try {
      await api.post(`/alerts/${id}/read`);
      load();
    } catch {
      toast.error("Could not update alert.");
    }
  };

  if (loading) {
    return (
      <div className="animate-pulse p-8 text-slate-500 dark:text-slate-400">
        Loading…
      </div>
    );
  }

  if (premiumLocked) {
    return (
      <div className="mx-auto max-w-lg rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800">
          <Lock className="h-7 w-7 text-slate-600 dark:text-slate-300" />
        </div>
        <h1 className="text-2xl font-semibold text-slate-900 dark:text-white">
          Smart alerts
        </h1>
        <p className="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
          Smart alerts require Premium access. Ask a platform or organization administrator to assign Premium to your
          account or upgrade the organization under Users → Access tier.
        </p>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-4xl space-y-8">
      <div className="flex items-start gap-3">
        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md">
          <Bell className="h-5 w-5" />
        </div>
        <div>
          <h1 className="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">
            Smart alerts
          </h1>
          <p className="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            Automated signals when complaint patterns cross defined thresholds.
          </p>
        </div>
      </div>

      {alerts.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-14 text-center text-sm leading-relaxed text-slate-600 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-400">
          No active alerts. Signals appear when monitoring thresholds are met.
        </div>
      ) : (
        <ul className="space-y-3">
          {alerts.map((a) => (
            <li
              key={a.id}
              className={`rounded-2xl border p-5 transition dark:border-slate-700 ${
                a.severity === "critical"
                  ? "border-red-200 bg-red-50 dark:border-red-900/50 dark:bg-red-950/30"
                  : a.severity === "warning"
                    ? "border-amber-200 bg-amber-50 dark:border-amber-900/50 dark:bg-amber-950/30"
                    : "border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
              }`}
            >
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="font-semibold text-slate-900 dark:text-white">
                    {a.title}
                  </p>
                  {a.body && (
                    <p className="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                      {a.body}
                    </p>
                  )}
                  <p className="mt-2 text-xs text-slate-400">
                    {a.triggered_at
                      ? new Date(a.triggered_at).toLocaleString()
                      : ""}
                  </p>
                </div>
                {!a.is_read && (
                  <button
                    type="button"
                    onClick={() => markRead(a.id)}
                    className="shrink-0 text-sm font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                  >
                    Mark read
                  </button>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

export default AlertsPage;
