---
name: Velvet Basalt
colors:
  surface: '#121316'
  surface-dim: '#121316'
  surface-bright: '#38393c'
  surface-container-lowest: '#0d0e11'
  surface-container-low: '#1b1b1f'
  surface-container: '#1f1f23'
  surface-container-high: '#292a2d'
  surface-container-highest: '#343538'
  on-surface: '#e3e2e6'
  on-surface-variant: '#c4c7c9'
  inverse-surface: '#e3e2e6'
  inverse-on-surface: '#2f3034'
  outline: '#8e9193'
  outline-variant: '#444749'
  surface-tint: '#c6c6c7'
  primary: '#ffffff'
  on-primary: '#2f3132'
  primary-container: '#e2e2e3'
  on-primary-container: '#636466'
  inverse-primary: '#5d5e60'
  secondary: '#45dfa4'
  on-secondary: '#003825'
  secondary-container: '#00bd85'
  on-secondary-container: '#00452e'
  tertiary: '#ffffff'
  on-tertiary: '#402d00'
  tertiary-container: '#ffdf9f'
  on-tertiary-container: '#815f00'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#e2e2e3'
  primary-fixed-dim: '#c6c6c7'
  on-primary-fixed: '#1a1c1d'
  on-primary-fixed-variant: '#454748'
  secondary-fixed: '#68fcbf'
  secondary-fixed-dim: '#45dfa4'
  on-secondary-fixed: '#002114'
  on-secondary-fixed-variant: '#005137'
  tertiary-fixed: '#ffdf9f'
  tertiary-fixed-dim: '#f9bd22'
  on-tertiary-fixed: '#261a00'
  on-tertiary-fixed-variant: '#5c4300'
  background: '#121316'
  on-background: '#e3e2e6'
  surface-variant: '#343538'
typography:
  headline-xl:
    fontFamily: Plus Jakarta Sans
    fontSize: 40px
    fontWeight: '600'
    lineHeight: 48px
    letterSpacing: -0.03em
  headline-xl-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 30px
    fontWeight: '600'
    lineHeight: 36px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 28px
    fontWeight: '600'
    lineHeight: 36px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 22px
    fontWeight: '500'
    lineHeight: 30px
    letterSpacing: -0.015em
  headline-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 18px
    fontWeight: '500'
    lineHeight: 26px
    letterSpacing: -0.01em
  body-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 26px
    letterSpacing: -0.005em
  body-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 22px
    letterSpacing: 0em
  body-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 20px
    letterSpacing: 0.005em
  label-caps:
    fontFamily: Plus Jakarta Sans
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.08em
  label-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 13px
    fontWeight: '500'
    lineHeight: 18px
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 11px
    fontWeight: '500'
    lineHeight: 16px
    letterSpacing: 0.02em
  data-tabular:
    fontFamily: Plus Jakarta Sans
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
    letterSpacing: -0.01em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 1.25rem
  gutter-desktop: 1.75rem
  margin: 1rem
  margin-tablet: 2rem
  margin-desktop: 3rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2.5rem
---

## Brand & Style

This design system embodies a serene, editorial, and tactile precision tailored for high-craft event production and studio operations. It harmonizes the functional velocity of command-menu interfaces (Linear, Raycast) with the contemplative restraint and warm materiality of slow-lifestyle publications (Kinfolk). 

The emotional response should evoke quiet mastery, focused composure, and architectural permanence. Avoid hyperactive neon accents, aggressive glow states, or visual clutter. The interface acts as a silent gallery backdrop: warm charcoal slate surfaces, whisper-thin boundaries, generous typographical tracking, and organic sage accents that reward prolonged analytical engagement without sensory fatigue.

## Colors

The palette is anchored in tactile basalt strata rather than synthetic void blacks.

- **Canvas & Backing (`#121316`)**: An intentional, warm charcoal base that prevents ocular fatigue while delivering deep visual authority.
- **Surface Elevation 1 (`#181A1F`)**: Soft slate container tone for workspace panes, metric panels, and event cards.
- **Surface Elevation 2 (`#1E2127`)**: Raised hover states, nested data cells, dropdown flyouts, and modal dialogs.
- **Primary Kinetic Accent (`#F4F4F5`)**: Crisp bone-white used purposefully for definitive primary affordances, command keystroke badges, and tier-one typography.
- **Secondary Accent (`#34D399`)**: A soft Nordic Sage Green denoting active registrations, healthy conversion curves, and live event states. Never emit neon drop glows.
- **Tertiary Accent (`#FBBF24`)**: Warm honey amber for ticket threshold warnings, pending payouts, and attention-required flags.
- **Structural Lines (`rgba(255, 255, 255, 0.08)`)**: Hairline borders defining geometry without visual obstruction.
- **Content Hierarchy**: High-contrast Bone White (`#F4F4F5`) for titles, Muted Stone Grey (`#9CA3AF`) for body and structural labels, and Dim Basalt (`#525866`) for metadata and shortcuts.

## Typography

Typography prioritizes quiet editorial confidence and optical clarity across dense ticketing schedules and analytical overviews.

- **Typeface Selection**: `Plus Jakarta Sans` provides geometric discipline infused with warm humanist terminal curves, delivering high readability across both micro metadata and expansive headline scales.
- **Tabular Figures**: All data-driven metrics, currency values, inventory capacities, and chronological timestamps must enforce OpenType tabular figures (`font-variant-numeric: tabular-nums; font-feature-settings: "tnum" 1`).
- **Small Caps & Badging**: Micro labels, section dividers, and operational status headers apply uppercase transformations with generous optical tracking (`0.08em`) to ensure legibility on dark basalt grounds without requiring heavy weights.

## Layout & Spacing

The structural layout uses an architectural 12-column responsive fluid grid structured around steady 8pt baseline intervals, emphasizing negative space over crowded card clusters.

- **Breakpoints & Canvas Flow**:
  - **Desktop (≥ 1280px)**: 12-column grid with `3rem` margins and `1.75rem` gutters. Maximum content bounding shell set to `1440px` centered to evoke publication spreads.
  - **Tablet (768px – 1279px)**: 8-column layout, `2rem` margins, collapsing non-essential peripheral inspectors into layered command panes.
  - **Mobile (< 768px)**: 4-column flow with `1rem` margins and `1rem` gutters; secondary dashboard telemetry stacks vertically beneath main schedule timelines.
- **Component Rhythms**: Internal element separation relies on consistent application of `space-sm` (8px) and `space-md` (16px), reserving `space-xl` (40px) exclusively to separate distinct programmatic domains (e.g., ticket tier matrices from payout charts).

## Elevation & Depth

Visual hierarchy rejects excessive blur radiuses, floating colored neons, or high-cast drop shadows. Depth is maintained strictly through tonal layer stratification and whisper-thin borders:

- **Baseline Canvas**: Ground zero at `#121316`. No border.
- **Resting Structural Units (Elevation 1)**: `#181A1F` bordered with `1px solid rgba(255, 255, 255, 0.08)`. Shadows are suppressed in resting state to create an architectural, inlaid appearance.
- **Interactive & Raised Floating Units (Elevation 2)**: Modals, popovers, and elevated hover cards transition to `#1E2127` bordered with `1px solid rgba(255, 255, 255, 0.12)`. Supported by an ultra-subtle, low-opacity ambient drop shadow: `0 8px 32px -4px rgba(0, 0, 0, 0.40), 0 2px 8px -2px rgba(0, 0, 0, 0.25)`.
- **Command Overlays & Focus Sheets**: Frosted basalt underlays (`rgba(18, 19, 22, 0.85)` with `backdrop-filter: blur(12px)`) provide total contrast insulation against dense data tables beneath.

## Shapes

The design uses Soft geometry (`0.25rem` / `4px` base radius) to communicate surgical utility, architectural permanence, and editorial poise. 

- **Inputs, Chips, & Buttons**: Built with `rounded` (`0.25rem`), preserving defined corner geometries reminiscent of precision hardware and bespoke stationery.
- **Cards & Data Modules**: Built with `rounded-lg` (`0.5rem`) to delineate distinct interactive workspaces without yielding rounded playfulness.
- **Modals, Drawers & Flyouts**: Built with `rounded-xl` (`0.75rem`) to smoothly separate modal layers from the primary viewport perimeter.
- **Status Indicators & Micro Avatars**: Fully rounded pills (`9999px`) reserved strictly for live state indicators, tag chips, and attendee token avatars.

## Components

### Buttons
- **Primary Action**: Crisp Bone White (`#F4F4F5`) fill, Basalt deep text (`#121316`), semi-bold weight. Subtle hover transition to `#E4E4E7`. Focus state adds an outer offset ring with `rgba(244, 244, 245, 0.3)`.
- **Secondary Surface**: Background `#181A1F`, border `1px solid rgba(255, 255, 255, 0.08)`, text `#F4F4F5`. On hover, background shifts to `#1E2127` with border brightening to `rgba(255, 255, 255, 0.16)`.
- **Ghost / Utility**: Transparent background, text `#9CA3AF`. Hover renders text `#F4F4F5` with background `#181A1F`. Keystroke hints display in discrete `label-sm` caps inside a recessed `#121316` pill.

### Chips & Badges
- **Status Chips**: Background `#181A1F`, border `1px solid rgba(255, 255, 255, 0.08)`. Accompanied by a 6px solid dot indicator: Nordic Sage (`#34D399`) for "Live On Sale", Honey Amber (`#FBBF24`) for "Few Seats Remaining", and Muted Grey (`#525866`) for "Concluded".
- **Category Filter Chips**: Minimal interactive pills. Unselected states blend into the canvas with muted stone grey text; selected states invert to soft slate `#1E2127` with a fine white border (`rgba(255, 255, 255, 0.20)`).

### Input Fields & Selectors
- **Base Surface**: `#121316` inset background with `1px solid rgba(255, 255, 255, 0.08)` border. Text in `#F4F4F5`, placeholder copy in `#525866`.
- **Active / Focus**: Border shifts smoothly to Nordic Sage (`#34D399`) or Bone White (`rgba(255, 255, 255, 0.35)`) with zero outer glow.
- **Command Bars**: Unified floating search console with backdrop blur (`#181A1F` at 90% opacity), integrated hotkey labels (`⌘K`), and high-speed list indexing.

### Cards & Container Panels
- **Structure**: Rendered in `#181A1F` slate, framed by a `1px solid rgba(255, 255, 255, 0.08)` hairline.
- **Section Dividers**: Inner horizontal and vertical separations use `rgba(255, 255, 255, 0.05)` rather than structural empty gaps to maintain cohesive data sheets.
- **Interactive State**: Elevates subtly to `#1E2127` on pointer hover with no positional translation.

### Checkboxes & Radios
- **Checkboxes**: Inset square (`16px × 16px`) with `rounded-sm` corners, `#121316` fill, and structural hairline border. When checked, fill transitions to `#F4F4F5` with a `#121316` micro checkmark icon.
- **Radio Buttons**: Concentric circles with `#121316` base; active state features a `#34D399` sage center disc.

### Lists & Telemetry Tables
- **Rows**: Fixed 48px baseline height for standard data entries, utilizing tabular numerals.
- **Dividers**: Bottom hairline border (`rgba(255, 255, 255, 0.04)`). Row hover transitions seamlessly to `#181A1F`.
- **Studio Additions**: Tier capacity progress meters built with ultra-thin 3px horizontal rails: `#1E2127` rail track with `#34D399` progress fill.