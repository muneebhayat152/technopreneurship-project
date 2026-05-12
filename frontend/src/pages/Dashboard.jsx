/* eslint-disable react-hooks/set-state-in-effect */

import { useEffect, useState, useCallback } from "react";
import { Link } from "react-router-dom";
import { api } from "../lib/api";
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  PieChart,
  Pie,
  Cell,
  ResponsiveContainer,
  LineChart,
  Line,
} from "recharts";
import { roleLabel } from "../lib/format";
import { OrganizationPlanPanel } from "../components/OrganizationPlanPanel";
import { clearSession, isAuthenticated, saveUser } from "../lib/auth";

function Dashboard() {
  const [data, setData] = useState(null);
  const [user, setUser] = useState(null);

  const fetchUser = useCallback(async () => {
    try {
      const res = await api.get("/user");
      setUser(res.data.user);
      saveUser(res.data.user);
    } catch {
      clearSession();
      window.location.href = "/login";
    }
  }, []);

  const fetchData = useCallback(async () => {
    try {
      const res = await api.get("/dashboard");
      setData(res.data);
    } catch {
      clearSession();
      window.location.href = "/login";
    }
  }, []);

  useEffect(() => {
    if (!isAuthenticated()) {
      window.location.href = "/login";
      return;
    }
    fetchUser();
    fetchData();
    const interval = setInterval(fetchData, 15000);
    const handleStorage = () => fetchData();
    window.addEventListener("storage", handleStorage);
    return () => {
      clearInterval(interval);
      window.removeEventListener("storage", handleStorage);
    };
  }, [fetchUser, fetchData]);

  const handleLogout = async () => {
    try {
      await api.post("/auth/logout");
    } catch {
      // Ignore logout network failures.
    } finally {
      clearSession();
      window.location.href = "/login";
    }
  };

  if (!data) {
    return (
      <div className="mx-auto max-w-6xl animate-pulse space-y-6 p-6">
        <div className="h-20 rounded-xl bg-slate-200 dark:bg-slate-800" />
        <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
          <div className="h-24 rounded-xl bg-slate-200 dark:bg-slate-800" />
          <div className="h-24 rounded-xl bg-slate-200 dark:bg-slate-800" />
          <div className="h-24 rounded-xl bg-slate-200 dark:bg-slate-800" />
        </div>
      </div>
    );
  }

  if (data.platform_overview) {
    const org = data.organizations || {};
    const isPremium = data.plan?.is_premium === true;

    return (
      <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 p-6 dark:from-slate-950 dark:to-slate-900">
        <div className="mx-auto max-w-6xl">
          <div className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div>
              <p className="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                Platform
              </p>
              <h1 className="mt-1 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">
                Organizations overview
              </h1>
              <p className="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                Welcome back,{" "}
                <span className="font-medium text-slate-800 dark:text-slate-200">
                  {user?.name}
                </span>
                . Signed in as {roleLabel(user?.role)} ·{" "}
                <span
                  className={
                    isPremium
                      ? "font-medium text-emerald-600 dark:text-emerald-400"
                      : "font-medium text-slate-600 dark:text-slate-400"
                  }
                >
                  {isPremium ? "Premium" : "Free"}
                </span>{" "}
                for platform tools.
              </p>
              <div className="mt-4 flex flex-wrap gap-2">
                <Link
                  to="/companies"
                  className="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                >
                  Organizations
                </Link>
                <Link
                  to="/approvals"
                  className="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
                >
                  Approval queue
                </Link>
                <Link
                  to="/platform/audit-log"
                  className="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
                >
                  Audit log
                </Link>
              </div>
            </div>
            <button
              type="button"
              onClick={handleLogout}
              className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
            >
              Sign out
            </button>
          </div>

          {data.privacy_note && (
            <div className="mb-6 rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm leading-relaxed text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
              {data.privacy_note}
            </div>
          )}

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div className="rounded-2xl bg-slate-900 p-5 text-white shadow dark:ring-1 dark:ring-white/10">
              <p className="text-sm text-slate-400">Organizations</p>
              <p className="mt-1 text-3xl font-bold">{org.total ?? 0}</p>
            </div>
            <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm dark:border-emerald-900/50 dark:bg-emerald-950/40">
              <p className="text-sm font-medium text-emerald-900 dark:text-emerald-200">Active tenants</p>
              <p className="mt-1 text-3xl font-bold text-emerald-950 dark:text-emerald-50">
                {org.active_tenants ?? 0}
              </p>
            </div>
            <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/40">
              <p className="text-sm font-medium text-amber-900 dark:text-amber-200">Pending registration</p>
              <p className="mt-1 text-3xl font-bold text-amber-950 dark:text-amber-50">
                {org.pending_registration ?? 0}
              </p>
            </div>
            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
              <p className="text-sm text-slate-500 dark:text-slate-400">Rejected registration</p>
              <p className="mt-1 text-3xl font-bold text-slate-900 dark:text-white">
                {org.rejected_registration ?? 0}
              </p>
            </div>
            <div className="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm dark:border-indigo-900/50 dark:bg-indigo-950/40">
              <p className="text-sm font-medium text-indigo-900 dark:text-indigo-200">Pending approvals</p>
              <p className="mt-1 text-3xl font-bold text-indigo-950 dark:text-indigo-50">
                {data.pending_approval_requests ?? 0}
              </p>
              <p className="mt-1 text-xs text-indigo-800/80 dark:text-indigo-300/90">
                Subscription, status changes, and similar requests from org admins
              </p>
            </div>
          </div>
        </div>
      </div>
    );
  }

  const isPremium = data.plan?.is_premium === true;

  const statusData = [
    { name: "Open", value: data.status?.open || 0 },
    { name: "In progress", value: data.status?.in_progress || 0 },
    { name: "Resolved", value: data.status?.resolved || 0 },
  ];

  const sentimentData = [
    { name: "Positive", value: data.sentiment?.positive || 0 },
    { name: "Negative", value: data.sentiment?.negative || 0 },
    { name: "Neutral", value: data.sentiment?.neutral || 0 },
  ];

  const COLORS = ["#22c55e", "#ef4444", "#94a3b8"];

  const trendData = (data.trend_7d || []).map((r) => ({
    date: r.date?.slice(5) || r.date,
    count: r.count,
  }));

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 p-6 dark:from-slate-950 dark:to-slate-900">
      <div className="mx-auto max-w-6xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <div>
            <p className="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
              Overview
            </p>
            <h1 className="mt-1 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">
              Dashboard
            </h1>
            <p className="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
              Welcome back,{" "}
              <span className="font-medium text-slate-800 dark:text-slate-200">
                {user?.name}
              </span>
              . Signed in as {roleLabel(user?.role)} ·{" "}
              <span
                className={
                  isPremium
                    ? "font-medium text-emerald-600 dark:text-emerald-400"
                    : "font-medium text-slate-600 dark:text-slate-400"
                }
              >
                {isPremium ? "Premium" : "Free"}
              </span>{" "}
              plan.
            </p>
            <div className="mt-4 flex flex-wrap gap-2">
              <Link
                to="/issues"
                className="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
              >
                Issue patterns
              </Link>
              <Link
                to="/alerts"
                className="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
              >
                Smart alerts
              </Link>
            </div>
          </div>
          <button
            type="button"
            onClick={handleLogout}
            className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
          >
            Sign out
          </button>
        </div>

        {user?.role === "user" && (data.status?.in_progress ?? 0) > 0 && (
          <div className="mb-6 rounded-2xl border border-sky-200 bg-sky-50 p-5 shadow-sm dark:border-sky-900/50 dark:bg-sky-950/40">
            <p className="text-sm font-semibold text-sky-950 dark:text-sky-100">
              Good news — we are working on {data.status.in_progress}{" "}
              {data.status.in_progress === 1 ? "complaint" : "complaints"} for you right now.
            </p>
            <p className="mt-2 text-sm leading-relaxed text-sky-900/90 dark:text-sky-200/90">
              “In progress” means your organization’s team has picked up the case and it is no longer sitting in the
              “open” queue. You can always see details on the Complaints page.
            </p>
            <Link
              to="/complaints"
              className="mt-4 inline-flex rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800"
            >
              View my complaints
            </Link>
          </div>
        )}

        {user?.role === "admin" && user?.company?.id && (
          <div className="mb-6">
            <OrganizationPlanPanel
              subscription={user.company.subscription}
              onUpdated={() => {
                fetchUser();
                fetchData();
              }}
            />
          </div>
        )}

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <div className="rounded-2xl bg-slate-900 p-5 text-white shadow dark:ring-1 dark:ring-white/10">
            <p className="text-sm text-slate-400">Total in scope</p>
            <p className="text-3xl font-bold mt-1">{data.total_complaints}</p>
          </div>
          <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p className="text-sm text-slate-500 dark:text-slate-400">Today</p>
            <p className="mt-1 text-3xl font-bold text-slate-900 dark:text-white">
              {data.complaints_today ?? 0}
            </p>
            <p className="mt-1 text-xs text-slate-400">Received today</p>
          </div>
          <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/40">
            <p className="text-sm text-amber-800 dark:text-amber-200">
              Critical queue (today)
            </p>
            <p className="mt-1 text-3xl font-bold text-amber-900 dark:text-amber-100">
              {data.critical_issues_today ?? 0}
            </p>
            <p className="mt-1 text-xs text-amber-800/80 dark:text-amber-300/90">
              High priority or negative sentiment
            </p>
          </div>
          <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p className="text-sm text-slate-500 dark:text-slate-400">
              Open vs resolved
            </p>
            <p className="mt-2 text-lg font-semibold text-slate-900 dark:text-white">
              {data.status?.open ?? 0} open · {data.status?.resolved ?? 0}{" "}
              resolved
            </p>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
          <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 className="font-semibold text-slate-900 dark:text-white">
              7-day volume
            </h2>
            <ResponsiveContainer width="100%" height={260}>
              <LineChart data={trendData}>
                <XAxis dataKey="date" />
                <YAxis allowDecimals={false} />
                <Tooltip />
                <Line
                  type="monotone"
                  dataKey="count"
                  stroke="#4f46e5"
                  strokeWidth={2}
                  dot={{ r: 3 }}
                />
              </LineChart>
            </ResponsiveContainer>
          </div>
          <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 className="font-semibold text-slate-900 dark:text-white">
              Sentiment
            </h2>
            <ResponsiveContainer width="100%" height={260}>
              <PieChart>
                <Pie data={sentimentData} dataKey="value" outerRadius={90} label>
                  {sentimentData.map((entry, index) => (
                    <Cell key={entry.name} fill={COLORS[index % COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
          <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 className="font-semibold text-slate-900 dark:text-white">
              Status
            </h2>
            <ResponsiveContainer width="100%" height={240}>
              <BarChart data={statusData}>
                <XAxis dataKey="name" />
                <YAxis allowDecimals={false} />
                <Tooltip />
                <Bar dataKey="value" fill="#6366f1" radius={[6, 6, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
          <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 className="font-semibold text-slate-900 dark:text-white">
              Categories
            </h2>
            <div className="mt-6 grid grid-cols-3 gap-3">
              <div className="rounded-xl border border-slate-100 bg-slate-50 p-4 text-center dark:border-slate-700 dark:bg-slate-800/80">
                <p className="text-2xl font-bold text-slate-900 dark:text-white">
                  {data.category?.delivery || 0}
                </p>
                <p className="text-sm text-slate-500 dark:text-slate-400">Delivery</p>
              </div>
              <div className="rounded-xl border border-slate-100 bg-slate-50 p-4 text-center dark:border-slate-700 dark:bg-slate-800/80">
                <p className="text-2xl font-bold text-slate-900 dark:text-white">
                  {data.category?.payment || 0}
                </p>
                <p className="text-sm text-slate-500 dark:text-slate-400">Payment</p>
              </div>
              <div className="rounded-xl border border-slate-100 bg-slate-50 p-4 text-center dark:border-slate-700 dark:bg-slate-800/80">
                <p className="text-2xl font-bold text-slate-900 dark:text-white">
                  {data.category?.service || 0}
                </p>
                <p className="text-sm text-slate-500 dark:text-slate-400">Service</p>
              </div>
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 className="font-semibold text-slate-900 dark:text-white">
              Top issues
            </h2>
            {isPremium ? (
              <ul className="mt-4 space-y-3">
                {(data.top_issues || []).map((issue) => (
                  <li
                    key={issue.id}
                    className="flex items-center justify-between rounded-xl border border-slate-100 px-4 py-3 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800/60"
                  >
                    <div>
                      <p className="font-medium text-slate-900 dark:text-white">
                        {issue.title}
                      </p>
                      <p className="text-sm text-slate-500 dark:text-slate-400">
                        {issue.count} complaints ·{" "}
                        <span className="capitalize">{issue.severity}</span>
                      </p>
                    </div>
                    <Link
                      to={`/diagnosis/${issue.id}`}
                      className="shrink-0 text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                    >
                      View
                    </Link>
                  </li>
                ))}
              </ul>
            ) : (
              <div className="mt-4 space-y-3">
                <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm leading-relaxed text-slate-600 dark:border-slate-600 dark:bg-slate-800/80 dark:text-slate-400">
                  Clustering, diagnosis, and Smart Alerts require Premium.
                </div>
                <p className="text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                  Upgrade to Premium for pattern detection, timelines, and alerts.
                </p>
              </div>
            )}
          </div>

          <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 className="font-semibold text-slate-900 dark:text-white">
              Customer mood
            </h2>
            {isPremium ? (
              <p className="mt-4 text-2xl font-semibold text-slate-800 dark:text-slate-100">
                {data.customer_mood || "—"}
              </p>
            ) : (
              <div className="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm leading-relaxed text-slate-600 dark:border-slate-600 dark:bg-slate-800/80 dark:text-slate-400">
                Mood summary is included with Premium.
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

export default Dashboard;
