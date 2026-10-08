# Changelog — RA Promo Manager

Versions follow semver: PATCH = fixes, MINOR = new backward-compatible features, MAJOR = breaking changes (none yet).

---

## 1.35.0

**New: product dots and the "Shop now" button on Slider and Feature banner promotions.** Phil: "this movable button feature or product tagging should be available in the standard banner as well, not just the shop the look."

- **In the form:** Slider (`hero`) and Feature banner (`fold_banner`) promotions get the **Place the Pieces** step (step 2), the same one Shop the Look has.
  - Click the picture to add a numbered dot and pick its product, and drag dots or use the arrow keys to move them.
  - Tick "Show a 'Shop now' button on the photo" and drag it into place.
  - **Desktop and phone pictures:** a **Desktop picture / Phone picture** switch places them on each picture separately, since the same spot rarely suits both.
    - A dot or the button starts on the phone picture at its desktop spot. Moving it there only changes its phone spot.
    - A dot added on the phone picture starts at the same spot on the desktop one.
    - With no phone picture yet, the phone view shows the desktop picture and says that phones will use the desktop spots.
    - The phone picture is shown phone-sized (340px wide) so all of it fits on screen.
  - "Which part to keep" and the screen-fit previews stay Shop the Look only, since a banner's pictures are made for their screens.
  - The Review step's Live Preview shows the dots and the button on whichever picture its Desktop/Mobile toggle shows.
- **Saved data:** the same `_rapm_dots` and `_rapm_photo_btn` as Shop the Look, plus `mx`/`my` (percent of the phone picture). `sanitize_dots()` and `sanitize_photo_btn()` keep them.
- **On the website** (new `rapm-pins.js` and `rapm-pins.css`, loaded with every Slider and Feature banner):
  - `RAPM_Hero_Carousel::render_pins()` prints each slide's dots and button. A product that's gone or unpublished loses its dot. The button only shows when the promotion has a Link, and it uses its Button Text or "Shop now".
  - They're placed with the picture's real fit, read from its computed `object-fit` and `object-position`, so the phone fallback's "contain" works too.
    - Phone spots are used on screens up to 768px when the slide has a phone picture.
    - Placement runs again on resize and when Swiper adds slide copies.
    - A cut-off dot is hidden, and the button is nudged inward.
  - **Dot card:** tapping a dot opens a small card with the picture, name, live price (crossed-out regular price on sale) and stock, plus "View product".
    - Prices come from one WooCommerce Store API request per carousel.
    - Escape, the × or a click elsewhere closes it.
    - Dots and the card don't trigger the slide's own link.
  - They use the brand color (1.34.0) and sit above the slide's link (z-index 6) so they're clickable.
  - `rapm-pins` is added to WP Rocket's Delay JavaScript exclusions.
- **Shop the Look fix, from Gates:** with `text="overlay"`, a look that has its own photo button also had its words and button on the photo, so the ad showed two buttons. Now such a look keeps its words and button below the photo, next to its product cards, as without overlay. Looks without a photo button are unchanged.
- **Add to cart** stays planned for 1.36.0.
- **How it was checked:**
  - **Website, on a real WordPress install** (local, with Elementor 4.3.4 and WooCommerce 11.2):
    - **Setup:** a Slider promotion using Gates' Homecoming rotator pictures (1920×600 desktop, 1080×1920 phone). It had a dot on the Stanton Sofa (desktop 72%/72%, phone 55%/76%), a button (desktop 20%/85%, phone 50%/50%) and a hand-picked list Link. There was also a Feature banner with the outlet pictures, one dot and a button.
    - **1440 wide:** both dots and buttons are at their desktop spots, fully inside, on top, and in the brand navy.
    - **375 wide:** the Slider shows the phone picture with the dot at 55%/76% and the button at 50%/50%. The Feature banner's dot and button are on top when scrolled into view.
    - **Tapping the dot** opened the card ("Stanton Sofa 376, $2,200.00 crossed out, $1,999.98, View product") without leaving the page. Escape closed it.
    - **The Slider button** opened its hand-picked list results.
    - **Heights:** the banners' heights are the same with and without the new files.
  - **Shop the Look fix, same install:** the look with a photo button shows one button on the photo, with its words and button below. The look without one keeps its button on the photo.
  - **The form, with real clicks:** the real banner Place the Pieces step was rendered by PHP with WordPress stand-ins and run in Chromium.
    - Dragging the button on the desktop picture saved 20%/85%.
    - On the phone picture, the dot and button started at their desktop spots. Dragging them saved `mx 55, my 76` and `mx 50, my 50`, and left the desktop spots unchanged.
    - The Live Preview showed desktop spots on Desktop and phone spots on Mobile.
    - The Shop the Look form's click test from 1.33.0 still passes unchanged.
    - No script errors.
  - **Not checked:** saving the banner form through WordPress in a browser (the local sign-in wouldn't keep cookies), and a banner with several slides and autoplay on a theme like WoodMart.

## 1.34.0

**New: pick the brand color from Elementor by name, and the dots use it.** Phil: "The button should pull from Elementor's brand/global colors... Same with the dots."

- **What was happening on Gates:**
  - Promo Manager did use Elementor's global colors, but only tried Primary, Accent, then Secondary, taking the first one white text reads well on.
  - Gates' Primary is a light blue (#5583D2) and its Accent is Elementor's default green (#61CE70). Both fail that check, so it landed on Secondary: Elementor's default gray (#54595F).
  - Gates' real brand navy (#192D6B) is a custom global color, which the automatic pick never looked at.
  - The dots were always white with a dark number.
- **Settings > Brand Color > Elementor color:**
  - A list of every global color in the site's Elementor kit, built-in and custom, by name and value. The color appears in a swatch beside the list.
  - The top choice is "Automatic", which names the color it's using right now.
  - Saved as the color's Elementor id (`accent_global`) and printed as a live `var(--e-global-color-<id>, <value>)`, so it follows Elementor if the color is changed there.
  - "Or an exact color" (the old color box) still overrides everything.
  - The section's note now says what the color is used for: Shop the Look's buttons, dots and tour line, and the Promotions Calendar. It used to say "Promotions Calendar only".
- **Dots:** dots and the card numbers are now in the brand color, with a white number and a white edge, so they match the buttons and still stand out on a dark or light room. Hovering or opening a dot brightens it.
- **Fallback:** the automatic choice's `var()` now falls back to the color's own value instead of a plain blue, so it's right even on pages where Elementor doesn't load its colors.
- **Add to cart** moves to 1.36.0. 1.35.0 is the button and dots on Slider and Feature banner promotions (Phil, next).
- **How it was checked, on a real WordPress install** (local, with Elementor 4.3.4 and WooCommerce 11.2). The Elementor kit had Gates' colors: Primary #5583D2, Secondary #54595F, Text #7A7A7A, Accent #61CE70, plus custom "Gates Navy" #192D6B and "Green" #23A455.
  - **Automatic:** resolves to Secondary, the same as Gates.
  - **The Settings page** (`render_page()`) lists "Automatic (now 'Secondary', #54595F)", the four built-in colors, "Gates Navy (#192D6B)" and "Green (#23A455)". `sanitize()` keeps the picked id.
  - **With Gates Navy picked, the page as a visitor in Chromium:** the photo button and the dots are rgb(25, 45, 107), which is #192D6B, with a white number and a white edge.
  - **Not checked:** saving through the Settings form in a browser (the local site's sign-in wouldn't keep cookies), and the Promotions Calendar with a picked color (it uses the same `resolve_accent_color_css()`).

## 1.33.1

**Fix: "hand-picked list" links always work, without setting up a page by hand.** Seen on Gates: the new Harvest Sale look links to a hand-picked list (Stanton Sofa 376, Kalispell Harvest Coffee Table, Barolo Vintage Brown Swivel Club Chair). On the website, neither the 1.33.0 photo button nor the look's own button showed. Phil: "The link should match what I've set in this section."

- **Root cause:** a hand-picked list opens on the site's one results page, set under Settings > Curated Results Page. Gates had never set one, and until it was set, `RAPM_Destination::curated_url()` returned an empty link. A look's buttons only print when it has a link, so both were left out. The form gave no sign of this, and its preview still showed the photo button.
- **Fix:** `RAPM_Curated_Results::ensure_page()` makes sure the page exists and is set:
  - It uses a published page that already has `[rapm_curated_results]`, or it makes one: "Shop the Selection" (`/shop-the-selection/`). The new page is marked noindex for Yoast and SEOPress, since its products change with every link.
  - It saves the page's address as the Curated Results Page setting. Only that one setting is written. The Settings form's `sanitize()` is bypassed for this, because it reads checkboxes as form fields and would turn a saved "off" back on.
  - **When it runs:** whenever a promotion is saved with a hand-picked list. Also on any admin screen (`admin_init`, `maybe_ensure_page()`) when such a promotion already exists and no page is set. So Gates gets the page the first time anyone opens the admin after updating, with nothing to save again.
  - A site that already has the setting is left alone.
- **How it was checked, on a real WordPress install** (WordPress 6.x on SQLite, WooCommerce 11.2, Promo Manager 1.33.1, run locally):
  - **Setup, matching Gates:** three products, and a "Harvest Sale" look on a page with `[rapm_looks placement="home" width="full" height="screen" text="overlay"]`. The look had a hand-picked list of the three SKUs, Button Text "Shop Harvest Sale" and the photo button at 73.6%/69%. Saved settings had autoplay off and no Curated Results Page.
  - **Before:** the link came out empty, and the page printed neither button. Same as Gates.
  - **After `maybe_ensure_page()` ran as the admin user** (it's hooked on `admin_init`):
    - "Shop the Selection" was published with `[rapm_curated_results]` and the noindex flags, and the setting was saved as its address.
    - Autoplay stayed off.
    - Running it again made no second page.
  - **The page, as a visitor in Chromium:** both buttons print, linked to `/shop-the-selection/?rapm_skus=STN376,KAL114,BAR912…`. The photo button is fully on the photo and on top. A real click opened the results page showing the three products in the order picked.
  - **Not checked:** the save-time call through the form (it calls the same `ensure_page()`), and a site where WordPress won't allow a new page.

## 1.33.0

**New: a "Shop now" button you place on a look's photo.** Phil asked for it: an ad-style photo for Gates' Shop the Look needs a button wherever the design has room, going to the same search results or list as the look's own button. "Yes it could be in two places but would allow the client options."

- **In the form (Place the Pieces):**
  - A new box, "Show a 'Shop now' button on the photo", turns it on.
  - The button appears on the photo (default spot: centered, 78% down). Drag it where it should go, or select it and use the arrow keys (Shift for bigger steps), just like a dot.
  - It shows the look's **Button Text**, or "Shop now" if that's empty, and updates as you type.
  - It goes to the look's **Link** (step 4), so a hand-picked list, a search, a category or a product all work, and both buttons always go to the same place.
  - The Review step's Live Preview shows it, on Desktop and Mobile.
- **Saved data:** `_rapm_photo_btn` holds `{on, x, y}`, the button's center as a percent of the photo. `RAPM_Looks::sanitize_photo_btn()` clamps it, and when the button is off the meta is deleted.
- **On the website:**
  - `<a class="rapm-look-photo-btn">` is printed inside the look's photo, only when the look has a Link.
  - `placeDots()` places it with the same cover-fit math as the dots, centered on its spot. If a screen's crop would cut it off, it's nudged inward instead of hidden, so it's always fully on the photo.
  - It sits above the words and the dots, so it's always clickable, and swiping the photo ignores it.
  - It uses the site's brand color. On phones it's smaller: 34px tall, 12px type.
- **Words and button:** the look's own words and button (under the photo, or on it with `text="overlay"`) are unchanged, so a look can have both.
- **Add to cart** moves to 1.34.0.
- **How it was checked:**
  - **Syntax:** `php -l` on `class-rapm-looks.php` and `class-rapm-upload-handler.php`, and `node --check` on both scripts.
  - **The form, with real clicks:** the real Place the Pieces and Live Preview markup was rendered by PHP (`render_look_pieces()` and `render_look_preview()` with WordPress stand-ins) and run in Chromium with the real script and CSS.
    - **Turning it on:** ticking the box adds the button at 50%/78%, labelled with the Button Text.
    - **Moving it:** a real drag moved it to 73.6%/69% (saved field `{"on":true,"x":73.6,"y":69}`) without adding a dot, and focus stayed on the button. → and Shift+↑ moved it to 74.6%/64%.
    - **Typing:** "Shop Now" in Button Text renamed it on the photo and in the preview.
    - **Turning it off:** unticking saved `{}` and removed it from the photo and the preview.
    - **Editing a saved look:** it opens with the box ticked and the button where it was saved.
    - No script errors.
  - **The website, nothing saved:** Gates' real `/home-2/` (1.32.2) in Chromium, with the 1.33.0 CSS and JS swapped in and the button added to the first look as the 1.33.0 PHP prints it.
    - **Every size (1440, 1920, 768, 375):** the button is fully inside the photo and is the top element at its center.
    - **Click:** a real click at 1440 opened the look's Link (`/furniture/living-room/`).
  - **Not checked:** saving through a real WordPress install of 1.33.0.

## 1.32.2

**Fix: a feature banner's words fit on phones.** Seen on Gates' new outlet banner (`/home-2/`, 1.32.1). At 375px wide its 1080×400 phone picture is 139px tall. The headline, smaller line and button needed about 164px, so the headline was pushed against the top edge. Phil: the fix belongs in the plugin, not in each page's CSS.

- **Root cause:** feature banners (`fold_banner`) used the same phone text sizes as the full-height slider: 24px padding, a 22px headline, a 15px line and a full-size button. A slider's phone picture is tall enough for that; a feature banner's isn't.
- **Fix:**
  - Each carousel now carries a class for its kind: `rapm-kind-hero` or `rapm-kind-fold-banner`.
  - On screens up to 768px, feature banners get 14px/16px padding, a 20px headline, a 13px line and a smaller button. Together they take about 114px.
  - A feature banner with no phone picture shows its 1920×300 desktop picture on phones, which is only about 59px tall. There, just the headline shows, at 16px, and the whole banner is still the link.
  - Sliders and computers are unchanged.
- **How it was checked:**
  - **Syntax:** `php -l` on `class-rapm-hero-carousel.php`.
  - **Real page, nothing saved:** Gates' real `/home-2/` (1.32.1) in Chromium, with the 1.32.2 CSS and the new class swapped in, and the page's own temporary outlet-banner CSS removed for the test.
    - **With the phone picture, 375 and 414 wide:** the words take 114px of a 139px and 153px banner, with nothing cut off.
    - **With the phone picture, 768 wide:** the same 114px, in a 284px banner.
    - **With the phone picture, 1440 wide:** unchanged (28px headline).
    - **Without the phone picture, 375 wide:** a 59px banner with the headline only, and the button hidden.
  - **Not checked:** a real WordPress install of 1.32.2 (the class was added to the page in the browser), and a feature banner on another site.
- **After updating Gates:** remove the "Outlet banner: fit the words on phones" block from `/home-2/`'s Custom CSS. 1.32.2 does the same thing for every feature banner.

## 1.32.1

**Fix: Shop the Look as a hero no longer crops the photo to a sliver, and its words are easier to read.** Seen on Gates' new home page (`/home-2/`, 1.32.0, all three options on). Phil: "the images are odd and hard to see."

- **Problem 1, the crop:**
  - **What happened:** the header is about 370px tall, including a 141px top row with nothing showing in it. At 1900×917 that left the photo 499px tall, a 3.8:1 strip cut from 1.6:1 photos, so only about 40% of each photo showed. Stormy Ridge keeps "bottom left", so it showed mostly rug.
  - **Root cause:** `height="screen"` had a 340px floor, but nothing stopped the strip from getting much wider than the default layout's 2.6:1.
  - **Fix:** the photo is never shorter than 38vw, the default layout's 2.6:1 strip. With a tall header, the tab bar now falls just below the screen instead of the photo becoming a sliver. With a shorter header it fits on screen as before (1440×900 with Gates' empty top row hidden: 623px photo, 2.3:1).
- **Problem 2, readability:** the 1.32.0 shade was too light over busy, light rooms. It's darker and reaches further up and across: a bottom-up shade plus a soft one from the bottom-left corner, with no hard edge. The text shadow is a little stronger.
- **Also seen, not plugin changes:**
  - **Photo size:** Gates' look photos are 1420px wide, stretched to 1900px on a wide screen, which makes them soft. The readme already asks for 2000px or wider. Upload 2400px versions.
  - **Dot on the headline:** dot 1 (the bench) on Stormy Ridge sits on the headline. Move it in Place the Pieces, or shorten the headline.
- **How it was checked:**
  - **Real page, nothing saved:** Gates' real `/home-2/` (1.32.0, real header and looks) was loaded in Chromium with the 1.32.1 CSS swapped in.
    - **1900×917:** the photo is 722px tall (2.63:1). The whole Stormy Ridge table, bench and server show, and the words read clearly.
    - **1440×900:** the same with the empty top row hidden (623px, 2.31:1). No sideways scroll at either size.
  - **Not checked:** a real WordPress install of 1.32.1. Only the CSS changed, so the PHP and JS are the same as 1.32.0.

## 1.32.0

**New: Shop the Look can be a page's full-screen hero.** Phil asked for the look on one page to fill more of the screen, with the photo filling the hero, the information at the bottom visible without scrolling, and text over the photo. Three new `[rapm_looks]` options, all off by default, so existing looks don't change:

- **`width="full"`:** the photo and tab bar run edge to edge, out of the theme's page width (WoodMart's 1400px container). The words and cards stay at the content width.
  - `rapm-looks.js` sets `--rapm-looks-vw` to the page's width without the scrollbar. Plain `100vw` includes the scrollbar and would cause a sideways scroll.
  - Assumes the section's column is centered on the page, as a page-wide Elementor container is.
- **`height="screen"`:** the photo is sized so that the photo plus the tab bar end at the bottom of the screen, under whatever header the page has.
  - `rapm-looks.js` measures where the section starts (header, admin bar, anything above it) as `--rapm-looks-top`, and the tab bar's height as `--rapm-looks-bar`. Both are measured again on resize and after the page finishes loading.
  - Never shorter than 340px, and never taller than 5:8 of the width, so an upright tablet doesn't get a tall, narrow crop. Uses `svh` with a `vh` fallback.
  - Phones keep the 4:3 photo.
- **`text="overlay"`:** the look's eyebrow, headline, smaller line and button sit on the photo, bottom left, over a soft dark shade so white text reads on a light room.
  - The piece cards stay below and take the full width. Below is hidden for a look with no pieces (class `is-bare`).
  - Phones show the words under the photo as before, because on a 375px-wide 4:3 photo they covered the pieces and ran into the dots. The words are printed twice (`.rapm-look-intro.is-phone`), and the CSS shows one copy per screen size.
  - Dots stay above the words (z-index), so a dot placed low and to the left can sit on the text. Place dots with that in mind on overlay looks.
- **Use on a hero page:** `[rapm_looks placement="home" width="full" height="screen" text="overlay"]`.
- **Add to cart** moves to 1.33.0.
- **How it was checked:**
  - **Syntax:** `php -l` (PHP 8.4) on `class-rapm-looks.php` and `node --check` on `rapm-looks.js`.
  - **Real page, nothing saved:** the R&A test site's real home page (1.31.4, real theme and data) was loaded in Chromium. The 1.32.0 CSS and JS were swapped in on the way to the browser, and the shortcode's 1.32.0 markup was recreated in the page.
    - **All three options, 1440×900 and 1920×1080:** the photo is the full page width. The tab bar ends exactly at the bottom of the screen (900 and 1080). The words are on the photo, all 3 dots show, and there's no sideways scroll.
    - **768×1024:** full width, the photo stops at 480px (5:8), words on the photo, no sideways scroll.
    - **375×812:** full width, 4:3 photo, the words and button under the tab bar, then the cards. No sideways scroll.
    - **No new options:** the photo's position and size match 1.31.4 exactly at all four sizes.
  - **Not checked:** a real WordPress install of 1.32.0 (the PHP output was recreated in the browser, not run), the Add/Edit form's preview (unchanged, and it uses none of the new classes), and Safari.

## 1.31.4

**Fix: the menu marks the screen you're on.**
- **Problem:** on Help, Settings and the other Promo Manager screens, the Promotions menu opened with no item marked. Seen on staging (1.31.3) and on Gates (1.31.2, the Help page), so it was there before 1.31.3.
- **Root cause, partly confirmed:** WordPress's own marking didn't work on these screens. The menu shows no item marked, but the exact core condition that fails wasn't confirmed without WordPress's source on hand.
- **Fix:** `RAPM_Promotions_Screen::submenu_file()` now names the current item itself whenever the screen is one of Promo Manager's own menu items. Editing a promotion still marks "All promotions", and the old list still marks "All Assets (list)". Other plugins' screens are left alone.
- **Checked:**
  - **PHP:** the PHP 8.4 check passes.
  - **Highlighting:** `submenu_file()` was run for each screen on the 1.31.3 menu. The Promotions screen marks "All promotions", Add New Asset, Sliders, Training Guide, Help and Settings each mark themselves, editing marks "All promotions", the old list marks its item, and another plugin's page gets nothing.

## 1.31.3

**Change: every screen is back in the menu, and the spot tools show for everyone.** Phil: "All menu options need to be available to everyone on the backend." Found on Gates. Its Promotions menu had only "All promotions" and "Help", so there was no visible way to reach Settings (for the update key) or to add a spot.

- **Why they were hidden:** 1.28.0 shortened the menu to "All promotions" and "Help" for store staff. Add New Asset, the old list, Sliders, Training Guide and Settings were only reachable through "Show R&A setup details" at the bottom of the Promotions screen. 1.29.0 put "+ New spot", spot codes and renaming behind that same link. This was recorded in the 1.28.0 and 1.29.0 notes, but not raised with Phil as a loss of menu items.
- **The menu now reads:** All promotions, Add New Asset, All Assets (list), Sliders, Training Guide, Help, Settings.
  - "All Assets (list)" is the old list with bulk actions and filters (`&rapm_list=1`).
  - Each screen keeps the permission it always had. Settings is for administrators (`manage_options`), as it was before 1.28.0, because it holds the GitHub update key. Everything else needs `edit_posts`.
  - **Two duplicates stay out of the menu:**
    - The Promotions screen's own item, because "All promotions" opens it.
    - WordPress's own "Add New", which has only ever forwarded to "Add New Asset".
  - Editing a promotion highlights "All promotions". The old list highlights its own item.
- **The Promotions screen shows everything all the time.** No more "Show R&A setup details" toggle.
  - "+ New spot" is at the top.
  - Each section shows its spot code, a rename box, the H1 note for sliders and banners, and "Remove this spot" where allowed.
  - A "Setup" panel at the bottom keeps the links and the last page-cache clear.
  - Wording made for R&A only now speaks to everyone:
    - "R&A only. Spot code:" now reads "Spot code:".
    - "Name clients see" now reads "Spot name".
    - "Not on any page yet. Ask R&A Marketing to place it." now reads "…Put its spot code (below) on a page to show it."
    - The empty screen now says how to add a spot, instead of "R&A Marketing sets up the spots".
- **Docs:** Help has a new answer, "How do I add a new spot?". The readme describes the full menu.
- **How it was checked:**
  - **PHP:** the in-browser PHP 8.4 check found 0 issues in all 27 files.
  - **Menu:** `tidy_menu()` was run on a WordPress-style menu built in the real registration order. It gave the seven items above, in order, with their permissions. Highlighting was checked for editing a promotion, the Promotions screen and the old list.
  - **Promotions screen:** rendered with the real CSS and JS. "+ New spot" is visible and opens the dialog with all five types. Spot codes are visible, and the panel reads "Setup". No script errors.

## 1.31.2

**Fix: product cards under Shop the Look's photo stay short when names are long.**
- **Problem:** on staging at 375px, the cards were 189px tall. "Americana Modern Dining Trestle Dining Table 88-112″ X 42″ (24″ Butterfly Leaf)" wrapped to seven lines. Every card in the row stretched to match, so "Breckenridge Bedroom Queen Panel Bed" had a big empty gap.
- **Root cause:** the card name had no size of its own, so it used the theme's 16px bold link style. ABC-style catalog names are long.
- **Fix:**
  - Card names are 15px, or 14px on phones, and stop at 3 lines with "…" (`-webkit-line-clamp`, supported by all current browsers). The pop-up and the product page still show the whole name, and screen readers read the whole name.
  - On phones the card picture is 64px instead of 84px.
  - The Review step's preview uses the same stylesheet, so it matches.
- **Checked:** the new rules were added to staging's real home page at 375px, with the real theme, before release. The Dining Room cards went from 189px to 96px tall, with names at 3 lines.
- **Confirmed on staging (real WordPress, 2026-10-06),** after updating only RA Promo Manager from Dashboard > Updates (1.31.1 to 1.31.2, "updated successfully"; the other plugin updates were left alone):
  - **Logged out (home page fetched without cookies):** `rapm-looks.css?ver=1.31.2` and `rapm-looks.js?ver=1.31.2`. The served CSS has the 3-line limit.
  - **375px (the home page in a 375px frame):** the cards are 96px tall and the three tabs fit with no fade. No sideways scroll.
  - **Desktop:** the Living Room cards are 84px tall.

## 1.31.1

Three fixes in the "Place the Pieces" step, found while placing dots on staging in 1.31.0.

**Fix: after clicking the photo, typing goes into the new dot's search box.**
- **Problem:** clicking the photo added a dot, but its search box didn't have focus. Typing went nowhere, and the space bar scrolled the page to the bottom.
- **Root cause:** the editor focused the search box during `pointerdown`. The browser's own `mousedown` comes right after it, and its default action moved focus to the photo. The photo can't hold focus, so focus ended up on the page itself. The 1.31.0 test page used simulated pointer events, which skip the browser's `mousedown` step, so it didn't show this.
- **Fix:** a `mousedown` on the photo (not on a dot) now has its default action prevented, so focus stays in the search box.

**Fix: searching words that aren't side by side in the product name.**
- **Problem:** on staging, "Apple Cider Console Loveseat" found nothing, though "Hancock – Apple Cider Power Zero Gravity Console Loveseat" exists.
- **Root cause:** this store's search only matches the words in the order they're typed, as one phrase. Checked against staging's Store API: "Console Loveseat" found 24 products and "Cider Power" found 3, but "Apple Cider Loveseat", "loveseat apple" and "hancock loveseat" each found 0.
- **Fix:** when a search of two or more words finds nothing:
  1. The editor asks the store how many products match each word (up to 5 words, `per_page=1`, reading `X-WP-Total`).
  2. It loads up to 100 products for the word with the fewest matches.
  3. It keeps the ones whose name or SKU contains every word.
- **Limits:**
  - If any word matches nothing, the search shows "Nothing matches" as before.
  - If even the rarest word matches more than 100 products, only its first 100 are checked.
  - A search that finds something the normal way doesn't change.

**Fix: a dot keeps focus after it's dragged.**
- **Problem:** after a drag, the arrow keys didn't move the dot.
- **Root cause:** the dots are redrawn when a drag ends, and the one that had focus is replaced.
- **Fix:** the redrawn dot gets focus back, so the arrow keys keep working.

**Also:** more space under "How it fits on screens", which sat right against the next step's heading when editing.

**How it was checked.**
- **Real clicks and typing** in the browser on the test page, using the real CSS and JS and a stand-in store that, like staging, only matches a phrase:
  - **Click focus:** clicking the photo added dot 4, its search box had focus, and the typing landed there.
  - **Fallback search:** "ottoman cocktail" found nothing as a phrase. The editor then asked the store about "ottoman" and "cocktail", loaded the "ottoman" matches and found "XL Square Cocktail Ottoman 33867".
  - **No match:** "ottoman purple" showed "Nothing matches…".
  - **Drag:** a real drag moved dot 1 from 38% to 48.4%, focus stayed on the dot, and ArrowLeft moved it to 47.4%.
- **Confirmed on staging (real WordPress, 2026-10-06),** after updating only RA Promo Manager (1.31.0 to 1.31.1, "updated successfully"):
  - **Click focus:** the Dining Room and Bedroom dots (8 in all) were placed by clicking the photo and typing straight away. Every time, the typing landed in the new dot's search box.
  - **Fallback search:** "trestle table 88" found the 88-112″ trestle table and the 10-piece trestle set, though neither name has those words side by side. "breckenridge nightstand" found the Breckenridge 3 Drawer Nightstand.
  - **Speed:** each store search took about 2.3–2.7 seconds on this server. The fallback added about 1.2 seconds: three word counts at once (~0.5s), then one 100-product request (~0.7s).

## 1.31.0

**New: numbered dots and products on Shop the Look.** This is the second part of the plan Phil approved on 2026-10-06; Add to cart comes in 1.32.0. Each look can have up to 12 numbered dots on the pieces in its photo. Each dot is linked to a WooCommerce product, and shoppers see the product's live price and stock. The system can't know where a sofa is in a photo, so staff place the dots by hand.

- **Saved data:** `_rapm_dots`, a list of `{p, x, y}`:
  - `p` is the product ID.
  - `x` and `y` are percent of the photo, 0–100, rounded to 0.1.
  - `RAPM_Looks::sanitize_dots()` clamps the numbers, drops dots without a product and caps the list at 12 (`MAX_DOTS`).
  - On save it also drops IDs that aren't products (`get_post_type()`).
- **On the website (`[rapm_looks]`):**
  - **Dots:** white numbered dots sit on the photo. `placeDots()` uses the same cover-fit math as the photo itself (`object-fit: cover` plus the look's "Which part to keep"), so a dot stays on its sofa at every screen size. It runs again on resize through `ResizeObserver`.
  - **Cut-off dots:** a dot that a screen's crop cuts off is hidden on that screen. Its card still shows.
  - **Pop-up:** clicking a dot opens a small card with the picture, name, price, stock, "View product" and a close button.
    - The tour pauses while it's open.
    - Escape, the close button or a click outside closes it, and Escape returns focus to the dot.
    - Hovering a dot lights up its card, and the other way around.
  - **Cards under the photo:** the look's words sit on the left and numbered product cards on the right. One column under 1023px. On phones the cards are a sideways-scrolling row.
  - **Live prices and stock:** one WooCommerce Store API request per page load (`/wc/store/v1/products?include=…&per_page=100`), so they're current behind a page cache.
    - Sale prices show the old price crossed out.
    - Variable products show a range.
    - A product priced 0 shows no price, since ABC-style "call for price" products are common.
    - Stock text goes through an inert `DOMParser` document and is set as text, never as live HTML.
    - If the request fails, the cards just have no price.
  - **Unpublished or deleted products:** `product_info()` only returns published products. A product that's unpublished or deleted loses its dot and card, and the rest are renumbered.
    - Because that's decided when the page is built, a cached page keeps the dot until the cache clears.
    - The browser doesn't hide dots the Store API leaves out. It isn't confirmed whether the Store API skips published products that are hidden from the catalog, and hiding those would be wrong.
- **The form: a new "Place the Pieces" step.** Looks now have 5 steps: basics, Place the Pieces, words, link, review. Sliders, banners, coupons and tiles keep their 4.
  - **Placing dots:**
    - Click the photo to add a dot. Its row opens with a search box already focused.
    - Search by product name (Store API `search=`) and by exact SKU (`sku=`) at the same time.
    - Drag a dot to move it. Arrow keys move it 1% at a time, or 5% with Shift.
    - "Add a dot in the middle" is there for keyboard users.
  - **Each row:** the product's picture, name, SKU, price and stock, with "Change product", "Move up", "Move down" and "Remove dot".
  - **Which part to keep:** the crop grid moved here from step 1, next to small Computer (2.6:1) and Phone (4:3) previews with the numbered dots on them.
    - A row says "Cut off on computers", "Cut off on phones" or both when that screen's crop would hide its dot (2% margin).
    - A line under the previews says whether every dot fits.
  - **A dot whose product is now unpublished or deleted:** its row (amber) says shoppers don't see it and asks for another product or removal. Its dot on the photo is amber too.
  - **Checks:** Next and Save are blocked while any dot has no product, naming the dots ("Missing: dot 4"). A look with no dots is allowed.
  - **Review step:** the Live Preview shows the dots and the cards with live prices, placed and numbered the way the website does it, for computers and phones.
  - **Without WooCommerce:** the step says dots can't be added, and nothing breaks.
- **Promotions screen:** a look's card reads "Tab: Living Room · 2 pieces". Only dots whose product is on the website are counted.
- **Files:**
  - New `assets/js/rapm-look-editor.js` and `assets/css/rapm-look-editor.css` (the preview's styles moved here from inline).
  - Front-end additions are in `rapm-looks.js` and `rapm-looks.css`.
- **Docs:**
  - **Help:** four new Shop the Look questions: placing dots, what shoppers see, "Cut off on phones", and products taken off the website.
  - **Training Guide:** the type card mentions dots, and there's a new "dot doesn't show on phones" problem.
  - **readme:** the `[rapm_looks]` section is updated.

**How it was checked.**
- **PHP:** the in-browser PHP 8.4 check (parse plus compile) found 0 issues in all 27 files. The Help and Training Guide pages rendered with no warnings.
- **Website, run with the real CSS and JS and a stand-in Store API:**
  - One request: `include=301,302&per_page=100`.
  - Cards show "~~$5,500.00~~ $4,399.98" and "~~$1,300.00~~ $799.93" with stock.
  - A dot opens its pop-up, which pauses the tour. Hover links dot and card. Escape closes it.
  - Planted HTML in the stock text did not run.
  - No sideways page scroll.
- **Form, edit and new, with the real CSS and JS and a stand-in Store API:**
  - **Adding and searching:** a simulated click on the photo added dot 4 and focused its search. With real clicks on staging the focus was lost; fixed in 1.31.1. Typing "otto" sent both `search=otto` and `sku=otto`, and picking the result filled the row.
  - **Moving:** dragging moved dot 1 to (30, 50). Shift+→ moved a dot from 50 to 55.
  - **Cut-off warnings:** "Top" and "Center" for which part to keep produced the expected warnings.
  - **Checks:** Next and Save were blocked with "Missing: dot 4".
  - **Unpublished product:** a product set to draft showed the amber row. The preview left it out and numbered the rest 1, 2, 3.
  - **New look:** step 2 asks for a photo first. Picking a 1500px photo showed it with "a little soft".
- **Fixed during testing, before release:**
  - The unpublished-product note shared a CSS class with the cut-off note, so updating cut-off notes erased or duplicated it.
  - The preview showed a "#303" card for an unpublished product, and its numbers didn't match the website.
  - `setPointerCapture` could throw and stop a drag. It's now wrapped.
- **Confirmed on staging (real WordPress, 2026-10-06),** after updating only RA Promo Manager (1.30.1 to 1.31.0, "updated successfully"):
  - **The form:** a look's form has the five steps, loads `rapm-look-editor.js?ver=1.31.0`, and points at staging's Store API.
  - **Search:** a name search ("Apple Cider Power Zero Gravity Sofa") and an exact SKU ("MHAN-812P3Z-APCI", and later "DAME#2218" with its "#") found their products, with "No price shown online · Available on backorder".
  - **Moving and saving:** a real drag moved the sofa's dot from 57.9% to 51%, and the save kept it.
  - **Bugs found here:** clicking the photo didn't leave focus in the search box, a search with words out of order found nothing, and a dragged dot lost focus. All three were fixed in 1.31.1.
  - **Dots placed on the three test looks,** each product matched by comparing its product photos to the room photo:
    - **Living Room:** Hancock Apple Cider Power Zero Gravity Sofa Group (MHAN-421P3Z-APCI), Recliner (MHAN-812P3Z-APCI) and Console Loveseat (MHAN-822CP3Z-APCI).
    - **Dining Room:** Americana Modern Trestle Dining Table 88-112″ (DAME#88TRES-2-COT), Upholstered Dining Chair (DAME#2218), and the 69″ Buffet / Display Hutch (DAME#69-2-COT). The hutch was picked over the 66″ Bar hutch because it has the narrow side shelves in the photo.
    - **Bedroom:** Breckenridge Queen Panel Bed (BBRE-KIT-Q-PNL; wood frame with a fabric inset, not the all-fabric Upholstered Bed), 3 Drawer Nightstand (BBRE-50), 5 Drawer Chest (BBRE-70), 7 Drawer Dresser (BBRE-60) and Mirror (BBRE-65).
    - Every dot shows on computers and phones.
  - **Promotions screen:** "Tab: Living Room · 3 pieces", "Tab: Dining Room · 3 pieces", "Tab: Bedroom · 5 pieces". The tab order is unchanged.
  - **Home page, logged out (fetched without cookies):** 3, 3 and 5 dots, 11 cards, and the Store API address.
  - **Home page, desktop:**
    - One Store API request, and all 11 dots visible.
    - Stock reads "Available on backorder". No price shows, because every test product on staging is priced 0.
    - Clicking dot 1 opened its pop-up with the name, stock and "View product", linking to the real sofa-group product page. The dot and its card turned the brand red, and the tour stopped. Escape closed it and returned focus to the dot.
    - No sideways scroll.
  - **375px:** all 11 dots show. This is where the long-name card problem fixed in 1.31.2 was found.
  - **Still not checked on real WordPress:**
    - Prices and sale prices. Staging's test products are all priced 0, so the crossed-out sale price has only been seen on the test page.
    - A product unpublished after its dot was placed.

## 1.30.1

Two fixes found on staging in 1.30.0.

**Fix: a spot's automatic name no longer says its type twice.**
- **Problem:** "Home page looks" showed as "Home Page Looks looks".
- **Root cause:** `RAPM_Spots::name()` always added the type's word ("slider", "banner", "coupons", "tiles", "looks") after the words from the spot code. A code placed on a page without ever being named, like `home-page-looks`, already ends in that word.
- **Fix:** the word is now left off when the code's last word is the same word, singular or plural.
- **Examples:** `home-page-looks` reads "Home Page Looks", `living-room-slider` reads "Living Room Slider", and `clearance-coupon` reads "Clearance Coupon". `home`, `default` and every other code read as before ("Home page slider", "Main banner", "Living Room tiles").
- **No data changes:** names R&A gave in setup details are untouched.

**Fix: Shop the Look's tab bar on phones.**
- **Problem:** on staging at 375px, the three tabs (309px) didn't fit the bar (277px), so "Bedroom" was cut off with nothing to show the bar scrolls.
- **Root cause:** the tabs' spacing assumed the full phone width, but the theme's page gutter left the hero 50px narrower.
- **Fix:**
  - On phones the tabs use 12px side padding and 13px type, and the tour button is 40px wide.
  - When tabs are hidden past either edge, that edge fades (`is-more` / `is-less` on `.rapm-looks-tabs`, set on build, scroll and resize). Shoppers can see there's more to swipe.

**How it was checked.**
- **PHP:** the in-browser PHP 8.4 check (parse plus compile) found 0 issues in all 27 files.
- **Names:** `RAPM_Spots::name()` was run in that PHP on eight codes, giving the names listed above. Codes without a doubled word are unchanged.
- **Tab bar:** run with the real CSS and JS at staging's exact width (375px screen, 25px page gutter):
  - Three tabs fit (285px of tabs in a 285px bar) with no fade.
  - Four tabs overflow: the right edge fades at the start. Picking the last tab scrolls it into view and moves the fade to the left. Going back to the first tab returns the bar to the start with only the right fade.
  - No sideways page scroll.
- **Confirmed on staging (real WordPress, 2026-10-06),** after updating only RA Promo Manager from Dashboard > Updates (1.30.0 to 1.30.1, "updated successfully"; the other ten pending plugin updates were left alone):
  - **Promotions screen:** the spot reads "Home Page Looks". The other six names are unchanged: "Home page slider", "Homepage Looks", "Home page banner", "About banner", "Promotions coupons", "Living Room tiles".
  - **Home page, logged out, 375px:**
    - The page loads `rapm-looks.css?ver=1.30.1`.
    - "Living Room", "Dining Room" and "Bedroom" all fit the bar (285px of tabs in 285px), with no fade and no sideways scroll.

## 1.30.0

**New: Shop the Look, a fifth type (`look`).** Built from the mockups Phil approved on 2026-10-06 for the Gates Furniture homepage (v3 front end and the look editor). The front end is a full-width room photo, a thin bar of tabs under it, and the look's headline, smaller line and button below. Each tab is one look, and the client names it: a room ("Living Room") or a collection ("Stanton 338"). This release has the type, the tabs, their order and dates. Numbered dots on the pieces in the photo, with live prices and stock, come in 1.31.0, and Add to cart in 1.32.0.
- **One look = one promotion.** So it has what promotions already have: on/off switch, start and end dates, drag or "Move earlier/later" to reorder (the order is the tab order), Make a copy, Move to trash, and page-cache clearing (`[rapm_looks]` is in `RAPM_Spot_Usage::TAGS`). New fields:
  - `_rapm_tab_label`, up to 24 characters, required.
  - `_rapm_focus`, "Which part to keep", one of the nine positions the crop picker already uses, stored as a CSS `object-position`.
- **One photo of any shape, not a Desktop and a Mobile picture.** Dots (1.31.0) are placed on one photo, so two photos would mean placing every dot twice.
  - New slot `look_photo` marked `flexible` (`RAPM_Slots::is_flexible()`). Any shape fits, so there's no crop picker and a Drive folder uses its newest picture.
  - A photo wider than 2400px is scaled down, keeping its shape. Under 800px wide it is turned down with the numbers. Then WebP, under 450KB, like every slot.
  - 2400, 800 and 450KB are judgment calls, not a standard. Width and file size can be changed in Settings, where the slot reads "Any shape, up to 2400 wide".
  - The card shape on the Promotions screen is 3:2.
- **The form (Add/Edit, kind `look`):**
  - **Step 1:**
    - **Tab name** field.
    - One **Room photo** box. Upload or link; no Mobile row.
    - A live size note: under 800 "turned down when you save"; under 1200 "blurry across a big screen"; under 2000 "a little soft"; otherwise "big enough".
    - **Which part to keep**: the 3×3 grid, with small Computer (2.6:1) and Phone (4:3) previews of the crop.
  - **Step 2, "Words Under the Tabs":** Headline, Smaller line, Button text only. No "Sale Text" question and no alignment, color, style or typeface, since nothing is printed on the photo.
  - **Step 3:** reads "Where the Button Goes".
  - **Step 4:** the Live Preview is the look as shoppers see it, using `rapm-looks.css`: photo, tab, words, Desktop/Mobile.
  - The step-1 check in the browser and the save both require a tab name and a photo.
  - The "who else shares this spot" line talks about tabs instead of a carousel.
  - Sliders, banners, coupons and tiles get exactly the same form as before.
- **The Promotions screen:**
  - A look spot shows **"Tabs on the website, in this order:"**, the looks showing now in card order. It redraws after a switch, a drag or "Move earlier/later" (`refreshTabOrder()` in `rapm-promotions-admin.js`).
  - Each look card shows **Tab: Living Room**, and "Find a promotion" also matches tab names.
  - The add card says **Add a look**, and the hint says "Drag to change the order of the tabs".
  - Look spots sort right after sliders.
  - "+ New spot" offers Shop the Look ("For example: Home page looks").
- **Front end, `[rapm_looks placement="…"]` (`RAPM_Looks`, `rapm-looks.js`, `rapm-looks.css`):**
  - **Looks:** all looks are printed with their dates, and `RAPM_Schedule.watch()` builds the tabs from the ones live in the visitor's browser, so dates work behind WP Rocket. Photos and words are printed as two groups (the bar sits between them on the page), matched by `data-rapm-key`.
  - **Photo size:** 340–720px tall on computers (38% of the screen width), 4:3 on phones, cover-cropped at the look's "Which part to keep".
  - **The tour:** 8 seconds per look (`speed`, `autoplay="no"`). It pauses while the mouse is over the photo or a control has keyboard focus, and stops for good when a shopper picks a tab, uses an arrow or swipes. It has a pause/play button and never runs with reduced motion on. The timer is a CSS animation on the active tab's top line, so pausing is a class and the next look starts on `animationend`.
  - **Accessibility:** tabs are a tablist (arrow keys, Home, End); hidden looks are `inert`.
  - **Colors:** the accent (timer line, button) is the calendar's: Settings > Brand Color, else a readable Elementor global color, else `#2271b1`. For Gates, set the brand color in Settings.
  - **Theme styles:** WoodMart styles plain buttons (gray fill, uppercase, padding), so every button here sets those properties itself.
  - **WP Rocket:** "Delay JavaScript" leaves `rapm-looks` / `RAPM_Looks` alone.
  - **No Elementor widget yet:** place it with Elementor's Shortcode widget.
- **Help & FAQ:** a "Shop the Look" Q&A (what it is, tab names, order, one photo, photo size, taking a tab off for a while), plus the type list and the step list. **Training Guide:** "The 5 Types", a Shop the Look card, a size-table row and a Promotions-page line. **readme.txt** updated.
- **Worth knowing:** like every type, a look with both a start and an end date also shows on the Promotions Calendar.

**How it was checked.** Still no PHP on this Mac (standing constraint), so a new method replaced the structural checker: real PHP 8.4.1, compiled to WebAssembly (php-wasm 0.2.0), running in the browser.
- **Syntax and compile, PHP 8.4's own parser and compiler** (`token_get_all( …, TOKEN_PARSE )`, then `eval()` behind an early `return`, so nothing runs):
  - It caught all four planted bugs: the 1.24.1-style unescaped apostrophe, a missing brace, a missing `endforeach`, and `isset()` on a function result. The old checker could never see that last one.
  - 0 issues in the 26 shipped 1.29.1 files, and 0 in all 27 current files.
  - A search found no syntax newer than PHP 7 in the new code.
- **The real render code, run in that PHP** with stand-ins for the WordPress functions it calls and test data (three looks, one future-dated, one switched off, and one slider):
  - The new-look form, the edit-look form, the unchanged slider form, the Promotions screen and the `[rapm_looks]` output all rendered with no PHP warnings or notices. Settings rendered through the picture-size table; the updater section wasn't loaded.
  - All 26 inline scripts in those pages parse.
- **Then in the browser,** with the plugin's own CSS and JS and a WoodMart-like button rule:
  - **Front end:**
    - 3 tabs: the future look is left out until its date and the switched-off one isn't printed.
    - The first tab's timer runs, and `animationend` moves to the next look.
    - Mouse over the photo pauses it; picking a tab stops the tour and shows that look's words.
    - The next arrow works, and the pause button toggles.
    - "center bottom" became `object-position: 50% 100%`, and the theme's uppercase gray buttons were overridden.
    - At 375px wide the photo is 4:3, the three tabs and the pause button fit, and nothing scrolls sideways.
  - **Form:**
    - "Next" with nothing filled in lists all three missing things.
    - A real 1420px photo chosen in the file box gets "a little soft"; 957px gets the same (now "blurry", under 1200).
    - The focus grid sets the field and both small previews.
    - The preview shows tab, headline and button and switches to Mobile.
    - No script errors in the new-look, edit-look or slider forms.
  - **Promotions screen:** "Move later" on Living Room redraws the tabs as Bedroom > Living Room > Dining Room. Switching Bedroom off and on removes it from the tabs and brings it back. Searching "dining" finds the look by its tab.
- **What these can't show, so staging is still the confirmation:**
  - Real WordPress saving and uploading (WebP conversion and scaling a wide photo down to 2400px).
  - Page-cache clearing.
  - The real WoodMart theme.
  - The tour running on its own in a visible browser (the test browser was hidden, so CSS animations didn't play by themselves).
- **Confirmed on staging (real WordPress, ABC Furniture Retailer, 2026-10-06),** after the normal GitHub update, using the test site's own products. Three looks were added through the form in Phil's Chrome, each photo pasted as a link to the product's own image on the site:
  - **The three looks:**
    - "Hancock living room" (Apple Cider sofa group, 1000px).
    - "Americana Modern dining room" (trestle table with eight upholstered chairs, 800px).
    - "Breckenridge bedroom" (queen panel bed, nightstand, chest, dresser and mirror, 1000px).
  - **Form:**
    - The link check found each picture.
    - The size note read "blurry" for all three (under 1200px).
    - "Which part to keep" updated both small previews.
    - The spot line went from "this will be its first tab" to "2 other promotions already use this spot … each one is its own tab".
    - The category picker set Living Room (340), Dining Room (383) and Bedroom (355).
    - Each save converted the photo to WebP in `/2026/10/` and kept the tab name and focus.
  - **Promotions screen:** "Home Page Looks looks" shows 3 showing now, "Tabs on the website, in this order: Living Room, Dining Room, Bedroom", the tab name on each card, and "Add a look".
  - **Home page, logged in, real WoodMart theme:**
    - The three tabs appeared, with the timer line moving.
    - The tour moved from Living Room to Dining Room by itself after 8 seconds.
    - The accent came out as the site's own red (#971819); tabs are in Lato and the title in Poppins.
    - The button goes to `/living-room/`.
  - **Logged out:** the home page's HTML has all three looks (with and without a cache-busting query).
  - **Phone, logged out, 375px:** 4:3 photo, words and button below, no sideways scroll.
- **Not covered on staging yet:**
  - Scaling a photo wider than 2400px: the test site has no product photo that wide.
  - Switching a look off and watching its tab leave the logged-out page through Rocket.net's CDN.
- **Found on staging, for a later fix:**
  - **Doubled spot name.** A spot placed by its code alone gets a doubled automatic name when the code already ends in the type's word: `home-page-looks` reads "Home Page Looks looks". This happens to any type, for example `living-room-slider` would read "Living Room Slider slider". R&A can rename it in setup details.
  - **Tab bar on phones.** At 375px the three tabs (309px) are a little wider than the bar (277px), so "Bedroom" is partly hidden until the bar is swiped.

## 1.29.1

**Change: the main heading (H1) is set where a spot is placed, not on the spot.** Phil's call, after staging showed the per-spot switch from 1.29.0 put two H1s on the Promotions page. Home page slider also shows there, and that page already has its own H1. A spot can sit on pages that need an H1 and pages that don't, so only the placement knows.
- **Shortcode:** `heading="h1"` on `[rapm_hero]` or `[rapm_fold_banner]`, for example `[rapm_hero placement="home" heading="h1"]`. The default stays `h2`. It works the same as 1.29.0: the first promotion showing gets the `h1`, the rest `h2`, `rapm-schedule.js` moves it to whichever promotion is actually showing, and the `h1` keeps the headline's look.
- **Elementor:** the Promo Carousel widget has a "Main heading (H1)" switch: "Turn on only where this is the top of a page with no other H1, like the home page." It adds `heading="h1"` to the code the widget saves and renders.
- **Removed:** the per-spot checkbox, its AJAX action and its cache hook. In R&A setup details, each slider and feature banner now says where to set it instead. The staging test had switched the per-spot setting back off, so nothing was left set.

**New: "Remove this spot" (R&A setup details).** It only appears for a spot that exists just because it was named: no promotions (the trash doesn't count) and not on any page, template or widget. Removing it asks first, deletes the spot's name and settings, and shows "Spot removed." A spot with promotions or placements can't be removed (`RAPM_Spots::removable()`). The server checks that again, not just the screen. Built to clean up the 1.29.0 staging test spot, and any spot made by mistake.

**How it was checked.**
- **PHP:** the structural checker found 0 issues in 26 files. A search confirmed nothing still calls the per-spot H1.
- **Remove spot:** tested in a browser on the admin test page. Cancelling the question sends nothing; confirming sends the spot and reloads the screen.
- **Confirmed on staging (real WordPress, 2026-10-05),** after the normal GitHub update:
  - **R&A setup details:** only "Test spot (Claude, remove me)" offered "Remove this spot". The five real spots didn't, since they have promotions and are on pages. No per-spot H1 checkbox is left, and the slider and both feature banners show the "set it where it's placed" note.
  - **Remove:** removing the test spot asked first, then showed "Spot removed.", and the list went back to the five real spots.
  - **Shortcode:** a draft page with `[rapm_hero placement="home" heading="h1"]` and `[rapm_fold_banner placement="home"]` had exactly one H1 ("The Fall Living Room Event"). The slider's other two promotions and the banner stayed H2.
  - **Elementor:** the Promo Carousel widget has the "Main heading (H1)" switch. With the spot "Home page slider" picked and the switch on, it rendered the first promotion as an H1 and the rest as H2. Not saved.
  - The test page was moved to the trash, not deleted. The live home page is unchanged (no H1 yet). R&A turns on the switch, or adds `heading="h1"`, where the slider sits on the home page.

## 1.29.0

**New: spots you can name and add, plain type names, and a per-spot main heading (H1) for search engines.** Phil approved the spot names and type labels in the mockup (2026-10-05) and asked for the H1 setting. He wants several of each type of banner: the home page slider may also go on other pages, and the "home page banner" is really a feature banner.
- **Plain type names everywhere clients look:**
  - The four types are now Slider (was Hero), Feature banner (was Fold Banner), Coupon row and Tile row (was Marquee). Each has a one-line description.
  - This covers the add/edit form's type list, picture-size labels in error messages ("Slider — Mobile"), the Elementor widget, Settings, Help & FAQ and the Training Guide.
  - The codes stay the same (`hero`, `fold_banner`, `coupon`, `marquee`), so shortcodes and saved promotions don't change.
- **Spots by name (`RAPM_Spots`).** A spot is named for its job ("Main slider", "Living Room slider"), not its page, because one spot can go on several pages. Names and settings live in the `rapm_spots` option. 1.28.0's `rapm_spot_names` renames are folded in on first read. A spot without a saved name keeps its automatic name ("Home page slider", "Home page banner", "Promotions coupons", "Living Room tiles"). Each section on the Promotions screen shows its type as a small gray label next to its name.
- **"+ New spot" (R&A setup details).** It opens a window with the four types as picture cards and a name box ("Name it for its job, not its page"), with a live "Clients will see" preview.
  - Creating the spot makes its placement code from the name (`living-room-slider`, adding `-2`, `-3`… if that type already uses it).
  - The screen then shows the new spot as its own section, with "Add a picture". A message explains how to put it on a page: pick it in the Promo Carousel widget, or paste the code given.
- **Elementor's Promo Carousel widget picks a spot by name** from a new Spot list, showing each spot's type. It lists only sliders and feature banners, the two types the widget can show.
  - Widgets saved before 1.29.0 have no spot yet and keep using their old Kind and Placement fields, now labeled Type and Placement code. Those fields show only until a spot is picked, so nothing already on a page changes.
  - The list is built only in the editor. Elementor also builds widget controls on the live site, but uses a saved value as-is without checking it against the options (`Control_Base_Data::get_value()` in Elementor's source), so live pages skip the lookups.
  - The "Shows on" scan reads the new Spot setting too.
- **The add/edit form's "Which Spot on the Site" text box is now "Where it shows",** a list of this type's spots by name. A typo can no longer quietly start a new spot that isn't on any page. The three paragraphs explaining placements and the shortcode box are replaced by one line, plus the spot code in small type for R&A.
- **Main heading (H1) for search engines**, a checkbox per slider or feature banner in R&A setup details:
  - **What it does.** When it's on, the first promotion showing in that spot uses an `h1` for its big words and every other promotion keeps `h2`. Coupon and tile rows don't get the option: a row of cards has no single headline.
  - **Why it's per spot, not on every promotion.** Checked on staging, logged out: the home page and Home 2 have no H1 at all (the slider headlines are their only top headings, all H2). About Us, Promotions and Living Room Collection already have one. Making every promotion an H1 would put four on the Promotions page.
  - **Sources.** Google is fine with several H1s (Search Engine Journal on Google's guidance). Accessibility guidance treats the H1 as the page's main heading (W3C WAI headings tutorial) and warns against several (BOIA).
  - **On the page.** The server gives the `h1` to the first promotion in order that has words, and marks the carousel `data-rapm-h1`. The schedule may be hiding that one, so `rapm-schedule.js` moves the `h1` to the first promotion actually showing every time the carousel rebuilds.
  - **Same look.** Themes and Elementor's site styles can make an `h1` bigger than an `h2` (`.elementor-kit-N h1` outranks `.rapm-headline`). A new rule in `rapm-hero.css` holds the `h1` to the same size, weight, line height, color and spacing as the headline it replaces. The font family stays the site's or the promotion's Typeface.
  - **Cache.** Changing the setting clears the cache for that spot's pages (`RAPM_Cache::queue_spot()`).
  - **Measured on staging first:** turning the slider headline on Promotions into an `h1` in the browser changed no style at all on this WoodMart + Elementor site.

**How it was checked.** No PHP runtime is available here (standing constraint).
- **PHP:** the structural checker found 0 issues in all 26 files. A pattern search found no `isset()` on a function result.
  - Two bugs were caught by reading: a match pattern that failed on an indentation mismatch, which wrote nothing; and a loop variable `$tag` that would have overwritten the shortcode's own `$tag` in `RAPM_Hero_Carousel::render()`, renamed `$heading`.
  - Apostrophes in new strings were checked for escaping.
- **Main heading, in a browser** with real Swiper 11.2.10, the plugin's own `rapm-hero.css` and a deliberately aggressive `.elementor-kit-1 h1` rule (60px, red, uppercase):
  - The first promotion gets the `h1`, and the rest `h2`.
  - When it ends, the `h1` moves to the next one showing; when it comes back, the `h1` goes back. There's exactly one `h1` throughout.
  - Loading with the first one already ended gives the second the `h1`.
  - With the setting off, there's no `h1`.
  - The aggressive theme rule was held off: 28px, 700, white, no uppercase.
- **Admin screen**, on a page with the same markup and WordPress's admin CSS, with server replies faked:
  - "+ New spot" is hidden until R&A details are on.
  - The window opens with focus in the name box. Picking a type updates the example and the "Clients will see" type, and typing updates the name.
  - Create sends the type and name. A failed create shows the error in red and keeps the window open; Cancel closes it.
  - The H1 checkbox saves, and a failed save puts the box back and says so.
- **Confirmed on staging (real WordPress, 2026-10-05),** after the normal GitHub update:
  - **Promotions screen:** the five spots kept their names ("Home page slider", "Home page banner", "About banner", "Promotions coupons", "Living Room tiles"), each with its type label. The H1 box appears only on the slider and the two feature banners. "+ New spot" appears only with R&A details on.
  - **Add/edit form:** "Where it shows" is a list by name: About banner, and Home page banner (selected). The type list reads "Slider — Desktop 1920x600, Mobile 1080x1920" and so on.
  - **Elementor editor:** the Promo Carousel's Spot list offers "About banner (Feature banner)", "Home page banner (Feature banner)" and "Home page slider (Slider)". Type and Placement code show only while no spot is picked. Nothing was saved.
  - **"+ New spot":** created "Test spot (Claude, remove me)" as a Slider. The message gave the next step and the code `[rapm_hero placement="test-spot-claude-remove-me"]`. The new section showed "Not on any page yet" and "0 showing now", and its "Add a picture" opened the form with that spot. **It's still on staging:** there's no way to remove a spot yet.
  - **Main heading:** switched on for Home page slider, a fresh home page had exactly one H1, "The Fall Living Room Event" (before: none).
- **Found on staging: the per-spot H1 is the wrong level.** Home page slider also shows on Promotions, which already has its own H1 ("This month's best deals"). With the setting on, Promotions had two. A spot can sit on several pages, and only some of them lack an H1, so a per-spot switch can't get both right. Switched back off on staging. Next: Phil to choose whether the H1 choice moves to each placement (the widget or shortcode on each page).

## 1.28.2

**New: R&A setup details show what the last page-cache clear did.** Built to find out why 1.28.1 didn't clear Rocket.net's copy of "Home 2". After the update, switching Free Design Help on still left Home 2 cached without the banner (`HIT`).
- `RAPM_Cache` now records each clear: the time, the pages, whether the whole site was cleared, and one line per cache it reached. For Rocket.net, it also records what that plugin's call answered. If the expected Rocket.net class isn't there, it lists the cache-related classes that are loaded, or the class's methods if the call doesn't exist, so the right name can be found without guessing. It catches and records any error too.
- Shown under "Last page-cache clear" in R&A setup details. Stored in the `rapm_last_cache_clear` option (not autoloaded).
- Staging's must-use plugin is Rocket.net's "CDN Cache Plugin" 1.1.13 (Plugins > Must-Use).
- **What it showed on staging (2026-10-05). Rocket.net page clearing is still not solved.**
  - The call runs and Rocket.net answers `{"success":true,…,"result":{"status":"success"}}` for all four pages, but Home 2's CDN copy is not cleared: the same copy kept aging (`age` 228, 231, 234…). That held even 3½ minutes after the previous clear, so it isn't a repeat-request limit.
  - The one earlier `MISS` right after a switch was most likely the plugin update clearing everything just before.
  - A phone browser gets its own separate CDN copy (`MISS` while the computer copy was a `HIT`). A plain "clear this address" call may not reach those per-device copies. That's a lead, not a confirmed cause.
  - Rocket.net's own **CDN Cache → Purge Everything** (an admin link with `cdn-action=purge`) did clear it: Home 2 came back `MISS` with the banner. So a whole-CDN clear works.
  - **Next:** read the real `CDN Cache Plugin` 1.1.13 source (wp-content/mu-plugins, from Rocket.net's file manager or SFTP), or ask Rocket.net support how to clear specific pages including every device copy. Then call that instead.


## 1.28.1

**Fix: switching a promotion off on a Rocket.net site left it showing to visitors on its own page.** Found checking 1.28.0 on staging. Free Design Help (the Home page banner, shown only on "Home 2") was switched off.
- A fresh render of Home 2 no longer had the banner, so the switch itself worked.
- Rocket.net's CDN kept serving its cached copy of Home 2, banner included (`cf-cache-status: HIT`).
- The home page was cleared at the same moment (`MISS`).
- **Root cause.** Rocket.net's must-use plugin clears only its own short list of pages when a promotion is saved, not the pages that show it. 1.28.0 assumed it cleared everything, based on one save that cleared the home page. The other pages that came back `MISS` that time had simply not been cached yet.
- **The fix.** `RAPM_Cache` now asks Rocket.net to clear the exact pages a spot shows on: `CDN_Clear_Cache_Api::cache_api_call( $urls, 'purge' )`.
  - Rocket.net publishes no developer API. This is the call its own plugin makes to clear a post's pages, as quoted from that plugin's code in WP Rocket issue #5252 (github.com/wp-media/wp-rocket/issues/5252, 2024).
  - It only runs if that class and method exist, and any error is caught, so a renamed class or changed method can't break a save.
  - When a spot sits in a template or widget, Rocket.net still gets the spot's known pages. No "clear everything" call is known for Rocket.net, so that case is a gap: use the admin bar's CDN Cache > Purge Everything.
- **To confirm on staging:** switch Free Design Help back on and off, and check that Home 2 comes back `MISS` with the banner present, then absent.

## 1.28.0

**New: the Promotions screen.** This is the first release of the redesign, built from the clickable mockup Phil approved on 2026-10-05. Clients no longer manage promotions from the 12-row All Assets list with its Kind, Shortcode and Date columns. They get one screen that matches how they think about the site: where each picture shows.
- **One section per spot,** named in plain words ("Home page slider", "Living Room tiles"). Each says which pages it actually shows on, as links. Spots placed on a page but with no promotions yet appear too. A spot with promotions that isn't on any page says "Not on any page yet. Ask R&A Marketing to place it."
  - Where a spot shows is found by scanning (`RAPM_Spot_Usage`): page content (Elementor's Shortcode widget and our Promo Carousel widget save their shortcode there), Elementor's own `_elementor_data` (for pages whose plain content is out of date, and Promo Carousel widgets saved before 1.26.0), and classic Text, Custom HTML and block widgets. Templates and widgets are labeled as such.
  - The result is cached for 12 hours and dropped whenever any post or widget is saved.
- **Large picture cards** in each spot's real shape: wide for sliders and banners, tall for coupons, square for tiles.
  - Each card has a plain status worked out in the site's timezone: "Showing now · Until Oct 31", "Ends in 7 days (Oct 12)" in amber, "Starts Nov 20", "Hidden", or "Ended".
  - A count at the top of each section says how many are showing now, starting later and hidden.
- **An on/off switch on every card.** On = published, off = draft. The live site only ever shows published promotions, so off really means off. A copy made with "Make a copy" starts hidden.
- **A "…" menu:** Edit, Make a copy, Move earlier, Move later, Move to trash. Trashing asks first, then shows "Moved to the trash. Undo". Undo brings the promotion back exactly as it was: WordPress 5.6+ would otherwise restore it as a draft, quietly switching it off (`wp_untrash_post_status`, checked in core).
- **Drag a card to change the order it plays in.** It saves through the existing reorder action. "Move earlier/later" does the same from the keyboard or a touch screen, where drag and drop doesn't work.
- **Ended promotions fold away** under "Show N ended", each with a "Use again" button that opens it to set new dates.
- **"Find a promotion"** filters by name. Ended matches show too.
- **Shorter menu:** "Promotions" with "All promotions" and "Help". Add New Asset, Add Post, Sliders, Training Guide and Settings are out of the menu, but every one still works at its usual address.
  - The old list's own address now opens the new screen. Any more specific address still opens the old list: a search, a filter, WordPress's own return after a bulk action, or `&rapm_list=1`.
  - The Training Guide is linked from Help, and Settings from R&A setup details.
  - The add/edit form has a "← All promotions" link back.
- **R&A setup details,** a link at the bottom of the screen (Phil's call: anyone can open it, it's just clearly labeled). It shows each spot's shortcode and a box to rename the spot (`rapm_spot_names` option; blank goes back to the automatic name), plus links to the old list, Sliders and Settings. Your browser remembers it's open.
- **Help & FAQ and the Training Guide** now describe this screen instead of Sliders and All Assets. That includes the 1.27.0 "Show at most" answer and new answers for switching off, ordering, ended promotions, copying and deleting, and finding a promotion.

**New: changes now clear the site's page cache (`RAPM_Cache`).** Phil's call: clear only the pages a spot shows on.
- **Why it's needed.** Promotions are a private post type, so caching plugins don't know which public pages display them. In their own source: WP Rocket, WP Super Cache, SiteGround, Cloudflare and Kinsta all skip non-public post types. W3 Total Cache only clears the post itself and the blog home. The schedule never needed this, because it's decided in the visitor's browser, but the new switch does. Without it, a promotion switched off would stay on cached pages until the cache expired.
- **When it runs.** Switching on or off, saving, reordering, trashing or restoring, and deleting. Changes are collected and cleared once at the end of the request, after everything is saved. For a new promotion, WordPress fires its first save before its spot is stored, so the spot is looked up at the end.
- **What it clears.** The pages that spot shows on, plus any Promotions Calendar pages. If the spot sits in a template or widget, there's no single page, so it clears the whole cache.
- **Which caches.** WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, SiteGround, Nginx Helper and WP Engine. Every function name was checked against that plugin's current source (WP Rocket 3.23.5.1, LiteSpeed 7.9.1, W3TC 2.10.7, WP Super Cache 3.1.4, SiteGround 7.8.3, Nginx Helper 2.4.1) or WP Engine's docs. Each runs only if that plugin is active. Other hosts can hook the new `rapm_cache_cleared` action.
- **Rocket.net (the staging host)** publishes no developer API, so it isn't called directly. Measured on staging instead: the home page CDN copy was `HIT`, 39 minutes old. After one promotion save, home, Promotions and About Us all came back `MISS`. Rocket.net's own plugin clears its CDN on a WordPress save, and every change above goes through one. **Corrected in 1.28.1:** Rocket.net clears only its own short list (the home page). Promotions and About Us had simply not been cached yet.
- **Also fixed: a picture swapped in by the hourly Drive-folder sync could show as broken on cached pages.** The sync deleted the old picture file but changed only hidden details, never saving the promotion, so no cache was cleared and cached pages kept pointing at the deleted file. The sync now saves the promotion when its picture changes. That clears the cache through `RAPM_Cache`, and Rocket.net's plugin clears its CDN too.

**How it was checked.** No PHP runtime is available here (standing constraint).
- **PHP:** the structural checker found 0 issues in all 25 files, after catching all three planted bugs. It can't see an `isset()` on a function result, which is a fatal error in PHP: one was caught by reading and fixed before testing, and a pattern search found no others. A separate search found no syntax newer than PHP 7.
- **WordPress behavior relied on was read in core's source, not assumed:**
  - When the first submenu item is a plugin page, the top-level menu link becomes `admin.php?page=…` (`menu-header.php`). That's why the top link points at the old list's address and is forwarded instead.
  - After trash and untrash, WordPress returns to the screen you came from with `trashed`/`untrashed` and `ids` (`post.php`).
  - `wp_untrash_post_status` passes the previous status.
- **Screen behavior:** tested in a browser on a page with the same markup the PHP prints and WordPress's own admin CSS, with the server's replies faked:
  - **Switch:** off → "Hidden" and section count updates; back on; a failed save puts the switch back and says so.
  - **Menu:** opens with focus on Edit; closes on an outside click or Escape, with focus returned.
  - **Ordering:** Move later and earlier send the right order, with ended promotions kept last; "Move earlier" on the first card sends nothing; dragging onto the right half of a card drops it after that card.
  - **Search:** filters by name, includes ended matches, and shows "No promotions match" when nothing fits.
  - **Other controls:** Show/Hide ended; R&A details on/off, remembered; rename; cancelling the trash question stays on the page.
  - **Phone width (375px):** one card per row, no sideways scroll.
- **Still to confirm on staging:** the screen with real data, the switch really hiding a promotion for logged-out visitors through Rocket.net's CDN, trash and Undo, the menu, and the old list's redirect.

## 1.27.1

**Fix: our carousels and Elementor's own carousels no longer take over each other's Swiper.** Found while checking 1.27.0 on staging.
- **Root cause.** Both our Swiper 11 file and Elementor's Swiper 8 file (`assets/lib/swiper/v8/swiper.min.js`) set the same global, `window.Swiper`, so whichever runs last wins for everyone on the page. Elementor's Image Carousel asks for its Swiper 8 file directly (`get_script_depends()` returns `swiper`), and the editor preview always loads it. Elementor builds its carousels with whatever `window.Swiper` already is (`assets/dev/js/frontend/utils/swiper.js`, `createSwiperInstance()`). All checked in Elementor's source. So:
  - **Elementor's file runs last** (the editor preview's order): our carousel runs on Swiper 8. Swiper 8 loops by adding copies of the slides, which our script and `rapm-hero.css` weren't built for. Our once-a-minute re-check counted the copies, so it rebuilt the carousel every minute even when nothing had changed.
  - **Ours runs last:** Elementor's carousels run on our Swiper 11 instead of the version Elementor was built and styled for.
- **The fix, part 1: each keeps its own.** A one-line script right before our Swiper file notes what `window.Swiper` was, and one right after it saves ours as `window.RAPM_Swiper`. If the page already had a different Swiper, it puts that one back. `rapm-schedule.js` now builds carousels with `RAPM_Swiper`, falling back to `window.Swiper` only if ours wasn't saved. If the page had no Swiper before ours, `window.Swiper` is left as ours, the same as before. Elementor's carousel loads its own file anyway.
- **The fix, part 2: the re-check counts only the carousel's own slides,** not every `.swiper-slide` in it. That way, even if a carousel does end up on Swiper 8 (for example our Swiper file was blocked), it isn't rebuilt every minute.
- WP Rocket "Delay JavaScript execution" exclusions now also cover the two new one-line scripts (`RAPM_Swiper`). Not tested on a WP Rocket site.
- **How it was checked.** No PHP runtime is available here (standing constraint).
  - **PHP:** the structural PHP checker found 0 issues in all 22 plugin files, after catching all three planted bugs.
  - **Behavior:** tested in a browser with real Swiper 11.2.10, Elementor's real Swiper 8.4.5 file (from Elementor's wordpress.org trunk), and a stand-in for an Elementor Image Carousel that starts the way Elementor's does. The two one-line scripts were copied word for word out of `class-rapm-assets.php`.

    | Case | 1.27.0 | 1.27.1 |
    |---|---|---|
    | Elementor's Swiper 8 runs after ours | Ours on Swiper 8 (2 slide copies), rebuilt on a quiet re-check; Elementor's on 8 | Ours on 11, Elementor's on 8, not rebuilt |
    | Elementor's Swiper 8 runs before ours | Ours on 11; **Elementor's on 11** | Ours on 11, Elementor's on 8 |
    | Our Swiper file missing, only Elementor's | On 8, rebuilt on a quiet re-check | On 8, not rebuilt; a promotion starting mid-visit lands in the right spot |
    | No Elementor | Unchanged | Unchanged |

    The full 1.27.0 cap sequence (7 steps) and the slide-order test came out the same on 1.27.1.
  - **Known gap:** if an optimizer delays our Swiper file but not the small scripts around it, nothing is saved. Both carousels then share whichever Swiper loads last, the same as before 1.27.1 (tested: no worse, no better).
  - **Confirmed on staging (real WordPress, 2026-10-05),** after updating from 1.27.0 through the normal GitHub update:
    - Home page: the hero shows its 3 slides on our saved Swiper 11 (`RAPM_Swiper` is set, so the script after our Swiper file ran).
    - A draft page with our Promo Carousel widget (Show at most 2) and a real Elementor **Image Carousel** (3 pictures):
      - **Elementor editor:** ours ran on Swiper 11 with 2 slides and no copies, and Elementor's on Swiper 8. After 67 seconds ours was still the same carousel, still on slide 2. Under 1.27.0 it had been rebuilt by then.
      - **Front end:** both Swiper files loaded, ours first and then Elementor's, the order that broke 1.27.0. Ours ran on Swiper 11 (The Fall Living Room Event, then Gather Around for Less). Elementor's ran on Swiper 8, showed its pictures and moved 1 → 2 → 3 → 1. Both checked by screenshot.
    - The test page was moved to the trash, not deleted.

## 1.27.0

**New: a carousel can show only the first few promotions ("Show at most").** Built for Tyner. A carousel can now be set to show, for example, only 3 promotions even when more are live. It shows the first ones that are live right now, starting from the top of the Sliders list.
- **Two ways to set it.** A `max` shortcode attribute, for example `[rapm_hero placement="home" max="3"]` (also `[rapm_fold_banner]`), and a matching **Show at most** number box on the Promo Carousel Elementor widget (0 to 20). The default is 0, meaning no limit, so existing carousels don't change.
- **The limit is applied in the visitor's browser, after the schedule check** (`rapm-schedule.js`), like the schedule itself. So it works behind a full-page cache, and a promotion that hasn't started yet or has already ended never takes one of the spots. When one ends, the next one in line moves up on its own, within a minute, without a reload.
- The once-a-minute re-check compares against the same capped list, so a capped carousel isn't rebuilt (and sent back to slide one) every minute when nothing changed.
- **Help & FAQ:** new answer for "Why does the carousel show fewer promotions than the Sliders page says are live?" The Sliders page counts every live promotion in a group. It can't know a page's cap, since the cap is set on each page, not on the group.
- **Settings > Shortcodes** and the readme list the new `max` attribute.

**Fix: when a promotion started or ended while a page was open, the carousel could play its slides in the wrong order.** Found while testing the cap. The bug was already in 1.26.1 and earlier; the cap would have triggered it much more often.
- **Root cause.** On a change, the carousel put the new set of slides in place and *then* shut the old Swiper carousel down. In loop mode, Swiper's `destroy()` re-sorts the slides in the wrapper by the numbers it gave them (`loopDestroy()` in Swiper 11.2.10, reads `data-swiper-slide-index`). By then the wrapper held the new set, and a slide the old carousel never had has no number, so Swiper read it as slide 0 and moved it.
- **The fix.** The old carousel is now shut down first, while the wrapper still holds exactly its own slides, and the new set goes in after.
- **Proven against the old code.** Same test page, real Swiper 11.2.10 (the version `swiper@11` resolves to on the CDN today), slides A, B, D, E live and C starting while the page is open. **1.26.1 played A, C, B, D, E. 1.27.0 played A, B, C, D, E.**

**How it was checked.** No PHP runtime is available here (standing constraint).
- **PHP:** the structural PHP checker found 0 issues in all 22 plugin files. It was re-calibrated first: it caught all three planted bugs (unescaped apostrophe, missing brace, missing `endforeach`). The Elementor NUMBER control's `min` / `max` / `step` settings were checked against Elementor's source (`includes/controls/number.php`).
- **Behavior:** tested in a browser with real Swiper 11.2.10, the real `rapm-hero.css`, and the same start-up script the shortcode prints. Cap of 3, with B already ended:

  | Step | Shown |
  |---|---|
  | Start | A, C, D |
  | Two re-checks, nothing changed | A, C, D (same carousel, not rebuilt) |
  | A ends | C, D, E |
  | B starts again | B, C, D |
  | C and D end | B, E |
  | Everything ends | carousel hidden |
  | E comes back | E (loop off for one slide) |

  Paging through the capped A, C, D carousel wraps A → C → D → A both ways, and never reaches B or E. A cap of 1 shows one slide with loop off; a cap of 0 shows all five.
- **Confirmed on staging (real WordPress, 2026-10-05),** after updating from 1.26.1 through the normal GitHub update:
  - The home page hero still shows all 3 slides (no cap set, so nothing changed).
  - A draft page with `[rapm_hero placement="home" max="2"]` showed 2 slides and 2 dots: The Fall Living Room Event, then Gather Around for Less (Dining Days), the first two in Sliders order. All 3 were still in the page HTML; Mattress Month was left out in the browser.
  - The real Elementor editor shows **Show at most** (0–20, default 0, new wording). Setting it to 2 previewed 2 slides. Saving wrote `[rapm_hero placement="home" max="2"]` as the page's plain content. On the front end, the widget and the shortcode on the same page each showed the same 2 slides.
  - Help & FAQ and Settings > Shortcodes show the new text. The test page was moved to the trash, not deleted.
- **Found on staging, not fixed in this version (fixed in 1.27.1): Elementor's own Swiper 8 can take over our carousel.** The Elementor editor preview loads both our Swiper 11 and Elementor's `assets/lib/swiper/v8/swiper.min.js`. Elementor's loads second, so `window.Swiper` is Swiper 8, which loops by adding copies of the slides (`swiper-slide-duplicate`). The once-a-minute re-check counts every `.swiper-slide`, copies included (4 against 2 real ones), so it rebuilds the carousel every minute even when nothing changed. Seen in the real editor: a different carousel after 65 seconds. This dates back to the re-check itself, not to 1.27.0. On the front end it can only happen on a page that also has an Elementor widget built on Swiper (such as Image Carousel). No staging page has one, so that case is untested. WoodMart's own Swiper doesn't clash: it uses its own name (`wdSwiper`), and the Promotions page carousel ran on ours.

## 1.26.1

**Fix: pages with no promotions no longer load every Promo Manager file.** Found while checking 1.26.0 on staging. The front page loaded all four stylesheets plus Swiper (about 150 KB of script) and the schedule script, even though it showed no promotion at all.
- **Root cause.** Every display mode's early `<head>` check had a fallback: if any classic Text widget was active anywhere on the site, it loaded its files on every page. The staging site has four, in the WoodMart footer (`text-46`, `text-13`, `text-14`, `text-15`). WoodMart's demo footers use Text widgets, so many WoodMart sites are likely the same.
- That fallback also hid the 1.26.0 bug. On a site with any Text widget, promotions outside the page's own content happened to work, because everything was already loaded. That's why the demos never showed the problem.
- **The fix.** The fallback is removed from all four display modes. It's no longer needed: since 1.26.0, a shortcode loads its own files when it renders. WordPress core runs shortcodes in both classic Text widgets and block widgets (`widget_text_content` and `widget_block_content` → `do_shortcode`, confirmed in core's `default-filters.php`), so a promotion in a sidebar still loads what it needs. Its files print in the footer instead of the head. That's harmless, because every display stays hidden until its script runs.
- The `rapm_force_load_ids` filter for the hero carousel is unchanged.
- Checked with the structural PHP checker (0 issues in 23 files). Final confirmation is on staging. The front page should now load only the hero's files. A promotion placed outside a page's own content (for example in a reusable block pattern) should still show. With the fallback gone, staging can now actually catch that bug if it ever comes back.

## 1.26.0

**Fix: promotions now show anywhere they're placed, including inside Elementor popups, Theme Builder headers and footers, and product and category pages.** Found while planning VFM's sign-up and offer popups. A promotion placed in an Elementor popup would have stayed invisible.
- **Root cause.** Each display mode decided in the page `<head>` whether to load its files, and only looked for its shortcode in the current page's own content. An Elementor popup is a separate template, and so are Theme Builder headers and footers, so their shortcodes were never seen. Product and category pages failed the same way. This plugin's own Promo Carousel widget was probably affected too. Elementor saves a widget's rendered HTML as the page's plain content, not the shortcode, so the check couldn't find it. Every display starts hidden and only `rapm-schedule.js` reveals it, so without the files it stayed hidden. The only thing that sometimes masked this was a classic Text or Shortcode widget active anywhere on the site, which loaded the files on every page.
- **The fix.** A new `RAPM_Assets` class registers every file on every front-end request (nothing prints until something asks for it). Each shortcode now asks for its files the moment it renders. WordPress prints anything requested after `<head>` in the footer. If the footer has already printed, the shortcode prints the tags itself, right before its own markup. The early `<head>` check stays as an optimization for the common case.
- **Removed a full extra page render on every request.** The old check also ran `apply_filters( 'the_content' )` during script loading. That rendered the whole page a second time and could never find a shortcode in already-rendered output. Elementor's own Shortcode widget saves the raw shortcode as the page's plain content (confirmed in Elementor's source, `includes/widgets/shortcode.php`), so the plain `has_shortcode()` check already covers it.
- **Promo Carousel widget:** now declares its files to Elementor (`get_style_depends()` / `get_script_depends()`). Like Elementor's Shortcode widget, it now saves the shortcode itself as the page's plain content (`render_plain_content()`), so the head check finds it and the carousel still shows if Elementor is ever switched off. Method names were checked against Elementor's source (`Widget_Base`, `Element_Base`).
- **Carousels inside popups now size to the popup.** A carousel in a popup starts while the popup is hidden, so Swiper measured a zero-width box. After opening, the slides couldn't move or be swiped. `rapm-schedule.js` now re-measures every carousel when Elementor opens a popup. It listens for the `elementor/popup/show` event both as a native window event (Elementor Pro 3.9 and later) and as a jQuery event (older versions). Swiper's own `observer` / `observeParents` settings are also on, as a second path.
- **Each display's start-up script now waits for a late file.** If the shared script arrives after the display's own small inline script, it waits for the page `load` event once and tries again instead of giving up.
- **WP Rocket:** "Delay JavaScript execution" exclusions now also cover those small inline start-up scripts (`RAPM_Schedule`, `rapm-cal-`), not only the two files. Before, the inline scripts could be held until the visitor scrolled or clicked. Not tested on a WP Rocket site.
- Small side effect: a page with only a Coupon Book now also loads Swiper's stylesheet (about 16 KB, from the same CDN), because the coupon cards share the hero stylesheet, which depends on it. That keeps the style order right when a carousel and coupon cards share a page.
- **How it was checked.** No PHP runtime is available here (standing constraint).
  - **PHP:** every file passed a structural PHP checker (strings, comments, heredocs, PHP/HTML switching, brackets, `foreach … endforeach`). The checker was calibrated first: zero issues on the shipped 1.25.0 files, and it caught all three planted bugs (the 1.24.1-style unescaped apostrophe, a missing brace, a missing `endforeach`).
  - **JavaScript:** `rapm-schedule.js` and all four inline start-up scripts compile in Chrome's own JS engine.
  - **Behavior:** tested in Chrome against the real Swiper 11.2.10 bundle and the real `rapm-hero.css`, old script against new.
    - Carousel inside a popup that starts hidden: 1.25.0 still had no measured size after the popup opened, so sliding was broken. 1.26.0 measured 600px and moved to slide two correctly.
    - Script arriving late: the 1.25.0 start-up code left the carousel hidden. 1.26.0 started it.
  - **Gap:** Swiper's observer path couldn't be judged in this test, because the test tab sat in a background window where Chrome pauses animation frames. The popup-event path doesn't depend on them.
  - **Still to confirm on staging:** a Coupon card and a hero inside a real Elementor Pro popup, the Promo Carousel widget on a page with no other promotion, and a promotion on a product or category template.

## 1.25.0

**New: this site now checks in once a day with R&A's Plugin Monitor (help.ramarketing.com).** Until now the monitor couldn't see Promo Manager on its own. On sites that also ran Woo Native Dimensions it listed Promo Manager as "too old to report", and sites running only Promo Manager didn't appear at all. So an expired GitHub token or a stuck update went unnoticed.
- Bundles the shared `lib/ra-monitor-client/ra-monitor-client.php` (RA Monitor Client 1.1.0, byte-identical to the copy Woo Native Dimensions has shipped since its v1.25) and registers it in `RAPM_Updater::init()`, right after the update checker is built, passing the checker, whether a token is set, and the channel.
- Each check-in lists this site's R&A plugins, their versions, on/off state, update channel, and whether the last update check actually reached GitHub (with GitHub's error when it didn't). It sends the site's address and name plus WordPress/WooCommerce/PHP versions. Never tokens, customer data, orders or promotion content.
- Checks in within a minute or two of an install, update or activation, then daily. The Plugins screen shows the last check-in's result under Promo Manager, with a "Check in now" link.
- Turn off on a site with `define( 'RA_MONITOR_DISABLE', true );` in wp-config.php.
- **Not yet run in real PHP** (standing constraint). Final confirmation is on staging: update, then check that Promo Manager's row on the Plugins screen says the check-in was delivered, and that the staging site shows Promo Manager on the monitor.

## 1.24.2

**Fix: pasting a Drive folder into the Mobile image box blocked saving when the folder had no tall picture yet.** Phil hit this setting up the England demo. The folder held only the wide desktop picture, so the Mobile slot found nothing its shape, and the save bounced back to the form with "None of the newest pictures in that Google Drive folder are the right shape for Hero — Mobile". A mobile picture has always been optional for normal uploads (phones fall back to the desktop picture), so a folder with no tall picture yet shouldn't be treated as an error either.
- **Mobile folder links now save even with no tall picture in the folder.** Phones show the desktop picture, and the hourly sync (or "Check link now") picks up a tall picture automatically as soon as one is added. The hourly sync treats "no tall picture yet" as a normal state, not a failure: no error and no admin email. The form shows a plain note instead of a red error: "There's no tall picture in this folder yet, so phones show the desktop picture…". The live preview shows the same note, in normal text rather than red.
- Only "folder has no picture of that shape" / "folder is empty" counts as waiting (`RAPM_Link_Source::is_waiting_for_picture()`). Real problems, like the folder not being shared or Drive unreachable, still show as errors, and the **desktop** picture is still required.
- Checked with the PHP-aware syntax checker (zero issues across every file) and Chrome's JS parser for the preview script. Not yet run in real PHP; staging is the confirmation: paste the England folder into both boxes and save.

## 1.24.1

**Fix: the Add/Edit Asset live preview showed a broken image whenever "Use a link" had a Google Drive link.** Phil pasted the England folder link and got an empty dark box. The preview set the pasted text directly as the picture's address. That only ever worked for direct image links: a Drive folder link, and even a normal Drive file share link (`…/file/d/…/view`), is a web page rather than an image. This is preview-only; saving always fetched the link server-side correctly.
- New `rapm_preview_link` AJAX check. As you paste (after a short pause), the server resolves the link exactly the way saving will. For a folder, that's the newest right-shape picture. It returns that picture for the preview, plus a plain-language line under the box: "Using the newest picture in the folder that fits: england-hero-fall-sale.webp", or the real reason it can't be used (folder not shared, empty, no picture of the right shape, or the picture is a different shape and will be turned down on save). Previewing never marks folder files as "seen", so it can't affect which picture a later sync picks.
- Refactor: the "find and download what this link points to" step is now `RAPM_Link_Source::resolve_to_tmp()`, shared by saving, the hourly sync and the preview, so the preview can't disagree with what actually gets saved.
- **Caught before shipping:** an unescaped apostrophe in a new message (`'Couldn't check that link.'`) was a PHP syntax error. Since this file loads on every request, that would have taken down the whole site after updating, not just this form. Found by checking the new strings. Every plugin PHP file (and the 1.24.0 already on staging) was then run through a PHP-aware syntax checker (strings, comments, heredocs and `<?php ?>` blocks). It was proven to flag this exact bug before being trusted, and it reports zero issues. The new JavaScript was parsed by Chrome's own JS engine.
- **Still not run in real PHP** (standing constraint). Final confirmation is on staging: paste the folder link and the England picture should appear in the preview with the "Using…" line.

## 1.24.0

**Feature: "Use a link" now accepts a Google Drive folder, so clients can change a promotion by just dropping a new picture into a folder, under any file name.** Phil's request, while setting up a link-swap demo: file links only swap when the new picture replaces the old file under the same link, which in Drive means re-uploading with the exact same file name and choosing "Replace". That's not realistic to expect from a client every time.
- Paste a Drive folder link (`drive.google.com/drive/folders/…`) into either image's link box. **The newest picture in the folder that fits that slot's shape is used.** "Newest" is decided in two steps: a file the slot has never seen before always wins, so a new upload takes over whatever it's named or dated. After that, files are ordered by Drive's last-modified date. The seen-file list is stored per slot (`_rapm_image_{desktop|mobile}_folder_seen`).
- **One folder can feed both slots.** Wrong-shape pictures are skipped rather than treated as errors, so the same folder pasted into Desktop and Mobile gives the wide picture to Desktop and the tall one to Mobile. Every picture still goes through the same size/format checks as a normal upload. At most 5 candidates are downloaded per check, so a folder full of old pictures stays cheap to sync hourly.
- **No Google login or API key needed** (Phil's choice over the official Drive API, which would need a key pasted into every site). The folder is read from Drive's own embeddable folder view (`embeddedfolderview?id=…`). That's a public page, not an official API, so Google could change it. If that happens, the existing behavior applies: the last good picture stays up, the error shows on the asset, and the admin is emailed.
- **New "Check link now" button** on the Add/Edit Asset screen for any linked asset. It re-checks immediately instead of waiting for the hourly sync, for showing a swap live. It shows "Updated" or "Checked, nothing changed".
- Form help text, a new Help & FAQ entry ("Can I use a Google Drive folder instead of one picture?") and the readme explain it in plain language.
- **Verified against real Drive, not a mock.** A shared test folder (Phil's Drive, "England - Hero Slider") was fetched with no login, the same position a WordPress server is in. The listing returned the file's ID, name and date. The file downloaded byte-for-byte identical to what was uploaded. The picking logic (ported to a script) chose the 1920×600 picture for Desktop, correctly found no tall picture for Mobile, and put a never-seen file ahead of an already-seen one. **One bug was caught by that test before shipping:** the page check first looked for a marker (`flip-view`) that Drive's real response doesn't contain, which would have rejected every folder. It now checks for `flip-embedded`, which is present in real folder listings and absent from Drive's 404/sign-in pages.
- **Not yet run in PHP** (standing constraint). The full end-to-end swap happens on staging after this updates: point an asset at the folder, drop in a new picture, click "Check link now".

## 1.23.2

**Three fixes found while building the demo pages on the staging site (gbh0yydkkp.wpdns.site), each root-caused on the real Woodmart theme rather than a mock.**

**1. Hero: on phones, the headline and button sat below the fold.** Phil caught this on the new Home page. A slide with a dedicated mobile picture takes that picture's 9:16 shape, so at full phone width it's about as tall as the whole screen (≈667px at 375px wide). With the site header above it, the bottom-anchored copy started around 713px down the page, off-screen on most phones.
- Mobile slides are now capped at 75% of the visible screen height (`max-height: 75svh`, with `75vh` first as a fallback). The picture is pinned to the slide box and cover-cropped, so it trims evenly top and bottom. Mobile pictures carry no baked-in text, so nothing important is lost.
- **Found along the way:** the theme's own image rules outranked the plugin's `.rapm-slide-img { height: 100% }`, so the picture kept its natural height instead of filling the slide. It was invisible until now because the two heights happened to match. Hero image rules are now scoped under `.rapm-hero .rapm-slide`. The "shrink to fit" rule for slides with no mobile picture (1.21.2) got the same bump so it still wins.
- Verified on the live staging Home page in 390px-wide frames at 667, 740 and 844px tall. Before: the button was off-screen at 667 and 740. After: on screen at all three, with the picture exactly filling the slide. The desktop hero (1270×397) and the mobile Fold Banner (375×139) are unchanged.

**2. Promotions Calendar: automatic brand-color matching never worked on any site.** The plugin declared `--rapm-calendar-accent: var(--e-global-color-accent, …)` on `:root`, but Elementor defines its `--e-global-color-*` variables on the kit class it adds to `<body>`. A var() on `:root` can't see them, so every site fell back to the default orange. The variable is now declared on `body`.
- **The auto-pick order also changed, from "Accent, then Primary" to "Primary, then Accent, then Secondary",** skipping any color that white text doesn't meet WCAG AA contrast (4.5:1) on (`RAPM_Elementor::auto_accent_id()`). Many kits, including Indian River's, use Accent for a light neutral (#ECEDED, 1.17:1), which would have made the bars' white text unreadable. No existing site could depend on the old order, since detection never worked.
- The Settings > Brand Color description, Help & FAQ answer and readme now describe the real behavior. A manual Brand Color still always wins.
- Verified on the live staging Promotions page: with the variable on `body`, the bars resolve to the kit's Primary (#971819). With the old `:root` declaration they stayed orange. The pick order was checked against three real palettes: Indian River's (picks Primary), ABC's previous one (Primary), and Elementor's out-of-the-box defaults (Secondary, the only readable one).

**3. Marquee: prev/next arrows showed even when every tile fit on one page.** The script already set `hidden` on the arrows correctly, but the arrow's own `display: flex` (and a theme's button styling) outranks the browser's built-in `[hidden]` rule. Added `.rapm-marquee-arrow[hidden] { display: none !important; }`. Verified on the live Living Room page: 4 tiles with 4 per row, and both arrows are now hidden.

- **No PHP runtime available here** (standing constraint). The CSS fixes were verified by injecting the exact shipped rules into the real published staging pages. The PHP changes (the contrast helper, `body` scoping, settings wording) were checked by porting the contrast math to a script and running a brace/paren balance check. Final confirmation is on staging after this updates.

## 1.23.1

**Test release only, no functional change.** This is a version-number bump, pushed to the staging repo only, to prove the new GitHub self-update from 1.23.0 really delivers an "Update Available" notice and installs on a real WordPress site. Room Planner followed the same precedent with v7.19.1: the mechanism isn't trusted just because the code looks right. It needs a live, watched test first.

## 1.23.0

**Infrastructure: RA Promo Manager can now update itself from GitHub, the same way Universal Room Planner does.** Brought across from Room Planner's proven system (v7.17.0–v7.20.0 there) unchanged, rather than solving the same problem a second way. It's not a change to anything client staff see day to day.
- Bundles the free, MIT-licensed `YahnisElsts/plugin-update-checker` (v5p7, the exact copy Room Planner already runs live, unmodified) at `lib/plugin-update-checker/`, wired up in the new `includes/class-rapm-updater.php`.
- **Two separate private repos, not two branches.** Production is `ra-license/rapm-promo-manager` and staging is `ra-license/rapm-promo-manager-staging`. This matches Room Planner v7.19.0: the library prefers tags/releases over the configured branch, so only separate repos reliably keep an untested version off live sites.
- New **Auto-Update Settings** section on Promo Manager > Settings, with an Update Channel dropdown (defaults to Production, the safe choice) and a GitHub Update Key field for sites managed only through wp-admin. The wp-config.php constants `RAPM_UPDATE_CHANNEL` and `RAPM_GITHUB_UPDATE_TOKEN` take priority when set. Both values are stored as their own options, outside `rapm_settings`, so that array's reset-to-defaults sanitizer can never wipe a site's key.
- Auto-updates are on by default for this plugin (`auto_update_plugin` filter, same as Room Planner v7.20.0). Promoting a version to the production repo is the real gate, and once that's done every live site should pick it up with zero clicks.
- **The token must cover BOTH repos.** A token scoped to only one of them fails silently on the other channel. That's exactly the bug Room Planner hit in v7.19.1.
- **Not yet verified live.** No PHP runtime is available here (standing constraint), so this was checked by matching Room Planner's working code line for line plus a brace/paren balance check. A site only starts checking GitHub once it has this version installed manually one last time and a key entered. Until then, it behaves exactly as before.

## 1.22.2

**Fix: on Promo Manager > All Assets, the post title and the default "Edit" row action silently opened a different, useless screen than the plugin's own "Edit" button in the same row.** Surfaced by the user noticing a calendar promotion had no destination link and asking why — investigation traced it to this: `rapm_asset` deliberately supports only `title` (no image/editor meta boxes — real fields live entirely on this plugin's own custom form, per `class-rapm-post-types.php`), but WordPress's *default* title link and "Edit" row action always point at the native `post.php?action=edit` screen regardless. That screen shows nothing but a bare title field — no image, headline, destination, or schedule — so anyone using the standard, more prominent "Edit" entry point (rather than the small button in the dedicated "Edit" column added for exactly this reason) landed somewhere that looks like editing but silently can't touch any real field.
- This plugin already had the identical fix for the "Add New" side (`maybe_redirect_native_add_new()`, shipped early on) — the "Edit" side of the same problem had just never been covered.
- Fixed at the source with a `get_edit_post_link` filter (`RAPM_Admin_List::filter_edit_post_link()`) so the title link and the default "Edit" row action both resolve directly to the real Add/Edit Asset screen — no redirect bounce needed. Added `maybe_redirect_native_edit()` (`admin_init`, mirroring the existing "Add New" redirect) as a safety net for anyone with the native edit URL already bookmarked or typed directly.
- Also removed the native "Quick Edit" row action for this post type — same category of trap (title/slug/date/status only, none of the plugin's real fields), and now the only remaining native shortcut that could look like a working edit path while not being one.
- **No PHP execution environment available to run this live** (standing constraint for this project) — verified by tracing `get_edit_post_link()`'s actual call sites in WordPress core (`WP_Posts_List_Table::handle_row_actions()` and the title column) to confirm both consume this filter, plus brace/paren balance on every touched file. Real end-to-end confirmation happens once this is installed on the live site — flag if the title link and the dedicated "Edit" button don't yet match after updating.

## 1.22.1

**Fix: the calendar shipped in 1.22.0 rendered broken on a real live site — nearly every day cell solid-filled in the site's own brand purple — even though it passed every local check.** All of that session's verification used a mocked example page with fabricated colors; it never touched a real WordPress theme's CSS, which is exactly what exposed the gap. Confirmed via a real screenshot from the client's actual staging site (`/promo-calendar/`), then root-caused directly rather than guessed at.
- **Root cause**: every day cell renders as a real `<button>` (for keyboard accessibility), and the calendar's own CSS only reset `background`/`border` on it. A theme's own global `button` styling (very common on WooCommerce-based themes — solid brand-color background, white text, bold/uppercase) has no reason to expect a plugin to coexist with it, and simply painted over the reset.
- **Two distinct leaks, found by rebuilding a local test page with a deliberately hostile mock theme** (`button { background-color: ... !important }`, matching the real site's apparent styling) and actually clicking/hovering through the result rather than reading the CSS and assuming it would hold:
  1. The *base* (non-interactive) state leaked because the theme's selector could tie or beat the calendar's single-class selector on specificity.
  2. Even after fixing that, *hovering or clicking* a day cell still filled it purple — a theme's `button:hover`/`:focus`/`:active` rule carries a type+pseudo-class specificity that a bare single class doesn't automatically beat, `!important` on both sides notwithstanding.
- Fixed by scoping every interactive selector under `.rapm-calendar` (raising specificity above a plain `button`/`.theme-class button` rule) and adding explicit, `!important`-guarded `:hover`/`:focus`/`:active` overrides alongside the base state — applied consistently to the day-number cells, the prev/next nav buttons, and the "+N more" button.
- **Also addressed direct client feedback that the redesigned calendar "should be a bit larger and look more like a Google Calendar"**: `max-width` grew from 480px to 900px (still responsive down to mobile widths), and each day now renders inside an actual bordered box — a full-height background element per day column, painted behind the day number and any promotion bars sharing that column — instead of numbers and bars floating with no grid lines at all. Sizing, spacing, and type were also tuned closer to Google Calendar's own month view (larger title, circular nav buttons with a hover state, more generous cell padding).
- Verified against a from-scratch local harness that reproduces the exact shipped markup/JS and layers a hostile mock theme stylesheet in front of it — confirmed white cells hold under hover, focus, and click, at both desktop and mobile widths, with the existing day-detail/"+more" click-through behavior unaffected.

## 1.22.0

**Feature: the Promotions Calendar now shows what's actually happening, not just how many things are.** Direct follow-up to a real screenshot of the live calendar — a plain number badge per day, with the promotion's actual name hidden behind a click, "looks basic and unengaging... even Google Calendar looks nicer than this." Fair. Rebuilt the month grid around named event bars stretching across each promotion's real date range, matching how Google Calendar (and most real calendar apps) render a month view — the same idea previous releases already applied everywhere else in this plugin (live previews, live counts, live indicators) instead of describing state in the abstract.
- Each week is now its own mini-layout: a row of day numbers, with promotion bars stacked directly beneath — one bar per promotion, spanning exactly the columns (days) it's active on that week, computed via a greedy lane-assignment algorithm so overlapping promotions stack into separate rows instead of colliding.
- A bar is rounded only at a promotion's true start/end; a promotion running longer than a week continues as its own bar on the next row with a square, "keeps going" edge instead of a rounded cap.
- Caps at 3 stacked bars per week — a 4th+ overlapping promotion collapses into a small "+N more" link, computed independently per week (the same promotion can render as a full bar in a quiet week and get "+more"'d in a busier one, exactly like a real calendar app).
- "Today" is now a filled, colored circle around the date (matching the now-brand-matched accent color from 1.21.0), not just an underline.
- Clicking any day, or a "+more" link, still opens the existing detail list below the calendar — now the actual overflow escape hatch rather than the primary way to see anything at all.
- **Two real bugs caught before shipping, both by actually clicking through the rebuilt calendar rather than trusting the render logic on sight**: (1) the first "+N more" implementation pointed at "whatever happens to be active on the first day of the week" instead of the promotion(s) that actually overflowed — fixed to track and show the real overflow list. (2) That fix's first pass then shared one `overflowItems` array across every week's button (a classic `var`-in-a-loop closure bug — every button closed over the same, function-scoped variable, so clicking any of them showed only the *last* week's overflow), caught by clicking a "+more" button and seeing an empty list; fixed with the same per-iteration IIFE-capture pattern already used correctly elsewhere in this file for each day's click handler.
- Verified with a realistic overlapping data set (a 16-day promotion crossing three week boundaries, two shorter overlapping promotions, and a forced 4th-overlap to trigger "+more") — confirmed every bar's start/end rounding, lane placement, and the "+more" click all resolved to the mathematically correct promotion in every case.

## 1.21.2

**Fix: a Hero/Fold Banner slide with no dedicated mobile picture showed a large black box on phones, instead of the desktop image simply resized to fit.** Confirmed via a real screenshot from a live client site — the fallback introduced in 1.14.0 (shrink the desktop image to fit on phones rather than crop it) shrank the *image* correctly, but never resized the *carousel itself*, which stayed locked to the tall mobile-slot shape regardless of which image was actually showing. A wide desktop image shrunk to fit inside that tall box left most of the box empty — filled by the carousel's own black background.
- Each slide's own height is now driven by its own `aspect-ratio`: the desktop shape by default, switching to the mobile shape on phones only for slides that actually have a dedicated mobile picture (a new `.has-mobile-img` class, set per-slide). A slide using the fallback keeps the desktop shape on phones too, so the shrink-to-fit image fills its box edge-to-edge with nothing left over.
- Swiper's `autoHeight` option is now enabled (`assets/js/rapm-schedule.js`) so the visible carousel resizes to match whichever slide is actually active, since a single carousel can mix slides that do and don't have a dedicated mobile picture.
- **Verified against the real Swiper 11 bundle** (the exact version this plugin loads from its CDN), not just reasoned about — and this genuinely needed to be verified rather than assumed: an early check via `getComputedStyle()`/`getBoundingClientRect()` gave contradictory, spec-defying readings (an element's own explicit inline height appearing to be ignored) that would have wrongly suggested the fix didn't work. Actual pixel screenshots settled it — the black box is gone, and a slide with a real mobile picture is fully unaffected — confirming the DOM-measurement APIs, not the fix, were unreliable in that specific check.

## 1.21.1

**Fix: the Training Guide's calendar-indicator mockup (added in 1.20.0) rendered broken — a numbered chip stacked above its text instead of sitting beside it.** Found while updating the standalone client-facing guide artifact for a broader request and actually re-rendering the real shipped screen to check it, rather than trusting an earlier isolated test harness that happened to define more CSS than the real file did. Root cause: `.mk-note` was never actually defined as a CSS class in `class-rapm-training-guide.php` — only `.mk-warn` was — and the chip (`.rapm-tg-chip`) is `display:flex`, which makes it a block-level box; without a flex container around it and the text, the two stack instead of sitting side by side. Added the missing `.rapm-tg-mockup .mk-note` rule (mirroring `.mk-warn`, plus `display:flex; align-items:center; gap:7px`) and wrapped the trailing text in its own `<span>` so the flex layout has two proper children. No functional/data impact — this is a single admin-only illustration, not the live front end.

## 1.21.0

**Feature: the Promotions Calendar now matches a site's actual brand color, instead of a fixed hardcoded accent.** Direct follow-up after the user asked to preview the calendar and "make sure it... is pulling the brand colors from the website." It wasn't — a full audit of every display mode's CSS found the Calendar was the *only* place in the plugin with a genuinely hardcoded, non-brand-aware accent color (`#b5651d`, used for the has-promotion highlight, the day count, and — previously unstyled — the day-detail links, which fell back to plain browser-default blue). Hero's CTA button and Marquee's arrows were checked too and left alone: they're deliberately neutral white/black or use `color: inherit` already, for photo-legibility reasons, not an oversight.
- New **Elementor Global Colors bridge** (`RAPM_Elementor::global_colors()` / `color_value_map()`), the exact structural twin of the existing Global Fonts bridge (`system_colors`/`custom_colors` kit settings instead of `system_typography`/`custom_typography`).
- New `RAPM_Elementor::resolve_accent_color_css()` — the single function every accent-colored element in the plugin should call, in priority order: (1) an explicit hex set under **Promo Manager > Settings > Brand Color**, (2) Elementor's Global "Accent" color if set, else "Primary," referenced live via `var(--e-global-color-*, ...)` so it keeps tracking Elementor rather than freezing a resolved value, (3) the original neutral default, on any site with neither.
- New **Brand Color** section on the Settings screen: a single color field plus a "Clear (use automatic)" button, with a live-computed hint showing exactly which Elementor color (name + hex) is currently being auto-detected, if any.
- `assets/css/rapm-calendar.css` now reads `var(--rapm-calendar-accent, #b5651d)` everywhere it previously hardcoded the accent, including a `color-mix()`-derived tint for the has-promotion background (so it can never drift out of sync with the border color it's paired with) and the previously-unstyled day-detail links.
- Verified in a browser against the real shipped CSS: swapping the resolved `--rapm-calendar-accent` value updates the border, background tint, day-count, and day-detail links consistently and immediately; the Settings screen's enable/clear/submit logic (untouched → stays automatic, Clear → explicitly stays automatic, a color actually picked → sent and saved) verified for all three states.

## 1.20.0

**Feature: the Promotions Calendar is now discoverable, and every promotion tells you directly whether it's on it.** Direct follow-up after the user pointed out the calendar shortcode kept getting glossed over across this project's own documentation — a fair critique: unlike every other display mode, it has no menu item, no Kind of its own, and nothing in the admin UI ever surfaces it. The feature itself was complete; only its visibility was missing.
- **New "Promotions Calendar" banner on the Sliders dashboard** (`Promo Manager > Sliders`) — shows the `[rapm_promotions_calendar]` shortcode, the optional `placement=`/`kind=` scoping attributes, and a live count (`RAPM_Calendar::count_scheduled()`) of how many published promotions currently qualify. The calendar still has no dedicated settings screen, on purpose — there's nothing to configure, so the banner just surfaces what already exists rather than adding a redundant management page.
- **New live indicator on Step 4 (Review & Schedule)** of the Add/Edit Asset screen, right below the start/stop date fields: a note that says outright whether *this* promotion will show up on the Promotions Calendar or not, and updates instantly as the two date fields are filled in or cleared. Doubles as a safety net against the specific mistake that prompted this — someone assuming a promotion is on the calendar when it's actually missing a start or end date; the note makes that state impossible to miss rather than a silent gap you'd only notice by checking the calendar itself.
- Deliberately **not** a blocking validation — a promotion with no dates (evergreen, "runs until I turn it off") is a fully valid, common, and already-documented default; this only ever informs, never prevents saving.
- Updated Help & FAQ (two entries: the existing calendar one now points at the Sliders banner, plus a new one for the Step 4 indicator) and the in-plugin Training Guide (a new "Showing a Calendar of Your Promotions" section — it did not previously exist there at all, unlike the standalone reference document, which is the discoverability gap this release actually closes).
- Verified in a browser against the real shipped markup/CSS/JS: the Step 4 indicator switches between its two states correctly and instantly as both date fields are set/cleared (verified via both fields independently and together); the Sliders banner lays out correctly side-by-side at realistic admin width and wraps sensibly narrower.

## 1.19.0

**Feature: a plain-language Training Guide, built into the plugin itself, for someone using it for the very first time.** Direct request for a "service and feature guide along with fool-proof instructions for someone with a 5th grade reading and comprehension level" to onboard new employees — first delivered as a standalone Artifact page, then brought inside the plugin on request so it's reachable from the WordPress backend without a separate link to find or keep updated.

- New **Promo Manager > Training Guide** admin screen (`RAPM_Training_Guide`), open to anyone who can add assets (`edit_posts`). Distinct from Help & FAQ on purpose: Help & FAQ is short Q&A for someone who already knows the tool and hit a specific snag; the Training Guide is a full walkthrough for someone who has never opened the tool before, with a table of contents, illustrated mockups of all 4 wizard steps built field-for-field from the real Add/Edit Asset screen (not live screenshots, so they're exact but need updating by hand if a field ever changes), a picture-size cheat sheet, good/bad shape examples, a common-problems section, and a printable quick checklist.
- Help & FAQ now points new users to the Training Guide first; the Add New Asset screen's title bar gets a "Training Guide" button alongside the existing "Help & FAQ" one (Add screen only — editing an existing asset doesn't need the first-time walkthrough).
- Loads its own type pairing (a display serif plus Atkinson Hyperlegible — a typeface literally designed for maximum reading clarity, fitting for a document built around a 5th-grade reading level) via `wp_enqueue_style`, scoped to load only on this one screen, same pattern as the hero-preview CSS on the Add/Edit Asset screen.
- Every CSS class in the new screen is prefixed `rapm-tg-` and every base-element rule (`h1`, `h2`, `a`, `p`, and so on) is scoped under the page's own wrapper — this is admin-screen content sharing a document with the rest of wp-admin's chrome and every other plugin's screens, not a standalone page, so nothing here is allowed to leak out and restyle anything else in the dashboard.
- **Verified without a live WordPress environment** (standing constraint for this project): since no PHP interpreter exists here either, rendered the actual PHP file's real output by mechanically extracting every `esc_html_e()`/`esc_attr_e()` string in place of guessing at the text, then loaded that real markup inside a hand-built approximation of the WordPress admin chrome (fixed admin bar, left sidebar menu, `.wrap` container) in a browser — confirming the scoped styles hold up against that surrounding chrome and nothing bleeds across the boundary, not just that the page looks fine in isolation.

## 1.18.0

**Feature: the Add New Asset form is now a guided, step-by-step wizard for creating a promotion — but editing an existing one skips it entirely.** Direct follow-up to feedback that the form's one long scroll (originally proposed as tabs, matching WooCommerce's own product editor) was the wrong call once the actual audience was named explicitly: non-technical staff with a 5th-grade reading level, who need to be walked through an unfamiliar, multi-part process one decision at a time — tabs assume someone already knows which section they need, which this audience can't be assumed to. A follow-up correction refined it further: the wizard should never stand between someone and a quick edit on a promotion that's already live — "possible to edit without going through the whole initial setup guide."
- **Creating a new promotion** now walks through four groups in order — 1) The Basics (name, spot on the site, images), 2) Your Message (headline/subhead/button/style), 3) Where It Links (destination), 4) Review & Schedule (live preview, start/end dates, save) — with a progress bar, Back/Next buttons, and a plain-language warning if you try to skip ahead without an internal name or a desktop picture. The four groupings reuse the form's existing sections; nothing about what each section does or how it saves changed.
- **Editing an existing promotion shows every section at once, nothing hidden** — the exact opposite of the wizard for new ones. The same progress bar becomes a set of jump links: click "Where It Links" and the page scrolls straight to that section instead of hiding the other three. No step to click through just to change a date or swap a linked product.
- The Live Preview section physically moved (in the DOM) from right after the text fields to the final "Review & Schedule" group, so someone reviews it as one of the last things before saving rather than mid-way through typing.
- Removed the two native HTML `required` attributes (Internal Name, Desktop image) — a `required` field sitting inside a hidden wizard step silently blocks submission with no visible focus target, a well-known multi-step-form bug. Server-side validation in `handle_save()` (already in place since 1.0.0) is unchanged and remains the actual source of truth; the wizard's own per-step check is just an earlier, friendlier heads-up.
- **Caught by visual verification, not just code review**: the per-step warning box's "show" logic set `element.style.display = ''` intending to reveal it, but the box's own CSS class already defaults to `display: none` — clearing an inline override falls back to that CSS default, not to visible. Screenshotting the actual behavior (not just reading the code) surfaced this immediately; fixed to explicitly set `display: 'block'`.
- Verified in a browser against the real shipped markup/CSS/JS: wizard mode blocks advancing with a visible, correctly-worded warning until required fields are filled, unblocks once they are, keeps unvisited steps' progress-pills disabled (a locked pill click is a no-op) while visited ones stay clickable to jump back; edit mode shows all four sections simultaneously with the Back/Next buttons hidden and no pill marked "current."

## 1.17.0

**Feature: closes the biggest gaps found when auditing this plugin directly against Soliloquy and other industry-standard slider/promo tools.** Direct follow-up to a UI/UX audit requested to check the plugin against real competitor conventions — the audit turned up five concrete, prioritized gaps versus baseline industry practice (filtering a growing list, duplicating a similar asset, a visible "manage this slider" screen, drag-and-drop reordering, and a way to salvage a wrong-shaped upload instead of just rejecting it). This release ships all five. Deliberately **not** built, since the same audit found they aren't actually industry-standard baseline even among premium competitors: text-style presets, Placement folders/groups, and built-in click analytics/A-B testing.

- **Type and Placement filters on "All Assets."** Two dropdowns above the list (`restrict_manage_posts`), narrowing to just one Kind, one Placement, or both — the list was previously one flat, unfilterable table regardless of how many promotions existed.
- **"Duplicate" row action on every asset.** Creates a draft copy with the same images (reuses the same attachment, doesn't re-upload the file) and every setting, for the common case of a promotion that's a small variation on an existing one.
- **New "Sliders" screen (Promo Manager > Sliders).** The Placement confusion this project kept circling back to (1.14.1, 1.15.0's precursor discussion, 1.16.0, 1.16.1) was never really a copy problem — a Placement was always a real, functioning thing (a group of assets rotating together), it just never had a screen of its own to be *seen* on. This gives it one: a card per Kind+Placement combo (thumbnail, live slide count, shortcode, Manage Slides / Add Slide), and a detail view per card listing its slides.
- **Drag-and-drop slide reordering**, on the new detail view. Persists via `menu_order` — the same column every renderer (`RAPM_Hero_Carousel`, `RAPM_Marquee`, `RAPM_Coupon_Book`) already sorts by, so this is the one missing piece that makes reordering take effect live, with nothing else to change. New `wp_ajax_rapm_reorder_slides` endpoint saves the order the instant a drag ends, no separate save step.
- **Optional crop-anchor picker for wrong-shaped uploads.** Previously any shape mismatch was a hard rejection, full stop — safe, but with no way to actually use a photo that was 90% right. Now, a mismatched upload shows a 3x3 grid of anchor points (defaulting to center); picking one crops to that anchor and proceeds, server-side, via a new `RAPM_Webp_Converter::crop_to()` (Imagick primary, GD fallback). Staying strictly **opt-in**: with no anchor chosen, behavior is unchanged from before — the same hard rejection, since guessing which part of someone's photo matters is exactly the kind of automatic decision this plugin has avoided from the start (see 1.11.0).
- Verified without a live WP/PHP environment (same constraint as always for this project): brace/paren/bracket balance checked on every touched file; the crop math verified via a Python simulation of the exact cropping algorithm across 6 cases (center/top/left/right/bottom anchors plus an exact-match no-op); the drag-reorder algorithm verified by executing the real `getDragAfterElement()` JS in a browser against two scenarios (insert-in-middle, append-at-end); the crop picker's show/hide/select behavior and the Sliders grid/detail layout both verified visually in a browser against static test harnesses built from the actual shipped markup/CSS/JS.
- New Help & FAQ entries: the Sliders page, the Type/Placement filters, Duplicate, and the crop picker.

## 1.16.1

**Fix: named the actual misconception behind the Placement confusion, rather than rewording around it again.** Direct user hypothesis: they assumed "Which Spot on the Site" was defined by the target page's real URL slug. It isn't, and never has been — it's a purely arbitrary grouping label with zero connection to any page's web address; the *only* thing that determines which page a carousel appears on is where its shortcode gets pasted by hand. Confirmed this was a genuine misconception (not a copy gap) before writing anything, rather than reinforcing an incorrect mental model in the UI.

- Rewrote the field's description to state this directly: it's just a label, typing a real URL does nothing, and what actually controls the page is the shortcode shown below it.
- Kept the one part of the original instinct that *is* good practice: naming a spot to match its target page (e.g. "dining-room") is a genuinely helpful memory aid — just not a technical requirement, and now framed that way explicitly instead of left ambiguous.
- Updated the matching Help & FAQ entry, and added a new one directly answering "so how does the promotion actually end up on the right page?"

## 1.16.0

**Feature: a live "who else shares this spot" summary, since three rounds of rewording "Which Spot on the Site" still hadn't made the concept land.** Confirmed the underlying capability was already fully built (multiple slides auto-rotating on a fully adjustable timer, on both desktop and mobile — same mechanism either way since only the *image* differs by device, not the carousel itself; independent carousels via separate Placements) — the actual gap was that the abstract idea of "a shared Placement" wasn't landing through description alone, no matter how it was worded.

- As "Which Spot on the Site" is typed into, a box now shows — live, using this site's *actual* other promotions by name — exactly what would happen: "Nothing else is using this spot yet," or "2 other promotions already use this spot ('Labor Day Sale', 'Summer Clearance') — they'll all take turns rotating together in the same carousel." Turns an abstract rule into a concrete fact about this specific site, rather than one more paragraph to read.
- New `wp_ajax_rapm_placement_summary` endpoint (`RAPM_Upload_Handler::ajax_placement_summary()`).
- New Help & FAQ entry explaining what Placement actually does, pointing at this live summary as the way to see it working.
- Verified the message-building logic (singular/plural wording, and the "+N more" case when more than 5 promotions share a spot) in a browser against mocked data for all four cases before shipping.

## 1.15.0

**Feature: the "hand-picked list" bulk import now accepts real Excel (.xlsx) files, not just .csv.** Direct follow-up to confirming the CSV importer (v1.7.0) still didn't cover Excel — every `.xlsx` was rejected with a message asking to re-save as CSV first.

- New small, purpose-built `.xlsx` reader (`RAPM_Destination::read_xlsx_skus()`) — `ZipArchive` + the XML inside, both built into PHP core, rather than pulling in a full spreadsheet library for what's really just "read one column of one sheet."
- **Verified against real generated files, not just reasoned about**, since there's no PHP interpreter in this environment to run the code directly: generated an actual `.xlsx` with Python's `openpyxl` and inspected its real internal XML, then hand-built a second test file matching real Excel/Google Sheets' own encoding (shared strings, including a multi-run rich-text string) — and simulated the exact read algorithm against both in Python before writing the final PHP.
- **That testing caught two real bugs before shipping**: (1) `openpyxl` — and likely other real tools — writes text cells as inline strings (`t="inlineStr"`, text inside `<is><t>`), not shared-string references; the first version only handled shared strings, which would have silently failed to find the "SKU" column header at all on a file like that. (2) The worksheet's relationship `Target` path came back as an *absolute* in-zip path (`/xl/worksheets/sheet1.xml`) in the generated file, not the relative path assumed; the original resolution logic would have doubled the `xl/` prefix and pointed at a file that doesn't exist. Both fixed and re-verified against both test files before shipping.
- Same 2,000-row cap and not-found reporting as the existing CSV path. Added a tip in the field's own help text: a SKU column with leading zeros should be formatted as Text in Excel before saving, or Excel silently drops them — a real, well-known spreadsheet gotcha no importer can recover from after the fact.
- Also fixed a stale Help & FAQ entry along the way — it still described the old manual SKU-textarea flow that the search picker replaced back in 1.6.0.

## 1.14.1

**Fix: the "where this shows up" shortcode box only appeared after saving, and sat far from the field it explains.** Direct feedback on a screenshot of the Add New form: the box only rendered on the *edit* screen (after a first save), so it wasn't there yet when someone was actually filling in "Which Spot on the Site" and wondering what it was for — and even on the edit screen, it sat well below that field, down near the Images section, rather than right next to it.

- Moved directly under "Which Spot on the Site," and it now shows on the **Add** screen too, not just Edit — the shortcode can be computed from the Kind and Placement already chosen, no save required.
- **Live-updates as you type** in the Placement field, so typing something other than "default" immediately shows the exact `placement="..."` shortcode it produces, right where the connection is obvious, instead of only being explained in prose.
- Verified visually: typing "Category Living Room" correctly and immediately updated the preview to `[rapm_hero placement="category-living-room"]`.

## 1.14.0

**Feature: the desktop-picture-on-mobile fallback now shrinks to fit instead of cropping.** Follow-up to confirming (researched against the actual HTML spec and how WordPress core, Shopify, Elementor/Divi/Beaver Builder, and Cloudinary/imgix all behave) that *showing* the desktop picture as a mobile fallback is the correct, industry-standard call — no platform hides content when a device-specific image is missing. This refines *how* it's shown, not whether.

- A slide without a dedicated Mobile Promotion picture now has its desktop picture **shrink to fit** the phone-shaped frame on mobile screens, instead of being cropped edge-to-edge — nothing gets cut off, at the cost of empty space above/below. A slide *with* a real mobile picture is unaffected — it still fills the frame edge-to-edge, since that crop was made on purpose.
- Only applies to Hero and Fold Banner, where desktop and mobile genuinely need different shapes. Coupon and Marquee already require the *same* dimensions for both fields, so there's no shape mismatch to fall back from there in the first place.
- The Add/Edit Asset form's Live Preview (Desktop/Mobile toggle, 1.13.0) updated to match, so what's previewed in wp-admin now matches what actually ships.
- Verified visually with a deliberately edge-heavy test image (content placed at the far left and right, where a naive crop would cut it) before and after — confirmed the crop was destroying that content and the fix preserves all of it.

## 1.13.0

**Feature: Desktop/Mobile toggle on the Live Preview.** Direct follow-up to a question about how to actually verify the right picture shows on the right device — the mechanism was already solid (native `<picture>`/`<source>`, decided by the browser itself, not something that could quietly fail), but confirming it meant leaving wp-admin and manually resizing a browser window, since the Live Preview only ever showed the desktop picture.

- Two small buttons above the preview switch it between the Desktop and Mobile picture, live — no save needed, updates immediately as pictures are uploaded/changed.
- If no Mobile Promotion has been uploaded yet, switching to Mobile honestly shows what a phone visitor would actually see instead: the desktop picture, cropped down into the narrower phone shape — with a note explaining that's what's happening, so a bad automatic crop is easy to catch (and fix by adding a dedicated mobile picture) before it ever goes live.
- Also wired up for link-sourced pictures (the "Use a link" option) — the pasted URL previews directly, best-effort, since it hasn't been fetched/validated yet at that point.
- New Help & FAQ entry: "How do I check what the mobile version will actually look like?"
- Caught and fixed while building this: reading a blank `<img>` element's `.src` back out in JS resolves to the current page's own URL, not an empty string — a real gotcha that would have broken the "no image yet" state on a brand-new asset had it shipped. Read the actual attachment URL from PHP instead.

## 1.12.2

**Fix: nothing on the form ruled out "Which Spot on the Site" being mistaken for desktop-vs-mobile targeting.** Device targeting was already fully handled — every promotion has its own Desktop Promotion and Mobile Promotion pictures, shown automatically to the right visitor — but a real user, mid-form, typed "Mobile" into the Placement field (which is actually for running independent promotions in different page *locations*, unrelated to device), which would have silently produced a promotion that never displays anywhere (no shortcode targets a "mobile" placement).

- Added a direct note under "Which Spot on the Site" clarifying it's not about phones vs. computers, and a matching note at the top of the Images section explaining the two pictures are shown automatically per device.
- New Help & FAQ entry: "Do I need to create a separate promotion for mobile and desktop?" — No.

## 1.12.1

**Fix: the "All Assets" list showed the raw placement value ("default") instead of the actual shortcode needed to display it.** Direct follow-up to 1.12.0's "where this shows up" guidance on the edit screen — the same information is more useful directly in the list, where several promotions are visible at once, than the bare placement word was. That column (still keyed `rapm_placement` internally) is now labeled "Shortcode" and shows the real thing to copy, e.g. `[rapm_hero]` or `[rapm_coupon_book placement="category-living-room"]`, computed the same way as the edit screen's own guidance box.

## 1.12.0

**Fix: saving an asset gave no sign it had worked, and no way to tell where it actually shows up on the site.** Direct feedback: a Hero was submitted, the page just returned to what looked like the same form, with nothing confirming success or explaining how to actually get it onto the website if the shortcode wasn't already placed somewhere.

- `handle_save()` was already redirecting back with `rapm_saved=1` on success — but nothing ever read that flag. The Add/Edit Asset form now shows a clear "Saved." notice when it's present.
- New "Where this shows up on the site" box, shown whenever editing an existing asset: the exact shortcode for that asset's Kind and Placement (e.g. `[rapm_hero]`, or `[rapm_coupon_book placement="category-living-room"]`), a note that Hero/Fold Banner can also be added via the "Promo Carousel" Elementor widget instead, and a plain reminder that this only needs to be placed once per spot — not for every new promotion added afterward.
- `RAPM_Slots::kinds()` now carries each kind's shortcode tag and whether it has a dedicated Elementor widget, so this stays accurate as new kinds get added rather than needing a second hardcoded list.

## 1.11.1

**Fix: error messages on the Add/Edit Asset form ran all together with no spaces.** The message (e.g. "This needs to be 1920x600 px...") was being run through `sanitize_key()` before display — a function meant for slugs (lowercase letters/numbers/dashes only), which silently strips every space, capital letter, and punctuation mark. On top of that, a leftover `rawurldecode()` at the display site was operating on text `sanitize_key()` had already mangled, so it did nothing useful. Switched to `sanitize_text_field()` (the correct sanitizer for actual human-readable text) and removed the redundant decode — `$_GET` values are already decoded once by PHP itself, so `fail()`'s one-time `rawurlencode()` when building the redirect URL was always sufficient on its own. Checked every other `sanitize_key()` call in the plugin; this was the only one being used on free text rather than an actual slug/key.

## 1.11.0

**Feature: automatic, safe image resizing — the one remaining manual step in "optimize sizing, format, and file size."** Format and file size were already fully automatic since 1.0.0; dimensions were always a hard rejection, on purpose (auto-cropping to fit a different shape risks cutting off whatever the photo is actually of). Direct feedback asked whether sizing itself could be automated — the answer, worked through explicitly rather than assumed: split it into a genuinely safe case and a genuinely risky one, and only automate the safe one.

- If an upload is the **same shape** as a slot but the wrong resolution (e.g. a 3840×1200 export dropped into a 1920×600 hero slot — same 3.2:1 ratio, just 2x) — it's now **scaled to fit automatically**, with nothing cropped or distorted. New `RAPM_Slots::aspect_ratio_matches()` (reuses each slot's existing `aspect_ratio_tolerance`) and `RAPM_Webp_Converter::resize_to()` (Imagick preferred, GD fallback, same pattern as the WebP conversion path).
- A **genuinely different shape** (a square photo into a wide hero slot) still hard-rejects exactly like before — fitting that means either cropping or squashing, and either one risks damaging the photo in a way that needs a person to decide, not software. This boundary was a deliberate, explicit choice this round, not a default.
- Updated the Add/Edit Asset form's own copy and two Help & FAQ entries to explain the new behavior in plain terms, since "why did my picture get rejected" now has a more precise answer than it used to.

## 1.10.0

**Feature: promotion text can use one of the site's own Elementor fonts.** Previously "Text Style" was a fixed Bold/Elegant/Minimal preset using hardcoded system font stacks — no way to actually match a specific site's own brand typeface. Researched Elementor's Kit/Global Fonts API directly against its own source (not guessed) before building against it, since getting internal API structure wrong here would mean a broken or empty feature.

- New **Typeface** field on the Add/Edit Asset form — only appears on sites running Elementor with at least one font set up under Site Settings > Global Fonts. Lists the site's actual named fonts ("Primary," "Secondary," "Text," "Accent," or any custom names an editor added) exactly as Elementor itself shows them, plus "Site Default."
- **Stays in sync automatically, doesn't freeze a name**: the chosen font is stored as Elementor's own stable font id and rendered on the front end as `font-family: var(--e-global-typography-{id}-font-family, inherit)` — a live reference to Elementor's own CSS variable, confirmed from Elementor's source to be declared on `<body class="elementor-kit-*">` sitewide (not just on Elementor-built pages). If the site owner later changes what "Primary" means in Elementor, every promotion using it updates with no re-save needed here.
- The Add/Edit Asset form's own Live Preview can't see that CSS variable (wp-admin never loads Elementor's front-end kit styles), so the preview resolves and shows the literal font name instead, purely for WYSIWYG accuracy — the live site always uses the auto-syncing variable, never a frozen name.
- Applies to Hero, Fold Banner, Coupon, and Marquee's below-image caption. Whitelisted server-side against the site's actual current fonts at save time, not just trusted from the submitted form value.
- Gracefully absent (not broken) on any non-Elementor site, or an Elementor site that's never touched Global Fonts — the existing Bold/Elegant/Minimal presets keep working unchanged either way.
- **Known unverified gap, flagged rather than assumed**: whether Elementor reliably enqueues the Google Fonts `<link>` for a global font that's *only* referenced by this plugin (not by any actual Elementor widget on the page) couldn't be confirmed from source alone — worth checking on a real site before relying on it for a custom (non-system) font.

## 1.9.1

**Fix: Marquee's wrapper now hugs the tile row's actual width instead of stretching full-width.** `.rapm-marquee-wrap` was a block-level flex container with no width constraint, so it always spanned its full parent width regardless of item count — most obvious at `items="1"`, where a single small tile sat centered inside a much wider empty band. Set to `width: max-content` (capped by `max-width: 100%` so it still shrinks correctly on narrow screens) so the whitespace around the row always matches the row's own width, at every item count from 1 to 5. Verified visually at items=1/3/5 with the wrapper's own background tinted for inspection.

## 1.9.0

**Fix: Marquee tiles beyond the visible count now page in like a carousel, not wrap onto more rows.** Direct follow-up to 1.8.0 — the wrap-to-more-rows behavior shipped there wasn't what was wanted; extra tiles should be paged to, not stacked below.

- `[rapm_marquee]` now shows exactly `items` tiles at a time (never more), with Prev/Next arrow buttons and an optional auto-advance timer for anything beyond that count — the same "carousel" expectation as the hero, hand-rolled in plain JS rather than pulling in Swiper (consistent with this display mode's existing no-library approach).
- New Settings (Promo Manager > Settings > Marquee Defaults): **Auto-Advance** (on by default for Marquee, unlike the hero's carousel which defaults off) and **Auto-Advance Speed**. Arrow buttons work regardless of the Auto-Advance setting. Auto-advance pauses on hover and is skipped entirely for `prefers-reduced-motion`.
- Verified visually (not just reasoned about) with a stand-in page simulating 7 scheduled tiles at 3-per-page: confirmed exactly 3 show at once, Prev/Next page correctly, and Next wraps from the last page back to the first.

## 1.8.0

**Redesign: Marquee is now a compact row of square image tiles, not a scrolling text ticker.** The 1.7.0 build of Marquee (pure scrolling text, no picture) turned out to be a mismatch for what was actually wanted: a small "quick links" style row of images — the user pointed at a live example (a furniture site's own tile row under its main nav) where every tile displayed at a consistent size on screen despite the source photos having wildly different natural proportions (checked directly: 786×499, 786×589, 786×524 among them), which is exactly the inconsistency problem this plugin exists to prevent, just not yet applied to this display mode.

- Marquee is now an image Kind again, using a new **1080×1080 "Marquee Tile" slot** — deliberately Instagram's own standard square post size, so it's an easy, recognizable export target and crops predictably regardless of the source photo's shape.
- `[rapm_marquee items="4"]` — a new `items` attribute (1–5) controls how many tiles sit in one row; extra active tiles wrap onto further rows rather than being hidden, so a promotion is never silently dropped just because a row is "full." Defaults to a new site-wide **Tiles Per Row** setting (Promo Manager > Settings > Marquee Defaults), same pattern as the existing Carousel Defaults.
- Rendered as a plain CSS grid (image tile + caption below), not Swiper — each tile is capped at a max width so the row stays compact (roughly matching the reference example) rather than stretching to fill a wide page.
- The Add/Edit Asset form's "no image required" special-casing added for Marquee in 1.7.0 is now dead code for every current Kind (Marquee has a real image slot again like every other Kind) — left in place, generalized to plain "text-only kind" language, as a safety net for a future custom Kind added via the `rapm_slots`/`rapm_kinds` filters, rather than ripped out.

## 1.7.0

**Feature: the three remaining display modes from the original project plan (Coupon Book, Marquee, Promotions Calendar), plus an optional bulk-SKU CSV importer.** These were scoped as later phases from the start ("not this build") rather than dropped, but that phasing wasn't kept visible enough as work progressed — this release closes that gap.

- **Coupon** (new Kind): a small portrait card (600×750, WebP, under 150KB — same size for desktop/mobile since it's always shown small, not full-bleed) shown in a new `[rapm_coupon_book]` horizontal scroll-snap row. Plain CSS/JS, no slider library — reuses the existing image-validation pipeline and the same Text Alignment/Color/Style controls as every other kind.
- **Marquee** (new Kind): scrolling text only, no picture required at all — the Add/Edit Asset form skips the whole Images section and the "does your picture already have text" question for this one Kind, and requires the ticker text instead. New `[rapm_marquee]` shortcode, pure CSS `@keyframes` scroll (no library), pauses on hover and respects `prefers-reduced-motion`.
- **Promotions Calendar**: new `[rapm_promotions_calendar placement="..." kind="..."]` shortcode — a month-grid view of every promotion (any Kind) that has both a start and end date set, client-rendered from an embedded JSON payload for the same full-page-cache-safety reason as every other display mode here. Deliberately distinct from the separate Community Events Calendar plugin some client sites also run: this shows RA Promo Manager's own scheduled promotions, has no public submission path, and every item on it comes from the validated Add/Edit Asset admin form.
- `RAPM_Schedule.watch()`: a new, lighter-weight sibling to the existing Swiper-specific `init()` in the shared scheduling script, for display modes (Marquee, Coupon Book) that don't use Swiper.
- **CSV bulk-SKU import** for "hand-picked lists": upload a .csv file with a column titled "SKU" instead of searching for each product one at a time. Matched SKUs are added to the picker's selected-products list automatically; any SKU not found on the site is reported and downloadable as its own "SKUs not found" .csv, so a client can follow up on it rather than having it silently dropped. Capped at 2,000 rows per file, with that cap surfaced in the result rather than silently truncating.
- **Known gap, not silently skipped**: Marquee, Coupon Book, and Calendar don't have dedicated Elementor widgets yet (unlike Hero/Fold Banner) — their shortcodes work today via Elementor's own Shortcode widget in the meantime.

## 1.6.0

**Feature: text alignment/color/style controls, and a proper multi-select product picker for hand-picked lists.** Direct follow-up feedback on the earlier work in this session — several requested pieces hadn't actually been built yet (they were scoped as later phases in the original plan, not silently dropped, but that distinction wasn't surfaced clearly enough at the time).

- **Text styling**: each promotion now has Text Alignment (Left/Center/Right), Text Color (a color picker, default white), and Text Style (Bold/Elegant/Minimal — system font stacks only, so a style choice never adds an extra font download on the live site). The Live Preview reflects all three as you change them. The button's own white pill styling is intentionally unaffected by Text Color, for contrast.
- **Multi-select product picker for "hand-picked lists"**: the SKU textarea is replaced by a search-as-you-type picker (search by product name or SKU, indicated directly in the field's placeholder) — clicking a result adds it to a visible, removable list of selected products rather than requiring anyone to know or type raw SKUs. Re-opening an existing hand-picked list resolves its saved SKUs back into named chips automatically. The single-product/category/brand picker used elsewhere also now labels itself per type (e.g. "Search by product name or SKU" specifically for products).
- Bundled from the same feedback pass: the "Type of Promotion" dropdown shows each kind's required dimensions, and the image fields read "Desktop Promotion" / "Mobile Promotion" (shipped in 1.5.0, listed here for completeness of this feedback round).

**Not yet built, and not silently dropped either — flagged directly rather than assumed:** the Marquee display, a promotions calendar view, and a coupon-book display were scoped as later phases in the original project plan and still need to be scoped and built. See the project memory / conversation for the open questions on each before implementation starts.

## 1.5.0

**Feature: link a promotion picture to an outside file (direct URL or Google Drive) instead of uploading it, kept up to date automatically.** Requested for brand partners who host their own co-op promotional images and update them independently — rather than an account manager re-downloading and re-uploading a file every time a brand changes it.

- Each of Desktop Promotion / Mobile Promotion now offers "Upload a file" or "Use a link." A linked image goes through the exact same dimension/format/size validation as a direct upload (`RAPM_Upload_Handler::validate_convert_sideload()`, refactored to be the shared core both paths call) — it's held to the same standard, not a looser one.
- **Live sync, not a one-time import**: a new hourly WP-Cron job (`RAPM_Sync`) re-checks every linked image and swaps in the new picture only when the source file actually changed (compared by content hash, not just re-fetching blindly), so an unchanged source doesn't clutter the Media Library.
- **A failed check never breaks the live site.** If a link goes down, gets deleted, or starts returning the wrong-size image, the last picture that worked keeps showing — the failure is recorded, surfaced as a red "Link issue" note in All Assets, and emailed once to the site admin (not on every retry) so it gets noticed and fixed.
- **Google Drive support, researched rather than assumed reliable**: a normal Drive "Share" link isn't directly downloadable, so it's automatically rewritten to Drive's export-download URL. Flagged clearly in both the field's help text and the Help & FAQ page: Drive blocks larger files behind a "can't scan for viruses" warning page instead of serving them directly, so this is reliable for typical promotional-image sizes but not guaranteed for large files — a direct file URL from a CDN or marketing portal remains the more dependable option.
- New `RAPM_Link_Source` class handles URL normalization and the fetch-validate-sideload pipeline (built on WordPress's own `download_url()`).
- Two smaller fixes bundled in while touching this same form: the "Type of Promotion" dropdown now shows each kind's required dimensions directly in the option text, and the Desktop/Mobile picture fields are labeled "Desktop Promotion" / "Mobile Promotion" instead of the more generic slot label.

## 1.4.0

**Feature: in-plugin Help & FAQ page, written for a 5th-grade reading level.** Directly follows from the same non-technical-user requirement behind the rest of this plugin's UI — a help resource that itself uses jargon or long sentences doesn't actually help.

- New Promo Manager > Help & FAQ admin page (`RAPM_Help`), open to anyone who can add assets (`edit_posts`), not just Administrators. Step-by-step "Adding a New Promotion" walkthrough at the top, followed by short Q&A entries grouped by topic (Pictures, Sale Text, Where It Goes When Clicked, Scheduling).
- Built with native `<details>`/`<summary>` for the Q&A entries rather than a custom JS accordion — no JavaScript dependency, and screen readers announce the expand/collapse state correctly on their own.
- A "Help & FAQ" button now appears next to the page title on the Add/Edit Asset form itself, so it's discoverable right where questions actually come up.

## 1.3.0

**Feature: link a promotion to specific WooCommerce products, brands, or search results — pickable by name, not ID.** Prompted by the need to point a promotion at specific SKUs, a brand, or a category without asking a non-technical account manager to hunt down a numeric ID.

- New destination types (shown only when WooCommerce is active): "A specific product," "A product category," "A specific brand" (only offered if this site's brand taxonomy actually exists — see Settings below), "Search results for some words," and "A hand-picked list of products (with more filled in automatically)."
- **Search-as-you-type picker**, not raw ID entry. A new `wp_ajax_rapm_search_destination` endpoint (`RAPM_Destination::ajax_search()`) backs a plain type-a-name-see-matches field for the post/product/category/brand types — product search also matches an exact SKU first. Re-opening an existing asset resolves its saved ID back to a readable name automatically.
- **New "hand-picked list" type**: paste specific SKUs (one per line), and optionally choose what fills in the rest of the results — nothing else, search words, a category, or a brand. Renders via a new `[rapm_curated_results]` shortcode using WooCommerce's own product-loop template, so it matches the rest of the shop's styling. Set once under Promo Manager > Settings > Curated Results Page; every "hand-picked list" link reuses that one page.
- **Fast Simon compatibility, checked rather than assumed**: researched how Fast Simon's pinning and search actually work before building against it. Its merchandising/pinning is dashboard/API-only (no URL trigger), so it's already fully covered by the plain "specific link" type once a collection is pinned in Fast Simon's own dashboard — no separate integration needed. Its search results page location varies by site configuration (native `?s=` vs. a dedicated page), so the "search results" and fallback URL is now configurable per-site (Settings > Search Results Page) rather than hardcoded either way.
- New Settings (Promo Manager > Settings > Product Linking, WooCommerce sites only): Curated Results Page, Brand Field Name (for sites using a legacy `pa_brand` attribute instead of WooCommerce's native Brands taxonomy), and Search Results Page.
- **Fix, found while wiring this up:** the destination type selected in the form was being saved to the wrong postmeta key (`_rapm_dest_type` instead of `_rapm_destination_type`), so it was never actually being persisted — every asset's destination type silently fell back to its default on re-render. Destination *value* saving was also broken for non-URL types (always cast to an integer, which would have silently mangled a "search words" value). Both fixed as part of rebuilding this section.

## 1.2.0

**Feature: plain-language guided form for non-technical uploaders.** Prompted directly by the concern that the people actually using this form day-to-day may have no idea what "image size," "file type," or web jargon in general means, and previous copy leaned on exactly that (e.g. explaining live text via "keeps it readable to... AI answer tools").

- The Headline/Subheadline/Button Text fields are no longer just described in prose as "the alternative to baking text into the image" — there's now an explicit, plain-language either/or choice at the top of that section ("Does your picture already show the price, sale, or date on it?" — Yes/No radio buttons), and choosing "Yes" hides the text fields entirely rather than leaving someone to infer they should skip them.
- **Fix, found while wiring this up:** the hidden-but-still-present text fields would have been submitted along with the form regardless of the radio choice (CSS `display:none` doesn't stop a value from being submitted) — `handle_save()` now explicitly force-clears Headline/Subhead/CTA server-side whenever "already on the picture" is chosen, so a stale value can't sneak through.
- **Fix, found in the same pass:** the Button Text field's "Shop Now" suggestion was previously a pre-filled *value*, not a placeholder hint — meaning re-editing an asset where it had been deliberately left blank would silently re-populate "Shop Now" and could get re-saved on the next edit. Now a real `placeholder`, only ever saved if actually typed.
- Rewrote the jargon-heavy copy throughout the rest of the form into plain language: "Kind" → "Type of Promotion," "Links To"/"Link Target" → "Send visitors to..."/"Address / ID," "Schedule" → "When It Should Show," dimension requirements now explain what "pixels" means instead of assuming it, and all mentions of WebP/SEO/screen-readers/full-page-caching removed from user-facing copy (kept only in code comments, where they belong).

## 1.1.2

**Fixes, prompted by verifying that live text is genuinely optional (it is — Headline/Subhead/Button Text have never been required fields, server- or client-side):**
- An image-only slide (no headline/subhead/CTA at all) previously still rendered the `.rapm-slide-copy` gradient overlay wrapper unconditionally, subtly darkening the bottom of an otherwise "clean" image. Now that wrapper only renders when there's actually text to show.
- The desktop image, on the other hand, effectively *was* required — without one, `render_slide()` already silently skipped the asset entirely (nothing to render), but nothing told the person saving it that would happen. Added a clear error instead: *"Please upload a desktop image — it's required for this asset to actually display anywhere."* Plus client-side `required` on that file input for new assets, for immediate feedback.

## 1.1.1

**Feature: live WYSIWYG preview + text-clash warning on the Add/Edit Asset form.** Addresses a real gap: nothing previously stopped someone from uploading an image with sale text already baked into the pixels *and* separately filling in Headline/Subheadline/Button Text, producing visibly overlapping/duplicate text on the live page. Automatically detecting text inside an image would require OCR (a paid cloud vision API or a server binary rarely available on typical WordPress hosting) — a real external dependency and cost this plugin deliberately doesn't take on. Instead: the form now shows a live preview (image + text overlay rendered together, using the exact same CSS the live carousel uses) that updates as you type or choose a file, so whoever's uploading can see and fix a clash themselves before it ever publishes — plus an explicit on-screen warning next to the image fields. Loads `rapm-hero.css` only on this one admin screen.

## 1.1.0

**Feature: Fold Banner, a second display "kind" alongside the Hero.** Prompted by the user's own research suggesting a shorter banner positioned near the fold can outperform a full hero slider for engagement — checked this against real published research before building on it: the specific claim isn't directly backed by any citable study (no A/B test isolating banner position on CTR was found), but it's a plausible synthesis of several real, adjacent findings (NN/g's banner-blindness research ties ad-avoidance specifically to *position*, not just style; Chartbeat's ~2B-pageview analysis found engagement peaking just above/below the fold rather than at the very top; tracked carousel data shows real CTR problems). Rather than picking one placement as universally correct, both are now supported so a client's own data can decide.

- New `fold_banner` kind: 1920×300 desktop / 1080×400 mobile, both under 200KB (no established industry dimension convention exists for this shape — checked directly — so these are a documented starting point, adjustable per-site, not an external standard).
- `RAPM_Slots::kinds()` maps each kind to its desktop/mobile slot pair — the one place a third kind would need registering later.
- The "Add New Asset" form gained a **Kind** selector (Hero vs. Fold Banner) that switches which image dimensions are required, before any file is chosen.
- New `[rapm_fold_banner placement="..."]` shortcode, sharing the exact same carousel/scheduling engine as `[rapm_hero]` (`RAPM_Hero_Carousel::render()`, parameterized by kind) rather than a duplicated implementation. The Elementor widget gained a matching Kind control.
- Carousel container sizing switched from a fixed pixel height to CSS `aspect-ratio` driven by the active kind's slot dimensions — this also fixes the hero carousel now correctly sizing itself instead of assuming one fixed height, and means a future third kind needs no CSS changes at all.

## 1.0.0

Initial build — Phase 1 of the unified promotional display plugin (see the approved plan for full context). Consolidates two previously-separate things: Soliloquy (a third-party slider with confirmed live bugs — breaks on WP core updates, jQuery-version conflicts, unreliable scheduling, per its own WordPress.org support forum) and NovaSlider (an in-house Swiper.js plugin already live on kemperhomefurnishings.com, with zero image validation).

**Core: validated asset pipeline.**
- New `rapm_asset` custom post type. Real asset creation always goes through the validated "Add New Asset" admin form (`RAPM_Upload_Handler`), not the native post editor — the CPT is registered with no `thumbnail`/`editor` support, so there's no unvalidated image-upload path to begin with.
- Every uploaded image is checked against its slot's required pixel dimensions (± a small tolerance — client exports are rarely pixel-perfect) *before* being accepted; a mismatch is rejected with the exact numbers needed vs. what was uploaded, not a generic error.
- Non-WebP uploads are auto-converted to WebP and compressed toward the slot's file-size target server-side (`RAPM_Webp_Converter`: Imagick preferred, GD fallback, clear admin-facing failure if neither is available on the host) — removes the need for anyone to know what WebP is or how to make one.
- Default slots: Hero Desktop (1920×600, under 300KB) and Hero Mobile (1080×1920, under 300KB), both overridable per-site under Promo Manager > Settings without code changes. New slots can be registered via the `rapm_slots` filter.
- Promotional copy (headline, subheadline, CTA text) is always stored and rendered as real HTML text layered over the image — never baked into the image file. This is what keeps it crawlable, screen-reader accessible (WCAG 1.4.5), and editable without a re-upload.

**Display: hero carousel** (`[rapm_hero placement="default"]`, plus an Elementor widget).
- Built by evolving NovaSlider's proven approach rather than starting over: still Swiper.js, still schedules client-side.
- **Scheduling is deliberately client-side, not a server-render-time filter.** Every asset renders with `data-rapm-start`/`data-rapm-end` regardless of the current time; a shared script (`assets/js/rapm-schedule.js`) decides what's actually active in the visitor's own browser and re-checks every 60 seconds. This is what makes scheduling survive a full-page cache plugin (WP Rocket etc.) instead of "freezing" whatever was active when the cache was last built — the exact failure mode this plugin replaces.
- Multiple independent carousels per site via the `placement` field/attribute (e.g. a homepage hero and a separate category-page hero).
- Carries forward two real production fixes already proven in NovaSlider: conditional asset loading (`should_load_assets()` — only enqueues Swiper on pages that actually use the shortcode, with an Elementor-shortcode-widget detection workaround and a `rapm_force_load_ids` filter escape hatch) and a `rocket_delay_js_exclusions` filter so WP Rocket's "Delay JavaScript Execution" doesn't leave the carousel static.
- Emits `ImageObject` JSON-LD per rendered slide for basic SEO/AEO crawlability.
- Links to a plain URL, a WordPress post/page, or (if WooCommerce is active) a WooCommerce product/category — `RAPM_Destination` centralizes resolving all four so the renderer never branches on link type itself.

**Not in this phase** (see the plan): coupon-book scroll-snap display, marquee/ticker display, calendar display. All three are designed to read from the same `rapm_asset`/slot/scheduling core being built here, not a separate system.
