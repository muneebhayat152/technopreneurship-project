import { useCallback, useEffect, useMemo, useState } from "react";
import { Link, Navigate } from "react-router-dom";
import { api } from "../lib/api";
import toast from "react-hot-toast";
import { Layers, Lock } from "lucide-react";
import { getStoredUser } from "../lib/auth";

function Issues() {
  const [issues, setIssues] = useState([]);
  const [loading, setLoading] = useState(true);
  const [premiumLocked, setPremiumLocked] = useState(false);

  const me = useMemo(() => getStoredUser(), []);

  const loadIssues = useCallback(async () => {
    setLoading(true);
    try {
      setPremiumLocked(false);
      const { data } = await api.get("/issues");
      setIssues(data.issues || []);
    } catch (e) {
      if (e.response?.status === 402) {
        setPremiumLocked(true);
        setIssues([]);
      } else {
        toast.error(e.response?.data?.message || "Could not load patterns.");
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      if (cancelled) return;
      await loadIssues();
    })();
    return () => {
      cancelled = true;
    };
  }, [loadIssues]);

  if (me?.role === "super_admin") {
    return <Navigate to="/" replace />;
  }

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
          Issue patterns
        </h1>
        <p className="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
          Premium analytics requires Premium access. Your organization administrator can assign Free or Premium per user
          or upgrade the organization plan.
        </p>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-5xl space-y-8 p-6">
      <div className="flex items-start gap-3">
        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md">
          <Layers className="h-5 w-5" />
        </div>
        <div className="min-w-0 flex-1">
          <h1 className="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">
            Issue patterns
          </h1>
          <p className="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            Grouped complaint themes for your organization (Premium).
          </p>
        </div>
      </div>

      {issues.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-8 py-14 text-center text-sm leading-relaxed text-slate-600 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-400">
          <p>No patterns yet. Add a few complaints (similar wording helps grouping), then check again.</p>
        </div>
      ) : (
        <ul className="space-y-4">
          {issues.map((issue) => (
            <li
              key={issue.id}
              className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md dark:border-slate-700 dark:bg-slate-900"
            >
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  {issue.company_name && (
                    <p className="mb-1 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                      {issue.company_name}
                    </p>
                  )}
                  <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
                    {issue.title}
                  </h2>
                  <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    {issue.complaint_count} complaints ·{" "}
                    <span className="capitalize">{issue.severity}</span>
                  </p>
                  <div className="mt-3 flex flex-wrap gap-2">
                    {(issue.keywords || []).slice(0, 6).map((k) => (
                      <span
                        key={k}
                        className="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-200"
                      >
                        {k}
                      </span>
                    ))}
                  </div>
                </div>
                <Link
                  to={`/diagnosis/${issue.id}`}
                  className="inline-flex shrink-0 items-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"
                >
                  Open
                </Link>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

export default Issues;
