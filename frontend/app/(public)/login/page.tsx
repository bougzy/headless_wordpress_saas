import type { Metadata } from "next";
import { Header } from "@/components/layout/header";
import { Footer } from "@/components/layout/footer";
import { LoginForm } from "@/components/features/login-form";
import { Suspense } from "react";

export const metadata: Metadata = {
  title: "Log In",
  description: "Sign in to your Jenga account or create a new one.",
};

export default function LoginPage() {
  return (
    <>
      <Header user={null} />

      <main className="flex min-h-[60vh] items-center justify-center bg-white py-20">
        <Suspense fallback={<div className="text-center text-slate-500">Loading...</div>}>
          <LoginForm />
        </Suspense>
      </main>

      <Footer />
    </>
  );
}
