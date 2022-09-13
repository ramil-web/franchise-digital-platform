import Echo from "laravel-echo";

/**
 * Vue is a modern JavaScript library for building interactive web interfaces
 * using reactive data binding and reusable components. Vue's API is clean
 * and simple, leaving you to focus on building your next great project.
 */
window.Vue = require('vue');

// Define a central event bus
window.EventBus = new Vue();

window._ = require('lodash');

window.ImportedClasses = {};

// window.$ = window.jQuery = require('jquery');

require('bootstrap');

window.Cookies = require('js-cookie');

require('chart.js');
require('vue-click-outside');

window.moment = require('moment');

//collection for class types
window.AppClasses = {};
window.AppClasses.VuePageBuilder = require('./classes/VuePageBuilder').default;

window.axios = require('axios');
window.axios.defaults.headers.common = {
    'X-CSRF-TOKEN': window.Laravel.csrfToken,
    'X-Requested-With': 'XMLHttpRequest',
    'Accept': 'application/json'
};
window.axios.defaults.baseURL = window.Laravel.apiBaseUrl

window.Pusher = require('pusher-js');
window.echoOptions = {
  broadcaster: 'pusher',
  key: window.Laravel.pusherKey,
  cluster: window.Laravel.pusherCluster,
  forceTLS: true,
  authEndpoint: '/broadcasting/auth',
}

const token = window.Laravel.apiToken
if (token) {
  window.axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
  window.echoOptions.auth = {
    headers: {
      Authorization: `Bearer ${token}`
    }
  }
}

$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': window.Laravel.csrfToken
    }
});
