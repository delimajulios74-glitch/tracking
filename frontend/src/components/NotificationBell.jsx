import { useEffect, useRef, useState } from 'react';
import { getNotifications, markAllNotificationsRead, markNotificationRead } from '../api';

function NotificationBell({ align = 'left', onOpenTask }) {
  const [open, setOpen] = useState(false);
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const rootRef = useRef(null);

  async function loadNotifications(showLoading = false) {
    try {
      if (showLoading) setLoading(true);
      const response = await getNotifications();
      setNotifications(response.data);
      setUnreadCount(response.meta.unread_count);
      setError('');
    } catch (err) {
      setError(err.message || 'Unable to refresh notifications.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    let active = true;

    async function refreshNotifications() {
      try {
        const response = await getNotifications();
        if (!active) return;
        setNotifications(response.data);
        setUnreadCount(response.meta.unread_count);
        setError('');
      } catch (err) {
        if (active) setError(err.message || 'Unable to refresh notifications.');
      } finally {
        if (active) setLoading(false);
      }
    }

    refreshNotifications();
    const interval = setInterval(refreshNotifications, 30000);
    return () => {
      active = false;
      clearInterval(interval);
    };
  }, []);

  useEffect(() => {
    function closeOutside(event) {
      if (rootRef.current && !rootRef.current.contains(event.target)) setOpen(false);
    }
    function closeOnEscape(event) {
      if (event.key === 'Escape') setOpen(false);
    }

    document.addEventListener('mousedown', closeOutside);
    document.addEventListener('keydown', closeOnEscape);
    return () => {
      document.removeEventListener('mousedown', closeOutside);
      document.removeEventListener('keydown', closeOnEscape);
    };
  }, []);

  async function openNotification(notification) {
    let markReadFailed = false;
    if (!notification.read_at) {
      try {
        await markNotificationRead(notification.id);
        setUnreadCount((count) => Math.max(0, count - 1));
        setNotifications((current) => current.map((item) => item.id === notification.id
          ? { ...item, read_at: new Date().toISOString() }
          : item));
        setError('');
      } catch (err) {
        setError(err.message || 'Unable to mark this notification as read.');
        markReadFailed = true;
      }
    }
    setOpen(markReadFailed);
    if (notification.data?.task_id) {
      onOpenTask?.(notification.data.task_id, notification);
    }
  }

  async function readAll() {
    try {
      await markAllNotificationsRead();
      setUnreadCount(0);
      setNotifications((current) => current.map((item) => ({ ...item, read_at: item.read_at || new Date().toISOString() })));
      setError('');
    } catch (err) {
      setError(err.message || 'Unable to mark notifications as read.');
    }
  }

  return (
    <div className="relative" ref={rootRef}>
      <button
        aria-label={`Notifications${unreadCount ? `, ${unreadCount} unread` : ''}`}
        className="relative flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-600 hover:bg-slate-50"
        onClick={() => { setOpen((current) => !current); if (!open) loadNotifications(true); }}
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
        <div className={`absolute ${align === 'right' ? 'right-0' : 'left-0'} top-[calc(100%+8px)] z-[1000] w-[380px] max-w-[calc(100vw-2rem)] max-h-[70vh] overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl`}>
          <div className="sticky top-0 z-10 flex items-center justify-between border-b border-gray-100 bg-white px-4 py-3">
            <h3 className="text-sm font-semibold text-slate-900">Notifications</h3>
            {unreadCount > 0 && (
              <button className="text-xs font-semibold text-emerald-800" onClick={readAll} type="button">Mark all read</button>
            )}
          </div>
          {error && <p className="border-b border-rose-100 bg-rose-50 px-4 py-2 text-xs text-rose-800" role="alert">{error}</p>}
          {loading && notifications.length === 0 ? (
            <p className="px-4 py-8 text-center text-sm text-slate-500">Loading...</p>
          ) : notifications.length === 0 ? (
            <p className="px-4 py-8 text-center text-sm text-slate-500">No notifications</p>
          ) : (
            <div className="divide-y divide-gray-100">
              {notifications.map((notification) => (
                <button
                  className={`block w-full px-4 py-3 text-left transition-colors hover:bg-slate-50 ${notification.read_at ? '' : 'bg-sky-50/60'}`}
                  key={notification.id}
                  onClick={() => openNotification(notification)}
                  type="button"
                >
                  <p className="break-words text-sm font-semibold text-slate-900">{notification.title}</p>
                  <p className="mt-1 break-words text-xs leading-5 text-slate-600">{notification.message}</p>
                  {(notification.data?.priority || notification.data?.property_number) && (
                    <div className="mt-2 space-y-1">
                      {notification.data?.priority && (
                        notification.data.priority.toLowerCase() === 'urgent' ? (
                          <span className="inline-flex rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700">Urgent</span>
                        ) : (
                          <p className="text-[11px] font-medium text-slate-500">Priority: {notification.data.priority}</p>
                        )
                      )}
                      {notification.data?.property_number && (
                        <p className="break-words text-[11px] font-medium text-slate-500">Property No. {notification.data.property_number}</p>
                      )}
                    </div>
                  )}
                </button>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

export default NotificationBell;
