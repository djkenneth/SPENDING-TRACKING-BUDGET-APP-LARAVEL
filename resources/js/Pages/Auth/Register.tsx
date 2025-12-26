import { FormEventHandler } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Register() {
  const { data, setData, post, processing, errors, reset } = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    post('/register', {
      onFinish: () => reset('password', 'password_confirmation'),
    });
  };

  return (
    <AuthLayout>
      <Head title="Register" />

      <form onSubmit={submit} className="space-y-6">
        <div>
          <Label htmlFor="name">Full Name</Label>
          <Input
            id="name"
            name="name"
            value={data.name}
            className="mt-1"
            autoComplete="name"
            autoFocus
            onChange={(e) => setData('name', e.target.value)}
            required
          />
          {errors.name && (
            <p className="mt-2 text-sm text-red-600">{errors.name}</p>
          )}
        </div>

        <div>
          <Label htmlFor="email">Email address</Label>
          <Input
            id="email"
            type="email"
            name="email"
            value={data.email}
            className="mt-1"
            autoComplete="username"
            onChange={(e) => setData('email', e.target.value)}
            required
          />
          {errors.email && (
            <p className="mt-2 text-sm text-red-600">{errors.email}</p>
          )}
        </div>

        <div>
          <Label htmlFor="password">Password</Label>
          <Input
            id="password"
            type="password"
            name="password"
            value={data.password}
            className="mt-1"
            autoComplete="new-password"
            onChange={(e) => setData('password', e.target.value)}
            required
          />
          {errors.password && (
            <p className="mt-2 text-sm text-red-600">{errors.password}</p>
          )}
        </div>

        <div>
          <Label htmlFor="password_confirmation">Confirm Password</Label>
          <Input
            id="password_confirmation"
            type="password"
            name="password_confirmation"
            value={data.password_confirmation}
            className="mt-1"
            autoComplete="new-password"
            onChange={(e) => setData('password_confirmation', e.target.value)}
            required
          />
          {errors.password_confirmation && (
            <p className="mt-2 text-sm text-red-600">
              {errors.password_confirmation}
            </p>
          )}
        </div>

        <Button
          type="submit"
          className="w-full"
          disabled={processing}
        >
          {processing ? 'Creating account...' : 'Create account'}
        </Button>

        <p className="text-center text-sm text-gray-600 dark:text-gray-400">
          Already have an account?{' '}
          <Link
            href="/login"
            className="font-medium text-primary hover:underline"
          >
            Sign in here
          </Link>
        </p>
      </form>
    </AuthLayout>
  );
}
