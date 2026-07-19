// Little Stars Pre School — Firebase Messaging Service Worker
// This file MUST be at the root of your site (same folder as index files)
// so its scope covers the whole site.

importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js');

// ── PASTE YOUR firebaseConfig HERE (same as in parent_dashboard.php) ──
const firebaseConfig = {
  apiKey: "bmqg eqrb xgmi rhvw",
  authDomain: "pre-school-management-sy-689f0.firebaseapp.com",
  projectId: "pre-school-management-sy-689f0",
  storageBucket: "pre-school-management-sy-689f0.appspot.com",
  messagingSenderId: "385222994932",
  appId: "1:385222994932:web:3400c9aaba94fc48e6e7a1"
};

firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

// Handle background messages (when the tab/browser is not focused)
messaging.onBackgroundMessage((payload) => {
  console.log('[firebase-messaging-sw.js] Background message received:', payload);

  const notificationTitle = payload.notification?.title || 'Little Stars Pre School';
  const notificationOptions = {
    body: payload.notification?.body || '',
    icon: '/preschool/assets/icon-192.png', // optional, change path or remove if you don't have one
    badge: '/preschool/assets/badge-72.png', // optional
    data: payload.data || {}
  };

  self.registration.showNotification(notificationTitle, notificationOptions);
});