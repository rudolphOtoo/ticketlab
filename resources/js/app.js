import './globals/theme.js'; /* By Sheaf.dev */
import { Html5Qrcode } from 'html5-qrcode';
import ticketCheckout from './checkout/ticket-checkout.js';

window.Html5Qrcode = Html5Qrcode;

// Livewire bundles Alpine and starts it on DOMContentLoaded, after this module has run.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('ticketCheckout', ticketCheckout);
});
