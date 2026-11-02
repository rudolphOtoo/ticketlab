# 🌐 Track 3: Public Experience & Growth Specification
# Autonomous Work Stream Specification

> **Base Branch**: `dev`  
> **Feature Branch**: `feat/track-3-public-experience`  
> **Primary Route File**: `routes/public.php` & `routes/ops.php` (`/admin/studio/marketing`)  
> **Primary Namespaces**:
> - `App\Livewire\Public\...`
> - `App\Livewire\Admin\MarketingStudio`
> - `resources/views/livewire/public/...`
> - `resources/views/errors/...`  
> **Quality Standard**: 100% Pest pass, PER-CS / Mago formatted, Serene Studio & Sheaf UI tokens.

---

## 1. Domain Objective
Deliver the dynamic public event experience, non-coder promotional marketing tools, and bespoke branded error states for TicketLab. Guests experience a responsive, interactive event landing page with real-time countdown timers, speaker drawers, multi-day schedule filters, and dynamic registration questionnaires injected into checkout. Organizers receive a high-speed Social Media Flyer Generator with aspect ratio presets (`1:1`, `9:16`, `16:9`) and downloadable vector QR code passes.

---

## 2. Shared Data Contracts Consumed (Read/Write Boundaries)

| Model | Read/Write | Purpose |
| :--- | :--- | :--- |
| `Event` | **Read & Update** | Consume title, banner, dates, `customization` (JSON). |
| `EventSpeaker` | **Read-Only** | Render speaker cards, bio drawers, topic titles, and photos. |
| `EventSchedule` | **Read-Only** | Render day-by-day session timeline and venue halls. |
| `EventFaq` | **Read-Only** | Render expandable FAQ accordion. |
| `EventFormField` | **Read-Only** | Dynamic questionnaire inputs injected into checkout modal. |
| `TicketType` | **Read-Only** | Pricing, tier badges, and perks checklist. |
| `Attendee` | **Write** | Store custom questionnaire responses into `attendees.custom_fields` (JSON). |

---

## 3. Detailed Component Architecture

### 3.1 Dynamic Public Landing Page Sections
- **Component**: `App\Livewire\Public\EventShow` (`resources/views/livewire/public/event-show.blade.php`)
- **Route**: `GET /{org_slug}/{event_slug}` (Named: `public.event.show`)
- **Features**:
  1. **Dynamic Hero**: Live Alpine.js countdown timer (Days, Hours, Minutes, Seconds) targeted to `event.start_date`, high-impact headline, and smooth-scroll registration CTA.
  2. **Speakers & Guests Grid**: Interactive cards displaying photo, role, topic, and modal/drawer displaying full bio and social links.
  3. **Program Schedule Tabs**: Filter sessions seamlessly across Day 1, Day 2, Day 3 with venue hall tags and start/end time indicators.
  4. **Dynamic Questionnaire Injection**: Renders active `EventFormField` inputs inside the ticket checkout modal before redirecting/calling Paystack. Responses validated and passed to order fulfillment.
  5. **Interactive FAQ Accordion**: Clean search bar and expandable accordion items with smooth height transitions.

### 3.2 Marketing Studio: Social Media Flyer & Vector QR Generator
- **Component**: `App\Livewire\Admin\MarketingStudio` (`resources/views/livewire/admin/marketing-studio.blade.php`)
- **Route**: `GET /admin/studio/marketing` (Named: `admin.studio.marketing`)
- **Features**:
  1. **Aspect Ratio Switcher**:
     - `1:1` Square (1080x1080) for Instagram & Facebook feeds.
     - `9:16` Story/Status (1080x1920) for WhatsApp Status, Instagram Stories & TikTok.
     - `16:9` Landscape (1920x1080) for Twitter/X banners, display slides & website embeds.
  2. **Live HTML5 Canvas Preview**:
     - Draws customizable organization banner/logo, event date, venue, guest speaker badge, and ticket pass QR code.
     - Real-time client-side re-render on any input change.
  3. **1-Click Export**: Instant client-side download via `canvas.toDataURL('image/png')` with zero server lag.
  4. **Vector Link QR Code Studio**:
     - Generates high-res vector QR passes for the public event URL.
     - Foreground and background color hex pickers.
     - Download buttons for both high-precision `.svg` and print-ready `.png`.

### 3.3 Bespoke Branded Error Pages
- **Directory**: `resources/views/errors/`
- **Layout**: `resources/views/errors/layout.blade.php`
- **Views**:
  - `401.blade.php` (Unauthorized)
  - `403.blade.php` (Access Denied / Forbidden)
  - `404.blade.php` (Event or Page Not Found)
  - `419.blade.php` (Session Expired)
  - `429.blade.php` (Rate Limit Exceeded)
  - `500.blade.php` (Server Error)
  - `503.blade.php` (Platform Maintenance)
- **Aesthetic**: Serene Studio tokens (`#faf9f6`, `#121316`), tactile back-to-home button, helpful diagnostic copy.

---

## 4. Test Verification Matrix (Pest)

- `tests/Feature/Public/DynamicLandingPageTest.php`:
  - Renders countdown timer, speaker grid, schedule tabs, and FAQs from database.
  - Submits valid dynamic questionnaire answers and verifies they persist in `attendees.custom_fields`.
  - Validates required questionnaire field validation failure.
- `tests/Feature/Admin/MarketingStudioTest.php`:
  - Gated by admin authentication and tenant scoping.
  - Generates valid vector QR SVG and flyer canvas parameters.
- `tests/Feature/Errors/BrandedErrorPagesTest.php`:
  - Asserts HTTP status codes and verifies branded error layouts render without missing assets.
