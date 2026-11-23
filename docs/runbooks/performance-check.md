# Re-measuring page speed on the deployed site (desktop vs mobile)

Targets: on desktop, a ticket or zone tap updates at once with **no network request**, and **LCP < 2.5 s** on a warm
load. Measure on the live event page (`/<org_slug>/<event_slug>`, one with a banner, speakers and gallery) and on a
ticket page (`/tickets/<reference>`).

Local baseline (production build, `APP_ENV=production`, gzip in front, 2026-11-23), event page:

| | Lighthouse mobile | Lighthouse desktop | Warm LCP, Slow 4G + 4× CPU |
|---|---|---|---|
| Before | 93, FCP 2.11 s, LCP 2.87 s, 13 requests, 453 KB | 100, LCP 0.59 s | not measured |
| After | 94, FCP 1.52 s, LCP 3.01 s, 10 requests, 390 KB | 100, LCP 0.66 s | desktop 1.65 s, mobile 1.66 s |

Lighthouse's simulated mobile LCP varies by about ±0.3 s between runs; the LCP change is within that.

Desktop tap (6 taps, retina 1440×900): 200 ms → 15 ms of style, paint and raster work, and 309 M → 2.5 M px repainted.

## Checklist

Use Chrome in a **guest window** (no extensions), and note the date, the page and each number.

- [ ] **Lighthouse, both modes.** DevTools → Lighthouse → Performance, run **Mobile** then **Desktop**, three times
      each, and note the median score, FCP, LCP, TBT and CLS. Or from a laptop:
      ```bash
      npx lighthouse@12.6.1 https://<domain>/<org>/<event> --only-categories=performance --view
      npx lighthouse@12.6.1 https://<domain>/<org>/<event> --only-categories=performance --preset=desktop --view
      ```
      Expect desktop LCP well under 2.5 s, and no "Render-blocking resources" entry.
- [ ] **Network totals, desktop (1440 wide) and mobile (DevTools device toolbar, e.g. iPhone 14).** Disable cache,
      reload, and note the requests and transferred KB in the status bar. Then filter:
  - **Font:** exactly three `.woff2` files, no `.woff`;
  - **JS:** `app-*.js`, `livewire.min.js`, and `js.paystack.co/v2/inline.js` arriving **after** the `load` event
    (waterfall), never before Livewire;
  - **Img:** the banner is a `res.cloudinary.com/…/w_960…` or `w_1600…` copy, not the original upload, and the gallery
    and speaker photos below the fold load only when scrolled to (`loading=lazy`).
- [ ] **Warm load.** Enable cache, reload twice, and on the second reload check that `/build/*` and Cloudinary images
      come from `(disk cache)` or `(memory cache)`. Read LCP in DevTools → Performance → "Reload and record" (the LCP
      marker). Target < 2.5 s on desktop.
- [ ] **Taps, desktop.** With Network open (Fetch/XHR filter), click two ticket tiers and two zones: **no request**
      appears, and the card, the order summary and "seats left" change immediately. Then DevTools → Performance →
      record while clicking a tier: each click's "Interaction" bar should be under 50 ms, with no long purple
      "Rendering" or green "Painting" blocks covering the whole page. (Rendering → **Paint flashing** shows only the
      clicked card and the summary flashing, not the whole page.)
- [ ] **Scroll and idle, desktop.** Rendering → **Frame rendering stats** while scrolling the whole page: no frame
      drops. Leave the page idle for 10 s with Performance recording: only the once-a-second countdown, no other
      timers or requests (`wire:poll` is not used on public pages).
- [ ] **Compare with mobile.** Repeat the taps with the device toolbar (and DevTools → Performance → CPU 4× slowdown).
      Desktop should now be the same or faster. If desktop is slower again, record a Performance trace of one click
      on each and compare "Rendering" and "Painting" time and the size of the painted area.
- [ ] **Write the numbers** in the team notes next to the local baseline above, with the date.
