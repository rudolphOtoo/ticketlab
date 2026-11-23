// Livewire (with Alpine and its plugins) is bundled here rather than served by PHP from /livewire-*/livewire.min.js,
// so it ships from public/build with a hashed name and immutable caching, and is rebuilt on every deploy. The copy in
// resources/js/vendor is refreshed by `composer update` (composer.json post-update-cmd), and a test fails CI if it
// differs from vendor/livewire. Vercel runs `npm run build` before Composer, so vendor/ itself isn't there to import.
import { Alpine, Livewire } from './vendor/livewire.esm.js';
import './globals/theme.js'; /* By Sheaf.dev */
import ticketCheckout from './checkout/ticket-checkout.js';
import imageUpload from './media/image-upload.js';

Alpine.data('ticketCheckout', ticketCheckout);
Alpine.data('imageUpload', imageUpload);

// Registers the same Alpine plugins as Livewire's prebuilt script, then starts Alpine.
Livewire.start();
