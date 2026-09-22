# UI.md

# Mo Fashion Store Business Management System — MFBMS
## Official UI Design System

## 1. Purpose

This document locks the official visual design foundation for the **Mo Fashion Store Business Management System (MFBMS)**.

It defines the approved:

- Typography
- Brand colours
- Semantic colours
- Surface colours
- Interaction colours
- Contrast rules
- Usage guidance

This design system should be followed consistently throughout the dashboard, POS, inventory, orders, reports, customers, supplier management, settings, and future WhatsApp/payment interfaces.

---

## 2. Typography

### Primary Font

**Manrope**

Use Manrope as the single interface font family across the system.

### Approved Weights

- `400` — Regular
- `500` — Medium
- `600` — Semi Bold
- `700` — Bold

### Recommended Usage

| UI Element | Weight |
|---|---:|
| Page titles | 700 |
| Dashboard KPI numbers | 700 |
| Section headings | 600–700 |
| Card headings | 600 |
| Buttons | 600 |
| Table headers | 500–600 |
| Labels | 500 |
| Body text | 400 |
| Supporting text | 400 |

### Typography Principle

Use one font family throughout Version 1.

Do not mix decorative fashion fonts into the operational dashboard. The system is data-heavy and must prioritize readability in:

- Tables
- Forms
- Reports
- Inventory quantities
- Financial values
- POS workflows
- Mobile screens

---

## 3. Official Colour Palette

| Role | Hex | Primary Use |
|---|---|---|
| Primary / Brand Gold | `#C9A227` | Primary actions, active states, highlights |
| Gold Hover | `#B8962E` | Button hover and pressed states |
| App Background | `#FAF8F3` | Main application background |
| Card Surface | `#FFFFFF` | Cards, tables, forms, modals |
| Primary Text | `#18181B` | Headings, values, strong labels |
| Secondary Text | `#71717A` | Supporting text, captions, metadata |
| Border / Divider | `#E4E4E7` | Table lines, input borders, card outlines |
| Hover / Selected | `#F3EBD3` | Row hover, selected states, subtle emphasis |
| Information | `#1F5F8B` | Links, tooltips, neutral notices |
| Warning / Low Stock | `#E08A00` | Low stock, pending states, warnings |
| Error / Out of Stock | `#C62828` | Errors, failed payments, destructive actions |
| Success | `#2E7D32` | Completed sales, successful payments, in-stock confirmations |

---

## 4. Core Visual Hierarchy

The approved visual hierarchy is:

**Cream / White → Gold → Black / Charcoal → Champagne**

### Cream / White

Used for:

- Main page background
- Cards
- Forms
- Tables
- Content areas

### Gold

Used for:

- Primary buttons
- Active navigation
- Key brand highlights
- Important but non-dangerous actions

Gold must **not** be used as a warning colour.

### Black / Charcoal

Primary dark colour:

`#18181B`

Use intentionally for:

- Page headings
- KPI values
- Strong controls
- Sidebar contrast
- Table headings
- Important labels

Do not make the entire application heavily black. Black should provide contrast while the main interface remains light and premium.

### Pale Champagne

`#F3EBD3`

Use for:

- Selected navigation states
- Hovered table rows
- Soft highlighted areas
- Selected filters
- Secondary emphasis

---

## 5. Semantic Colour Rules

Semantic colours must retain the same meaning everywhere.

### Success — `#2E7D32`

Examples:

- Sale completed
- Payment successful
- Order delivered
- Stock received
- Successful system action

### Warning — `#E08A00`

Examples:

- Low stock
- Pending order
- Pending payment
- Action requiring attention

### Error — `#C62828`

Examples:

- Out of stock
- Failed payment
- Validation failure
- Delete/deactivate warning
- Failed transaction

### Info — `#1F5F8B`

Examples:

- Information messages
- Help links
- Tooltips
- Neutral system notices

---

## 6. Button System

### Primary Button

Background:

`#C9A227`

Text:

`#18181B` or white depending on tested contrast.

Hover / Pressed:

`#B8962E`

Examples:

- Complete Sale
- Add Product
- New Purchase
- Save
- Confirm Order

### Secondary Dark Button

Background:

`#18181B`

Text:

`#FFFFFF`

Use for strong secondary actions where appropriate.

### Danger Button

Background / emphasis:

`#C62828`

Use for:

- Cancel transaction
- Deactivate user
- Confirm destructive action
- Critical reversal actions

---

## 7. Background and Surface Rules

### Main Application Background

`#FAF8F3`

### Cards / Panels / Forms

`#FFFFFF`

### Borders

`#E4E4E7`

### Selected / Hovered Elements

`#F3EBD3`

The system should maintain a clean light interface with generous spacing and subtle borders instead of excessive shadows.

---

## 8. Sidebar Direction

Recommended sidebar styling:

- Dark charcoal / near-black base where a stronger premium look is desired
- Gold active navigation
- White or light text
- Subtle champagne/gold hover treatment

Alternatively, a light sidebar may be used if the final brand review prefers it.

The important requirement is that black/charcoal is used deliberately for contrast without turning the entire dashboard into dark mode.

---

## 9. Dashboard Rules

Dashboard KPI values should primarily use:

`#18181B`

Brand highlights:

`#C9A227`

Status colours should remain semantic:

- Green = positive/success
- Amber = warning
- Red = error
- Blue = information

Do not colour every KPI differently just for decoration.

---

## 10. Tables

### Header Text

`#18181B`

### Body Text

Primary:

`#18181B`

Secondary:

`#71717A`

### Borders

`#E4E4E7`

### Hover / Selected Row

`#F3EBD3`

Status badges must use their semantic colours.

---

## 11. Forms

Input surfaces:

`#FFFFFF`

Borders:

`#E4E4E7`

Labels:

`#18181B`

Supporting/helper text:

`#71717A`

Focused inputs may use the brand gold as the focus accent, provided contrast remains clear.

Validation errors:

`#C62828`

---

## 12. POS Interface

The POS should prioritize speed and clarity.

Use:

- White product/cart surfaces
- Off-white main background
- Black/charcoal prices and totals
- Gold primary CTA
- Red out-of-stock states
- Amber low-stock warnings
- Green successful sale confirmation

The final sale total should be one of the strongest visual elements on the screen.

---

## 13. Status Badge Examples

| Status | Colour |
|---|---|
| Completed | `#2E7D32` |
| Successful | `#2E7D32` |
| Delivered | `#2E7D32` |
| Low Stock | `#E08A00` |
| Pending | `#E08A00` |
| Out of Stock | `#C62828` |
| Failed | `#C62828` |
| Cancelled | `#C62828` |
| Information | `#1F5F8B` |

Always pair colour with readable status text.

Never communicate status through colour alone.

---

## 14. Accessibility

All colour combinations must be tested for readable contrast.

The visual system shall not rely only on colour for meaning.

Use:

- Text labels
- Icons where appropriate
- Status badges
- Clear focus states

Interactive controls must remain keyboard accessible.

---

## 15. Implementation Stack

The approved UI stack is:

- Laravel Blade
- Livewire
- Alpine.js
- Tailwind CSS
- Manrope typography

Reusable Tailwind components should be created for:

- Buttons
- Inputs
- Cards
- Tables
- Badges
- Alerts
- Modals
- Drawers
- KPI cards
- Empty states
- Loading states

---

## 16. Tailwind Design Tokens

Recommended application tokens:

```text
primary         #C9A227
primary-hover   #B8962E

background      #FAF8F3
surface         #FFFFFF

text-primary    #18181B
text-secondary  #71717A

border          #E4E4E7
selected        #F3EBD3

info            #1F5F8B
warning         #E08A00
danger          #C62828
success         #2E7D32
```

---

## 17. Final Design Direction

The Mo Fashion Store Business Management System shall visually feel:

- Premium
- Modern
- Fashion-oriented
- Clean
- Professional
- Operational
- Easy to read

The interface shall **not** feel like a generic admin template.

The visual identity is built around:

**Warm cream surfaces + clean white cards + brand gold actions + strong charcoal typography + pale champagne interactions + consistent semantic colours.**

---

## 18. Locked Decision

The following design foundation is officially locked for MFBMS Version 1:

**Font:** Manrope  
**Weights:** 400, 500, 600, 700

**Primary Gold:** `#C9A227`  
**Gold Hover:** `#B8962E`  
**App Background:** `#FAF8F3`  
**Card Surface:** `#FFFFFF`  
**Primary Text:** `#18181B`  
**Secondary Text:** `#71717A`  
**Border:** `#E4E4E7`  
**Selected / Hover:** `#F3EBD3`  
**Info:** `#1F5F8B`  
**Warning:** `#E08A00`  
**Error:** `#C62828`  
**Success:** `#2E7D32`

Any future UI changes should remain consistent with this system unless the Mo Fashion Store brand identity is deliberately revised.
