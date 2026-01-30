import { redirect } from "next/navigation";
import { Header } from "@/components/layout/header";
import { getCurrentUser } from "@/lib/auth";

export default async function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const user = await getCurrentUser();

  // Server-side guard (middleware handles the redirect, but this is a safety net)
  if (!user) {
    redirect("/login?redirect=/dashboard");
  }

  return (
    <>
      <Header user={user} />
      {children}
    </>
  );
}
