# Project Design & Production Standards

These strict rules govern all design, copy, and code decisions across this project.

## 1. Visual Design & UI
- **No Purple Gradients:** Never use generic purple or violet gradients. Use the college's authentic color palette (Navy `#0F2854`, Crimson `#C8202A`, Warm Cream `#FFF0BD` / `#FFF7E5`, and Slate/White).
- **No Pill-Shaped Buttons:** Buttons must use clean, balanced rounded rectangles (e.g. `border-radius: 8px` to `12px`), never `border-radius: 9999px` or pill shapes.
- **No Cursor or Over-the-Top Animations:** Keep interactions responsive, snappy, and accessible. No cursor-following effects, bouncy hover distractions, or aggressive scroll parallax.
- **No Emoji Icons:** Do not use emoji characters as UI icons. Use clean, professional inline SVG icons (e.g., Lucide-style or tailored SVGs).

## 2. Copywriting & Content Integrity
- **No AI Slop / Vague Copy:** Write clear, concise, direct copy that fits the college context (AITAM College of Engineering). No buzzword fluff or vague hero text.
- **No Fake Reviews or Metrics:** Do not introduce fictitious testimonials, fake user counts, or artificial star ratings. Keep all metrics genuine and tied to real database records.
- **No Em Dashes:** Never use em dashes (`—` or `&mdash;`). Use colons, commas, or standard hyphens (`-`) instead.

## 3. Production & Launch Requirements
- **Favicon:** Every page must include the college favicon (`assets/aitam.png`).
- **No AI Watermarks:** Never include "Made with AI", "Powered by AI", or similar branding.
- **Mandatory Legal Pages:** The platform must provide accessible **Privacy Policy** (`privacy.php`) and **Terms & Conditions** (`terms.php`) pages linked from the footer.
- **Flawless Code:** Maintain documentation integrity, write strict PHP and semantic HTML, validate SQL migrations, and eliminate errors.
