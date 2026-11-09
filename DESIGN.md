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
  dark-primary: "#7cb5ff"
  dark-accent: "#fb923c"
  dark-canvas: "#0d1728"
  dark-surface: "#152238"
  dark-surface-subtle: "#1b2a43"
  dark-ink: "#f2f6fb"
  dark-muted: "#c2cede"
  dark-border: "#30415b"
typography:
  headline:
    fontFamily: "Poppins, Inter, system-ui, sans-serif"
    fontSize: "clamp(1.65rem, 3vw, 2.25rem)"
    fontWeight: 700
  body:
    fontFamily: "Inter, system-ui, -apple-system, sans-serif"
    fontSize: "1rem"
    lineHeight: 1.55
  label:
    fontFamily: "Inter, system-ui, -apple-system, sans-serif"
    fontSize: "0.9rem"
    fontWeight: 600
rounded:
  control: "0.625rem"
  surface: "0.875rem"
  subtle: "0.75rem"
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
  surface:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.surface}"
---

# Design System: Minglanilla AutoCare Hub

## Overview

**Creative North Star: "The Clear Service Journey"**

The interface should feel like a calm, trustworthy service desk for drivers, not a crowded automotive control panel. Keep the existing navy, blue, white, and orange identity. Use orange sparingly to make the most important customer action obvious; use blue for regular actions, selection, and links. The same visual language serves public pages and all three portals, while each role sees its own relevant work.

This document records the current reusable system in `resources/css/app.css` and sets the quality bar for future UI upgrades. It does not claim that every existing page already meets the bar.

**Key characteristics:**

- One clear next step per workflow, supported by short, plain-language explanations.
- Strong reading contrast in light and dark mode; status never depends on color alone.
- Quiet, responsive surfaces around real tasks, not decorative dashboard clutter.
- Consistent photos, icons, maps, charts, and empty states with useful fallbacks.

## Colors

The light theme uses a soft gray-blue canvas and white work surfaces. The dark theme uses layered navy surfaces and brighter text, not a simple color inversion. The frontmatter tokens are the values currently declared in `resources/css/app.css`; use its CSS variables so both themes stay synchronized.

### Primary

- **Service blue:** standard buttons, selected navigation, links, and focus context.
- **Dark-mode sky blue:** the same role on dark surfaces; do not reuse the light-theme blue where contrast becomes weak.

### Secondary

- **Action orange:** a single high-priority call to action or automotive highlight, such as the customer's immediate next step. It is not a default background for every card.

### Neutral

- **Navy sidebar:** stable portal navigation and brand anchor.
- **Canvas, surface, and subtle surface:** separate page background, task panel, and supporting information.
- **Ink and muted ink:** primary and secondary copy; muted copy must still be readable in both themes.
- **Border:** quiet separation for tables and fields when spacing or tonal contrast alone is insufficient.

**The One Next Action Rule.** On a task-focused view, one action gets primary emphasis. Other valid actions remain visible but visually quieter.

## Typography

Headings use the declared Poppins/Inter stack; body copy uses the declared Inter/system stack. If those web fonts are not loaded, the system fallbacks are intentional. Do not rely on a font name alone for hierarchy.

- **Headline:** bold and compact for page titles; page headings currently scale from 1.65rem to 2.25rem.
- **Section title:** smaller than the page heading and positioned close to the content it names.
- **Body:** 1rem with a 1.55 line height; keep explanatory copy to a comfortable measure of about 65 characters where the layout allows.
- **Label:** 0.9rem and semibold; place labels above fields and retain them when placeholders disappear.
- **Numbers:** use tabular numerals for prices, counts, ratings, dates, and reports.

Write controls as actions: “Review estimate,” “Start repair,” and “Mark all as read.” Explain errors in the user's language and point to the field or next recovery step.

## Layout

Public pages use the shared public navbar and footer. Authenticated pages use a 16rem desktop sidebar, a compact topbar, and a centered content area capped at 100rem. The sidebar becomes an off-canvas menu on smaller screens; customer and shop portals have quick mobile navigation. Do not place the same destination in several competing positions unless it serves a distinct mobile or contextual role.

Use a clear sequence: page title and purpose, immediate action or attention queue, main task content, then secondary details. Customer pages should lead from find a shop → book → estimate decision → repair progress → payment → review/aftercare. Shop pages should lead with today's work and blocked jobs. Admin pages should lead with approvals, complaints, and operations needing attention. This is an information hierarchy, not a requirement to show every stage on every page.

Use the existing Bootstrap grid and spacing rhythm. Let cards, filters, and actions wrap without horizontal page overflow. Keep wide tables in a contained scroll region, but use compact records or stacked fields for mobile tasks when a table would make the primary action hard to reach. Test widths around 360px, 768px, and desktop, including long shop names and real counts.

## Elevation & Depth

The current light-theme surfaces use a soft, offset shadow; the dark theme relies more on tonal layering. Keep elevation restrained: one surface boundary is enough. Avoid stacking multiple rounded cards solely to create depth. Hover elevation may indicate an interactive card, but must not be the only cue that it can be opened.

## Shapes

Controls are gently rounded; main work surfaces have slightly larger corners. The brand logo and profile photos are circular with `object-fit: cover` so uploaded images retain a predictable shape. Shop covers and repair photos preserve their intended aspect ratios and use a visible fallback when files are missing. Badges are compact and must remain legible beside long titles.

## Components

### Buttons

Primary buttons use service blue. Orange is reserved for the most important customer next step when the page needs it. Outline or quiet buttons hold secondary actions. Minimum standard control height is 2.75rem; mobile primary actions may span the available width. Provide hover, focus-visible, disabled, busy, success, and error feedback.

### Navigation

Show one active route at a time. Group destinations by the user's mental model: starting/booking and service tracking for customers; today's work and shop operations for owners; platform and monitoring for admins. Keep notification and message counts near their respective topbar controls, with accessible text. A floating AI assistant is support, not a primary navigation destination.

### Cards and lists

Use a `surface` for a coherent task, not for every line of text. Put a clear title, status, relevant details, and next action in that order. Avoid long runs of identical metric cards; show meaningful comparisons or an attention queue where that helps a decision. An empty state says what is missing and offers the correct next action.

### Inputs and fields

Use visible labels, readable help text, and validation next to the relevant field. Fields use the surface and border tokens, with a visible blue focus treatment. Keep forms grouped by the user's task, preserve entered values after validation, and make destructive choices explicit. Do not use placeholder text as the only label.

### Status and feedback

Pair color with a status word and, when needed, a short next-step sentence. Show loading for requests that take time, confirmation after a successful action, and a human-readable recovery path after failure. Unread badges display actual counts, with 99+ only as a visual cap.

### Maps, charts, and images

Maps must show real geography and identify what each pin represents; a coordinate diagram is not a street map. Keep coordinates or an address available when external tiles fail. Charts need labels, units, dates, and a data or text alternative. Uploaded images need crop rules, replacement feedback, and a fallback asset; never show a broken-image icon.

## Do's and Don'ts

### Do:

- **Do** reuse the shared layouts, Bootstrap components, existing icon set, and theme variables before adding page-specific styling.
- **Do** make the customer's current next step discoverable without reading a full table or several overlapping menus.
- **Do** inspect each changed page in light and dark themes at mobile, tablet, and desktop sizes when browser tools are available; report when they are not.
- **Do** preserve functional routes, permissions, validation, and real data while improving presentation.

### Don't:

- **Don't** invent a new palette, add low-contrast gray copy in dark mode, or use color alone to explain a status.
- **Don't** duplicate actions in multiple cards or put ornamental metrics ahead of the user's real task.
- **Don't** turn a working map, chart, form, or upload into a visual placeholder.
- **Don't** claim a page is polished or deployment-ready without the relevant build, tests, and browser evidence.
