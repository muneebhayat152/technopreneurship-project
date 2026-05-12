import { useState } from "react";
import toast from "react-hot-toast";
import { api } from "../lib/api";
import { Crown, Sparkles } from "lucide-react";

/**
 * Organization billing panel.
 * Super administrators change plans directly on the Organizations screen.
 * Organization administrators submit a request for platform super admin approval.
 */
export function OrganizationPlanPanel({
  subscription,
  onUpdated,
}) {
  const [loading, setLoading] = useState(false);

  const current = subscription === "premium" ? "premium" : "free";

  const setPlan = async (next) => {
    if (next === current) return;
    try {
      setLoading(true);
      await api.post("/admin/approval-requests", {
        type: "subscription_change",
        payload: { subscription: next },
      });
      toast.success(
        "Request sent to the platform super administrator. Your plan will change after approval."
      );
      onUpdated?.();
    } catch (e) {
      toast.error(e.response?.data?.message || "Could not submit plan request.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
      <div className="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 pb-4 dark:border-slate-800">
        <div>
          <p className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            Billing
          </p>
          <h2 className="mt-1 text-lg font-semibold text-slate-900 dark:text-white">
            Organization subscription
          </h2>
          <p className="mt-2 max-w-xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            Premium adds clustering, diagnosis, timelines, and Smart Alerts. Changing the plan requires approval from
            the platform super administrator so billing stays under central control.
          </p>
        </div>
        <div className="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 ring-1 ring-slate-200/80 dark:bg-slate-800 dark:ring-slate-600">
          {current === "premium" ? (
            <Crown className="h-5 w-5 text-amber-500" aria-hidden />
          ) : (
            <Sparkles className="h-5 w-5 text-slate-400" aria-hidden />
          )}
          <span className="text-sm font-medium text-slate-800 dark:text-slate-100">
            Current: {current === "premium" ? "Premium" : "Free"}
          </span>
        </div>
      </div>

      <div className="mt-5 flex flex-wrap gap-3">
        <button
          type="button"
          disabled={loading}
          onClick={() => setPlan("free")}
          className={`rounded-xl px-5 py-2.5 text-sm font-semibold transition ${
            current === "free"
              ? "bg-slate-900 text-white shadow-sm dark:bg-white dark:text-slate-900"
              : "border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
          }`}
        >
          Request Free
        </button>
        <button
          type="button"
          disabled={loading}
          onClick={() => setPlan("premium")}
          className={`rounded-xl px-5 py-2.5 text-sm font-semibold transition ${
            current === "premium"
              ? "bg-indigo-600 text-white shadow-sm"
              : "border border-indigo-200 bg-indigo-50 text-indigo-900 hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-100 dark:hover:bg-indigo-900/30"
          }`}
        >
          Request Premium
        </button>
      </div>
    </div>
  );
}
