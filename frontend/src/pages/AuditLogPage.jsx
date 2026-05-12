import { useEffect, useMemo, useState } from "react";
import { Navigate } from "react-router-dom";
import toast from "react-hot-toast";
import { api } from "../lib/api";
import { getStoredUser } from "../lib/auth";
import { ScrollText } from "lucide-react";

function AuditLogPage() {
  const me = useMemo(() => getStoredUser(), []);
  const [logs, setLogs] = useState([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);

  const load = async (p = 1) => {
    try {
      setLoading(true);
      const { data } = await api.get("/admin/audit-logs", { params: { per_page: 25, page: p } });
      setLogs(data.logs || []);
      setPage(data.pagination?.current_page || 1);
      setLastPage(data.pagination?.last_page || 1);
    } catch {
      toast.error("Could not load audit log.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (me?.role !== "super_admin") return;
    const t = setTimeout(() => {
      load(1);
    }, 0);
    return () => clearTimeout(t);
  }, [me?.role]);

  if (me?.role !== "super_admin") {
    return <Navigate to="/" replace />;
  }

  return (
    <div className="mx-auto max-w-6xl space-y-8 p-6">
      <div className="flex items-start gap-3">
        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md">
          <ScrollText className="h-5 w-5" />
        </div>
        <div>
          <h1 className="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">
            Audit log
          </h1>
          <p className="mt-1 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            This is the “who did what” notebook for important changes: subscription changes, turning an organization off,
            removing a user, or changing a complaint’s status. It helps you prove accountability in a demo or real
            business review.
          </p>
        </div>
      </div>

      <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        {loading ? (
          <div className="p-10 text-center text-slate-500 dark:text-slate-400">Loading…</div>
        ) : logs.length === 0 ? (
          <div className="p-10 text-center text-slate-500 dark:text-slate-400">No entries yet.</div>
        ) : (
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-400">
              <tr>
                <th className="px-4 py-3">When</th>
                <th className="px-4 py-3">Who</th>
                <th className="px-4 py-3">Action</th>
                <th className="px-4 py-3">Details</th>
                <th className="px-4 py-3">IP</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {logs.map((row) => (
                <tr key={row.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                  <td className="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                    {row.created_at ? new Date(row.created_at).toLocaleString() : "—"}
                  </td>
                  <td className="px-4 py-3 text-slate-800 dark:text-slate-200">
                    {row.user?.email || row.user_email || "—"}
                  </td>
                  <td className="px-4 py-3 font-mono text-xs text-indigo-700 dark:text-indigo-300">{row.action}</td>
                  <td className="max-w-md px-4 py-3 text-xs text-slate-600 dark:text-slate-400">
                    <pre className="whitespace-pre-wrap break-words font-sans">
                      {row.metadata ? JSON.stringify(row.metadata, null, 0) : "—"}
                    </pre>
                  </td>
                  <td className="whitespace-nowrap px-4 py-3 text-xs text-slate-500">{row.ip_address || "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {lastPage > 1 && (
        <div className="flex justify-center gap-2">
          <button
            type="button"
            disabled={page <= 1 || loading}
            onClick={() => load(page - 1)}
            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold disabled:opacity-40 dark:border-slate-600"
          >
            Previous
          </button>
          <span className="self-center text-sm text-slate-600 dark:text-slate-400">
            Page {page} / {lastPage}
          </span>
          <button
            type="button"
            disabled={page >= lastPage || loading}
            onClick={() => load(page + 1)}
            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold disabled:opacity-40 dark:border-slate-600"
          >
            Next
          </button>
        </div>
      )}
    </div>
  );
}

export default AuditLogPage;
