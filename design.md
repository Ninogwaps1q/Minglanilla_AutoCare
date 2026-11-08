---
name: Minglanilla AutoCare Hub
description: Service desk design direction for clear, dependable local vehicle care.
colors:
  workshop-navy: "#122238"
  canvas: "#f4f5f7"
  surface: "#ffffff"
  surface-subtle: "#edf0f4"
  ink: "#111827"
  muted: "#536174"
  accent: "#fb923c"
  accent-hover: "#fdba74"
  border: "#d5dce5"
rounded:
  panel: ".75rem"
---

# Minglanilla AutoCare Hub Design Guide

## Overview

**Status: shared visual foundation implemented; page-specific direction remains proposed.** The shared stylesheet now applies the service desk palette, compact dashboard introductions, restrained navigation, flat bordered panels, readable tables, consistent form labels, and accent button states. Existing routes, content, and workflows remain in place. The tokens above describe the implemented light theme; existing dark-theme overrides remain in effect. Page-specific information architecture and component behavior below remain guidance for subsequent work, not a claim that every screen has been redesigned or visually verified.

**The Service Desk Rule.** Make every screen feel like a well-organized automotive service desk: practical, reassuring, and easy to act on. The signature is a clear vehicle identity, a readable service journey, and a visible next step. Use restrained workshop-inspired details rather than decorative automotive imagery across working screens.

The system serves drivers and service shops in Minglanilla, Cebu. Preserve the product name and existing logo. This proposal refines the blue, orange, and dark-neutral identity into a calmer interface: deep navy provides structure, orange identifies the main action, and neutral surfaces keep operational information readable.

| Surface | User goal | Proposed emphasis |
| --- | --- | --- |
| Public website | Find and assess a local shop | Search, location, services, verification, and genuine reviews |
| Customer portal | Arrange and understand vehicle care | Vehicle context, next appointment, approvals, repair progress, and payment status |
| Shop portal | Keep daily work moving | Today's work, pending decisions, job queues, staff, parts, and payments |
| Admin portal | Oversee platform operations | Verification queues, exceptions, service cases, monitoring, and audit evidence |

These are presentation priorities for existing features, not authorization to introduce new business rules or capabilities.

## Colors

**The One Action Rule.** Use orange for the dominant action in a section. Keep secondary controls neutral and use blue for supporting links and navigation. Do not make every action orange.

Proposed palette roles:

| Role | Direction | Application |
| --- | --- | --- |
| Structural | Deep workshop navy | Sidebar, public navigation, strong headings, and identity details |
| Primary action | Warm service orange | Booking, continuing a workflow, and the main next step |
| Supporting action | Restrained blue | Links, selected navigation, and informational emphasis |
| Canvas | Soft neutral gray | Workspace background |
| Surface | White in light mode; lifted navy in dark mode | Forms, records, cards, tables, and dialogs |
| Text | Near-black or near-white | Main content, adjusted to the theme |
| Secondary text | Legible slate | Supporting explanations and metadata |

Retain conventional semantic colors for success, warning, error, and information. Pair every status color with a visible label; orange branding must not obscure a warning or an emergency state. Define separate foreground/background pairs for each theme and verify contrast. Target WCAG AA text contrast and visible control boundaries.

Implementation source: `resources/css/app.css` defines `--ink`, `--muted`, `--surface`, `--canvas`, `--accent`, `--accent-dark`, `--surface-subtle`, and `--border-color`, with overrides under `html[data-theme="dark"]`. The new `--workshop-navy` and `--workshop-radius` roles unify the shared shell and panels. Evolve these shared roles rather than scattering new literal colors across templates. Accent buttons use dark text in both themes; blue remains the supporting navigation/link color.

## Typography

Use a readable sans-serif family with a dependable system fallback. Keep the existing Inter/system-font approach as the starting point; verify font delivery before depending on a named web font. Avoid adding a font dependency solely for decoration.

Use a compact hierarchy: one page title, section headings, record titles, body text, and secondary metadata. Emphasize vehicle identifiers, shop names, totals, and appointment times through weight and placement. Reserve uppercase for short labels. Avoid tiny text for repair status, fees, warnings, or instructions.

Use tabular numerals for money, stock counts, metrics, and timestamps. Wrap long names and references without breaking the page. Display currency, units, dates, and times consistently with the application's existing formatting and configuration.

## Layout

**The Next Step Rule.** Start each working page with its purpose, current context, and the most relevant next action. Follow with pending decisions and then supporting records. Keep greetings and decorative banners smaller than the work they introduce.

Preserve distinct public and authenticated layouts. Reuse `resources/views/components/layouts/public.blade.php` and `resources/views/components/layouts/app.blade.php` rather than creating parallel shells.

- Public pages: lead with shop discovery and clear search/filter controls; keep map and list views connected. Shop details should prioritize location, available services, verification, reviews, and booking.
- Customer pages: prioritize the relevant vehicle and active service journey. Keep quotations, repair evidence, invoices, and messages reachable from the associated work.
- Shop pages: prioritize an actionable daily queue. Group bookings, repair jobs, technicians, inventory, and payment work by task; preserve their existing relationships and permissions.
- Admin pages: prioritize pending verification and operational exceptions before aggregate reporting. Keep record context available when making a decision.

On desktop, use a stable sidebar and flexible content area. On smaller screens, use the existing offcanvas navigation and customer quick navigation as the starting point. Stack summaries and form columns; allow deliberate horizontal scrolling for dense tables rather than compressing text beyond readability. Keep primary actions reachable without covering content, inputs, or the message composer.

Use a consistent spacing rhythm and align related fields, labels, and actions. Separate information primarily through whitespace and subtle dividers. Avoid enclosing every value in its own card.

## Elevation & Depth

Favor flat working surfaces, subtle borders, and restrained shadows. Use stronger elevation for overlays and floating controls where depth explains interaction. Avoid large shadows on every dashboard panel.

In dark mode, distinguish canvas, surfaces, and overlays through tonal changes and borders. Preserve readable muted text, table separators, form controls, maps, and chart labels. Do not simulate dark mode by inverting the entire page.

## Shapes

Use modest, consistent corner rounding for cards, inputs, and buttons. Use pills for short statuses and counts; keep operational tables rectangular and aligned. Preserve the circular logo treatment where it supports brand recognition. Avoid mixing unrelated corner styles across portals.

Keep controls comfortably usable by touch; use the existing navigation minimum target height of 44px as a starting point. Preserve visible keyboard focus and recognizable disabled states.

## Components

Reuse existing Blade components in `resources/views/components/` before adding alternatives. The frontend currently imports Bootstrap and Bootstrap Icons through Vite; follow that component system for implementation. This proposal does not require a framework migration or dependency change.

| Component | Proposed design behavior | Existing starting point |
| --- | --- | --- |
| Page header | Title, short context, and a clear primary action | Existing page and section heading patterns |
| Action queue | Show actionable work with counts and direct destinations | `action-queue.blade.php` |
| Service progress | Label current stage and available next action; preserve actual workflow states | `service-journey.blade.php`, `repair-timeline.blade.php` |
| Forms | Persistent labels, related field groups, useful inline errors, preserved input | `form-errors.blade.php`, existing form templates |
| Lists and tables | Consistent columns, readable status labels, filters, empty results, pagination | Existing portal list views |
| Shop identity | Real image or fallback, name, location, verification, and review context | `uploaded-image.blade.php`, `star-rating.blade.php` |
| Repair evidence | Clear image captions and association with the correct repair | Existing repair evidence grid and handover components |
| Quotations and invoices | Separate items, totals, approval/payment state, and next action | Existing quotation views and `invoice-document.blade.php` |
| Messages | Keep recipient and service context visible; distinguish pending and failed delivery | `message-contact-list.blade.php`, `message-conversation.blade.php` |
| Notifications | Readable event text and direct record context | `notification-center.blade.php` |
| Reports | Clearly labeled figures, date/filter context, accessible chart alternatives | Existing report components |
| Theme controls | Consistent light/dark behavior without first-paint flashing | `theme-init.blade.php`, `theme-toggle.blade.php` |
| Error and empty states | Explain what happened and provide a relevant recovery action | `error-page.blade.php`, existing error views |

Keep destructive actions visually distinct and confirm consequential changes with specific record context. Show loading, empty, validation, permission, and unavailable states where relevant. Preserve server-side authorization; hiding a button is only presentation.

Use plain, action-oriented copy such as "Book a service", "Review quotation", or "View repair progress". Do not invent service guarantees, approval badges, ratings, availability, prices, or completed outcomes. Keep AI assistance clearly identifiable and secondary to the core workflow.

Use semantic headings, associated form labels, keyboard-operable controls, accessible icon names, and the existing skip link. Respect reduced-motion preferences. Motion should communicate a state change without delaying work.

## Do's and Don'ts

- Do preserve existing routes, roles, permissions, business states, and record relationships during visual work.
- Do inspect the affected page, shared styles, and sibling components before implementation.
- Do keep the customer, shop, and admin portals visually related while prioritizing different tasks.
- Do show the next step, vehicle/service context, and meaningful status text together.
- Do verify changed screens on mobile and desktop, in light and dark themes, with long content and relevant failure states.
- Do treat exact new tokens as unresolved until implemented and validated; then update this guide to distinguish adopted decisions from remaining proposals.
- Don't turn operational dashboards into oversized marketing pages or fill them with redundant metric cards.
- Don't depend on color or icons alone to convey status, urgency, or meaning.
- Don't replace shared components with page-specific copies or add a new UI framework without approval.
- Don't introduce business behavior as a side effect of a design change.

This guide proposes presentation direction. `AGENTS.md` and applicable project rules continue to govern implementation, testing, dependencies, and tooling.
