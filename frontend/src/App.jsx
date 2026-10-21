import { useEffect, useState } from 'react';
import {
  ApiError,
  createMaintenanceUser,
  getCurrentUser,
  logIn,
  logOut,
} from './api';
import AdminDashboard from './pages/AdminDashboard';
import LandingPage from './pages/LandingPage';
import MaintenanceDashboard from './pages/MaintenanceDashboard';

const ROLE_PATHS = {
  admin: '/admin',
  maintenance: '/maintenance',
};

function App() {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  function navigate(nextPath, replace = false) {
    if (window.location.pathname !== nextPath) {
      if (replace) {
        window.history.replaceState({}, '', nextPath);
      } else {
        window.history.pushState({}, '', nextPath);
      }
    }
  }

  useEffect(() => {
    function syncPath() {
      const currentPath = window.location.pathname;
      const expectedPath = user ? ROLE_PATHS[user.role] || '/' : '/';
      const nextPath = currentPath === expectedPath ? currentPath : expectedPath;

      if (nextPath !== currentPath) {
        window.history.replaceState({}, '', nextPath);
      }
    }

    window.addEventListener('popstate', syncPath);
    return () => window.removeEventListener('popstate', syncPath);
  }, [user]);

  useEffect(() => {
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
      const response = await createMaintenanceUser({
        username: form.get('username'),
        name: form.get('name'),
        password: form.get('password'),
        password_confirmation: form.get('password_confirmation'),
      });
      formElement.reset();
      setNotice(`Maintenance account "${response.data.username}" was created.`);
    } catch (err) {
      setError(err.message || 'Unable to create the account.');
    }
  }

  if (loading) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-slate-100 p-6">
        <p className="text-sm font-medium text-slate-600" role="status">Checking your secure session...</p>
      </main>
    );
  }

  if (user?.role === 'admin') {
    return (
      <AdminDashboard
        error={error}
        notice={notice}
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
