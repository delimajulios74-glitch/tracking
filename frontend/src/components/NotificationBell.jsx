import { useEffect, useRef, useState } from 'react';
import { getNotifications, markAllNotificationsRead, markNotificationRead } from '../api';

function NotificationBell({ onOpenTask }) {
  const [open, setOpen] = useState(false);
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(false);
  const rootRef = useRef(null);

  async function loadNotifications() {
    try {
      setLoading(true);
      const response = await getNotifications();
      setNotifications(response.data);
      setUnreadCount(response.meta.unread_count);
    } catch {
      // Keep the bell usable if a notification refresh fails temporarily.
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadNotifications();
    const interval = setInterval(loadNotifications, 30000);
    return () => clearInterval(interval);
  }, []);

  useEffect(() => {
    function closeOutside(event) {
      if (rootRef.current && !rootRef.current.contains(event.target)) setOpen(false);
    }
    document.addEventListener('mousedown', closeOutside);
    return () => document.removeEventListener('mousedown', closeOutside);
  }, []);

  async function openNotification(notification) {
    if (!notification.read_at) {
      await markNotificationRead(notification.id).catch(() => null);
      setUnreadCount((count) => Math.max(0, count - 1));
      setNotifications((current) => current.map((item) => item.id === notification.id
        ? { ...item, read_at: new Date().toISOString() }
        : item));
    }
    setOpen(false);
    if (notification.data?.task_id) {
      sessionStorage.setItem('open-maintenance-task-id', String(notification.data.task_id));
      onOpenTask?.(notification.data.task_id);
    }
  }

  async function readAll() {
    await markAllNotificationsRead().catch(() => null);
    setUnreadCount(0);
    setNotifications((current) => current.map((item) => ({ ...item, read_at: item.read_at || new Date().toISOString() })));
  }

  return (
    <div className="relative" ref={rootRef}>
      <button
        aria-label={`Notifications${unreadCount ? `, ${unreadCount} unread` : ''}`}
        className="relative flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-600 hover:bg-slate-50"
        onClick={() => { setOpen((current) => !current); if (!open) loadNotifications(); }}
        type="button"
      >
        <svg aria-hidden="true" className="h-5 w-5" fill="none" viewBox="0 0 24 24">
          <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.7" />
        </svg>
        {unreadCount > 0 && (
          <span className="absolute -right-1 -top-1 flex min-h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white">
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </button>
      {open && (
        <div className="absolute right-0 z-50 mt-2 w-[min(380px,calc(100vw-2rem))] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
          <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <h3 className="text-sm font-semibold text-slate-900">Notifications</h3>
            {unreadCount > 0 && (
              <button className="text-xs font-semibold text-emerald-800" onClick={readAll} type="button">Mark all read</button>
            )}
          </div>
          <div className="max-h-96 overflow-y-auto">
            {loading && notifications.length === 0 ? (
              <p className="px-4 py-8 text-center text-sm text-slate-500">Loading...</p>
            ) : notifications.length === 0 ? (
              <p className="px-4 py-8 text-center text-sm text-slate-500">No notifications yet.</p>
            ) : notifications.map((notification) => (
              <button
                className={`block w-full border-b border-slate-100 px-4 py-3 text-left hover:bg-slate-50 ${notification.read_at ? '' : 'bg-sky-50/60'}`}
                key={notification.id}
                onClick={() => openNotification(notification)}
                type="button"
              >
                <p className="text-sm font-semibold text-slate-900">{notification.title}</p>
                <p className="mt-1 text-xs leading-5 text-slate-600">{notification.message}</p>
                {notification.data?.priority && (
                  <p className="mt-1 text-[11px] font-medium text-slate-500">
                    Priority: {notification.data.priority}
                    {notification.data.property_number ? ` · Property No. ${notification.data.property_number}` : ''}
                  </p>
                )}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

export default NotificationBell;
