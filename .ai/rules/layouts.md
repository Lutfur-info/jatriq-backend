---
paths:
  - resources/js/layouts/public-layout.tsx
---

# Layouts

## The board's logo is the Flutter app's own lockup, and it needs a light plate
`public/images/jatriq-logo.png` is `apps/assets/images/logo_transparent.png` from the Flutter client, downscaled to 480px wide with a faint grey artefact cleared out of its top-right corner. Re-export it from that source, the same way, if the brand changes, so the two surfaces stay one product.

It is drawn in navy and green on transparency, so it must sit on the white plate `BrandLogo` gives it - it disappears against the navy header and against dark mode's slate otherwise. On the white footer that plate is simply invisible.

The header is solid `bg-brand-700`, deliberately not translucent: it is the colour the masthead band's gradient starts on, so the two read as one piece of chrome. A translucent bar shows a seam against the band at the top of the page.
