import { breakdown, formatCedis } from './fees.js';

/**
 * Ticket, quantity and zone choice on the public event page, held entirely in the browser.
 *
 * Tapping a tier, a zone or +/- never calls the server: the highlight, seats left and totals update at once.
 * Livewire is only told when the buyer presses Pay, and the server then re-prices and re-validates everything
 * (EventShow::startCheckout), refusing if the total it works out is not the one shown here.
 *
 * The config (built by EventShow::clientCheckout()) is read from a JSON block, not passed in x-data: a Livewire
 * re-render that changed the x-data attribute would rebuild the component and lose the buyer's choices.
 *
 * @param {object} [config] for tests; defaults to the page's [data-checkout-config] block
 */
export default function ticketCheckout(config = readConfig()) {
    return {
        tiers: config.tiers,
        zones: config.zones,
        zoneRequired: config.zoneRequired,
        zoneLabel: config.zoneLabel,
        maxPerOrder: config.maxPerOrder,
        fee: config.fee,

        ticketId: config.initial.ticketId,
        quantity: config.initial.quantity,
        zoneId: config.initial.zoneId,

        open: false,
        paying: false,
        choiceError: '',
        /** Set when the server refused Pay because the page was out of date and sent fresh data. */
        refreshed: false,

        init() {
            this.$watch('ticketId', () => this.clampQuantity());
            this.$watch('zoneId', () => this.clampQuantity());
        },

        get ticket() {
            return this.tiers.find((tier) => tier.id === this.ticketId) ?? null;
        },

        get zone() {
            return this.zones.find((zone) => zone.id === this.zoneId) ?? null;
        },

        tier(id) {
            return this.tiers.find((tier) => tier.id === id) ?? null;
        },

        /** Seats left in this tier for the chosen zone (or overall before a zone is chosen). */
        seatsLeft(tier) {
            if (tier === null) {
                return 0;
            }

            const seats = tier.seats[this.zoneId ?? 0];

            return seats === undefined ? (tier.seats[0] ?? 0) : seats;
        },

        selectable(tier) {
            return tier !== null && tier.onSale && this.seatsLeft(tier) > 0;
        },

        badge(tier) {
            if (tier.status !== null) {
                return tier.status;
            }

            const seats = this.seatsLeft(tier);

            if (seats <= 0) {
                return 'Sold Out';
            }

            return seats <= tier.limitedAt ? 'Limited' : 'Available';
        },

        badgeTone(tier) {
            const badge = this.badge(tier);

            if (badge === 'Limited') {
                return 'limited';
            }

            return badge === 'Available' ? 'available' : 'closed';
        },

        get maxQuantity() {
            return Math.max(1, Math.min(this.maxPerOrder, this.seatsLeft(this.ticket)));
        },

        clampQuantity() {
            this.quantity = Math.max(1, Math.min(this.quantity, this.maxQuantity));
        },

        increment() {
            if (this.quantity < this.maxQuantity) {
                this.quantity++;
            }
        },

        decrement() {
            this.quantity = Math.max(1, this.quantity - 1);
        },

        get totals() {
            return breakdown(this.ticket === null ? 0 : this.ticket.price * this.quantity, this.fee);
        },

        money(pesewas) {
            return `GHS ${formatCedis(pesewas)}`;
        },

        /** Opens the details form; the choices are checked here only so the buyer hears about them straight away. */
        proceed() {
            this.choiceError = '';

            if (!this.selectable(this.ticket)) {
                this.choiceError = 'Select a ticket tier that is on sale before continuing.';
            } else if (this.zoneRequired && this.zone === null) {
                this.choiceError = `Choose your ${this.zoneLabel} to continue.`;
            }

            if (this.choiceError !== '') {
                this.$nextTick(() => document.getElementById('tickets')?.scrollIntoView({ behavior: 'smooth' }));

                return;
            }

            this.open = true;
        },

        /**
         * Fresh prices, seats and fee settings from the server after it refused Pay (EventShow::pricesOrSeatsChanged).
         * The buyer's tier, zone and quantity stay as they were, trimmed to what is now available.
         */
        refresh(config) {
            this.tiers = config.tiers;
            this.zones = config.zones;
            this.fee = config.fee;
            this.maxPerOrder = config.maxPerOrder;
            this.clampQuantity();
            this.refreshed = true;
        },

        /** The only moment the selection reaches Livewire: deferred properties travel with the startCheckout call. */
        async pay() {
            if (this.paying) {
                return;
            }

            this.choiceError = '';

            if (!this.selectable(this.ticket)) {
                this.choiceError = 'This ticket is no longer available for your choice. Close this form and pick another.';

                return;
            }

            this.paying = true;
            this.refreshed = false;
            this.$wire.ticketTypeId = this.ticketId;
            this.$wire.quantity = this.quantity;
            this.$wire.subdivisionId = this.zoneId;
            this.$wire.clientTotalPesewas = this.totals.gross;

            try {
                await this.$wire.startCheckout();
            } finally {
                this.paying = false;
            }
        },
    };
}

function readConfig() {
    const block = document.querySelector('[data-checkout-config]');

    return JSON.parse(block?.textContent ?? '{}');
}
