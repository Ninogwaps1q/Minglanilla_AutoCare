---
name: "Minglanilla AutoCare Hub"
description: "A clear, dependable path from finding a shop to aftercare."
colors:
  primary: "#2563eb"
  primary-hover: "#1d4ed8"
  accent: "#f97316"
  accent-hover: "#d85c0b"
  ink: "#111827"
  muted: "#52627a"
  canvas: "#f5f7fb"
  surface: "#ffffff"
  surface-subtle: "#eef3f8"
  border: "#d6dee9"
  sidebar: "#0b1220"
  success: "#15803d"
  success-bg: "#e7f5ec"
  warning: "#b45309"
  warning-bg: "#fdf0dc"
  info-bg: "#e6efff"
  dark-primary: "#7cb5ff"
  dark-accent: "#fb923c"
  dark-canvas: "#0d1728"
  dark-surface: "#152238"
  dark-surface-subtle: "#1b2a43"
  dark-ink: "#f2f6fb"
  dark-muted: "#c2cede"
  dark-border: "#30415b"
  dark-success: "#6ee7a0"
  dark-success-bg: "#12352a"
  dark-warning: "#fdba74"
  dark-warning-bg: "#3a2a14"
  dark-info-bg: "#1b2f52"
typography:
  headline:
    fontFamily: "Poppins, Inter, system-ui, sans-serif"
    fontSize: "clamp(1.65rem, 3vw, 2.25rem)"
    fontWeight: 700
  title:
    fontFamily: "Poppins, Inter, system-ui, sans-serif"
    fontSize: "1.1rem"
    fontWeight: 600
  body:
    fontFamily: "Inter, system-ui, -apple-system, sans-serif"
    fontSize: "1rem"
    lineHeight: 1.55
  label:
    fontFamily: "Inter, system-ui, -apple-system, sans-serif"
    fontSize: "0.9rem"
    fontWeight: 600
  price:
    fontFamily: "Poppins, Inter, system-ui, sans-serif"
    fontWeight: 700
    fontFeature: "tabular-nums"
rounded:
  control: "0.625rem"
  surface: "0.875rem"
  subtle: "0.75rem"
  pill: "999px"
spacing:
  compact: "0.5rem"
  base: "1rem"
  section: "1.5rem"
  page: "2rem"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.surface}"
    rounded: "{rounded.control}"
    height: "2.75rem"
  button-accent:
    backgroundColor: "{colors.accent}"
    textColor: "#1a0d02"
    rounded: "{rounded.control}"
    height: "2.75rem"
  surface:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.surface}"
---

# Design System: Minglanilla AutoCare Hub

## Overview

**Creative North Star: "The Clear Service Journey"**

The interface should feel like a calm, trustworthy service desk for drivers, not a crowded automotive control panel and not a generic app template. It serves everyday car and motorcycle owners on phones, often on slow connections, and busy shop staff who are not technical. Keep the navy, blue, white, and orange identity. Use orange sparingly to make the single most important action obvious; use blue for regular actions, selection, and links. The same visual language serves public pages and all three portals, while each role sees its own relevant next step.

The product works for any place in the Philippines. Nothing in the interface, copy, defaults, or data assumes Minglanilla or Cebu; "Minglanilla AutoCare" is the brand name only.

This document records the reusable system in `resources/css/app.css` and sets the quality bar for UI upgrades. It does not claim that every page already meets the bar. The reference mockups (shop search, booking, repair tracking in light and dark mode) show the intended result; rebuild them with real components and data, never paste mockup markup.

**Key characteristics:**

- One clear next step per screen, supported by short, plain-language explanations.
- Strong reading contrast in light and dark mode; status never depends on color alone.
- Quiet, flat surfaces around real tasks, not decorative dashboard clutter.
- Pick instead of type: vehicles, addresses, and services are chosen from lists, never typed.
- Real content: real shop names, photos with fallbacks, prices in ₱, dates and times in Asia/Manila.
- Consistent photos, icons, maps, charts, and empty states with useful fallbacks.

## Colors

The light theme uses a soft gray-blue canvas and white work surfaces. The dark theme uses layered navy surfaces and brighter text, not a simple color inversion. The frontmatter tokens are the values declared in `resources/css/app.css`; use its CSS variables so both themes stay synchronized. Do not add colors without approval.

### Primary

- **Service blue:** standard buttons, selected navigation, links, selected rows, and focus context.
- **Dark-mode sky blue:** the same role on dark surfaces; never reuse the light-theme blue where contrast becomes weak.

### Secondary

- **Action orange:** one high-priority action per page, such as "Approve estimate" or "Continue to schedule". It is not a default background, a decoration, or a highlight for cards.

### Neutral

- **Navy sidebar:** stable portal navigation and brand anchor.
- **Canvas, surface, and subtle surface:** separate page background, task panel, and supporting information.
- **Ink and muted ink:** primary and secondary copy; muted copy must still be readable in both themes.
- **Border:** quiet separation for rows, tables, and fields.

### Status

- **Success (green):** OK, verified, paid, open.
- **Warning (amber):** needs attention, closed, waiting, expiring.
- **Info (blue tint):** selected rows, the "next step" box, independent mechanic.
- **Danger:** failed payments, destructive actions, errors. Use the existing Bootstrap danger tokens.

Every status pairs color with a word ("OK", "Needs attention", "Open until 6:00 PM"). Each theme has its own status values; check contrast in both.

**The One Next Action Rule.** On a task-focused view, one action gets primary emphasis. Other valid actions remain visible but visually quieter.

## Typography

Headings use the Poppins/Inter stack; body copy uses Inter and system fonts. If web fonts are not loaded, the system fallbacks are intentional. Do not rely on a font name alone for hierarchy.

- **Headline:** bold and compact for page titles; scales from 1.65rem to 2.25rem. Left-aligned.
- **Section title:** 1.1rem, semibold, placed close to the content it names.
- **Body:** 1rem with a 1.55 line height; keep explanatory copy under about 65 characters wide.
- **Label:** 0.9rem and semibold; place labels above fields and keep them when placeholders disappear.
- **Numbers:** tabular numerals for prices, counts, ratings, dates, and reports. Prices use the price style (Poppins bold) and the peso sign.
- Two weights are usually enough. Do not use all-caps labels, eyebrow text above headings, or a single accented word in a headline.

## Voice and copy

- Write like a helpful local shop: plain Philippine English, and natural Bisaya and Tagalog in the lang files (not word-for-word translations).
- Buttons say what will happen: "Book this shop", "Approve ₱4,830", "Pay ₱2,450", "Get directions". Never "Submit" or "Click here".
- Keep one name for one action through the whole flow: the button that says "Approve" produces a confirmation that says "Approved".
- Errors explain what went wrong and how to fix it, calmly, without apologizing or blaming. Empty states say what is missing and offer the next action.
- Short sentences, sentence case, no buzzwords, no exclamation marks, no filler, no invented statistics or testimonials. If real data is missing, hide the element.

## Layout

Public pages use the shared public navbar and footer. Authenticated pages use a 16rem desktop sidebar, a compact topbar, and a centered content area capped at 100rem. The sidebar becomes an off-canvas menu on smaller screens; customer and shop portals have a bottom navigation on mobile. Do not place the same destination in several competing positions.

Use a clear sequence: page title and purpose, immediate action or attention queue, main task content, then secondary details. Customer pages lead from find a shop → book → estimate decision → repair progress → payment → review and aftercare. Shop pages lead with today's work and blocked jobs. Admin pages lead with approvals, complaints, and operations needing attention.

Vary layout by purpose: lists with dividers for shops and bookings, a timeline for repairs, tables for admin work, and map plus filters for search. Do not repeat grids of identical cards.

Use the Bootstrap grid and spacing rhythm. Let filters and actions wrap or scroll without horizontal page overflow. Filter chips never wrap their text; they scroll horizontally on small screens. Keep wide tables in a contained scroll region, but use compact records on mobile when a table hides the primary action. Test widths around 360px, 768px, and 1440px, including long shop names and long Bisaya or Tagalog text.

## Screen patterns

### Shop search

- Heading with location context, a search field, a distance selector, and a row of filter chips (open now, services, motorcycle, roadside help).
- A result bar ("14 shops found · sorted by distance") and a List/Map toggle.
- Desktop: list on the left, map on the right. Mobile: List/Map toggle.
- Each shop is a row: logo or initials, name, a text badge (Verified or Independent mechanic), rating and review count, distance, open or closed with the time, short services line, "from ₱…" price, and one primary action "Book this shop". Favorites and compare stay quiet and secondary.
- Selecting a row highlights its pin. Pins show the name and starting price; the popup offers Book and Directions.

### Booking

- A step flow with a progress bar and "Step 2 of 4 · Choose services" (vehicle, services, schedule, review). The shop name stays in the header with a back button.
- The chosen vehicle is a compact row with "Change".
- Services are large checkable rows (44px or taller): name, what the price includes, and the price. Selected rows show a checkmark, border, and tint.
- A sticky bottom bar shows the number of services, the estimate before parts, and one primary button. Validation is inline and entered data is kept after errors. The message to the shop is optional and appears at the review step.

### Repair tracking

- Header: vehicle, plate, shop, and a text status badge.
- A "next step" box with the exact action and deadline, which changes by stage: inspection waiting, estimate waiting ("Approve ₱4,830"), repair in progress, payment due ("Pay ₱…"), ready for pickup ("Get directions"), completed ("Leave a review").
- An itemized estimate with what each price includes, a total, and an expiry date.
- Inspection results as a checklist with text badges, the mechanic's note, and photo thumbnails that open larger.
- A progress timeline (Booked, Inspection done, Waiting for your approval, Repair, Payment, Ready for pickup) with the current step highlighted and times in Asia/Manila.

## Elevation & Depth

Surfaces are flat with a one-pixel border; the light theme may add a very soft shadow, and the dark theme relies on tonal layering. Keep elevation restrained: one surface boundary is enough. No gradients as decoration, no glow, no glassmorphism, no stacked rounded cards for depth. Hover elevation may indicate an interactive card but is never the only cue that it can be opened.

## Shapes

Controls use 0.625rem corners, main work surfaces 0.875rem, and pills 999px for chips and badges. The brand logo and profile photos are circular with `object-fit: cover`. Shop logos fall back to initials. Shop covers and repair photos preserve their aspect ratios and use a visible fallback when files are missing. Badges are compact and stay legible beside long titles.

## Components

### Buttons

Primary buttons use service blue. Orange is reserved for the single most important action on a page. Outline or quiet buttons hold secondary actions. Minimum control height is 2.75rem; mobile primary actions may span the available width. Provide hover, focus-visible, disabled, busy, success, and error states.

### Navigation

Show one active route at a time. Group destinations by the user's mental model: finding and booking and service tracking for customers; today's work and shop operations for owners; platform and monitoring for admins. Keep notification and message counts near their topbar controls, with accessible text. A floating assistant is support, not primary navigation.

### Rows, cards and lists

Use a `surface` for a coherent task, not for every line of text. Put a clear title, status, relevant details, and next action in that order. Prefer divided rows over many separate cards. Avoid long runs of identical metric cards; show an attention queue or a meaningful comparison instead.

### Inputs and fields

Visible labels, readable help text, and validation next to the field. Fields use the surface and border tokens with a visible blue focus ring. Group forms by task, preserve entered values, and make destructive choices explicit. Use the vehicle picker and address picker components; never ask for typed make, model, region, province, city, or barangay.

### Status and feedback

Pair color with a status word and, when needed, a short next-step sentence. Show loading for slow requests, confirmation after success, and a recovery path after failure. Unread badges show real counts, with 99+ only as a visual cap.

### Maps, charts, and images

Maps show real geography and identify what each pin represents. Keep the address available when tiles fail. Charts need labels, units, dates, and a text alternative. Uploaded images need crop rules, replacement feedback, and a fallback; never show a broken-image icon.

## Accessibility and performance

Visible keyboard focus, labels on all inputs, passing contrast in both themes, 44px touch targets, meaningful alt text, and no meaning by color alone. Respect reduced motion; use motion only to show what changed after a person's action. Lazy-load images and maps, avoid heavy scripts on pages that do not need them, and keep pages usable on slow mobile data.

## Do's and Don'ts

### Do:

- **Do** reuse the shared layouts, Bootstrap components, existing icon set, and theme variables before adding page-specific styling.
- **Do** make the customer's current next step discoverable without reading a full table or several menus.
- **Do** use real data, real photos with fallbacks, and ₱ prices.
- **Do** inspect each changed page in light and dark themes at mobile, tablet, and desktop sizes, and report when browser tools are not available.
- **Do** preserve working routes, permissions, validation, and real data while improving presentation.

### Don't:

- **Don't** invent a new palette, add low-contrast gray copy in dark mode, or explain a status with color alone.
- **Don't** use gradients, glow, glassmorphism, heavy shadows, emoji icons, stock imagery, or rows of identical icon-title-text cards.
- **Don't** use vague slogans, buzzwords, exclamation marks, fake statistics, or all-caps eyebrow labels.
- **Don't** duplicate actions in multiple places or put ornamental metrics ahead of the user's real task.
- **Don't** turn a working map, chart, form, or upload into a visual placeholder.
- **Don't** hard-code Minglanilla or Cebu anywhere except the brand name.
- **Don't** claim a page is polished or deployment-ready without the relevant build, tests, and browser evidence.
