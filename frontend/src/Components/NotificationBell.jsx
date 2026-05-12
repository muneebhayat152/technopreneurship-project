import { useCallback, useEffect, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Bell, CheckCheck, Loader2 } from "lucide-react";
import toast from "react-hot-toast";
import { api } from "../lib/api";
import { isAuthenticated } from "../lib/auth";

/**
 * In-app bell for approval-related alerts (database notifications + same events as email).
 */
export function NotificationBell() {
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [unread, setUnread] = useState(0);
  const [items, setItems] = useState([]);
  const wrapRef = useRef(null);

  const load = useCallback(async () => {
    if (!isAuthenticated()) return;
    try {
      setLoading(true);
      const { data } = await api.get("/user/notifications", { params: { per_page: 12 } });
      setUnread(data.unread_count ?? 0);
      setItems(data.notifications || []);
    } catch {
      /* ignore when session expired — api interceptor may redirect */
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!isAuthenticated()) return undefined;
    const kick = setTimeout(() => {
      load();
    }, 0);
    const t = setInterval(load, 45000);
    return () => {
      clearTimeout(kick);
      clearInterval(t);
    };
  }, [load]);

  useEffect(() => {
    if (!open) return undefined;
    const kick = setTimeout(() => {
      load();
    }, 0);
    const onDoc = (e) => {
      if (wrapRef.current && !wrapRef.current.contains(e.target)) {
        setOpen(false);
      }
    };
    document.addEventListener("mousedown", onDoc);
    return () => {
      clearTimeout(kick);
      document.removeEventListener("mousedown", onDoc);
    };
  }, [open, load]);

  const markRead = async (id) => {
    try {
      const { data } = await api.post(`/user/notifications/${id}/read`);
      setUnread(data.unread_count ?? 0);
      setItems((prev) => prev.map((n) => (n.id === id ? { ...n, read: true } : n)));
    } catch {
      toast.error("Could not mark notification read.");
    }
  };

  const markAllRead = async () => {
    try {
      const { data } = await api.post("/user/notifications/read-all");
      setUnread(data.unread_count ?? 0);
      setItems((prev) => prev.map((n) => ({ ...n, read: true })));
      toast.success("All caught up.");
    } catch {
      toast.error("Could not clear notifications.");
    }
  };

  const openItem = async (n) => {
    if (!n.read) {
      await markRead(n.id);
    }
    if (n.path) {
      navigate(n.path);
      setOpen(false);
    }
  };

  const badge =
    unread > 0 ? (unread > 9 ? "9+" : String(unread)) : null;

  return (
    <div className="relative" ref={wrapRef}>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
        aria-label="Notifications"
        aria-expanded={open}
      >
        <Bell className="h-5 w-5" />
        {badge ? (
          <span className="absolute -right-0.5 -top-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white shadow">
            {badge}
          </span>
        ) : null}
      </button>

      {open && (
        <div className="absolute right-0 z-50 mt-2 w-[min(100vw-2rem,22rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
          <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
            <p className="text-sm font-semibold text-slate-900 dark:text-white">Notifications</p>
            <div className="flex items-center gap-2">
              {unread > 0 && (
                <button
                  type="button"
                  onClick={markAllRead}
                  className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-950/50"
                >
                  <CheckCheck className="h-3.5 w-3.5" />
                  Mark all read
                </button>
              )}
            </div>
          </div>

          <div className="max-h-80 overflow-y-auto">
            {loading && items.length === 0 ? (
              <div className="flex items-center justify-center gap-2 py-10 text-sm text-slate-500">
                <Loader2 className="h-4 w-4 animate-spin" />
                Loading…
              </div>
            ) : items.length === 0 ? (
              <p className="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                No notifications yet. Approval updates will appear here and in your email.
              </p>
            ) : (
              <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                {items.map((n) => (
                  <li key={n.id}>
                    <button
                      type="button"
                      onClick={() => openItem(n)}
                      className={`flex w-full flex-col gap-0.5 px-4 py-3 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/80 ${
                        n.read ? "opacity-75" : "bg-indigo-50/50 dark:bg-indigo-950/20"
                      }`}
                    >
                      <span className="font-semibold text-slate-900 dark:text-white">{n.title}</span>
                      <span className="line-clamp-2 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                        {n.body}
                      </span>
                      <span className="text-[10px] font-medium uppercase tracking-wide text-slate-400">
                        {n.created_at ? new Date(n.created_at).toLocaleString() : ""}
                        {n.path ? " · Tap to open" : ""}
                      </span>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
