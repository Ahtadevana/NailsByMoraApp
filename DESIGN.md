---
name: nailsByMora
colors:
  surface: '#fff8f8'
  surface-dim: '#e2d8d9'
  surface-bright: '#fff8f8'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#fcf1f2'
  surface-container: '#f6ebec'
  surface-container-high: '#f0e6e7'
  surface-container-highest: '#eae0e1'
  on-surface: '#1f1a1b'
  on-surface-variant: '#564244'
  inverse-surface: '#342f30'
  inverse-on-surface: '#f9eeef'
  outline: '#897174'
  outline-variant: '#dcc0c2'
  surface-tint: '#a3394f'
  primary: '#6c0c28'
  on-primary: '#ffffff'
  primary-container: '#8b263e'
  on-primary-container: '#ffa3b0'
  inverse-primary: '#ffb2bc'
  secondary: '#7e5352'
  on-secondary: '#ffffff'
  secondary-container: '#fdc4c2'
  on-secondary-container: '#794e4d'
  tertiary: '#4c2f0b'
  on-tertiary: '#ffffff'
  tertiary-container: '#66461f'
  on-tertiary-container: '#e1b585'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#ffd9dd'
  primary-fixed-dim: '#ffb2bc'
  on-primary-fixed: '#400013'
  on-primary-fixed-variant: '#842039'
  secondary-fixed: '#ffdad8'
  secondary-fixed-dim: '#f1b9b7'
  on-secondary-fixed: '#311212'
  on-secondary-fixed-variant: '#643c3b'
  tertiary-fixed: '#ffddba'
  tertiary-fixed-dim: '#ecbe8e'
  on-tertiary-fixed: '#2b1700'
  on-tertiary-fixed-variant: '#5f401a'
  background: '#fff8f8'
  on-background: '#1f1a1b'
  surface-variant: '#eae0e1'
typography:
  display-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 36px
    fontWeight: '700'
    lineHeight: 44px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 30px
    fontWeight: '600'
    lineHeight: 38px
    letterSpacing: -0.015em
  headline-lg-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 17px
    fontWeight: '600'
    lineHeight: 24px
  body-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 18px
  label-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.01em
  label-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.02em
  label-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.04em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-mobile: 0.75rem
  margin: 1.5rem
  margin-mobile: 1rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2rem
---

## Brand & Style

This design system expresses modern luxury, refined artistry, and effortless operational clarity tailored for boutique nail studios, independent nail artists, and aesthetic salon operators.

### Personality & Emotional Resonance
The experience merges haute editorial aesthetics with precise tactile utility. It balances soft feminine warmth with crisp, professional executive presence. The emotional resonance is calm, orderly, and deeply chic—alleviating administrative chaos while making appointment booking, client nail history tracking, and inventory curation feel like curating an art catalog.

### Design Movement: Modern Warm Minimalist Luxe
The design system draws from modern warm minimalism infused with soft glass accents and warm ambient depth. Rather than stark clinical software or juvenile bubblegum pinks, it uses warm cosmetic tones: velvety rose hues, warm ceramic nudes, and deep slate typography that maintains surgical contrast without the harshness of pitch black. Surfaces feel like polished marble and soft-touch lacquer.

## Colors

The palette mirrors luxurious cosmetic pigments, metallic foils, and architectural salon finishes.

### Roles & Semantic Application
- **Primary (`#8B263E` - Deep Burgundy)**: The signature tone representing confidence, luxury, and decisive action. Used for primary interactive triggers (primary CTA buttons, active state indicators, key numeric metrics, and verified salon tags).
- **Secondary (`#DDA7A5` - Polished Blush)**: Delicate soft rose supporting tint. Used for sub-navigation highlights, selected chip backgrounds, soft badge fills, and delicate progress bars.
- **Tertiary (`#C59B6D` - Satin Rose Gold)**: Premium accent tone applied to VIP badges, master artist tiers, revenue markers, and delicate focus highlights.
- **Neutral Surface & Base**:
  - `Canvas / Background`: `#FAF7F5` (Warm Cream Nude / Cashmere). Soft on the eyes during long daily salon shifts.
  - `Card / Container Surface`: `#FFFFFF` (Crisp Porcelain White). Delivers high definition against the nude canvas.
  - `Muted Surface`: `#F3ECE8` (Soft Buff) for table headers, segment controls, and input backgrounds.
- **Neutral Typography (`#2B2627` - Deep Slate Charcoal)**: High-contrast, warm-tinted dark charcoal. Softened enough to avoid the sterile bite of `#000000` while surpassing WCAG AAA for legibility. Secondary body text uses `#6E6466`, and borders use `#EFE7E4`.

## Typography

The typography leverages **Plus Jakarta Sans** across all levels. Its balanced geometric proportions, wide apertures, and contemporary polish evoke modern luxury branding while ensuring razor-sharp legibility on mobile viewports.

### Formatting Guidelines
- **Numbers & Currencies**: When presenting service revenues, nail set durations, and appointment timestamps, use tabular figures (`font-variant-numeric: tabular-nums`) with `FontWeight: 600`.
- **Editorial Badges & Status Tags**: Use `label-sm` with subtle uppercase tracking (+0.04em) to achieve a boutique editorial impression.
- **Section Headers**: Use `headline-md` for grouped mobile cards, retaining tight line heights to conserve vertical screen economy.

## Layout & Spacing

The layout system is tailored for a responsive, mobile-first web app accessed on smartphones, tablets at the reception counter, and desktop management dashboards.

### Grid & Canvas Structure
- **Mobile Handheld (360px – 599px)**: Single-column fluid view with `margin-mobile: 1rem` (16px) dynamic gutter spacing. Full edge-to-edge card layouts leverage `space-md` inner padding.
- **Tablet / Counter POS (600px – 1023px)**: 6-column fluid grid, `gutter: 1rem`, `margin: 1.5rem`. Split views support the daily calendar alongside the active ticket detail pane.
- **Desktop (1024px+)**: 12-column layout maxed at 1280px container width. Accommodates the persistent left navigation sidebar, analytical summary cards, and multi-tech appointment tables.

### Vertical Rhythm
Interface spacing relies on an 8pt baseline rhythm:
- Micro-gaps between label and field input: `space-xs` (4px).
- Sibling chips and status pills: `space-sm` (8px).
- Internal card padding and form row separation: `space-md` (16px).
- Section grouping and bottom navigation safe clearance: `space-xl` (32px).

## Elevation & Depth

The design system eschews muddy, heavy dropshadows in favor of warm, luminous ambient depth and low-contrast outlines.

### Elevation Levels
- **Level 0 (Flat Surface)**: Used for canvas backgrounds (`#FAF7F5`). No shadow.
- **Level 1 (Resting Cards & Tiles)**: Crisp porcelain cards (`#FFFFFF`) set over the cream background. Elevated using an ultra-diffused blush-tinted ambient shadow:
  `box-shadow: 0 4px 20px -2px rgba(139, 38, 62, 0.04), 0 2px 6px -1px rgba(43, 38, 39, 0.03);`
  accompanied by a hairline border: `1px solid #EFE7E4`.
- **Level 2 (Dropdowns, Popovers, & Quick Action Menus)**:
  `box-shadow: 0 10px 30px -4px rgba(139, 38, 62, 0.08), 0 4px 10px -2px rgba(43, 38, 39, 0.04);`
- **Level 3 (Modals & Collapsible Drawer Navigation)**:
  `box-shadow: 0 24px 48px -8px rgba(43, 38, 39, 0.16);` paired with a backdrop blur overlay (`backdrop-filter: blur(8px); background-color: rgba(43, 38, 39, 0.35)`).

### Tactile Light Cues
Top edges of primary buttons and hero cards feature a 1px subtle white inset highlight (`box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.25)`), evoking the luster of a clean gel topcoat.

## Shapes

The roundedness language mirrors smoothed organic curves reminiscent of almond, squoval, and oval nail silhouettes.

### Corner Radius Standards
- **Interactive Controls (Inputs, Buttons, Segment Tabs)**: `0.5rem` (8px). Delivers a confident, modern tactile frame.
- **Content Cards & CRUD Containers (`rounded-lg`)**: `1rem` (16px). Creates soft, inviting pockets of content that feel distinct on small screens.
- **Sheets, Modals & Slide-out Drawers (`rounded-xl`)**: `1.5rem` (24px) on top edge boundaries for mobile bottom sheets and drawer envelopes.
- **Badges, Filter Chips, & Avatars**: Fully rounded pill shapes (`9999px`) to maintain fluid touch-target ergonomics.

## Components

### Buttons
- **Primary**: Deep Burgundy (`#8B263E`) background, crisp white typography, subtle top inset highlight. Height: 44px on mobile for finger-friendly hit targets. Hover state shifts to `#731E32`.
- **Secondary**: Crisp white card surface with a 1px border (`#EFE7E4`), text in `#2B2627`. On active press, tints with a 4% secondary blush wash.
- **Tertiary / Ghost**: Transparent background with burgundy text (`#8B263E`). Used for dismissive actions, table inline utilities, or modal cancellation.

### Chips & Filter Badges
- Filter pills use `label-md` and height 32px.
- Unselected: Soft buff fill (`#F3ECE8`) with `#6E6466` text.
- Selected: Polished blush fill (`#F7E9E8`) with deep burgundy text (`#8B263E`) and an active border (`#DDA7A5`).

### Mobile CRUD Cards & Tables
- **Mobile Card View**: Due to narrow phone viewports, table rows transform into rich list cards. Each card highlights the client name, nail art photo thumbnail, technician, appointment time, and status badge.
- **Swipe Actions**: Smooth reveal of edit (burgundy tint) and complete/archive gestures.
- **Desktop/Tablet Table**: Hairline horizontal dividers (`#EFE7E4`) with zero vertical borders. Header labels use `label-sm` in all-caps slate muted (`#6E6466`).

### Input Fields & Selects
- Inputs feature a 44px minimum height with a subtle `#F3ECE8` background and a `#EFE7E4` border.
- Focused state transitions to a crisp `#FFFFFF` background with a primary burgundy border ring (`0 0 0 2px rgba(139, 38, 62, 0.15)`).

### Collapsible Sidebar Drawer
- Slides in smoothly from the left or acts as a bottom sheet on mobile screens.
- Header displays salon branding and artist switcher with rose gold indicators.
- Navigation links feature an active pill indicator in soft blush (`#F7E9E8`) with deep burgundy icon and text color.

### Modal & Bottom Sheets
- On mobile devices, center modals automatically reflow into bottom sheets with top corner radii of `1.5rem` and an ergonomic swipe-down grab bar (`36px x 4px` in `#DDA7A5`).