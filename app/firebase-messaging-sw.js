// ==============================================================================
// PAVANCAB GOA TAXI - Firebase Cloud Messaging Service Worker
// Location: /app/firebase-messaging-sw.js
// ==============================================================================

importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js');

const firebaseConfig = {
  apiKey: "AIzaSyA9_ZfLPnMew12a0g4EtikIne2jn7nEIkY",
  authDomain: "pavancab-4a1daa.firebaseapp.com",
  projectId: "pavancab-4a1daa",
  storageBucket: "pavancab-4a1daa.firebasestorage.app",
  messagingSenderId: "1064014558872",
  appId: "1:1064014558872:web:bb0b2d5ae8e4e79a0ac2ed"
};

if (!firebase.apps.length) {
  firebase.initializeApp(firebaseConfig);
}
const messaging = firebase.messaging();

const FCM_TONES = {
  'NEW_BOOKING':     { url: 'https://assets.mixkit.co/active_storage/sfx/2771/2771-preview.mp3', vibrate: [200, 50, 200, 50, 300] },
  'DRIVER_ASSIGNED': { url: 'https://assets.mixkit.co/active_storage/sfx/2020/2020-preview.mp3', vibrate: [100, 50, 100, 50, 200, 50, 200] },
  'DRIVER_ACCEPTED': { url: 'https://assets.mixkit.co/active_storage/sfx/2000/2000-preview.mp3', vibrate: [100, 30, 100, 30, 100, 30, 300] },
  'RIDE_STARTED':    { url: 'https://assets.mixkit.co/active_storage/sfx/1999/1999-preview.mp3', vibrate: [300, 100, 300, 100, 400] },
  'IN_TRANSIT':      { url: 'https://assets.mixkit.co/active_storage/sfx/1999/1999-preview.mp3', vibrate: [300, 100, 300, 100, 400] },
  'RIDE_COMPLETED':  { url: 'https://assets.mixkit.co/active_storage/sfx/2770/2770-preview.mp3', vibrate: [50, 30, 50, 30, 200, 50, 200] },
  'COMPLETED':       { url: 'https://assets.mixkit.co/active_storage/sfx/2770/2770-preview.mp3', vibrate: [50, 30, 50, 30, 200, 50, 200] },
  'CANCELLED':       { url: 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3', vibrate: [400, 100, 400] },
  'CANCELLED_BY_USER': { url: 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3', vibrate: [400, 100, 400] },
  'CANCELLED_BY_ADMIN': { url: 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3', vibrate: [400, 100, 400] },
  'FARE_BOOSTED':    { url: 'https://assets.mixkit.co/active_storage/sfx/2770/2770-preview.mp3', vibrate: [50, 30, 50, 30, 50, 30, 200] },
  'FARE_UPDATED':    { url: 'https://assets.mixkit.co/active_storage/sfx/2770/2770-preview.mp3', vibrate: [100, 50, 100, 50, 200] },
  'DRIVER_DECLINED': { url: 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3', vibrate: [400, 200, 400, 200, 400] },
  'RIDE_RESET':      { url: 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3', vibrate: [300, 100, 300, 100, 300] },
  'PENDING':         { url: 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3', vibrate: [300, 100, 300, 100, 300] },
  'default':         { url: 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3', vibrate: [300, 100, 300, 100, 400] }
};

function getTone(eventType) {
  return FCM_TONES[eventType] || FCM_TONES['default'];
}

messaging.onBackgroundMessage((payload) => {
  console.log('[FCM SW] Background message received:', payload);

  const notif = payload.notification || {};
  const data  = payload.data || {};

  const title = notif.title || data.title || '🚕 PAVANCAB Alert';
  const body  = notif.body  || data.body  || 'New booking or ride update received. Tap to view.';
  const icon  = notif.icon  || data.icon  || 'https://pavancab.com/app/logo-pavancab.png';
  const url   = data.url    || data.click_action || '/app/';
  const evtType = data.event_type || data.status || 'default';
  const tone = getTone(evtType);

  const options = {
    body: body,
    icon: icon,
    badge: 'https://pavancab.com/app/logo-pavancab.png',
    tag: 'pavancab-alert-' + (data.booking_id || Date.now()),
    requireInteraction: true,
    vibrate: tone.vibrate,
    data: {
      url: url,
      booking_id: data.booking_id || '',
      event_type: evtType
    },
    actions: [
      { action: 'open', title: '📋 Open App' },
      { action: 'dismiss', title: '✕ Dismiss' }
    ]
  };

  self.registration.showNotification(title, options);
  broadcastToClients(title, body, url, evtType);

  // Play notification tone audio via fetch
  try {
    fetch(tone.url).then(r => r.blob()).then(blob => {
      const ctx = new AudioContext();
      blob.arrayBuffer().then(buf => ctx.decodeAudioData(buf).then(decoded => {
        const src = ctx.createBufferSource();
        src.buffer = decoded;
        src.connect(ctx.destination);
        src.start(0);
        src.onended = () => ctx.close();
      }).catch(()=>{}));
    }).catch(()=>{});
  } catch(e) {}
});

function broadcastToClients(title, body, url, eventType) {
  try {
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        client.postMessage({
          type: 'FCM_PUSH_RECEIVED',
          title: title,
          body: body,
          url: url,
          event_type: eventType || 'default'
        });
      }
    });
  } catch(e){}
}

self.addEventListener('push', (event) => {
  if (!event.data) return;
  try {
    const rawData = event.data.json();
    const notif = rawData.notification || {};
    const data  = rawData.data || {};
    const title = notif.title || data.title || '🚕 PAVANCAB Ride Update';
    const body  = notif.body  || data.body  || 'Tap to view live trip details.';
    const icon  = notif.icon  || data.icon  || 'https://pavancab.com/app/logo-pavancab.png';
    const url   = data.url    || data.click_action || '/app/';
    const evtType = data.event_type || data.status || 'default';
    const tone = getTone(evtType);

    const options = {
      body: body,
      icon: icon,
      badge: 'https://pavancab.com/app/logo-pavancab.png',
      tag: 'pavancab-alert-' + (data.booking_id || Date.now()),
      requireInteraction: true,
      vibrate: tone.vibrate,
      data: {
        url: url,
        booking_id: data.booking_id || '',
        event_type: evtType
      },
      actions: [
        { action: 'open', title: '📋 View Details' },
        { action: 'dismiss', title: '✕ Dismiss' }
      ]
    };

    broadcastToClients(title, body, url, evtType);
    event.waitUntil(self.registration.showNotification(title, options));

    // Play tone
    try {
      fetch(tone.url).then(r => r.blob()).then(blob => {
        const ctx = new AudioContext();
        blob.arrayBuffer().then(buf => ctx.decodeAudioData(buf).then(decoded => {
          const src = ctx.createBufferSource();
          src.buffer = decoded;
          src.connect(ctx.destination);
          src.start(0);
          src.onended = () => ctx.close();
        }).catch(()=>{}));
      }).catch(()=>{});
    } catch(e) {}
  } catch (e) {
    try {
      const text = event.data.text();
      event.waitUntil(
        self.registration.showNotification('🚕 PAVANCAB Notification', {
          body: text || 'Live ride update available',
          icon: 'https://pavancab.com/app/logo-pavancab.png',
          badge: 'https://pavancab.com/app/logo-pavancab.png'
        })
      );
    } catch(err) {}
  }
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  if (event.action === 'dismiss') return;

  let targetUrl = event.notification.data?.url || '/app/';
  try {
    if (!targetUrl.startsWith('http')) {
      targetUrl = new URL(targetUrl, self.location.origin).href;
    }
  } catch(e) {}

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url && 'focus' in client) {
          if ('navigate' in client) {
            client.navigate(targetUrl);
          }
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});
