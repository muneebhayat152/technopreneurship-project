import { useEffect, useState, useMemo } from "react";
import { useParams, Link, Navigate } from "react-router-dom";
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  Tooltip,
  ResponsiveContainer,
} from "recharts";
import { api } from "../lib/api";
import toast from "react-hot-toast";
import { getStoredUser } from "../lib/auth";

const TABS = [
  { id: "diagnosis", label: "Diagnosis" },
  { id: "timeline", label: "Timeline" },
  { id: "samples", label: "Samples" },
];

function IssueDiagnosis() {
  const { id } = useParams();
  const [tab, setTab] = useState("diagnosis");
  const [diagnosis, setDiagnosis] = useState(null);
  const [timeline, setTimeline] = useState(null);
  const [loading, setLoading] = useState(true);

  const me = useMemo(() => getStoredUser(), []);

  useEffect(() => {
    if (!id) return;
    if (getStoredUser()?.role === "super_admin") {
      setLoading(false);
      return;
    }
    let cancelled = false;
    (async () => {
      setLoading(true);
      try {
        const [dRes, tRes] = await Promise.all([
          api.get(`/issues/${id}/diagnosis`),
          api.get(`/issues/${id}/timeline`),
        ]);
        if (!cancelled) {
          setDiagnosis(dRes.data.issue);
          setTimeline(tRes.data);
        }
      } catch (e) {
        if (e.response?.status === 402) {
          toast.error("Premium is required for diagnosis.");
        } else {
          toast.error(e.response?.data?.message || "Could not load diagnosis.");
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [id]);

  if (me?.role === "super_admin") {
    return <Navigate to="/" replace />;
  }

  if (loading || !diagnosis) {
    return (
      <div className="animate-pulse p-8 text-slate-500 dark:text-slate-400">
        Loading…
      </div>
    );
  }

  const chartData = (diagnosis.chart_7d || []).map((r) => ({
    date: r.date?.slice(5) || r.date,
    count: r.count,
  }));

  return (
    <div className="mx-auto max-w-4xl space-y-6 p-6">
      <div className="flex flex-wrap items-center gap-4 text-sm text-slate-600 dark:text-slate-400">
        <Link
          to="/issues"
          className="font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
        >
          ← Issue patterns
        </Link>
        <span className="text-slate-300 dark:text-slate-600">|</span>
        <span className="capitalize">
          Status: {timeline?.status || "—"}
        </span>
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <p className="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
          Analysis
        </p>
        <h1 className="mt-1 text-2xl font-bold text-slate-900 dark:text-white">
          {diagnosis.title}
        </h1>
        <p className="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
          Volume trend, keywords, and suggested responses.
        </p>
      </div>

      <div className="flex gap-2 border-b border-slate-200 dark:border-slate-700">
        {TABS.map((t) => (
          <button
            key={t.id}
            type="button"
            onClick={() => setTab(t.id)}
            className={`rounded-t-lg px-4 py-2 text-sm font-semibold transition-colors ${
              tab === t.id
                ? "bg-indigo-600 text-white"
                : "text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {tab === "diagnosis" && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div className="rounded-2xl bg-slate-900 p-5 text-white">
              <p className="text-sm text-slate-400">Complaints in pattern</p>
              <p className="mt-1 text-3xl font-bold">{diagnosis.complaint_count}</p>
            </div>
            <div className="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-900/50 dark:bg-red-950/40">
              <p className="text-sm text-red-800 dark:text-red-200">Severity</p>
              <p className="mt-1 text-2xl font-bold capitalize text-red-700 dark:text-red-300">
                {diagnosis.severity}
              </p>
            </div>
            <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
              <p className="text-sm text-slate-500 dark:text-slate-400">
                Change vs prior window
              </p>
              <p
                className={`mt-1 text-2xl font-bold ${
                  diagnosis.percent_change_last_period > 0
                    ? "text-red-600 dark:text-red-400"
                    : "text-emerald-600 dark:text-emerald-400"
                }`}
              >
                {diagnosis.percent_change_last_period > 0 ? "+" : ""}
                {diagnosis.percent_change_last_period}%
              </p>
            </div>
          </div>

          <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 className="font-semibold text-slate-900 dark:text-white">
              Keywords
            </h3>
            <div className="mt-3 flex flex-wrap gap-2">
              {(diagnosis.keywords || []).map((k) => (
                <span
                  key={k}
                  className="rounded-full bg-indigo-50 px-3 py-1 text-sm font-medium text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-200"
                >
                  {k}
                </span>
              ))}
            </div>
          </div>

          <div className="h-72 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 className="mb-4 font-semibold text-slate-900 dark:text-white">
              Recent volume
            </h3>
            <ResponsiveContainer width="100%" height="85%">
              <LineChart data={chartData}>
                <XAxis dataKey="date" stroke="#94a3b8" />
                <YAxis allowDecimals={false} stroke="#94a3b8" />
                <Tooltip
                  contentStyle={{
                    backgroundColor: "rgb(15 23 42)",
                    border: "1px solid rgb(51 65 85)",
                    borderRadius: "8px",
                  }}
                  labelStyle={{ color: "#e2e8f0" }}
                />
                <Line
                  type="monotone"
                  dataKey="count"
                  stroke="#6366f1"
                  strokeWidth={2}
                  dot={{ r: 3 }}
                />
              </LineChart>
            </ResponsiveContainer>
          </div>

          <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 className="font-semibold text-slate-900 dark:text-white">
              Suggested actions
            </h3>
            <ul className="ml-5 mt-3 list-disc space-y-2 text-slate-600 dark:text-slate-400">
              {(diagnosis.suggested_actions || []).map((s, i) => (
                <li key={i}>{s}</li>
              ))}
            </ul>
          </div>
        </div>
      )}

      {tab === "timeline" && (
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <h3 className="mb-6 font-semibold text-slate-900 dark:text-white">
            Issue Timeline
          </h3>
          <ul className="ml-2 space-y-6 border-l-2 border-indigo-200 pl-6 dark:border-indigo-800">
            {(timeline?.events || []).map((ev, idx) => (
              <li key={idx} className="relative">
                <span className="absolute -left-[1.35rem] top-1 h-3 w-3 rounded-full bg-indigo-600 ring-4 ring-indigo-100 dark:ring-indigo-950" />
                <p className="text-xs text-slate-400">
                  Day {ev.day} · {ev.date}
                </p>
                <p className="font-semibold text-slate-900 dark:text-white">
                  {ev.label}
                </p>
                <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
                  {ev.detail}
                </p>
              </li>
            ))}
          </ul>
        </div>
      )}

      {tab === "samples" && (
        <div className="space-y-4">
          <p className="text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            Sample complaints in this cluster.
          </p>
          {(diagnosis.sample_complaints || []).map((c) => (
            <div
              key={c.id}
              className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900"
            >
              <p className="text-slate-800 dark:text-slate-200">{c.text}</p>
              <p className="mt-2 text-xs text-slate-400">
                {c.user} · {c.sentiment} ·{" "}
                {c.created_at ? new Date(c.created_at).toLocaleDateString() : ""}
              </p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

export default IssueDiagnosis;
