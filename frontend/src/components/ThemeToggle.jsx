import { useEffect, useState } from "react";
import { Moon, Sun } from "lucide-react";

function isDark() {
  if (typeof document === "undefined") return false;
  return document.documentElement.classList.contains("dark");
}

export function ThemeToggle({ className = "" }) {
  const [dark, setDark] = useState(isDark);

  useEffect(() => {
    const onChange = () => setDark(isDark());
    window.addEventListener("acd-theme", onChange);
    return () => window.removeEventListener("acd-theme", onChange);
  }, []);

  const toggle = () => {
    const next = !document.documentElement.classList.contains("dark");
    if (next) {
      document.documentElement.classList.add("dark");
      localStorage.setItem("theme", "dark");
    } else {
      document.documentElement.classList.remove("dark");
      localStorage.setItem("theme", "light");
    }
    setDark(next);
    window.dispatchEvent(new Event("acd-theme"));
  };

  return (
    <button
      type="button"
      onClick={toggle}
      className={`inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 ${className}`}
      aria-label="Toggle color theme"
    >
      {dark ? (
        <>
          <Sun className="h-4 w-4" /> Light mode
        </>
      ) : (
        <>
          <Moon className="h-4 w-4" /> Dark mode
        </>
      )}
    </button>
  );
}
