RA Promo Manager
================

Validated, scheduled promotional assets for client WordPress sites — built to replace Soliloquy (unreliable, breaks on WP core updates and jQuery version conflicts) and to unify with NovaSlider (an in-house hero-carousel plugin, already live on kemperhomefurnishings.com) into one system.

== What this solves ==
Non-technical client staff upload promotional graphics that are often the wrong dimensions, the wrong file format, or too large to load quickly. This plugin fixes what's safe to fix automatically, and clearly rejects what isn't: file format and file size are always converted/compressed automatically (no one has to know what WebP is or how to make one), and a picture that's the right *shape* but the wrong resolution is scaled to fit automatically too. A genuinely wrong *shape* offers a crop picker — a 3x3 grid to choose which part of the picture to keep — rather than an automatic guess; only if no choice is made is it a hard rejection, with a clear message about what to fix.

**What isn't caught automatically:** whether the image already has sale text/pricing/dates baked into the pixels, which would visibly clash with the Headline/Subheadline/Button Text fields below it once both render together. Detecting text inside an image needs OCR — a paid cloud API or a server binary this plugin deliberately doesn't depend on, since it'd be an external cost and a point of failure across every client host. Instead, the Add/Edit Asset form has a live preview showing the image and text overlay together exactly as they'll appear live, specifically so this is easy to catch by eye before publishing.

== Getting help ==
**Promo Manager > Help & FAQ** is a plain-language walkthrough and Q&A written for non-technical users, open to anyone who can add assets. There's also a "Help & FAQ" button on the Add/Edit Asset form itself.

== Adding an asset ==
Use **Promo Manager > Add New Asset**. Don't use the native "Add New" screen under Promo Manager > All Assets — it's a bare title field on purpose (no image/format path exists there at all) and will redirect you to the real form automatically.

Creating a new asset walks through four groups in order — The Basics, Your Message, Where It Links, Review & Schedule — with a progress bar and Back/Next buttons, built for an audience with no WordPress experience. **Editing an existing asset skips this entirely**: every group shows on one page at once, nothing hidden, and the same progress bar becomes a set of jump links (click one to scroll straight to that section) — a small edit never requires walking back through the whole guide.

Fields, across whichever view you're in:
- **Kind** — Hero (the full-height carousel), Fold Banner (a shorter strip near the fold), Coupon (a card in a horizontal scrolling row), or Marquee (a small square tile in a compact row, like a "quick links" strip). Switches which image dimensions are required below.
- **Internal Name** — for your own reference in the admin list, not shown publicly.
- **Placement** — which `[rapm_hero placement="..."]` or `[rapm_fold_banner placement="..."]` this belongs to (matched separately per Kind, so a "default" hero and a "default" fold banner don't mix). Leave as "default" unless the site needs more than one of the same Kind (e.g. a homepage hero and a separate category-page hero).
- **Desktop/Mobile images** — upload whatever format/size you have, or choose "Use a link" to point at a file hosted elsewhere (a brand partner's site, a Google Drive share link set to "Anyone with the link"). A Google Drive **folder** link works too: the newest picture in the folder that fits the slot's shape is used, so changing a promotion is just adding a new picture to the folder under any file name, and one folder can feed both the desktop (wide) and mobile (tall) slot. "Check link now" on the edit screen re-checks immediately instead of waiting for the hourly sync. Either way it's validated and converted automatically; a mismatch tells you the exact dimensions needed vs. what was found. If the picture is a different *shape* than needed, a crop picker appears — a 3x3 grid of anchor points (top-left, center, bottom-right, etc.) — pick which part to keep and it's cropped to fit on save; it defaults to center, so submitting without touching it still works. A linked image is re-checked every hour and updated automatically if the source file changes — if the link ever breaks, the last picture that worked keeps showing and you're emailed once to fix it.
- **Headline / Subheadline / Button Text** — real text rendered over the image, never part of the image file itself. This is what keeps it searchable and accessible, and lets you fix a typo without re-uploading anything.
- The **Live Preview** has a Desktop/Mobile toggle — switch to Mobile to confirm the phone picture (or, if none was uploaded, to see the desktop picture shrunk to fit the phone shape instead — nothing is ever cropped off) before saving.
- **Text Alignment / Text Color / Text Style** — control how that text looks. The Live Preview updates as you change them.
- **Typeface** — only shown on sites running Elementor with fonts already set up under Site Settings > Global Fonts. Lets the promotion's text use one of those same site fonts (by name — "Primary," "Secondary," or whatever the site calls them) instead of a generic style, and keeps tracking that font automatically if it's ever changed in Elementor later.
- **Link** — a plain web address, a page/post on this site, or (if WooCommerce is active) a specific product, a category, a brand, search results for some words, or a hand-picked list of SKUs with automatic fill-in. For anything other than a plain web address, start typing a name and pick from the matches — no ID numbers needed. A hand-picked list can also be built by uploading a spreadsheet (.csv or Excel .xlsx) with a "SKU" column instead of searching one at a time; any SKUs not found on the site are reported back and downloadable as their own list.
- **Schedule** — start/end date-time. Works correctly even behind a full-page cache plugin (WP Rocket etc.) — the schedule is checked in each visitor's own browser, not baked into a cached page, so nothing "freezes." A live note right below these fields tells you directly whether this specific promotion will show up on the Promotions Calendar (see below) or not, based on whether both dates are filled in — updates the moment you add or remove one.

== Managing what you've built ==
**Promotions** (the left menu now reads just "All promotions" and "Help", 1.28.0) shows one section per spot on the site: each Kind + Placement pair, named in plain words ("Home page slider"), with the pages it actually shows on (found by scanning page content, Elementor data and widgets), large picture cards, and a status on each card ("Showing now · Until Oct 31", "Hidden", "Starts Nov 20", "Ended"). Each card has an on/off switch (on = published, off = draft), a "…" menu (Edit, Make a copy, Move earlier/later, Move to trash with Undo), and can be dragged to change the play order. Ended promotions fold away under "Show N ended" with a "Use again" button. "Find a promotion" filters by name. The Promotions Calendar line at the bottom lists the pages that carry the calendar.

**Spots (1.29.0).** A spot is one Kind + Placement pair. Clients see each spot by a name and a plain type: Slider (`hero`), Shop the Look (`look`, 1.30.0), Feature banner (`fold_banner`), Coupon row (`coupon`), Tile row (`marquee`). The codes in brackets are what shortcodes and saved promotions still use. Name spots for their job ("Main slider", "Living Room slider"), not their page, since one spot can go on several pages. In R&A setup details:
- **+ New spot** makes another spot of any type. Its placement code comes from the name (`living-room-slider`).
- Each spot has a rename box.
- A spot with no promotions that isn't on any page can be removed (**Remove this spot**).
The page's **main heading (H1)** is set where a spot is placed, not on the spot (1.29.1): `heading="h1"` in the shortcode, or the Promo Carousel widget's "Main heading (H1)" switch. Use it only on pages with no other H1, like the home page. The first promotion showing gets an `h1` for its big words, and the rest keep `h2`. `rapm-schedule.js` moves the `h1` to whichever promotion is actually showing first.
Settings live in the `rapm_spots` option. 1.28.0's `rapm_spot_names` is folded into it. The Promo Carousel Elementor widget picks a spot by name from a **Spot** list; widgets saved before 1.29.0 keep their Kind/Placement fields until a spot is picked. The add/edit form's "Where it shows" is the same list of spots by name, not a typed code.

**R&A setup details** (a link at the bottom of the Promotions screen, remembered per browser) shows each spot's shortcode, a box to rename the spot, and links to the old All Assets list (`&rapm_list=1`), the old Sliders screen and Settings. Those screens still work; they're only out of the menu.

**Cache clearing (1.28.0):** switching a promotion on or off, saving, reordering, trashing, or a Drive-folder picture changing clears the page cache for just the pages that spot shows on (WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, SiteGround, Nginx Helper, WP Engine), or the whole cache when the spot is in a template or widget. Rocket.net clears its CDN on any WordPress save, which every one of these goes through. Other hosts can hook `rapm_cache_cleared`.

== Displaying a carousel ==
`[rapm_hero]` — the full hero carousel, "default" placement.
`[rapm_fold_banner]` — the shorter fold-banner carousel, "default" placement.
`[rapm_hero placement="category-living-room"]` — a separate carousel scoped to just that placement (works the same for `[rapm_fold_banner]`).
`[rapm_hero placement="home" max="3"]` — shows only the first 3 promotions that are live right now, in Sliders-screen order (works the same for `[rapm_fold_banner]`). Leave `max` out, or set it to 0, to show them all. The limit is applied in the visitor's browser after the schedule check, so it works behind a full-page cache, and a promotion that hasn't started yet or has ended never takes one of the spots.
An Elementor widget ("Promo Carousel," under the Promo Manager category) wraps either shortcode via its own Kind control, plus a "Show at most" box that sets `max`.
Every display works wherever it's placed: on a page, inside an Elementor popup, in a Theme Builder header or footer, or on a product or category template. Each one loads its own files when it appears.
`[rapm_curated_results]` — put this on one plain page, then set its address under Promo Manager > Settings > Curated Results Page. Every "hand-picked list" link on the site reuses this one page automatically.

== Other display modes ==
`[rapm_marquee items="4"]` — a compact row of square image tiles, "items" (1–5) controlling how many show at once. Extra tiles page in like a carousel (Prev/Next buttons plus an optional timer, both configurable under Settings) rather than showing all at once. Add assets with Kind set to Marquee.
`[rapm_coupon_book]` — a horizontal scrolling row of coupon cards (Kind: Coupon).
`[rapm_looks placement="home"]` — Shop the Look (1.30.0, Kind: `look`): a full-width room photo with a thin bar of tabs under it, one tab per look, and the look's headline, smaller line and button below. Each look is one promotion with one room photo of any shape (no separate mobile picture: computers show a wide strip, phones a 4:3 part, chosen with the look's "Which part to keep"), a **Tab name** (a room like "Living Room" or a collection like "Stanton 338", up to 24 characters), the usual dates and on/off switch; card order on the Promotions screen is tab order, shown there as "Tabs on the website, in this order". The tabs play as a tour (8 seconds each; `speed="…"` in milliseconds, `autoplay="no"` to turn it off) that pauses on hover or keyboard focus, stops for good once a shopper picks a tab, arrow or swipe, has a pause button, and never runs for visitors with reduced motion. Which looks are live is decided in the visitor's browser (rapm-schedule.js `watch()`), so dates work behind a full-page cache. The tour line and button use the same brand color as the calendar. No Elementor widget yet: use Elementor's Shortcode widget. Numbered dots on the pieces in the photo, with live prices and stock, come in 1.31.0.
`[rapm_promotions_calendar]` — a month calendar of every promotion (any Kind) that has both a start and end date set. Add `placement="..."` or `kind="..."` to scope it. This is RA Promo Manager's own promotions calendar — a different thing from any separate community-events-calendar plugin the site might also run, and it has no public submission form; every promotion on it comes from the Add/Edit Asset admin form. Each promotion renders as a named bar spanning the days it runs (Google Calendar-style month view, not just a day-count badge); up to 3 overlapping promotions show at once per week, with a "+N more" link for anything beyond that — clicking it, or any day, lists everything active then. Its accent color automatically matches this site's Elementor Global Colors when Elementor is active (Primary first, then Accent, then Secondary, skipping any that white text isn't readable on) — override it with an exact hex under Promo Manager > Settings > Brand Color if it picks the wrong one, or the site doesn't use Elementor.
Marquee, Coupon Book, Shop the Look and Calendar don't have their own Elementor widgets yet — use Elementor's own Shortcode widget with the shortcodes above in the meantime.

== Image specs (defaults — adjustable under Promo Manager > Settings) ==
- Hero Desktop: 1920x600px, WebP, under 300KB
- Hero Mobile: 1080x1920px, WebP, under 300KB
- Fold Banner Desktop: 1920x300px, WebP, under 200KB
- Fold Banner Mobile: 1080x400px, WebP, under 200KB
- Coupon Card: 600x750px, WebP, under 150KB (same size used for both desktop and mobile)
- Marquee Tile: 1080x1080px (Instagram's standard square size), WebP, under 200KB (same size used for both desktop and mobile)
- Shop the Look room photo: any shape, at least 800px wide, scaled down to 2400px wide if bigger (2000+ looks best), WebP, under 450KB. One photo for every screen.

The hero numbers were checked against real Core Web Vitals/LCP guidance, not picked arbitrarily. The fold banner and coupon card numbers are documented starting points, not external standards — no established industry convention exists for either shape (checked directly) — adjust freely if a site's own data says otherwise. See CHANGELOG.md for the full research trail.

== Updates ==
Promo Manager updates itself from a private GitHub repo, just like any plugin from WordPress.org, once a site has been set up for it one time: under **Promo Manager > Settings > Auto-Update Settings**, paste the GitHub Update Key from R&A Marketing and leave Update Channel on "Production." (Or set `RAPM_GITHUB_UPDATE_TOKEN` in wp-config.php instead.) Only the one staging/test site should ever be set to "Staging." New versions go there first and reach live sites only after they've been checked.

== Requirements ==
PHP with either the Imagick extension (preferred) or GD's WebP support, for automatic format conversion. If a host has neither, the plugin will clearly say so and ask for a pre-converted .webp file instead of failing silently.

== Roadmap ==
Phase 1 (asset core + hero + fold banner) through the coupon book/marquee/calendar phase are all built. Not yet built: dedicated Elementor widgets for Marquee/Coupon Book/Shop the Look/Calendar (shortcodes work today via Elementor's Shortcode widget). Shop the Look's numbered dots and products (1.31.0) and Add to cart (1.32.0) are next.
