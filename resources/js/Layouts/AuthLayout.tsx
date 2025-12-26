import { ReactNode } from 'react';

interface AuthLayoutProps {
  children: ReactNode;
}

export default function AuthLayout({ children }: AuthLayoutProps) {
  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8">
        <div className="text-center">
          <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
            Budget Tracker
          </h1>
          <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Manage your finances with ease
          </p>
        </div>
        {children}
      </div>
    </div>
  );
}
