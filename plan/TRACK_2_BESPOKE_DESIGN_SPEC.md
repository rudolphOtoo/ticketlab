# 🏛️ TicketLab Track 2: Bespoke Design Specification
# Visual Identity: "Thermal Hardware & Warm Newsprint" with Bespoke Stencil

> **Branch**: `feat/track-2-ui-designs`  
> **Aesthetic Philosophy**: Tactile Industrial / Thermal Receipt Hardware meets Contemporary West African Precision.  
> **Brand Typeface**: [Bespoke Stencil](https://www.fontshare.com/fonts/bespoke-stencil) (Indian Type Foundry / Fontshare).  
> **Approach**: Mobile-first, glare-resilient, zero AI slop (no generic glassmorphism, no neon purple gradients, no untinted gray card walls).

---

## 🎨 1. Color System & Material Tokens

The palette is anchored in **Warm Newsprint & Charcoal Ink** for daylight clarity, backed by a **Deep Graphite Monolith** mode for night/dim auditoriums. Both modes avoid cold, dead digital grays by incorporating subtle warm ochre undertones.

```css
/* ==========================================================================
   TICKETLAB DESIGN SYSTEM TOKENS (Tailwind / Sheaf Compatible)
   ========================================================================== */

:root {
  /* --- LIGHT MODE: "Warm Newsprint & Thermal Paper" (Default for Outdoor Gate) --- */
  --bg-canvas: #F5F3EF;          /* Warm bone/chalk paper substrate */
  --bg-surface: #FFFFFF;         /* Crisp card and sheet surface */
  --bg-surface-raised: #EDEAE3;  /* Recessed slots, keypad buttons */
  
  --ink-primary: #121316;        /* Deep carbon ink (sharp, high-contrast) */
  --ink-secondary: #575962;      /* Muted metadata, field labels */
  --ink-tertiary: #8C8E99;       /* Inactive indicators, table borders */
  
  --border-structural: #121316;  /* 1.5px hard physical boundary */
  --border-subtle: #E2DDD5;      /* Dividers, table row lines */

  /* Semantic Stamps & Signal Accents */
  --stamp-emerald: #047857;      /* Verified first-time check-in (forest ink) */
  --stamp-emerald-bg: #ECFDF5;
  --stamp-amber: #B45309;        /* Duplicate / Prior check-in alert (warning ochre) */
  --stamp-amber-bg: #FFFBEB;
  --stamp-crimson: #B91C1C;      /* Invalid hash / Fraud flag (crimson red) */
  --stamp-crimson-bg: #FEF2F2;
  --stamp-gold: #B45309;         /* VIP Gold credential border & seal */
  --stamp-gold-bg: #FEF3C7;
  --accent-action: #0F172A;       /* Primary mechanical button background */
}

.dark {
  /* --- DARK MODE: "Deep Graphite & Stencil Monolith" (Auditorium / Night) --- */
  --bg-canvas: #0E1015;          /* Deep carbon graphite */
  --bg-surface: #16181F;         /* Elevated equipment face */
  --bg-surface-raised: #1F222B;  /* Tactile button blocks */
  
  --ink-primary: #F3F4F6;        /* Bone white */
  --ink-secondary: #9CA3AF;      /* Soft zinc */
  --ink-tertiary: #6B7280;       /* Muted technical tags */
  
  --border-structural: #2B2F3B;  /* Crisp technical frame */
  --border-subtle: #1F222B;
  
  --stamp-emerald: #10B981;
  --stamp-emerald-bg: rgba(16, 185, 129, 0.12);
  --stamp-amber: #F59E0B;
  --stamp-amber-bg: rgba(245, 158, 11, 0.12);
  --stamp-crimson: #EF4444;
  --stamp-crimson-bg: rgba(239, 68, 68, 0.12);
  --stamp-gold: #FBBF24;
  --stamp-gold-bg: rgba(251, 191, 36, 0.12);
  --accent-action: #F3F4F6;
}
```

---

## 🔤 2. Typographic Architecture

| Hierarchy | Typeface | Weights | Visual Purpose |
| :--- | :--- | :--- | :--- |
| **Brand Mark & Hero Display** | **Bespoke Stencil** | Bold (700) | "TICKETLAB", "KINGDOM SUMMIT 2026", "SESSION CLOSEOUT" |
| **Verification Seals & Stamps** | **Bespoke Stencil** | Medium (500) | `[ VERIFIED ]`, `[ DUPLICATE ]`, `[ VIP GOLD ]` |
| **UI Controls & Body Copy** | **Cabinet Grotesk** / **General Sans** | Medium (500), Bold (700) | Form inputs, buttons, navigation, usher prompts |
| **Financial Ledgers & Timestamps** | **Geist Mono** / **IBM Plex Mono** | Regular (400), Medium (500) | `GHS 150.00`, `08:42:19 AM`, QR hashes (tabular figures) |

### Key CSS Typography Rules:
- All financial numbers and clock counters MUST declare `font-variant-numeric: tabular-nums` to eliminate jitter when state updates.
- Stencil headers use `letter-spacing: 0.04em` and `text-transform: uppercase` for a mechanical plate-stamp feel.

---

## 📱 3. Mobile-First Component Blueprints

### 3.1 Gate Scanner Viewport (`App\Livewire\Ops\ScannerTerminal`)
- **Structure**:
  - **Top Anchor (15% height)**:
    - Bespoke Stencil event headline: `SUMMIT 2026`.
    - Active Zone tag (`ZONE 03 · KUMASI`).
    - Turnout pill (`412 / 650` in tabular mono).
  - **Viewfinder Zone (50% height)**:
    - 4-corner mechanical viewfinder brackets (thick 3px L-brackets).
    - Camera feed framed cleanly with zero rounded-corner ballooning (crisp 8px radius).
  - **Verification Stamp Card (Lower 35% Thumb Zone)**:
    - Renders as a physical stamped ticket pass with a serrated dotted line separator.
    - **First-Time Scan**: Stamped in Forest Green with `[ VERIFIED ]` in Bespoke Stencil, Attendee Name in Cabinet Grotesk 22px bold, and tier pill.
    - **Duplicate Scan**: Stamped in Warning Ochre with `[ ALREADY CHECKED IN ]` and exact timestamp: `09:14 AM by Usher Mensah`.

### 3.2 Walk-In "Cash Desk" Portal (`App\Livewire\Ops\CashDeskTerminal`)
- **Structure**:
  - Touchscreen-optimized numeric entry for Ghana phone numbers (`+233`).
  - Tier selection chips styled like perforated physical tokens (e.g. `REGULAR · GHS 50` / `VIP GOLD · GHS 120`).
  - Big tactile cash calculator: Instant change math (`Collected - Price = Change Due`).
  - Primary Action Button: High-contrast mechanical button with 2px solid bottom bevel that physically depresses on tap.
  - Digital Ticket Pass: Emulates a thermal receipt with printable QR code and 1-tap WhatsApp sharing.

---

## 🔒 4. Administrative Surfaces

### 4.1 24-Hour Daily Session Closeout (`App\Livewire\Admin\DailySessionLedger`)
- **Layout**: High-density columnar ledger.
- **Top Metrics**: Online Momo/Card vs. Gate Cash vs. Total Gross.
- **Session Freeze**: Red padlock action "Lock Session Day" with double confirmation modal.
- **PDF Output**: Clean, black-and-white audited statement template optimized for thermal printing or A4 official reporting.
