import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Permintaan Pembelian (Order Request) — Duta Tunggal ERP",
  description: "Form Permintaan Pembelian Cepat & Ringan Duta Tunggal ERP",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="id">
      <body className="bg-slate-50 text-slate-900 min-h-screen antialiased">
        {children}
      </body>
    </html>
  );
}
