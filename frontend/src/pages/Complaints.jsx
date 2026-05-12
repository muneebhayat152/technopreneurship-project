import { useCallback, useEffect, useMemo, useState } from "react";
import toast from "react-hot-toast";
import { api } from "../lib/api";
import { useNavigate, Navigate } from "react-router-dom";
import {
  complaintStatusLabel,
  sentimentLabel,
  categoryLabel,
} from "../lib/format";
import { ClipboardList, Pencil, Trash2, CheckCircle, Loader2, PlayCircle } from "lucide-react";
import { clearSession, getStoredUser, isAuthenticated } from "../lib/auth";

function Complaints() {
  const [complaints, setComplaints] = useState([]);
  const [text, setText] = useState("");
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState("");
  const [filter, setFilter] = useState("all");
  const [editing, setEditing] = useState(null);
  const [editText, setEditText] = useState("");

  const navigate = useNavigate();

  const storedUser = useMemo(() => {
    return getStoredUser();
  }, []);

  const role = storedUser.role || "user";
  const userId = storedUser.id;
  const canManageStatus = role === "admin";

  const handleLogout = useCallback(async () => {
    try {
      await api.post("/auth/logout");
    } catch {
      // Ignore logout network failures.
    } finally {
      clearSession();
      navigate("/login");
    }
  }, [navigate]);

  const fetchComplaints = useCallback(async () => {
    try {
      setLoading(true);
      const res = await api.get("/complaints");
      setComplaints(res.data.complaints || []);
    } catch (err) {
      if (err.response?.status === 401) {
        toast.error("Session expired. Sign in again.");
        handleLogout();
        return;
      }
      toast.error("Could not load complaints.");
    } finally {
      setLoading(false);
    }
  }, [handleLogout]);

  useEffect(() => {
    if (!isAuthenticated()) {
      navigate("/login");
      return undefined;
    }

    const t = setTimeout(() => {
      fetchComplaints();
    }, 0);

    const handleStorage = () => fetchComplaints();
    window.addEventListener("storage", handleStorage);
    return () => {
      clearTimeout(t);
      window.removeEventListener("storage", handleStorage);
    };
  }, [fetchComplaints, navigate]);

  if (storedUser?.role === "super_admin") {
    return <Navigate to="/" replace />;
  }

  const handleSubmit = async () => {
    if (!text.trim()) {
      toast.error("Enter a description.");
      return;
    }
    if (role === "super_admin") {
      toast.error("Switch to an organization account to submit complaints.");
      return;
    }

    try {
      setLoading(true);
      await api.post("/complaints", { complaint_text: text });
      setText("");
      await fetchComplaints();
      toast.success("Complaint submitted.");
    } catch (err) {
      if (err.response?.status === 401) {
        handleLogout();
        return;
      }
      toast.error(err.response?.data?.message || "Submission failed.");
    } finally {
      setLoading(false);
    }
  };

  const resolveComplaint = async (id) => {
    try {
      const res = await api.put(`/complaints/${id}/status`, { status: "resolved" });
      await fetchComplaints();
      if (res.status === 202 || res.data?.pending) {
        toast.success(
          res.data?.message ||
            "Submitted for platform super administrator approval. Status will change after approval."
        );
      } else {
        toast.success("Status updated to resolved.");
      }
    } catch (err) {
      if (err.response?.status === 403) {
        toast.error("You do not have permission to change status.");
      } else if (err.response?.status === 401) {
        handleLogout();
      } else {
        toast.error("Update failed.");
      }
    }
  };

  const markInProgress = async (id) => {
    try {
      const res = await api.put(`/complaints/${id}/status`, { status: "in_progress" });
      await fetchComplaints();
      if (res.status === 202 || res.data?.pending) {
        toast.success(
          res.data?.message ||
            "Submitted for platform super administrator approval. The customer will see the new status after approval."
        );
      } else {
        toast.success("Marked as in progress — the customer will see this on their dashboard.");
      }
    } catch (err) {
      if (err.response?.status === 403) {
        toast.error("You do not have permission to change status.");
      } else if (err.response?.status === 401) {
        handleLogout();
      } else {
        toast.error("Update failed.");
      }
    }
  };

  const saveEdit = async () => {
    if (!editing || !editText.trim()) return;
    try {
      await api.put(`/complaints/${editing}`, {
        complaint_text: editText.trim(),
      });
      setEditing(null);
      setEditText("");
      await fetchComplaints();
      toast.success("Changes saved.");
    } catch {
      toast.error("Could not save changes.");
    }
  };

  const deleteComplaint = async (id) => {
    if (!window.confirm("Delete this complaint?")) return;
    try {
      await api.delete(`/complaints/${id}`);
      await fetchComplaints();
      toast.success("Complaint removed.");
    } catch (err) {
      if (err.response?.status === 403) {
        toast.error("You do not have permission to delete this record.");
      } else if (err.response?.status === 401) {
        handleLogout();
      } else {
        toast.error("Delete failed.");
      }
    }
  };

  const startEdit = (c) => {
    setEditing(c.id);
    setEditText(c.complaint_text || "");
  };

  const inProgressMine = useMemo(() => {
    if (role !== "user") return 0;
    return complaints.filter((c) => c.status === "in_progress").length;
  }, [complaints, role]);

  const filteredComplaints = complaints
    .filter((c) =>
      (c.complaint_text || "").toLowerCase().includes(search.toLowerCase())
    )
    .filter((c) => {
      if (filter === "all") return true;
      return c.status === filter;
    });

  const statusStyles = (status) => {
    if (status === "open")
      return "bg-amber-50 text-amber-900 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-100 dark:ring-amber-800";
    if (status === "in_progress")
      return "bg-sky-50 text-sky-950 ring-1 ring-sky-200 dark:bg-sky-950/50 dark:text-sky-100 dark:ring-sky-800";
    if (status === "resolved")
      return "bg-emerald-50 text-emerald-900 ring-1 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-100 dark:ring-emerald-800";
    return "bg-slate-100 text-slate-800 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-100 dark:ring-slate-600";
  };

  const sentimentStyles = (sentiment) => {
    if (sentiment === "negative")
      return "bg-rose-50 text-rose-900 ring-1 ring-rose-200 dark:bg-rose-950/40 dark:text-rose-100 dark:ring-rose-800";
    if (sentiment === "positive")
      return "bg-emerald-50 text-emerald-900 ring-1 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-100 dark:ring-emerald-800";
    return "bg-slate-100 text-slate-700 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-600";
  };

  return (
    <div className="mx-auto max-w-7xl space-y-8">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
            Operations
          </p>
          <h1 className="mt-1 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">
            Complaints
          </h1>
          <p className="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            {role === "user"
              ? "Submit feedback and track status. When you see “In progress”, your team is actively working on it."
              : "Intake and triage. Administrators set Open → In progress → Resolved; customers may edit or delete their own items."}
          </p>
        </div>
      </div>

      {role === "user" && inProgressMine > 0 && (
        <div className="rounded-2xl border border-sky-200 bg-sky-50/95 p-5 shadow-sm dark:border-sky-900/50 dark:bg-sky-950/40">
          <div className="flex flex-wrap items-start gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-600 text-white shadow">
              <Loader2 className="h-5 w-5 animate-spin" aria-hidden />
            </div>
            <div>
              <p className="font-semibold text-sky-950 dark:text-sky-100">
                {inProgressMine === 1
                  ? "1 of your complaints is in progress"
                  : `${inProgressMine} of your complaints are in progress`}
              </p>
              <p className="mt-1 text-sm leading-relaxed text-sky-900/90 dark:text-sky-200/90">
                That means someone on your organization’s admin team has started handling it. You will still see it
                here until it is marked resolved.
              </p>
            </div>
          </div>
        </div>
      )}

      {role !== "super_admin" && (
        <div className="card card-pad">
          <div className="mb-4 flex items-center gap-2">
            <ClipboardList className="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
            <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
              New complaint
            </h2>
          </div>
          <textarea
            className="input min-h-[120px] resize-y"
            placeholder="Describe the issue."
            value={text}
            onChange={(e) => setText(e.target.value)}
          />
          <button
            onClick={handleSubmit}
            disabled={loading}
            className="btn-primary mt-4"
            type="button"
          >
            {loading ? "Submitting…" : "Submit"}
          </button>
        </div>
      )}

      {role === "super_admin" && (
        <div className="rounded-xl border border-indigo-200/80 bg-indigo-50/90 px-4 py-3 text-sm leading-relaxed text-indigo-950 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-100">
          Cross-tenant overview. To record complaints, sign in as an organization
          user.
        </div>
      )}

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <input
          type="search"
          placeholder="Search"
          className="input flex-1"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <select
          className="input max-w-xs"
          value={filter}
          onChange={(e) => setFilter(e.target.value)}
        >
          <option value="all">All statuses</option>
          <option value="open">Open</option>
          <option value="in_progress">In Progress</option>
          <option value="resolved">Resolved</option>
        </select>
      </div>

      <div className="card overflow-hidden p-0 dark:border-slate-700">
        {loading ? (
          <div className="p-12 text-center text-slate-500 dark:text-slate-400">
            Loading…
          </div>
        ) : filteredComplaints.length === 0 ? (
          <div className="p-12 text-center text-slate-500 dark:text-slate-400">
            No complaints match these filters.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50 text-xs font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-400">
                <tr>
                  <th className="px-5 py-4">#</th>
                  <th className="px-5 py-4">Submitter</th>
                  <th className="px-5 py-4">Organization</th>
                  <th className="px-5 py-4">Detail</th>
                  <th className="px-5 py-4">Status</th>
                  <th className="px-5 py-4">Sentiment</th>
                  <th className="px-5 py-4">Category</th>
                  <th className="px-5 py-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {filteredComplaints.map((c, index) => {
                  const isOwner = Number(c.user_id) === Number(userId);
                  const canEditRow = isOwner;
                  const canDeleteRow = role === "super_admin" || isOwner;
                  const showResolve =
                    canManageStatus &&
                    c.status !== "resolved";
                  const showMarkProgress =
                    canManageStatus &&
                    c.status === "open";

                  return (
                    <tr
                      key={c.id}
                      className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                    >
                      <td className="px-5 py-4 font-medium text-slate-500 dark:text-slate-400">
                        {index + 1}
                      </td>
                      <td className="px-5 py-4 font-medium text-slate-900 dark:text-slate-100">
                        {c.user?.name || "—"}
                      </td>
                      <td className="px-5 py-4 text-slate-600 dark:text-slate-300">
                        {c.user?.company?.name || "—"}
                      </td>
                      <td className="max-w-md px-5 py-4 text-slate-800 dark:text-slate-200">
                        {editing === c.id ? (
                          <textarea
                            className="input min-h-[88px]"
                            value={editText}
                            onChange={(e) => setEditText(e.target.value)}
                          />
                        ) : (
                          <span className="leading-relaxed">{c.complaint_text}</span>
                        )}
                      </td>
                      <td className="px-5 py-4">
                        <div className="flex flex-col gap-1">
                          <span
                            className={`inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-semibold ${statusStyles(c.status)}`}
                          >
                            {complaintStatusLabel(c.status)}
                          </span>
                          {role === "user" && c.status === "in_progress" && (
                            <span className="text-xs font-medium text-sky-800 dark:text-sky-200">
                              In progress — your team is working on this.
                            </span>
                          )}
                        </div>
                      </td>
                      <td className="px-5 py-4">
                        <span
                          className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${sentimentStyles(c.sentiment)}`}
                        >
                          {sentimentLabel(c.sentiment)}
                        </span>
                      </td>
                      <td className="px-5 py-4">
                        <span className="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-800 ring-1 ring-indigo-100 dark:bg-indigo-950/50 dark:text-indigo-100 dark:ring-indigo-900">
                          {categoryLabel(c.category)}
                        </span>
                      </td>
                      <td className="px-5 py-4">
                        <div className="flex flex-wrap justify-end gap-2">
                          {showMarkProgress && (
                            <button
                              type="button"
                              onClick={() => markInProgress(c.id)}
                              className="inline-flex items-center gap-1 rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700"
                            >
                              <PlayCircle className="h-3.5 w-3.5" />
                              In progress
                            </button>
                          )}
                          {showResolve && (
                            <button
                              type="button"
                              onClick={() => resolveComplaint(c.id)}
                              className="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                            >
                              <CheckCircle className="h-3.5 w-3.5" />
                              Resolve
                            </button>
                          )}
                          {canEditRow && (
                            <>
                              {editing === c.id ? (
                                <>
                                  <button
                                    type="button"
                                    onClick={saveEdit}
                                    className="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700"
                                  >
                                    Save
                                  </button>
                                  <button
                                    type="button"
                                    onClick={() => {
                                      setEditing(null);
                                      setEditText("");
                                    }}
                                    className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
                                  >
                                    Cancel
                                  </button>
                                </>
                              ) : (
                                <button
                                  type="button"
                                  onClick={() => startEdit(c)}
                                  className="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800"
                                >
                                  <Pencil className="h-3.5 w-3.5" />
                                  Edit
                                </button>
                              )}
                            </>
                          )}
                          {canDeleteRow && editing !== c.id && (
                              <button
                                type="button"
                                onClick={() => deleteComplaint(c.id)}
                                className="inline-flex items-center gap-1 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700"
                              >
                                <Trash2 className="h-3.5 w-3.5" />
                                Delete
                              </button>
                            )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}

export default Complaints;
