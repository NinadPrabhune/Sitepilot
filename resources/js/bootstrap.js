import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Check if pusherConfig is available
if (window.pusherConfig && window.pusherConfig.key && window.pusherConfig.cluster) {
    window.Pusher = Pusher; // Attach Pusher to window
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: window.pusherConfig.key,
        cluster: window.pusherConfig.cluster,
        forceTLS: true
    });
}

// Axios interceptors can be configured here
window.axios.interceptors.request.use((config) => {
    config.headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    return config;
});
