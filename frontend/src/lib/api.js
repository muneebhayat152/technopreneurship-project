import axios from "axios";
import { clearSession, getToken } from "./auth";

const envBaseUrl = import.meta.env.VITE_API_URL;
const fallbackBaseUrl = "http://127.0.0.1:8000/api";
const isProd = import.meta.env.PROD;

if (isProd && !envBaseUrl) {
  throw new Error(
    "VITE_API_URL is required for production builds. Set it in Vercel environment variables."
  );
}

const baseURL = envBaseUrl || fallbackBaseUrl;

export const api = axios.create({
  baseURL,
  timeout: 15000,
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
  },
});

api.interceptors.request.use((config) => {
  const token = getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  config.headers["X-Requested-With"] = "XMLHttpRequest";
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error?.response?.status;

    if (status === 401 || status === 403) {
      clearSession();
      if (window.location.pathname !== "/login") {
        window.location.assign("/login");
      }
    }

    if (error?.code === "ECONNABORTED") {
      error.userMessage = "Request timed out. Please try again.";
    } else if (!error?.response) {
      // Browser blocks CORS, DNS/SSL failure, or wrong API host — not "Wi‑Fi down".
      error.userMessage =
        `Can't reach the API (${baseURL}). On Vercel set VITE_API_URL to your Railway URL ending in /api and redeploy. On Railway set CORS_ALLOWED_ORIGINS to your exact Vercel URL (https://…).`;
    } else {
      error.userMessage =
        error.response?.data?.message || "Request failed. Please try again.";
    }

    return Promise.reject(error);
  }
);

export function getApiBase() {
  return baseURL.replace(/\/?api\/?$/, "") || "";
}
