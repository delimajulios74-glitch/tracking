import { useEffect, useLayoutEffect, useState } from 'react';
import {
  ApiError,
  createUserAccount,
  getCurrentUser,
  logIn,
  logOut,
} from './api';
import AdminDashboard from './pages/AdminDashboard';
import ForgotPasswordPage from './pages/ForgotPasswordPage';
import LandingPage from './pages/LandingPage';
import MaintenanceDashboard from './pages/MaintenanceDashboard';
import EndUserDashboard from './pages/EndUserDashboard';
import ResetPasswordPage from './pages/ResetPasswordPage';

const ROLE_PATHS = {
  admin: '/admin',
  maintenance: '/maintenance',
  end_user: '/end-user',
};

function App() {
  const [currentPath, setCurrentPath] = useState(window.location.pathname);
  const [resetCredentials] = useState(() => {
    if (window.location.pathname !== '/reset-password') return null;

    const params = new URLSearchParams(window.location.search);
    return {
      token: params.get('token') || '',
      email: params.get('email') || '',
    };
  });
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  useLayoutEffect(() => {
    if (window.location.pathname === '/reset-password' && window.location.search) {
      window.history.replaceState({}, '', '/reset-password');
    }
  }, []);

  function navigate(nextPath, replace = false) {
    if (window.location.pathname !== nextPath) {
      if (replace) {
        window.history.replaceState({}, '', nextPath);
      } else {
        window.history.pushState({}, '', nextPath);
      }
    }
    setCurrentPath(nextPath);
  }

  useEffect(() => {
    function syncPath() {
      const currentPath = window.location.pathname;
      if (!user && ['/forgot-password', '/reset-password'].includes(currentPath)) {
        setCurrentPath(currentPath);
        return;
      }

      const expectedPath = user ? ROLE_PATHS[user.role] || '/' : '/';
      const nextPath = currentPath === expectedPath ? currentPath : expectedPath;

      if (nextPath !== currentPath) {
        window.history.replaceState({}, '', nextPath);
      }
      setCurrentPath(nextPath);
    }

    window.addEventListener('popstate', syncPath);
    return () => window.removeEventListener('popstate', syncPath);
  }, [user]);

  useEffect(() => {
    if (['/forgot-password', '/reset-password'].includes(window.location.pathname)) {
      setLoading(false);
      return undefined;
    }

    let isMounted = true;

    getCurrentUser()
      .then((response) => {
        if (isMounted) {
          setUser(response.data);
          const rolePath = ROLE_PATHS[response.data.role];
          if (rolePath) navigate(rolePath, true);
        }
      })
      .catch((err) => {
        if (!isMounted) return;
        if (!(err instanceof ApiError) || err.status !== 401) {
          setError(err.message || 'Could not connect to the system. Please try again.');
        } else if (window.location.pathname !== '/') {
          navigate('/', true);
        }
      })
      .finally(() => {
        if (isMounted) setLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  async function handleLogin(event) {
    event.preventDefault();
    setError('');
    setNotice('');
    setSubmitting(true);

    const form = new FormData(event.currentTarget);
    try {
      const response = await logIn({
        username: form.get('username'),
        password: form.get('password'),
      });
      setUser(response.data);
      const rolePath = ROLE_PATHS[response.data.role];
      if (rolePath) navigate(rolePath);
    } catch (err) {
      setError(err.message || 'Unable to sign in.');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleLogout() {
    setError('');
    setNotice('');
    try {
      await logOut();
      setUser(null);
      navigate('/');
      setNotice('You have been signed out.');
    } catch (err) {
      setError(err.message || 'Unable to sign out.');
    }
  }

  async function handleCreateUser(event) {
    event.preventDefault();
    setError('');
    setNotice('');

    const formElement = event.currentTarget;
    const form = new FormData(formElement);
    try {
      const response = await createUserAccount({
        username: form.get('username'),
        name: form.get('name'),
        password: form.get('password'),
        email: form.get('email'),
        password_confirmation: form.get('password_confirmation'),
        role: form.get('role'),
      });
      formElement.reset();
      const roleName = response.data.role === 'admin'
        ? 'Property Custodian'
        : response.data.role === 'end_user'
          ? 'End User'
          : 'Maintenance';
      setNotice(`${roleName} account "${response.data.username}" was created.`);
      return true;
    } catch (err) {
      setError(err.message || 'Unable to create the account.');
      return false;
    }
  }

  if (loading) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-slate-100 p-6">
        <p className="text-sm font-medium text-slate-600" role="status">Checking your secure session...</p>
      </main>
    );
  }

  if (!user && currentPath === '/forgot-password') {
    return <ForgotPasswordPage />;
  }

  if (!user && currentPath === '/reset-password') {
    return (
      <ResetPasswordPage
        credentials={resetCredentials}
        onSuccess={() => {
          navigate('/', true);
          setNotice('Your password has been reset. You can now sign in.');
        }}
      />
    );
  }

  if (user?.role === 'admin') {
    return (
      <AdminDashboard
        error={error}
        notice={notice}
        onAccountUpdate={setUser}
        onCreateUser={handleCreateUser}
        onLogout={handleLogout}
        user={user}
      />
    );
  }

  if (user?.role === 'maintenance') {
    return (
      <MaintenanceDashboard
        error={error}
        notice={notice}
        onLogout={handleLogout}
        user={user}
      />
    );
  }

  if (user?.role === 'end_user') {
    return (
      <EndUserDashboard
        onAccountUpdate={setUser}
        onLogout={handleLogout}
        user={user}
      />
    );
  }

  return (
    <LandingPage
      error={error}
      notice={notice}
      onSubmit={handleLogin}
      submitting={submitting}
    />
  );
}

export default App;
