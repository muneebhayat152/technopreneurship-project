import { useEffect, useMemo, useRef, useState, useCallback } from "react";
import { Navigate } from "react-router-dom";
import toast from "react-hot-toast";
import { api } from "../lib/api";
import { getStoredUser } from "../lib/auth";
import { ClipboardCheck, Inbox } from "lucide-react";

function statusBadge(status) {
  const s = String(status || "").toLowerCase();
  if (s === "pending") {
    return "bg-amber-50 text-amber-900 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-100 dark:ring-amber-800";
  }
  if (s === "approved") {
    return "bg-emerald-50 text-emerald-900 ring-1 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-100 dark:ring-emerald-800";
  }
  return "bg-slate-100 text-slate-800 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-100 dark:ring-slate-600";
}

function typeLabel(t) {
  if (t === "subscription_change") return "Subscription change";
  if (t === "user_delete") return "Remove user";
  if (t === "user_promote_admin") return "Promote to admin";
  if (t === "complaint_status_change") return "Complaint status change";
  return t;
}

/**
 * Super admin: review pending organization-admin requests.
 * Organization admin: see requests you submitted and their status.
 */
function ApprovalRequestsPage() {
  const me = useMemo(() => getStoredUser(), []);
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [pendingOnly, setPendingOnly] = useState(true);
  const lastToastAtRef = useRef(0);

  const load = useCallback(async () => {
    if (!me || !["super_admin", "admin"].includes(me.role)) return;
    try {
      setLoading(true);
      const params =
        me.role === "super_admin" ? { pending_only: pendingOnly ? 1 : 0 } : {};
      const { data } = await api.get("/admin/approval-requests", { params });
      setRows(data.requests || []);
    } catch (e) {
      // Avoid toast spam if a request keeps failing (network/CORS/auth issues).
      const now = Date.now();
      if (now - lastToastAtRef.current > 2000) {
        lastToastAtRef.current = now;
        toast.error(e.userMessage || "Could not load requests.");
      }
    } finally {
      setLoading(false);
    }
  }, [me, pendingOnly]);

  useEffect(() => {
    const t = setTimeout(() => {
      load();
    }, 0);
    return () => clearTimeout(t);
  }, [load]);

  if (!me || !["super_admin", "admin"].includes(me.role)) {
    return <Navigate to="/" replace />;
  }

  const approve = async (id) => {
    try {
      await api.post(`/admin/approval-requests/${id}/approve`, {});
      toast.success("Approved and applied.");
      load();
    } catch (e) {
      toast.error(e.response?.data?.message || "Approve failed.");
    }
  };

  const reject = async (id) => {
    const note = window.prompt("Optional note for the organization admin (or leave blank):") || "";
    try {
      await api.post(`/admin/approval-requests/${id}/reject`, { note });
      toast.success("Request rejected.");
      load();
    } catch (e) {
      toast.error(e.response?.data?.message || "Reject failed.");
    }
  };

  return (
    <div className="mx-auto max-w-5xl space-y-8 p-6">
      <div className="flex items-start gap-3">
        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md">
          {me.role === "super_admin" ? (
            <ClipboardCheck className="h-5 w-5" />
          ) : (
            <Inbox className="h-5 w-5" />
          )}
        </div>
        <div>
          <h1 className="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">
            {me.role === "super_admin" ? "Approval queue" : "Your plan & access requests"}
          </h1>
          <p className="mt-1 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            {me.role === "super_admin"
              ? "Organization administrators can do a lot—but changing billing, removing people, or granting admin rights needs your sign-off as platform super administrator."
              : "Sensitive changes are sent to the platform super administrator. You will see Pending until they approve or reject."}
          </p>
        </div>
      </div>

      {me.role === "super_admin" && (
        <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
          <input
            type="checkbox"
            checked={pendingOnly}
            onChange={(e) => setPendingOnly(e.target.checked)}
            className="rounded border-slate-300"
          />
          Show only pending
        </label>
      )}

      <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        {loading ? (
          <div className="p-10 text-center text-slate-500">Loading…</div>
        ) : rows.length === 0 ? (
          <div className="p-10 text-center text-slate-500 dark:text-slate-400">
            No requests to show.
          </div>
        ) : (
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase text-slate-500 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-400">
              <tr>
                <th className="px-4 py-3">When</th>
                {me.role === "super_admin" && <th className="px-4 py-3">Organization</th>}
                <th className="px-4 py-3">Type</th>
                <th className="px-4 py-3">Details</th>
                {me.role === "super_admin" && <th className="px-4 py-3">Requested by</th>}
                <th className="px-4 py-3">Status</th>
                {me.role === "super_admin" && <th className="px-4 py-3 text-right">Actions</th>}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {rows.map((r) => (
                <tr key={r.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                  <td className="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                    {r.created_at ? new Date(r.created_at).toLocaleString() : "—"}
                  </td>
                  {me.role === "super_admin" && (
                    <td className="px-4 py-3 text-slate-800 dark:text-slate-200">
                      {r.company?.name || "—"}
                    </td>
                  )}
                  <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">
                    {typeLabel(r.type)}
                  </td>
                  <td className="max-w-xs px-4 py-3 text-xs text-slate-600 dark:text-slate-400">
                    <pre className="whitespace-pre-wrap break-words font-sans">
                      {JSON.stringify(r.payload || {}, null, 0)}
                    </pre>
                  </td>
                  {me.role === "super_admin" && (
                    <td className="px-4 py-3 text-slate-700 dark:text-slate-300">
                      {r.requester?.email || "—"}
                    </td>
                  )}
                  <td className="px-4 py-3">
                    <span
                      className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${statusBadge(r.status)}`}
                    >
                      {r.status}
                    </span>
                    {r.reviewer_note ? (
                      <p className="mt-1 text-xs text-slate-500">Note: {r.reviewer_note}</p>
                    ) : null}
                  </td>
                  {me.role === "super_admin" && (
                    <td className="px-4 py-3 text-right">
                      {r.status === "pending" ? (
                        <div className="flex justify-end gap-2">
                          <button
                            type="button"
                            onClick={() => approve(r.id)}
                            className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                          >
                            Approve
                          </button>
                          <button
                            type="button"
                            onClick={() => reject(r.id)}
                            className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800"
                          >
                            Reject
                          </button>
                        </div>
                      ) : (
                        <span className="text-xs text-slate-400">—</span>
                      )}
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}

export default ApprovalRequestsPage;
