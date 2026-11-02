# ⚙️ Track 4: Platform Operations & Core Engine Specification
# Autonomous Work Stream Specification

> **Base Branch**: `dev`  
> **Feature Branch**: `feat/track-4-platform-engine`  
> **Primary Route File**: `routes/ops.php` & `routes/platform.php` (or web.php)  
> **Primary Namespaces**:
> - `App\Livewire\Admin\...`
> - `App\Livewire\Platform\...`
> - `App\Http\Middleware\EnsureSuperAdmin`  
> **Quality Standard**: 100% Pest pass, PER-CS / Mago formatted, Serene Studio & Sheaf UI tokens.

---

## 1. Domain Objective
Deliver the administrative core of TicketLab: self-service ticket tier and price management, visual non-coder CMS customization, dedicated developer Super Admin controls, and high-performance streaming data exports that guarantee zero vendor lock-in.

---

## 2. Shared Data Contracts Consumed (Read/Write Boundaries)

| Model | Read/Write | Purpose |
| :--- | :--- | :--- |
| `Organization` | **Read & Update** | Tenant branding, settings, status. |
| `Event` | **Read & Update** | Active event configuration, `customization` (JSON). |
| `TicketType` | **Full CRUD** | Price, capacity, active status, sales window, perks checklist. |
| `EventSpeaker` | **Full CRUD** | Speaker roster management (photo, name, bio, topic). |
| `EventSchedule` | **Full CRUD** | Day-by-day program sessions and time allocations. |
| `EventFaq` | **Full CRUD** | Frequently asked questions and answers. |
| `EventFormField` | **Full CRUD** | Custom registration questionnaire fields and validation rules. |
| `Attendee` | **Read & Stream** | Attendee roster with dynamic questionnaire JSON unflattening. |
| `Order` | **Read & Stream** | Financial transaction records. |

---

## 3. Detailed Component Architecture

### 3.1 Organization Admin Ticket Tier & Price Manager
- **Component**: `App\Livewire\Admin\TicketManager` (`resources/views/livewire/admin/ticket-manager.blade.php`)
- **Route**: `GET /admin/tickets` (Named: `admin.tickets`)
- **Behaviors**:
  - Displays interactive table/cards of ticket tiers with capacity progress bars (`sold / total`).
  - Slide-over or modal form to create and edit tiers:
    - Tier Name, Description, Gross Price (GHS).
    - Total Capacity (with safeguard: cannot reduce capacity below currently sold tickets).
    - Sales Start and End Dates.
    - Active toggle (instantly pauses or opens sales).
    - Perks array builder (e.g. "Front Row Seating", "Lunch Buffet", "Access to Workshop Materials").
  - Soft-delete or archive action for tiers with zero sales.

### 3.2 Non-Coder Visual Event Customizer Studio
- **Component**: `App\Livewire\Admin\EventCustomizer` (`resources/views/livewire/admin/event-customizer.blade.php`)
- **Route**: `GET /admin/studio` (Named: `admin.studio`)
- **Tabs**:
  1. **Brand & Palette**:
     - Theme selector (Default Serene Linen/Basalt, Royal Amethyst & Gold, Emerald & Bronze, or Custom Accent Hex).
     - Logo and hero banner file uploader with live preview.
  2. **Hero & Countdown**:
     - Main headline, event tagline, countdown target date & time.
  3. **Speakers & Guests Manager**:
     - Inline form to add/edit/delete speakers with photo upload, title, bio, and social links.
  4. **Program Schedule Builder**:
     - Organize sessions by Day tabs (Day 1, Day 2, etc.) with time pickers, speaker assignment, and hall location.
  5. **Dynamic Questionnaire Builder**:
     - Add custom fields (`text`, `select`, `radio`, `checkbox`, `textarea`).
     - Set field key, label, placeholder, options list (for dropdowns), and required toggle.
  6. **FAQ Accordion Manager**:
     - Add, edit, reorder, and publish FAQ pairs.

### 3.3 Dedicated Super Admin Console (`/platform`)
- **Component**: `App\Livewire\Platform\PlatformDashboard` (`resources/views/livewire/platform/platform-dashboard.blade.php`)
- **Middleware**: `App\Http\Middleware\EnsureSuperAdmin` (`auth`, `users.role === 'super_admin'`)
- **Route**: `GET /platform` (Named: `platform.dashboard`)
- **Capabilities**:
  - Cross-tenant executive overview:
    - Total Tenants / Organizations.
    - Total Published Events.
    - Platform Gross Merchandise Value (GMV).
    - Developer Platform Fee Accumulator (e.g. GHS 3.00 per paid ticket).
    - Active System Health, Database Connections & Queue Heartbeat.
  - Tenant list with 1-click drilldown into tenant event stats.

### 3.4 Anti-Vendor-Lock-In Data Export Central
- **Component**: `App\Livewire\Admin\DataExportCentral` (`resources/views/livewire/admin/data-export-central.blade.php`)
- **Route**: `GET /admin/exports` (Named: `admin.exports`)
- **Features**:
  1. **Streaming Attendee Roster (CSV / Excel)**:
     - Leverages `spatie/simple-excel` with 500-row chunking ($O(1)$ memory).
     - Dynamically inspects `event_form_fields` and expands `attendees.custom_fields` (JSON) into separate first-class columns (e.g. `Bus Pickup`, `Department`, `Dietary Preference`).
  2. **Full JSON Tenant Backup**:
     - Generates a single complete `.json` export file containing the organization's entire data footprint (Event configuration, ticket types, orders, attendees, speakers, schedules, FAQs).

---

## 4. Test Verification Matrix (Pest)

- `tests/Feature/Admin/TicketManagerTest.php`:
  - Org Admin can create, update, and toggle ticket tiers.
  - Prevents reducing capacity below sold tickets count.
  - Multi-tenant isolation: Admin cannot view or mutate another tenant's ticket tiers.
- `tests/Feature/Admin/EventCustomizerTest.php`:
  - Saves customization payload to `events.customization`.
  - CRUD operations for speakers, schedules, FAQs, and custom form fields.
- `tests/Feature/Platform/SuperAdminTest.php`:
  - Rejects unauthenticated and non-super-admin users with 403 Forbidden.
  - Super admin successfully accesses dashboard and calculates cross-tenant aggregates.
- `tests/Feature/Admin/DataExportTest.php`:
  - Streaming CSV export contains all standard and dynamic questionnaire columns.
  - JSON backup generates valid, parseable schema containing all tenant records.
