# 🏛️ TicketLab Design System: "Serene Studio"
# Durable Design System & Token Architecture (/impeccable + /wayfinder)

> **Design Philosophy**: Calm, soothing, high-end minimalist functionalism.  
> **Anti-Pattern Banned**: Zero radioactive neon greens, zero pitch-black AMOLED burn, zero fake cyberpunk HUD boxes, zero visual clutter.  
> **Key Metric**: Zero eye fatigue over an 8-hour shift, effortless customizability via 12 root CSS variables.

---

## 🎨 1. Global Color Tokens & Customization Engine

All visual properties across Public Landing, Admin Dashboard, and Field Terminals inherit directly from these semantic tokens. **Changing a single variable updates the entire application.**

```css
/* ==========================================================================
   TICKETLAB SERENE STUDIO TOKENS (resources/css/theme.css)
   ========================================================================== */

:root {
  /* --- Canvas & Surfaces (Warm Linen & Porcelain) --- */
  --bg-canvas: #FAF9F6;          /* Soothing warm parchment canvas (anti-glare) */
  --bg-surface: #FFFFFF;         /* Pure crisp card surface */
  --bg-subtle: #F3F1EC;          /* Recessed inputs, active tab pills */
  --bg-hover: #EFECE6;           /* Gentle interactive hover state */

  /* --- Text & Contrast --- */
  --text-primary: #18181B;        /* Deep charcoal graphite (never harsh #000000) */
  --text-secondary: #71717A;      /* Quiet neutral gray for labels & metadata */
  --text-tertiary: #A1A1AA;       /* Subtle captions & disabled states */

  /* --- Structural Hairlines --- */
  --border-hairline: #E4E2DC;    /* 1px soft hairline border */
  --border-focused: #18181B;     /* High-intent focus outline */

  /* --- Calming Semantic Accents (Anti-Neon) --- */
  --accent-brand: #18181B;       /* Deep minimalist obsidian primary */
  --accent-brand-fg: #FFFFFF;
  --status-success: #15803D;     /* Nordic Pine / Soothing Forest Green (NOT NEON) */
  --status-success-bg: #F0FDF4;  /* Ultra-soft sage tint */
  --status-warning: #B45309;     /* Warm amber ochre */
  --status-warning-bg: #FFFBEB;
  --status-danger: #B91C1C;      /* Calming brick red */
  --status-danger-bg: #FEF2F2;

  /* --- Geometry & Radii --- */
  --radius-sm: 6px;              /* Small badges, chips */
  --radius-md: 10px;             /* Inputs, buttons */
  --radius-lg: 16px;             /* Cards, modals, drawers */
}

.dark {
  /* --- Dark Mode: "Velvet Basalt" (Warm Charcoal, NOT Pitch Black) --- */
  --bg-canvas: #121316;          /* Deep basalt slate (gentle on retinas) */
  --bg-surface: #181A1F;         /* Elevated card surface */
  --bg-subtle: #20232B;          /* Recessed controls */
  --bg-hover: #282C36;

  --text-primary: #F4F4F5;        /* Soft warm off-white */
  --text-secondary: #9CA3AF;      /* Soft zinc */
  --text-tertiary: #6B7280;

  --border-hairline: rgba(255, 255, 255, 0.08); /* Feather-light border */
  --border-focused: #F4F4F5;

  --accent-brand: #F4F4F5;       /* Crisp white interactive in dark mode */
  --accent-brand-fg: #121316;
  --status-success: #34D399;     /* Gentle sage green (soothing in dark) */
  --status-success-bg: rgba(52, 211, 153, 0.12);
  --status-warning: #FBBF24;
  --status-warning-bg: rgba(251, 191, 36, 0.12);
  --status-danger: #F87171;
  --status-danger-bg: rgba(248, 113, 113, 0.12);
}
```

---

## 🔤 2. Typography Hierarchy

We pair clean, character-rich modern typography with fixed-width tabular figures:

1. **Headings & Brand Title**: **Instrument Sans** or **Plus Jakarta Sans** (clean, humanist geometric warmth, weight 600, letter-spacing `-0.02em`).
2. **Body & UI Elements**: **Inter** or **Geist** (neutral, ultra-legible at 13px–15px).
3. **Financials & Telemetry**: **Geist Mono** with `font-variant-numeric: tabular-nums` (Cedi values like `GHS 1,450.00` never shift or jitter).

---

## 🏛️ 3. How the System Expresses Itself Across 3 Core Surfaces

### Surface A: The Public Event Landing Page (Track 1)
- **Atmosphere**: Welcoming, calm, high-editorial trust.
- **Header**: Minimalist wordmark with subtle active event badge.
- **Hero & Ticket Selection**:
  - Clean event banner with gentle warm parchment backdrop.
  - Ticket tiers displayed as clean white cards with 1px soft hairline borders (`--border-hairline`).
  - Active tier indicated by a crisp, deep obsidian ring (`#18181B`), not screaming neon.
- **Checkout Drawer**: Clean modal summarizing items with a quiet, confident Paystack CTA.

### Surface B: The Event Organizer Dashboard & Financial Ledger (Track 2)
- **Atmosphere**: Professional serenity. An admin can audit 1,000 attendees and GHS 50,000 in cash without eye strain.
- **Top Metric Cards**: White cards with delicate hairlines. Numbers are large, bold, and quiet.
- **Data Tables**: Zero heavy black cell borders. Subtle alternating row hovers (`--bg-subtle`), right-aligned monospace money, and soothing sage pills (`[ Checked In ]`).
- **Session Closeout**: Clear status indicator, one dignified lock button with double confirmation.

### Surface C: The Mobile Operations Terminals (Door Scanner & Cash Desk)
- **Atmosphere**: High-focus, ergonomic, and zero-distraction.
- **Door Scanner**:
  - Full viewfinder framed by delicate 1.5px white corner guides.
  - When verified: A soft **Nordic Pine** banner (`#15803D`) slides in with attendee name in bold white. Zero glowing neon green boxes.
- **Cash Desk**:
  - Large, calm touch targets for tiers (Regular, VIP, Executive).
  - Clean Ghana Phone input with auto-detected mobile money carrier.
  - Large Cedi total, clean physical banknote presets (`[ EXACT ]`, `[ GHS 100 ]`, `[ GHS 200 ]`), and a solid, dignified primary button: `[ Confirm Cash Payment ]`.

---

## 🛠️ 4. How to Customize in 10 Seconds

If you or a client want to re-brand TicketLab in the future:
1. **Want a Warm Terracotta/Earthy Accent?**  
   Change `--accent-brand: #C2410C` in `:root`.
2. **Want a Modern Deep Indigo?**  
   Change `--accent-brand: #4338CA` in `:root`.
3. **Want Sharper or Rounder Corners?**  
   Change `--radius-lg: 24px` for ultra-soft cards, or `4px` for Swiss architectural sharpness.
4. **Want Pure Neutral Grays?**  
   Change `--bg-canvas: #F9FAFB` and `--bg-subtle: #F3F4F6`.
