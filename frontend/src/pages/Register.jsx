import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { api } from "../lib/api";
import toast from "react-hot-toast";
import { saveUser, setToken } from "../lib/auth";

function Register() {
  const navigate = useNavigate();
  const [name, setName] = useState("");
  const [companyName, setCompanyName] = useState("");
  const [companyEmail, setCompanyEmail] = useState("");
  const [industry, setIndustry] = useState("");
  const [country, setCountry] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);

  const handleRegister = async () => {
    if (!name || !email || !password || !companyName || !companyEmail || !industry || !country) {
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
        industry,
        country,
      });

      if (res.data?.pending_owner_approval) {
        toast.success(
          res.data.message ||
            "Registration received. Platform owners will review your organization.",
          { duration: 6000 }
        );
        navigate("/login");
        return;
      }

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
    <div className="flex h-full min-h-0 max-h-full w-full flex-col overflow-hidden bg-slate-950">
      <h1 className="sr-only">AI Complaint Doctor — Create account</h1>

      <div className="flex min-h-0 flex-1 flex-col px-4 pb-0 pt-3 sm:px-5 sm:pt-4">
        <div className="card flex min-h-0 w-full max-w-lg flex-1 flex-col self-center overflow-hidden border-slate-700 bg-slate-900 py-3 dark:border-slate-700 sm:py-4">
          <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 sm:px-5">
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:gap-2.5">
              <div className="sm:col-span-2">
                <label htmlFor="register-name" className="label dark:text-slate-200">
                  Full Name
                </label>
                <input
                  id="register-name"
                  type="text"
                  placeholder="Full Name"
                  className="input mt-1 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
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
                  className="input mt-1 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
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
                  className="input mt-1 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                  value={companyEmail}
                  onChange={(e) => setCompanyEmail(e.target.value)}
                />
              </div>

              <div>
                <label htmlFor="register-industry" className="label dark:text-slate-200">
                  Industry
                </label>
                <input
                  id="register-industry"
                  type="text"
                  placeholder="e.g. Retail, Healthcare"
                  className="input mt-1 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                  value={industry}
                  onChange={(e) => setIndustry(e.target.value)}
                />
              </div>

              <div>
                <label htmlFor="register-country" className="label dark:text-slate-200">
                  Country
                </label>
                <input
                  id="register-country"
                  type="text"
                  placeholder="e.g. Pakistan, United Kingdom"
                  className="input mt-1 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                  value={country}
                  onChange={(e) => setCountry(e.target.value)}
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
                  className="input mt-1 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
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
                  className="input mt-1 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                />
              </div>
            </div>

            <p className="mt-2.5 rounded-lg border border-amber-200/80 bg-amber-950/35 px-2.5 py-2 text-[11px] leading-snug text-amber-100 dark:border-amber-900/60">
              New organizations are always created on the <strong>Free</strong> plan. A platform owner must{" "}
              <strong>approve</strong> your company before you can sign in. Owners may activate you on{" "}
              <strong>Free</strong> or <strong>Premium</strong>.
            </p>

            <p className="mt-3 pb-1 text-center text-sm text-slate-400">
              Already have an account?{" "}
              <button
                type="button"
                onClick={() => navigate("/login")}
                className="font-semibold text-violet-400 underline decoration-violet-400/30 underline-offset-2 hover:text-violet-300 hover:decoration-violet-300/50"
              >
                Login
              </button>
            </p>
          </div>
        </div>

        {/* Half of the primary CTA sits below the viewport fold (overflow-hidden on root). */}
        <div className="relative mx-auto mt-2 w-full max-w-lg shrink-0 px-4 sm:px-5">
          <button
            type="button"
            onClick={handleRegister}
            disabled={loading}
            className="btn-primary h-12 w-full translate-y-1/2 shadow-lg shadow-indigo-950/40 sm:h-[3.25rem]"
          >
            {loading ? "Creating…" : "Create Account"}
          </button>
        </div>
      </div>
    </div>
  );
}

export default Register;
