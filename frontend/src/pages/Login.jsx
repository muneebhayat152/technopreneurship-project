import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { api } from "../lib/api";
import toast from "react-hot-toast";
import { BrandLogo } from "../components/BrandLogo";
import { saveUser, setToken } from "../lib/auth";

function Login() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);

  const navigate = useNavigate();

  const finishSignIn = (res) => {
    setToken(res.data.token);
    const mergedUser = {
      ...res.data.user,
      company: res.data.company || null,
    };
    saveUser(mergedUser);
    toast.success("Signed in.");
    navigate("/");
  };

  const handleLogin = async () => {
    if (!email || !password) {
      toast.error("Fill in all fields.");
      return;
    }

    try {
      setLoading(true);

      const res = await api.post("/auth/login", { email, password });

      finishSignIn(res);
    } catch (err) {
      if (err.response?.status === 401) {
        toast.error("Invalid email or password.");
      } else if (err.response?.status === 403) {
        const msg =
          err.response?.data?.message ||
          err.userMessage ||
          "Sign-in not allowed for this account.";
        toast.error(msg, { duration: 6500 });
      } else {
        toast.error(err.userMessage || "Sign-in failed. Try again.");
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex h-full min-h-0 w-full flex-col items-center justify-center">
      <div className="mb-6 shrink-0 text-center">
        <div className="mx-auto mb-3 flex justify-center">
          <BrandLogo />
        </div>
        <h1 className="text-2xl font-black tracking-tight text-slate-900 dark:text-white sm:text-3xl">
          AI Complaint Doctor
        </h1>
      </div>

      <div className="card w-full max-w-md border-slate-200 px-5 py-5 dark:border-slate-700 dark:bg-slate-900 sm:p-6">
        <div className="space-y-4">
          <div>
            <label htmlFor="login-email" className="label dark:text-slate-200">
              Email
            </label>
            <input
              id="login-email"
              type="email"
              autoComplete="email"
              placeholder="Email"
              className="input mt-2 dark:border-slate-600 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </div>
          <div>
            <label htmlFor="login-password" className="label dark:text-slate-200">
              Password
            </label>
            <input
              id="login-password"
              type="password"
              autoComplete="current-password"
              placeholder="Password"
              className="input mt-2 dark:border-slate-600 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </div>

          <button
            type="button"
            onClick={handleLogin}
            disabled={loading}
            className="btn-primary mt-2 w-full"
          >
            {loading ? "Signing In…" : "Sign In"}
          </button>

          <p className="text-center text-sm text-slate-600 dark:text-slate-400">
            New user?{" "}
            <button
              type="button"
              onClick={() => navigate("/register")}
              className="font-semibold text-violet-700 underline decoration-violet-700/30 underline-offset-2 hover:text-violet-800 hover:decoration-violet-800/50 dark:text-violet-400 dark:hover:text-violet-300"
            >
              Register
            </button>
          </p>
        </div>
      </div>
    </div>
  );
}

export default Login;
