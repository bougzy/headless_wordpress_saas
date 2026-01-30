"use client";

import { useState } from "react";
import { useSearchParams, useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

export function LoginForm() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const initialTab = searchParams.get("tab") === "register" ? "register" : "login";
  const redirect = searchParams.get("redirect") || "/dashboard";

  const [tab, setTab] = useState<"login" | "register">(initialTab);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const [form, setForm] = useState({
    email: "",
    password: "",
    first_name: "",
    last_name: "",
  });

  function updateField(field: string, value: string) {
    setForm((prev) => ({ ...prev, [field]: value }));
    setError("");
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError("");

    try {
      const res = await fetch("/api/auth", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: tab,
          ...form,
        }),
      });

      const data = await res.json();

      if (!res.ok) {
        setError(data.error || "Something went wrong. Please try again.");
        return;
      }

      router.push(redirect);
      router.refresh();
    } catch {
      setError("Network error. Please try again.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="mx-auto w-full max-w-md">
      <div className="text-center">
        <h1 className="text-2xl font-bold text-slate-900">
          {tab === "login" ? "Welcome back" : "Create your account"}
        </h1>
        <p className="mt-2 text-sm text-slate-500">
          {tab === "login"
            ? "Sign in to access your content and dashboard."
            : "Start your journey with Jenga today."}
        </p>
      </div>

      {/* Tab switcher */}
      <div className="mt-8 flex rounded-lg bg-slate-100 p-1">
        <button
          onClick={() => setTab("login")}
          className={`flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
            tab === "login"
              ? "bg-white text-slate-900 shadow-sm"
              : "text-slate-500 hover:text-slate-700"
          }`}
        >
          Log in
        </button>
        <button
          onClick={() => setTab("register")}
          className={`flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
            tab === "register"
              ? "bg-white text-slate-900 shadow-sm"
              : "text-slate-500 hover:text-slate-700"
          }`}
        >
          Register
        </button>
      </div>

      <form onSubmit={handleSubmit} className="mt-6 space-y-4">
        {tab === "register" && (
          <div className="grid grid-cols-2 gap-3">
            <Input
              id="first_name"
              label="First name"
              value={form.first_name}
              onChange={(e) => updateField("first_name", e.target.value)}
              placeholder="John"
            />
            <Input
              id="last_name"
              label="Last name"
              value={form.last_name}
              onChange={(e) => updateField("last_name", e.target.value)}
              placeholder="Doe"
            />
          </div>
        )}

        <Input
          id="email"
          type="email"
          label="Email address"
          value={form.email}
          onChange={(e) => updateField("email", e.target.value)}
          placeholder="you@example.com"
          required
          autoComplete="email"
        />

        <Input
          id="password"
          type="password"
          label="Password"
          value={form.password}
          onChange={(e) => updateField("password", e.target.value)}
          placeholder={tab === "register" ? "Min 8 characters" : ""}
          required
          minLength={tab === "register" ? 8 : undefined}
          autoComplete={tab === "login" ? "current-password" : "new-password"}
        />

        {error && (
          <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            {error}
          </div>
        )}

        <Button type="submit" loading={loading} className="w-full" size="lg">
          {tab === "login" ? "Sign in" : "Create account"}
        </Button>
      </form>
    </div>
  );
}
