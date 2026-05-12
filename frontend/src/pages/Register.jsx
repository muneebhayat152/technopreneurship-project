import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { api } from "../lib/api";
import toast from "react-hot-toast";
import { BrandLogo } from "../components/BrandLogo";
import { saveUser, setToken } from "../lib/auth";

function Register() {
  const navigate = useNavigate();
  const [name, setName] = useState("");
  const [companyName, setCompanyName] = useState("");
  const [companyEmail, setCompanyEmail] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);

  const handleRegister = async () => {
    if (!name || !email || !password || !companyName || !companyEmail) {
      toast.error("Fill in all fields.");
      return;
    }

    try {
      setLoading(true);

      const res = await api.post("/auth/register", {
        name,
        email,
        password,
        company_name: companyName,
        company_email: companyEmail,
      });

      toast.success("Account created.");

      setToken(res.data.token);
      const mergedUser = {
        ...res.data.user,
        company: res.data.company || null,
      };
      saveUser(mergedUser);

      navigate("/");
    } catch (err) {
      if (err.response?.data?.errors) {
        toast.error(Object.values(err.response.data.errors).join(" · "));
      } else {
        toast.error(err.userMessage || "Registration failed.");
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex h-full min-h-0 w-full flex-col items-center justify-center">
      <div className="mb-4 shrink-0 text-center sm:mb-5">
        <div className="mx-auto mb-3 flex justify-center">
          <BrandLogo />
        </div>
        <h1 className="text-2xl font-black tracking-tight text-slate-900 dark:text-white sm:text-3xl">
          AI Complaint Doctor
        </h1>
      </div>

      <div className="card w-full max-w-lg shrink-0 border-slate-200 px-4 py-4 dark:border-slate-700 dark:bg-slate-900 sm:px-6 sm:py-5">
        <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 sm:gap-3">
          <div className="sm:col-span-2">
            <label htmlFor="register-name" className="label dark:text-slate-200">
              Full Name
            </label>
            <input
              id="register-name"
              type="text"
              placeholder="Full Name"
              className="input mt-1.5 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
              value={name}
              onChange={(e) => setName(e.target.value)}
            />
          </div>

          <div>
            <label htmlFor="register-company-name" className="label dark:text-slate-200">
              Company Name
            </label>
            <input
              id="register-company-name"
              type="text"
              placeholder="Company Name"
              className="input mt-1.5 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
              value={companyName}
              onChange={(e) => setCompanyName(e.target.value)}
            />
          </div>

          <div>
            <label htmlFor="register-company-email" className="label dark:text-slate-200">
              Company Email
            </label>
            <input
              id="register-company-email"
              type="email"
              placeholder="Company Email"
              className="input mt-1.5 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
              value={companyEmail}
              onChange={(e) => setCompanyEmail(e.target.value)}
            />
          </div>

          <div>
            <label htmlFor="register-email" className="label dark:text-slate-200">
              Your Email
            </label>
            <input
              id="register-email"
              type="email"
              placeholder="Your Email"
              className="input mt-1.5 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </div>

          <div>
            <label htmlFor="register-password" className="label dark:text-slate-200">
              Password
            </label>
            <input
              id="register-password"
              type="password"
              placeholder="Password"
              className="input mt-1.5 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </div>
        </div>

        <div className="mt-4 space-y-3 sm:mt-6">
          <button
            onClick={handleRegister}
            disabled={loading}
            className="btn-primary w-full"
          >
            {loading ? "Creating…" : "Create Account"}
          </button>

          <p className="text-center text-sm text-slate-600 dark:text-slate-400">
            Already have an account?{" "}
            <button
              type="button"
              onClick={() => navigate("/login")}
              className="font-semibold text-violet-700 underline decoration-violet-700/30 underline-offset-2 hover:text-violet-800 hover:decoration-violet-800/50 dark:text-violet-400 dark:hover:text-violet-300"
            >
              Login
            </button>
          </p>
        </div>
      </div>
    </div>
  );
}

export default Register;
