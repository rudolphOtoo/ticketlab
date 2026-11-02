# 🏛️ TicketLab Brand Identity & Field Ergonomics Mood Board
# Systematic Research & Design Engineering Architecture (/systematic-research)

> **Context**: Event gate operations in West Africa (Accra/Kumasi conferences, campus festivals, major church summits).  
> **Benchmarks Studied**: **DICE Access** (London/NY music venues), **RA Scan** (Resident Advisor global electronic festivals), **Luma Check-In** (San Francisco tech summits), and **Square Register / Stripe Terminal** (retail hardware mPOS).  
> **Anti-Pattern Exterminated**: Generic "vibe-coding" AI slop (gratuitous "AI Optical 60fps" pills, card-within-card stacking, generic blue buttons, dollar signs, and overlapping nav docks).

---

## 🔬 1. Systematic Research: Why the Previous Mockup Felt "Vibe-Coded"

Our empirical audit of the Stitch mockup identified the exact AI hallucination patterns that kill professional credibility:

| Vibe-Coded AI Hallucination | Real-World Event Benchmark (DICE / RA / Square) | Design Engineering Fix |
| :--- | :--- | :--- |
| **Gimmick Micro-Copy**: "AI Optical 60fps", "Quick KYC", "Zero Surcharge" | Field apps show **zero marketing copy**. Door ushers need raw operational telemetry: Gate Number, Lane Name, Sync Status. | Purge all gimmick tags. Show only `GATE 04`, `ONLINE`, and `412 / 650`. |
| **Two-Step Manual Admission**: Scanning shows a card, requiring usher to tap "Admit & Scan Next" button | **Single-Pass Auto-Admission**: DICE and RA automatically stamp admission on detection. The screen flashes green, vibrates, and resets in 1.2s. | Auto-admit on valid hash; manual button only for overrides or exceptions. |
| **Card Stack Overload**: 5 separate rounded cards stacked on top of each other, pushing content off-screen | **Unified Plane / Progressive Disclosure**: 1 clear viewport or 1 clean bottom sheet with generous breathing room. | Kill nested card borders; use whitespace and subtle surface elevation. |
| **Wrong Currency**: `$120`, `$45`, `$250` | Ghanaian event software handles **Ghana Cedis (GHS)** with physical cash notes (GHS 10, 20, 50, 100, 200). | Strict currency: `GHS 120.00` in fixed-width tabular monospace. |
| **Dock Collision**: Floating bottom navigation bar overlaps and covers the primary action button | **Fixed Ergonomic Thumb Shelf**: Navigation is docked or hidden during active scan; the primary trigger owns the bottom 72px. | Clean thumb-zone hierarchy with zero element collisions. |

---

## 🎨 2. The TicketLab Mood Board: "Nordic Utility × African Event Pulse"

### 2.1 The Concept
TicketLab is not a social network or an e-commerce store. It is **mission-critical infrastructure** at the gate. It blends the brutal precision of Scandinavian industrial design (Teenage Engineering, Bang & Olufsen, Linear) with the vibrant, high-energy presence of contemporary African events.

### 2.2 Color Architecture
- **Primary Canvas**: 
  - *Light Mode*: Pristine Gallery White (`#FAFAFA`) with subtle warm slate undertone (`#F3F4F6`).
  - *Dark Mode (Recommended for Gate)*: Deep Carbon Titanium (`#0B0D11`) with elevation surface (`#13171F`).
- **The Signal Color (The "Gate Flash")**: 
  - **Electric Acid Mint (`#00E575`)**: High-luminance, unmistakable green that floods the border or status banner when a ticket is valid. Visible from 10 feet away.
  - **Emergency Crimson (`#FF3B30`)**: Instant warning flood for duplicate or invalid passes.
- **Accents & Tiers**:
  - **VIP Amber Gold (`#F59E0B`)**: Subtle, luxurious access tag.
  - **Muted Steel (`#6B7280`)**: Secondary metadata, gate timestamps.

### 2.3 Typographic Identity
- **Primary Workhorse**: **Geist** or **General Sans** — neutral, ultra-sharp geometric sans with negative letter-spacing (`-0.02em`) on bold labels.
- **Display Mark**: **Bespoke Stencil** (used with extreme restraint: only on the TicketLab wordmark and the `[ ADMITTED ]` stamped confirmation seal).
- **Tabular Figures**: **Geist Mono** — every single Cedi price (`GHS 120.00`) and counter (`412 / 650`) uses fixed-width figures so the screen never vibrates when numbers tick up.

---

## 📱 3. Real-World Field Ergonomics

### 3.1 The 1.2-Second Gate Loop (DICE / RA Model)
1. **Target**: Camera viewfinder is large (65% of screen height). Clean, razor-thin white reticle brackets.
2. **Scan**: QR code hits the frame.
3. **Instant Affirmation**:
   - Audio: Short 120ms high-pitch sine chime (880Hz).
   - Haptic: Single 180ms crisp vibration.
   - Visual: Screen perimeter pulses in **Electric Acid Mint (`#00E575`)** with guest name: **"KWESI MENSAH · VIP GOLD"**.
4. **Auto-Reset**: After 1.4 seconds, the verification clears automatically. The camera never pauses. 40 people admitted in 60 seconds.

### 3.2 The Cash Desk (Square POS Model)
1. Single screen: No scrolling required.
2. Left/Top: 3 large tier buttons (Regular GHS 50 / VIP GHS 120 / Executive GHS 300).
3. Middle: 2 clean text fields: Full Name & Ghana Phone (`+233`).
4. Bottom: Big Cash Tender bar with quick change presets: `[ EXACT ]` `[ GHS 150 ]` `[ GHS 200 ]`.
5. Giant full-width action button: `[ 💵 CHARGE GHS 120 (CASH) ]`.
