/* eslint-disable react-hooks/set-state-in-effect */

import { useEffect, useState, useMemo } from "react";
import { Navigate } from "react-router-dom";
import toast from "react-hot-toast";
import { api } from "../lib/api";
import { getStoredUser } from "../lib/auth";

function Companies() {
  const [companies, setCompanies] = useState([]);

  const isSuperAdmin = useMemo(() => {
    const u = getStoredUser();
    return u.role === "super_admin";
  }, []);

  const fetchCompanies = async () => {
    try {
      const res = await api.get("/companies");
      const rows = res.data.companies || [];
      const rank = (s) => {
        if (s === "pending") return 0;
        if (s === "active") return 1;
        if (s === "rejected") return 2;
        return 1;
      };
      rows.sort((a, b) => rank(a.registration_status || "active") - rank(b.registration_status || "active"));
      setCompanies(rows);
    } catch {
      toast.error("Could not load organizations.");
    }
  };

  const approveRegistration = async (id, subscription) => {
    try {
      await api.post(`/companies/${id}/approve-registration`, { subscription });
      fetchCompanies();
      toast.success(
        subscription === "premium"
          ? "Approved and activated on Premium."
          : "Approved and activated on Free."
      );
    } catch (err) {
      toast.error(err.response?.data?.message || "Approve failed.");
    }
  };

  const rejectRegistration = async (id) => {
    const note = window.prompt("Optional note (shown in audit log only):") || "";
    try {
      await api.post(`/companies/${id}/reject-registration`, { note });
      fetchCompanies();
      toast.success("Registration rejected.");
    } catch (err) {
      toast.error(err.response?.data?.message || "Reject failed.");
    }
  };

  useEffect(() => {
    if (!isSuperAdmin) return;
    fetchCompanies();
  }, [isSuperAdmin]);

  if (!isSuperAdmin) {
    return <Navigate to="/" replace />;
  }

  const toggleStatus = async (id) => {
    try {
      await api.post(`/companies/${id}/toggle`, {});
      fetchCompanies();
      toast.success("Organization status updated.");
    } catch (err) {
      toast.error(err.response?.data?.message || "Update failed.");
    }
  };

  const setSubscription = async (id, subscription) => {
    try {
      await api.put(`/companies/${id}/subscription`, { subscription });
      fetchCompanies();
      toast.success(
        `Plan set to ${subscription === "premium" ? "Premium" : "Free"}.`
      );
    } catch (err) {
      toast.error(
        err.response?.data?.message ||
          "Could not update plan. Only platform super administrators can change plans directly."
      );
    }
  };

  const deleteOrganization = async (c) => {
    const ok = window.confirm(
      `Permanently delete organization "${c.name}" (${c.email})? All users and complaints for this tenant will be removed.`
    );
    if (!ok) return;
    try {
      await api.delete(`/companies/${c.id}`);
      fetchCompanies();
      toast.success("Organization deleted.");
    } catch (err) {
      toast.error(err.response?.data?.message || "Could not delete organization.");
    }
  };

  return (
    <div className="mx-auto max-w-6xl space-y-6 p-6">
      <div>
        <h1 className="mb-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">
          Organizations
        </h1>
        <p className="max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
          New self-registered tenants start as <strong>Free</strong> and <strong>pending</strong> until a platform owner
          approves them (as Free or Premium) or rejects. You can still toggle active/inactivate and change plan for{" "}
          <strong>approved</strong> organizations.
        </p>
      </div>

      <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <table className="w-full text-left text-sm">
          <thead>
            <tr className="border-b border-slate-200 text-slate-500 dark:border-slate-700 dark:text-slate-400">
              <th className="py-3 pr-4">#</th>
              <th className="py-3 pr-4">Name</th>
              <th className="py-3 pr-4">Email</th>
              <th className="py-3 pr-4">Industry</th>
              <th className="py-3 pr-4">Country</th>
              <th className="py-3 pr-4">Plan</th>
              <th className="py-3 pr-4">Registration</th>
              <th className="py-3 pr-4">Status</th>
              <th className="py-3">Actions</th>
            </tr>
          </thead>

          <tbody>
            {companies.map((c, index) => (
              <tr
                key={c.id}
                className="border-b border-slate-100 hover:bg-slate-50/80 dark:border-slate-800 dark:hover:bg-slate-800/50"
              >
                <td className="py-3 pr-4 text-slate-500 dark:text-slate-400">
                  {index + 1}
                </td>
                <td className="py-3 pr-4 font-medium text-slate-900 dark:text-slate-100">
                  {c.name}
                </td>
                <td className="py-3 pr-4 text-slate-600 dark:text-slate-300">
                  {c.email}
                </td>
                <td className="py-3 pr-4 text-slate-600 dark:text-slate-300">
                  {c.industry || "—"}
                </td>
                <td className="py-3 pr-4 text-slate-600 dark:text-slate-300">
                  {c.country || "—"}
                </td>
                <td className="py-3 pr-4">
                  <span
                    className={
                      c.subscription === "premium"
                        ? "rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200"
                        : "rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                    }
                  >
                    {c.subscription === "premium" ? "Premium" : "Free"}
                  </span>
                </td>
                <td className="py-3 pr-4">
                  {c.registration_status === "pending" && (
                    <span className="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">
                      Pending approval
                    </span>
                  )}
                  {c.registration_status === "rejected" && (
                    <span className="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-900 dark:bg-red-950/50 dark:text-red-200">
                      Rejected
                    </span>
                  )}
                  {(c.registration_status === "active" || !c.registration_status) && (
                    <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                      Approved
                    </span>
                  )}
                </td>
                <td className="py-3 pr-4">
                  {c.is_active ? (
                    <span className="rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-800 dark:bg-green-950/50 dark:text-green-200">
                      Active
                    </span>
                  ) : (
                    <span className="rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-800 dark:bg-red-950/50 dark:text-red-200">
                      Inactive
                    </span>
                  )}
                </td>
                <td className="py-3">
                  <div className="flex flex-wrap gap-2">
                    {c.registration_status === "pending" && (
                      <>
                        <button
                          type="button"
                          onClick={() => approveRegistration(c.id, "free")}
                          className="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600"
                        >
                          Approve (Free)
                        </button>
                        <button
                          type="button"
                          onClick={() => approveRegistration(c.id, "premium")}
                          className="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700"
                        >
                          Approve (Premium)
                        </button>
                        <button
                          type="button"
                          onClick={() => rejectRegistration(c.id)}
                          className="rounded-lg border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-800 hover:bg-red-100 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200 dark:hover:bg-red-950/70"
                        >
                          Reject
                        </button>
                      </>
                    )}
                    {c.registration_status === "rejected" && (
                      <button
                        type="button"
                        onClick={() => deleteOrganization(c)}
                        className="rounded-lg border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-800 hover:bg-red-100 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200 dark:hover:bg-red-950/70"
                      >
                        Delete org
                      </button>
                    )}
                    {c.registration_status !== "pending" && c.registration_status !== "rejected" && (
                      <>
                        <button
                          type="button"
                          onClick={() => toggleStatus(c.id)}
                          className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        >
                          Toggle Active
                        </button>
                        <button
                          type="button"
                          onClick={() => setSubscription(c.id, "free")}
                          className="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600"
                        >
                          Free
                        </button>
                        <button
                          type="button"
                          onClick={() => setSubscription(c.id, "premium")}
                          className="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700"
                        >
                          Premium
                        </button>
                        <button
                          type="button"
                          onClick={() => deleteOrganization(c)}
                          className="rounded-lg border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-800 hover:bg-red-100 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200 dark:hover:bg-red-950/70"
                        >
                          Delete org
                        </button>
                      </>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export default Companies;
