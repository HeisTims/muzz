import { Outfit } from "next/font/google";
import "./globals.css";
import { AppProvider } from "@/context/AppContext";

const outfit = Outfit({
  subsets: ["latin"],
  weight: ["300", "400", "600", "800"],
  variable: "--font-outfit",
});

export const metadata = {
  title: "EazyMUZE — Where Desires Find Their Muze 💋",
  description: "EazyMUZE is a premium, erotic social networking platform. Find your muze, share secrets, and explore your desires safely.",
  manifest: "/manifest.json",
  appleWebApp: {
    capable: true,
    statusBarStyle: "black-translucent",
    title: "EazyMUZE",
  },
  icons: {
    icon: "/assets/img/logo1.png",
    apple: "/assets/img/logo1.png",
  },
};

export default function RootLayout({ children }) {
  return (
    <html lang="en" className={`${outfit.variable} h-full`}>
      <head>
        <link
          rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        />
      </head>
      <body>
        <div className="ambient-bg">
          <div className="glowing-orb orb-1"></div>
          <div className="glowing-orb orb-2"></div>
        </div>
        <AppProvider>
          <div id="appContainer">
            {children}
          </div>
        </AppProvider>
      </body>
    </html>
  );
}
