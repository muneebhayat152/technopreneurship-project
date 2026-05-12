/* eslint-disable react-hooks/set-state-in-effect */

import { useCallback, useEffect, useState, useMemo } from "react";
import { Navigate } from "react-router-dom";
import toast from "react-hot-toast";
import { api } from "../lib/api";
import { Users } from "lucide-react";
import { roleLabel } from "../lib/format";
import { clearSession, getStoredUser, isAuthenticated, saveUser } from "../lib/auth";

function describeAccessTier(u) {
  if (u.access_tier === "premium") return "Premium (assigned)";
  if (u.access_tier === "free") return "Free (assigned)";
  const org = u.company?.subscription === "premium" ? "Premium" : "Free";
  return `Inherit org (${org})`;
}

function AdminUsers() {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(false);
  const [actionLoading, setActionLoading] = useState(null);

  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [role, setRole] = useState("user");
  const [createAccessTier, setCreateAccessTier] = useState("");

  const [editUser, setEditUser] = useState(null);

  const [me, setMe] = useState(null);

  const canAccess = useMemo(() => {
    const r = getStoredUser().role;
    return r === "admin";
  }, []);

  const handleLogout = useCallback(async () => {
    try {
      await api.post("/auth/logout");
    } catch {
      // Ignore logout network failures.
    } finally {
      clearSession();
      window.location.href = "/login";
    }
  }, []);

  const fetchUsers = useCallback(async () => {
    try {
      setLoading(true);

      const res = await api.get("/admin/users");

      setUsers(res.data.users || []);
    } catch (err) {
      if (err.response?.status === 401) {
        toast.error("Session expired. Sign in again.");
        handleLogout();
      } else {
        toast.error(err.response?.data?.message || "Could not load users.");
      }
    } finally {
      setLoading(false);
    }
  }, [handleLogout]);

  useEffect(() => {
    if (!isAuthenticated()) return handleLogout();
    if (!canAccess) return;

    (async () => {
      try {
        const r = await api.get("/user");
        setMe(r.data.user);
      } catch {
        /* ignore */
      }
    })();

    fetchUsers();

    const handleStorage = () => {
      fetchUsers();
    };

    window.addEventListener("storage", handleStorage);

    return () => {
      window.removeEventListener("storage", handleStorage);
    };
  }, [canAccess, fetchUsers, handleLogout]);

  if (!canAccess) {
    return <Navigate to="/" replace />;
  }

  const createUser = async () => {
    if (!name || !email || !password) {
      toast.error("All fields are required.");
      return;
    }

    try {
      setActionLoading("create");

      const payload = { name, email, password, role };
      if (createAccessTier === "free" || createAccessTier === "premium") {
        payload.access_tier = createAccessTier;
      }

      await api.post("/admin/users", payload);

      toast.success("User created.");

      setName("");
      setEmail("");
      setPassword("");
      setRole("user");
      setCreateAccessTier("");

      fetchUsers();
    } catch (err) {
      toast.error(err.response?.data?.message || "Create failed.");
    } finally {
      setActionLoading(null);
    }
  };

  const updateUser = async () => {
    try {
      setActionLoading(editUser.id);

      const body = {
        name: editUser.name,
        email: editUser.email,
        role: editUser.role,
      };
      const canSetTier = me?.role === "admin" && editUser.role !== "super_admin";
      if (canSetTier) {
        body.access_tier = editUser.access_tier === "" ? null : editUser.access_tier;
      }

      await api.put(`/admin/users/${editUser.id}`, body);

      toast.success("User updated.");
      if (editUser.id === me?.id) {
        try {
          const r = await api.get("/user");
          setMe(r.data.user);
          saveUser(r.data.user);
        } catch {
          /* ignore */
        }
      }
      setEditUser(null);
      fetchUsers();
    } catch (err) {
      toast.error(err.response?.data?.message || "Update failed.");
    } finally {
      setActionLoading(null);
    }
  };

  const deleteUser = async (id) => {
    const msg =
      "Submit a removal request? The account stays active until the platform super administrator approves.";
    if (!window.confirm(msg)) return;

    try {
      setActionLoading(id);

      const res = await api.delete(`/admin/users/${id}`);

      if (res.status === 202 || res.data?.pending) {
        toast.success(res.data.message || "Submitted for super administrator approval.");
      } else {
        toast.success("User removed.");
      }
      fetchUsers();
    } catch (err) {
      toast.error(err.response?.data?.message || "Delete failed.");
    } finally {
      setActionLoading(null);
    }
  };

  const submitPromoteRequest = async (targetUserId) => {
    try {
      setActionLoading(`promote-${targetUserId}`);
      await api.post("/admin/approval-requests", {
        type: "user_promote_admin",
        payload: { target_user_id: targetUserId },
      });
      toast.success("Promotion request sent to the platform super administrator.");
    } catch (err) {
      toast.error(err.response?.data?.message || "Request failed.");
    } finally {
      setActionLoading(null);
    }
  };

  const restoreUser = async (id) => {
    try {
      setActionLoading(id);

      await api.put(`/admin/users/${id}/restore`, {});

      toast.success("User restored.");
      fetchUsers();
    } catch (err) {
      toast.error(err.response?.data?.message || "Restore Failed");
    } finally {
      setActionLoading(null);
    }
  };

  return (
    <div className="mx-auto max-w-6xl space-y-8 p-6">
      <div className="flex items-start gap-3">
        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md">
          <Users className="h-5 w-5" />
        </div>
        <div>
          <h1 className="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">
            Users
          </h1>
          <p className="mt-1 max-w-xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            Manage people in your organization. Assign Premium or Free access per user. Sensitive removals or promoting
            admins may still require platform approval.
          </p>
        </div>
      </div>

      <div className="card card-pad">
        <h2 className="mb-4 text-lg font-semibold text-slate-900 dark:text-white">
          Add user
        </h2>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <label className="label">Full Name</label>
            <input
              placeholder="Full Name"
              className="input mt-2"
              value={name}
              onChange={(e) => setName(e.target.value)}
            />
          </div>

          <div>
            <label className="label">Email</label>
            <input
              placeholder="Email"
              className="input mt-2"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </div>

          <div>
            <label className="label">Password</label>
            <input
              placeholder="Password"
              type="password"
              className="input mt-2"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </div>

          <div>
            <label className="label">Role</label>
            <select
              value={role}
              onChange={(e) => setRole(e.target.value)}
              className="input mt-2"
            >
              <option value="user">Customer</option>
            </select>
          </div>

          <div className="sm:col-span-2 lg:col-span-2">
            <label className="label">Access tier (personal)</label>
            <select
              value={createAccessTier}
              onChange={(e) => setCreateAccessTier(e.target.value)}
              className="input mt-2"
            >
              <option value="">Inherit organization plan</option>
              <option value="free">Free (override)</option>
              <option value="premium">Premium (override)</option>
            </select>
            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
              Overrides organization Free/Premium for this user only (Issue patterns and Smart alerts).
            </p>
          </div>
        </div>

        <button
          type="button"
          onClick={createUser}
          className="btn-primary mt-4"
          disabled={actionLoading === "create"}
        >
          {actionLoading === "create" ? "Creating…" : "Create"}
        </button>
      </div>

      {editUser && (
        <div className="card card-pad border-amber-200 bg-amber-50/80 dark:border-amber-900/50 dark:bg-amber-950/30">
          <h2 className="mb-4 text-lg font-semibold text-slate-900 dark:text-white">
            Edit user
          </h2>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <label className="label">Full Name</label>
              <input
                className="input mt-2"
                value={editUser.name}
                onChange={(e) =>
                  setEditUser({ ...editUser, name: e.target.value })
                }
              />
            </div>

            <div>
              <label className="label">Email</label>
              <input
                className="input mt-2"
                type="email"
                value={editUser.email}
                onChange={(e) =>
                  setEditUser({ ...editUser, email: e.target.value })
                }
              />
            </div>

            <div>
              <label className="label">Role</label>
              {editUser.role === "admin" ? (
                <p className="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                  {editUser.id === me?.id
                    ? "You are signed in as an organization administrator. Your role is fixed here; only a platform super administrator can change it."
                    : `${roleLabel("admin")} — only a platform super administrator can change another administrator’s role.`}
                </p>
              ) : (
                <p className="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                  {roleLabel("user")} — use “Request admin promotion” below to ask the platform super administrator to
                  grant organization admin rights.
                </p>
              )}
            </div>

            {me?.role === "admin" && editUser.role !== "super_admin" && (
              <div className="sm:col-span-2 lg:col-span-3">
                <label className="label">Access tier (personal)</label>
                <select
                  className="input mt-2"
                  value={editUser.access_tier ?? ""}
                  onChange={(e) =>
                    setEditUser({ ...editUser, access_tier: e.target.value })
                  }
                >
                  <option value="">Inherit organization plan</option>
                  <option value="free">Free (override)</option>
                  <option value="premium">Premium (override)</option>
                </select>
              </div>
            )}
          </div>

          {me?.role === "admin" && editUser.role === "user" && (
            <div className="mt-4 rounded-xl border border-indigo-200 bg-indigo-50/80 px-4 py-3 dark:border-indigo-900/50 dark:bg-indigo-950/30">
              <p className="text-sm text-indigo-950 dark:text-indigo-100">
                Need this person to help manage the organization? Ask the platform super administrator to approve.
              </p>
              <button
                type="button"
                disabled={actionLoading === `promote-${editUser.id}`}
                onClick={() => submitPromoteRequest(editUser.id)}
                className="btn-primary mt-3"
              >
                {actionLoading === `promote-${editUser.id}` ? "Submitting…" : "Request admin promotion"}
              </button>
            </div>
          )}

          <div className="mt-4 flex flex-wrap gap-2">
            <button
              type="button"
              onClick={updateUser}
              className="btn-primary"
              disabled={actionLoading === editUser.id}
            >
              Save changes
            </button>

            <button
              type="button"
              onClick={() => setEditUser(null)}
              className="btn-secondary"
            >
              Cancel
            </button>
          </div>
        </div>
      )}

      <div className="card overflow-hidden p-0">
        {loading ? (
          <div className="p-10 text-center text-slate-500 dark:text-slate-400">
            Loading…
          </div>
        ) : users.length === 0 ? (
          <div className="p-10 text-center text-slate-500 dark:text-slate-400">
            No users found.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-400">
                <tr>
                  <th className="px-5 py-4">#</th>
                  <th className="px-5 py-4">Name</th>
                  <th className="px-5 py-4">Email</th>
                  <th className="px-5 py-4">Organization</th>
                  <th className="px-5 py-4">Access</th>
                  <th className="px-5 py-4">Status</th>
                  <th className="px-5 py-4">Role</th>
                  <th className="px-5 py-4 text-right">Actions</th>
                </tr>
              </thead>

              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {users.map((u, index) => {
                  const isDeleted = u.deleted_at !== null;

                  return (
                    <tr
                      key={u.id}
                      className={
                        isDeleted
                          ? "bg-rose-50/80 opacity-90 dark:bg-rose-950/20"
                          : "hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                      }
                    >
                      <td className="px-5 py-4 text-slate-500 dark:text-slate-400">
                        {index + 1}
                      </td>
                      <td className="px-5 py-4 font-medium text-slate-900 dark:text-slate-100">
                        {u.name}
                      </td>
                      <td className="px-5 py-4 text-slate-600 dark:text-slate-300">
                        {u.email}
                      </td>
                      <td className="px-5 py-4 text-slate-600 dark:text-slate-300">
                        {u.company?.name || "—"}
                      </td>

                      <td className="px-5 py-4 text-xs text-slate-600 dark:text-slate-300">
                        {describeAccessTier(u)}
                      </td>

                      <td className="px-5 py-4">
                        {isDeleted ? (
                          <span className="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-800 dark:bg-rose-950/60 dark:text-rose-200">
                            Removed
                          </span>
                        ) : (
                          <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200">
                            Active
                          </span>
                        )}
                      </td>

                      <td className="px-5 py-4 text-slate-700 dark:text-slate-300">
                        {roleLabel(u.role)}
                      </td>

                      <td className="px-5 py-4">
                        <div className="flex flex-wrap justify-end gap-2">
                          {isDeleted ? (
                            <button
                              type="button"
                              onClick={() => restoreUser(u.id)}
                              className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                            >
                              Restore
                            </button>
                          ) : (
                            <>
                              <button
                                type="button"
                                onClick={() =>
                                  setEditUser({
                                    ...u,
                                    access_tier: u.access_tier ?? "",
                                  })
                                }
                                className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800"
                              >
                                Edit
                              </button>

                              <button
                                type="button"
                                onClick={() => deleteUser(u.id)}
                                className="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700"
                              >
                                Remove
                              </button>
                            </>
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

export default AdminUsers;
