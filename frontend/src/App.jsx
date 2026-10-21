
import { useEffect, useState } from 'react';
import { checkApi } from './api';

function App() {
  const [message, setMessage] = useState('Connecting to Laravel...');
  const [error, setError] = useState('');

  useEffect(() => {
    checkApi()
      .then((data) => {
        setMessage(data.message);
      })
      .catch((err) => {
        console.error(err);
        setError('Could not connect to the Laravel API.');
      });
  }, []);

  return (
    <main className="min-h-screen bg-slate-100 p-8">
      <div className="mx-auto max-w-3xl rounded-xl bg-white p-8 shadow">
        <h1 className="text-3xl font-bold text-slate-800">
          LGU-Trinidad
        </h1>

        <p className="mt-2 text-slate-600">
          Property and Equipment Tracking System
        </p>

        {error ? (
          <p className="mt-6 rounded-lg bg-red-100 p-4 text-red-700">
            {error}
          </p>
        ) : (
          <p className="mt-6 rounded-lg bg-green-100 p-4 text-green-800">
            {message}
          </p>
        )}
      </div>
    </main>
  );
}

export default App;
