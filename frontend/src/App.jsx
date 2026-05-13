import {
  BrowserRouter,
  Routes,
  Route,
  Navigate,
  useLocation,
} from "react-router-dom";
import { useEffect } from "react";

import Dashboard from "./pages/Dashboard";
import Complaints from "./pages/Complaints";
import Login from "./pages/Login";
import Register from "./pages/Register";
import AdminUsers from "./pages/AdminUsers";
import Companies from "./pages/Companies";
import Sidebar from "./components/Sidebar";
import { ThemeToggle } from "./components/ThemeToggle";
import { NotificationBell } from "./components/NotificationBell";
import IssueDiagnosis from "./pages/IssueDiagnosis";
import Issues from "./pages/Issues";
import AlertsPage from "./pages/AlertsPage";
import AuditLogPage from "./pages/AuditLogPage";
import ApprovalRequestsPage from "./pages/ApprovalRequestsPage";
import { api } from "./lib/api";
import { clearSession, isAuthenticated, saveUser } from "./lib/auth";

function ProtectedRoute({ children }) {
  if (!isAuthenticated()) {
    return <Navigate to="/login" replace />;
  }
  return children;
}

function PublicRoute({ children }) {
  if (isAuthenticated()) {
    return <Navigate to="/" replace />;
  }
  return children;
}

function AppLayout() {
  const location = useLocation();

  const hideSidebar =
    location.pathname === "/login" || location.pathname === "/register";
  const isAuthPage = hideSidebar;

  useEffect(() => {
    const theme = localStorage.getItem("theme");
    if (theme === "dark") {
      document.documentElement.classList.add("dark");
    } else {
      document.documentElement.classList.remove("dark");
    }
    window.dispatchEvent(new Event("acd-theme"));
  }, []);

  useEffect(() => {
    if (!isAuthenticated()) {
      return;
    }

    api
      .get("/user")
      .then((res) => {
        const user = res?.data?.user;
        if (user) {
          saveUser(user);
        }
      })
      .catch(() => {
        clearSession();
      });
  }, [location.pathname]);

  return (
    <div className="flex h-screen bg-slate-50 transition-colors dark:bg-slate-950">

      {!hideSidebar && <Sidebar />}

      <div
        className={`flex min-w-0 flex-1 flex-col overflow-hidden ${
          hideSidebar
            ? ""
            : "bg-slate-50 bg-[radial-gradient(70%_45%_at_50%_-10%,rgba(99,102,241,0.09),transparent_55%)] dark:bg-slate-950 dark:bg-[radial-gradient(70%_45%_at_50%_-10%,rgba(79,70,229,0.14),transparent_55%)]"
        }`}
      >
        <div
          className={`flex shrink-0 items-center justify-end gap-2 px-4 sm:px-6 ${isAuthPage ? "pt-3 pb-1" : "pt-6"}`}
        >
          {!hideSidebar && <NotificationBell />}
          <ThemeToggle />
        </div>
        <div
          className={`flex min-h-0 flex-1 flex-col px-4 sm:px-6 ${isAuthPage ? "overflow-y-auto overflow-x-hidden pb-8" : "overflow-auto pb-6"}`}
        >
        <Routes>

          <Route
            path="/login"
            element={
              <PublicRoute>
                <Login />
              </PublicRoute>
            }
          />

          <Route
            path="/register"
            element={
              <PublicRoute>
                <Register />
              </PublicRoute>
            }
          />

          <Route
            path="/"
            element={
              <ProtectedRoute>
                <Dashboard />
              </ProtectedRoute>
            }
          />

          <Route
            path="/complaints"
            element={
              <ProtectedRoute>
                <Complaints />
              </ProtectedRoute>
            }
          />

          <Route
            path="/admin"
            element={
              <ProtectedRoute>
                <AdminUsers />
              </ProtectedRoute>
            }
          />

          <Route
            path="/companies"
            element={
              <ProtectedRoute>
                <Companies />
              </ProtectedRoute>
            }
            
          />
          
          <Route
            path="/issues"
            element={
              <ProtectedRoute>
                <Issues />
              </ProtectedRoute>
            }
          />

          <Route
            path="/alerts"
            element={
              <ProtectedRoute>
                <AlertsPage />
              </ProtectedRoute>
            }
          />

          <Route
            path="/diagnosis/:id"
            element={
              <ProtectedRoute>
                <IssueDiagnosis />
              </ProtectedRoute>
            }
          />

          <Route
            path="/diagnosis"
            element={<Navigate to="/issues" replace />}
          />

          <Route
            path="/platform/audit-log"
            element={
              <ProtectedRoute>
                <AuditLogPage />
              </ProtectedRoute>
            }
          />

          <Route
            path="/approvals"
            element={
              <ProtectedRoute>
                <ApprovalRequestsPage />
              </ProtectedRoute>
            }
          />

          <Route path="*" element={<Navigate to="/" />} />

        </Routes>
        </div>
      </div>
    </div>
  );
}

function App() {
  return (
    <BrowserRouter>
      <AppLayout />
    </BrowserRouter>
  );
}

export default App;