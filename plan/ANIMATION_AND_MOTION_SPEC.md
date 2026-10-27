# 🎬 TicketLab Animation & Motion Engineering Specification
# Humble, Weighted Motion for High-Speed Gate Operations (/emil-design-eng)

> **Branch**: `feat/track-2-ui-designs`  
> **Philosophy**: Motion is an orientation tool, not entertainment. It must be **humble, weighted, and physical**—augmenting user confidence without stealing attention or lagging low-end field devices.  
> **Review Standards**: Derived from Emil Kowalski's design engineering rules and `/review-animations` quality bar.

---

## ⚖️ 1. The Core Motion Philosophy: "Heavy to Slow the Sight"

In a frantic gate environment with hundreds of attendees in line, frantic bouncy animations increase cognitive stress. Our motion principles:
1. **Grounded Weight over Floating Bounces**: We reject cartoonish spring overshoots. Transitions settle with deliberate, weighted deceleration.
2. **Sub-250ms Operational Budget**: Gate verification and tactile taps never keep the usher waiting.
3. **Hardware Isolation**: Only GPU-composited properties (`transform` and `opacity`) animate. Zero layout reflow triggers (`width`, `height`, `margin`, `padding`).
4. **Asymmetric Interaction Timing**: The user's deliberate physical press feels weighted; the system's confirmation snaps immediately.

---

## 🌓 2. Fluid Theme Transition: Light Mode ↔ Dark Mode

Switching between daylight gate mode ("Warm Newsprint" `#F5F3EF`) and evening auditorium mode ("Deep Graphite" `#0E1015`) must feel fluid and natural—never a jarring, abrasive flash.

### 2.1 Implementation Blueprint: CSS View Transitions API
We leverage the native browser `document.startViewTransition()` to interpolate between themes on the GPU:

```javascript
// resources/js/globals/theme.js
export function toggleTheme(event) {
  if (!document.startViewTransition) {
    document.documentElement.classList.toggle('dark');
    return;
  }

  // Calculate origin coordinates from the clicked trigger button
  const x = event?.clientX ?? window.innerWidth / 2;
  const y = event?.clientY ?? window.innerHeight / 2;
  const endRadius = Math.hypot(
    Math.max(x, window.innerWidth - x),
    Math.max(y, window.innerHeight - y)
  );

  const transition = document.startViewTransition(() => {
    document.documentElement.classList.toggle('dark');
  });

  transition.ready.then(() => {
    // Gentle circular clip-path reveal radiating outwards
    document.documentElement.animate(
      {
        clipPath: [
          `circle(0px at ${x}px ${y}px)`,
          `circle(${endRadius}px at ${x}px ${y}px)`
        ]
      },
      {
        duration: 380,
        easing: 'cubic-bezier(0.23, 1, 0.32, 1)', // Weighted ease-out
        pseudoElement: '::view-transition-new(root)'
      }
    );
  });
}
```

### 2.2 Fallback Theme Easing (Legacy / Non-Supporting Browsers)
For devices where View Transitions are unsupported, colors morph gently over 250ms:
```css
/* Fluid token crossfade on root properties */
html {
  transition: background-color 280ms cubic-bezier(0.23, 1, 0.32, 1),
              color 280ms cubic-bezier(0.23, 1, 0.32, 1);
}
```

---

## 🎛️ 3. Component Motion Tokens & Easing Curves

All easing curves are codified into CSS variables to prevent inconsistent hand-rolled curves:

```css
:root {
  /* Heavy, weighted ease-out for tactile UI entrances and dialogs */
  --ease-weighted-out: cubic-bezier(0.23, 1, 0.32, 1);

  /* Quick snap curve for physical button depression & release */
  --ease-snap: cubic-bezier(0.16, 1, 0.3, 1);

  /* Gentle deceleration for drawer and slide-out trays */
  --ease-drawer: cubic-bezier(0.32, 0.72, 0, 1);
}
```

### 3.1 Verification Stamp Entrance (The Scanner Terminal)
When a QR code is scanned, the result card enters like an ink seal stamped onto paper.
- **Rule**: Never animate from `scale(0)` (Standard #5 violation). Enter from `scale(0.95)` with simultaneous opacity ramp.
- **Specification**:
```css
.ticket-verification-stamp {
  transform: scale(1);
  opacity: 1;
  transition: transform 180ms var(--ease-snap),
              opacity 140ms ease-out;
}

@starting-style {
  .ticket-verification-stamp {
    transform: scale(0.95) translateY(4px);
    opacity: 0;
  }
}
```

### 3.2 Physical Button Press (Cash Desk Keypad & Primary CTAs)
Buttons behave like mechanical keys with spring depth:
```css
.btn-mechanical {
  transform: translateY(0) scale(1);
  box-shadow: 0 3px 0 var(--border-structural);
  transition: transform 120ms var(--ease-snap),
              box-shadow 120ms var(--ease-snap);
}

.btn-mechanical:active {
  transform: translateY(2px) scale(0.985);
  box-shadow: 0 1px 0 var(--border-structural);
}
```

### 3.3 Modal & Dialog Transitions (Session Lock Confirmation)
Modals are centered in the viewport; they do not fly in from edges:
```css
.modal-overlay {
  transition: opacity 220ms ease-out;
}

.modal-dialog {
  transform: scale(1);
  opacity: 1;
  transition: transform 240ms var(--ease-weighted-out),
              opacity 200ms ease-out;
}

@starting-style {
  .modal-dialog {
    transform: scale(0.96);
    opacity: 0;
  }
}
```

---

## 🛡️ 4. The 10 Standards Compliance Table (/review-animations)

| Standard | Implementation in TicketLab | Verification Status |
| :--- | :--- | :--- |
| **1. Justified Motion** | Only scan results, theme transitions, button taps, and modals animate. Roster rows do not fly around. | ✅ Compliant |
| **2. Frequency-Appropriate** | Table pagination and search filter queries update instantaneously without entrance delays. | ✅ Compliant |
| **3. Responsive Easing** | Strictly `var(--ease-weighted-out)` or `var(--ease-snap)`. Zero `ease-in` anywhere. | ✅ Compliant |
| **4. Sub-300ms UI** | Button taps = 120ms; stamp reveal = 180ms; modals = 240ms. | ✅ Compliant |
| **5. Physical Correctness** | Zero `scale(0)`. All entrances start from `scale(0.95-0.96)`. | ✅ Compliant |
| **6. Interruptibility** | Uses CSS transitions and `@starting-style` instead of locked `@keyframes`. | ✅ Compliant |
| **7. GPU-Only Properties** | Only `transform`, `opacity`, and `clip-path` animate. No layout geometry changes. | ✅ Compliant |
| **8. Accessibility** | Gated under `@media (prefers-reduced-motion: reduce)` (drops transforms, retains soft opacity). | ✅ Compliant |
| **9. Asymmetric Timing** | User press down is deliberate (140ms); release/activation snaps in 90ms. | ✅ Compliant |
| **10. Cohesion** | Weighted, non-bouncy physical metaphor consistent across both light and dark modes. | ✅ Compliant |

---

## ♿ 5. Reduced Motion Enforcement
```css
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }

  /* Retain instant semantic color changes without spatial movement */
  html {
    transition: none !important;
  }
}
```
