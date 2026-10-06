<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A plain-language help page for the people who actually use the Add/Edit
 * Asset form day to day — not developers. Every sentence here is meant to
 * be readable at roughly a 5th-grade level: short sentences, common words,
 * one idea at a time. Uses native <details>/<summary> for the FAQ items
 * (no JavaScript needed, and screen readers announce them correctly on
 * their own) rather than a custom accordion widget.
 */
class RAPM_Help {

	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=rapm_asset',
			__( 'Help & FAQ', 'rapm' ),
			__( 'Help', 'rapm' ),
			'edit_posts',
			'rapm-help',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		?>
		<div class="wrap rapm-help">
			<h1><?php esc_html_e( 'Help & FAQ', 'rapm' ); ?></h1>
			<p class="rapm-help-intro"><?php esc_html_e( 'This page answers the questions people ask most when adding a new promotion. Read the steps below, then check the questions and answers if you get stuck.', 'rapm' ); ?></p>
			<p class="description">
				<?php
				printf(
					/* translators: %s: link to the Training Guide admin page */
					esc_html__( 'Brand new to this tool? Start with the %s instead — it walks through everything with pictures.', 'rapm' ),
					'<a href="' . esc_url( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-training-guide' ) ) . '">' . esc_html__( 'Training Guide', 'rapm' ) . '</a>'
				);
				?>
			</p>

			<style>
				.rapm-help { max-width: 800px; font-size: 15px; line-height: 1.7; }
				.rapm-help h2 { margin-top: 2em; }
				.rapm-help-intro { font-size: 16px; }
				.rapm-help ol, .rapm-help ul { padding-left: 1.4em; }
				.rapm-help li { margin-bottom: 0.6em; }
				.rapm-help details { background: #fff; border: 1px solid #dcdcde; border-radius: 4px; margin-bottom: 10px; padding: 4px 16px; }
				.rapm-help summary { cursor: pointer; font-weight: 600; padding: 10px 0; }
				.rapm-help details p, .rapm-help details ul, .rapm-help details ol { margin-top: 0; padding-bottom: 10px; }
				.rapm-help .rapm-help-note { background: #f0f6fc; border-left: 4px solid #72aee6; padding: 10px 14px; margin: 1em 0; }
			</style>

			<h2><?php esc_html_e( 'Adding a New Promotion — Step by Step', 'rapm' ); ?></h2>
			<p class="description"><?php esc_html_e( 'The form for a new promotion walks you through this in order, one screen at a time, with Back and Next buttons — you can\'t get lost or skip something by accident.', 'rapm' ); ?></p>
			<ol>
				<li><?php esc_html_e( 'On the left menu, click "Promotions." Find the section for the spot where it should show (for example "Home page slider") and click "Add a picture" at the end of it.', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'The type of promotion is already picked for you, from the section you started in.', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'Type a name for your own records. Visitors will never see this name.', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'Upload your desktop picture and your phone picture. The page will tell you the exact size each one needs to be. (Shop the Look is different: it has one room photo and a tab name. See "Shop the Look" below.)', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'Answer the question about sale text: does your picture already show the price or sale? Pick Yes or No.', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'If you picked No, type your headline, a smaller line under it, and your button words (like "Shop Now").', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'Look at the preview box. It shows exactly what visitors will see.', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'Choose where people go when they click it — see "Where It Goes When Clicked" below for help.', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'Choose a start date and an end date, or leave them blank.', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'Click the button at the bottom to save.', 'rapm' ); ?></li>
			</ol>

			<div class="rapm-help-note"><?php esc_html_e( 'Tip: You can always come back and change anything later. Just find your promotion on the Promotions page and click its picture — editing shows everything on one page (no steps to click through), so you can jump straight to the one thing you want to change.', 'rapm' ); ?></div>

			<h2><?php esc_html_e( 'Types of Promotions', 'rapm' ); ?></h2>
			<details>
				<summary><?php esc_html_e( 'What\'s the difference between a Slider, Shop the Look, Feature banner, Coupon row and Tile row?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'A Slider is big rotating pictures, usually at the top of a page. Shop the Look is a big room photo with a bar of tabs under it, one tab for each room or collection. A Feature banner is a wide strip lower on a page that features one thing. A Coupon row is small coupon cards in a row people can scroll. A Tile row is small square tiles, like "Design Services," "Current Promotions," "Financing," "Visit Us" — good for quick links.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'Why does a Tile row only accept square pictures?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'So every tile in the row looks the same size. If different tiles used pictures with different shapes, the row would look uneven — one tile taller than the next. A square picture (the same width and height) crops predictably no matter what you upload.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'What does "Where it shows" mean?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'It is the spot on your website the promotion shows in, like "Home page slider." Promotions in the same spot take turns. Pick one from the list. R&A Marketing makes new spots, and the Promotions page lists every spot with the pages it shows on.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'So how does the promotion actually end up on the right page?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'R&A Marketing puts each spot on the pages where it belongs. After that, every promotion you add to that spot shows there by itself. One spot can be on several pages, and then the same promotions show on each.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'How do I show a calendar of upcoming promotions?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Ask whoever manages the website to add [rapm_promotions_calendar] to a page. It automatically shows every promotion that has both a start date and an end date set — nothing else to configure. This is a calendar of your own promotions, separate from any community events calendar the site might also have.', 'rapm' ); ?></p>
				<p><?php esc_html_e( "Each promotion shows as a named bar stretching across the days it runs, so a visitor can see what's happening at a glance without clicking anything — the same way a personal calendar app shows a multi-day trip or event. If more promotions overlap on the same days than there's room to show, a small \"+N more\" link appears — clicking it, or clicking any day, lists everything active then.", 'rapm' ); ?></p>
				<p><?php esc_html_e( 'The bottom of the Promotions page lists the pages that already have the calendar, and how many promotions are on it right now.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'How do I know if a promotion will show up on the calendar?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'On the last step of the form (Review & Schedule), right below the start/stop date fields, a note tells you directly — it says whether this specific promotion will show on the Promotions Calendar or not, and updates the moment you add or remove a date. A promotion needs both a start date and an end date to show up there; if either is blank, it simply won\'t appear on the calendar, which is completely fine if you\'re not using it for that promotion.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'Does the calendar match the colors on our website?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Yes, automatically, on sites built with Elementor — it uses this site\'s main Elementor brand color (its "Primary" color, or "Accent" or "Secondary" if white text wouldn\'t be easy to read on Primary), with no setup needed. If it\'s using the wrong color, or the site doesn\'t use Elementor, whoever manages the website can set an exact color for it under Promo Manager > Settings > Brand Color.', 'rapm' ); ?></p>
			</details>

			<h2><?php esc_html_e( 'Pictures — Questions and Answers', 'rapm' ); ?></h2>

			<details>
				<summary><?php esc_html_e( 'Why did my picture get rejected?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Each spot on the website needs a picture with a specific shape (like a wide banner, or a tall phone picture). If your picture is the right shape but a different resolution, it\'s automatically resized for you — nothing to fix. If it\'s a different shape entirely (like a square photo where a wide banner is needed), you\'ll usually be offered a way to pick which part of the picture to keep — see the next question. If that option doesn\'t appear, crop or export the picture again in the right shape and try uploading it a second time.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'What is the 3x3 grid of buttons that sometimes shows up under a picture?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'That\'s the crop picker. It only appears when the picture you chose is a different shape than the spot needs (not just a different size — that case is already handled for you automatically). Click the button for the part of the picture you want kept — top-left, center, bottom-right, and so on — and the rest is trimmed away automatically when you save. It starts on the middle button, so if the middle of your picture is the important part, you don\'t need to touch it at all.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'Does it ever resize my picture for me?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Yes, in two situations: if your picture is already the right shape (say, exported at twice the size needed), it\'s scaled down automatically with nothing lost or cropped out. And if it\'s a different shape, but you\'ve picked a spot on the crop picker described above, it\'s trimmed to that spot and resized. A picture is only ever cropped when you\'ve chosen where from — the computer never guesses which part of a photo matters.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'What is a "pixel"?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'A pixel is just a unit of measurement for pictures, like an inch is a unit of measurement for a piece of wood. Most photo editing tools (Canva, Photoshop, even your phone\'s Photos app) show you the size in pixels when you crop or resize a picture.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'What kind of picture file can I upload?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Any common kind works — JPG, PNG, or whatever your phone, camera, or design tool saves by default. You do not need to convert it to anything yourself. The tool does that for you automatically.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'Do I need both a desktop picture and a phone picture?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'The desktop picture is required — without it, your promotion will not show up anywhere. The phone picture is a good idea too, since it makes sure your promotion looks its best on phones, but you can add it later if you need to.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'Do I need to create a separate promotion for mobile and desktop?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'No. One promotion covers both — that\'s exactly what the Desktop Promotion and Mobile Promotion pictures are for. Upload both, and visitors on a computer automatically see the desktop one while visitors on a phone automatically see the mobile one. "Where it shows" further up the form is something different: which spot on your website it goes in.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'How do I check what the mobile version will actually look like?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Use the Desktop/Mobile buttons above the Live Preview box. Switching to Mobile shows your Mobile Promotion picture — or, if you haven\'t added one yet, it shows what phone visitors would see instead: your desktop picture, shrunk to fit the phone shape. Nothing gets cropped off, but you\'ll see empty space above and below it, which usually looks better with a dedicated mobile picture instead.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'Why does editing an existing promotion look different from adding a new one?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Adding something brand new walks you through it step by step, since there\'s more to figure out the first time. Editing something that already exists shows every part on one page instead — no steps, nothing hidden — so you can go straight to whatever you want to change (like a date, or a picture) without clicking through parts you don\'t need to touch. The numbered boxes at the top still work on this page too — click one to jump straight down to that part.', 'rapm' ); ?></p>
			</details>

			<h2><?php esc_html_e( 'The Promotions Page', 'rapm' ); ?></h2>
			<p><?php esc_html_e( 'Click "Promotions" on the left menu. This is where you see and manage everything.', 'rapm' ); ?></p>
			<details>
				<summary><?php esc_html_e( 'What am I looking at on the Promotions page?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'One section for each spot on your website where promotions show, like "Home page slider." Each section says which pages it shows on and how many promotions are showing now. Each picture card says whether that promotion is showing now, hidden, starting later, or ended, and when it ends.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'How do I take a promotion off the website without deleting it?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Click its switch. Blue means it shows on your website. Gray means it is hidden. Click it again any time to bring it back.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'How do I change the order promotions rotate in?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Drag a picture to a new place in its section. Or click the three dots on its card and choose "Move earlier" or "Move later." The new order saves by itself.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'Why does the carousel show fewer promotions than the Promotions page says are showing?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Some carousels are set to show only a few promotions, like 3. It shows the first 3 that are showing now, starting from the first one in its section. To show a different one, move it earlier. The number is set on the page itself, in a box called "Show at most." Ask R&A Marketing if you want it changed.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'Where did my ended promotions go?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'They are folded away at the bottom of their section. Click "Show ended" to see them. To run one again, click "Use again" and give it new dates.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'How do I copy or delete a promotion?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Click the three dots on its card. "Make a copy" makes a hidden copy with the same picture, words and settings, and opens it so you can change what is different. When it is ready, switch it on. "Move to trash" removes it. If you trash one by mistake, click "Undo" in the message at the top of the page.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'How do I find one promotion?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Type part of its name in the "Find a promotion" box at the top of the Promotions page.', 'rapm' ); ?></p>
			</details>

			<h2><?php esc_html_e( 'Linking to an Outside Picture — Questions and Answers', 'rapm' ); ?></h2>

			<details>
				<summary><?php esc_html_e( 'What does "Use a link" mean?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Instead of uploading a file from your computer, you paste in a web address that points straight to a picture — for example, one a brand partner keeps on their own marketing site, or a Google Drive file. We check it every hour and update the picture on your site automatically if it changes, so you never have to remember to re-upload it.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'Can I use a Google Drive folder instead of one picture?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Yes, and it is the easiest way to keep a promotion up to date. Make one folder for each promotion (for example "England - Slider"), share it as "Anyone with the link," and paste the folder\'s link into the box. The newest picture in the folder is used. To change the promotion, just add a new picture to the folder — the file name doesn\'t matter. You can put the wide desktop picture and the tall phone picture in the same folder and paste that folder into both boxes; each one picks the picture that fits its shape.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'How do I link to a Google Drive file?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'In Google Drive, right-click the file, choose Share, and change the setting to "Anyone with the link." Then copy that link and paste it into the box. If the setting is left more restricted than that, the link won\'t work.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'What happens if the link stops working?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Nothing breaks on your site — the last picture that worked keeps showing. You\'ll get an email, and you\'ll also see a red note under that promotion on the Promotions page, so you know to check it.', 'rapm' ); ?></p>
			</details>

			<div class="rapm-help-note"><?php esc_html_e( 'A linked picture still has to be the exact right size, just like an uploaded one — if a brand\'s picture is the wrong size, it will be turned down with a note explaining why, the same as an upload would be.', 'rapm' ); ?></div>

			<h2><?php esc_html_e( 'Sale Text — Questions and Answers', 'rapm' ); ?></h2>

			<details>
				<summary><?php esc_html_e( 'Do I have to type in a headline, sale text, or button words?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'No. These are only needed if your picture does not already show the price or sale. If your picture already has that text on it, pick "Yes" on the sale text question and leave the text boxes empty.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'What happens if I fill in the text boxes AND my picture already has text on it?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'The two would show up on top of each other, which looks messy. Always check the preview box before saving — if you see text twice, either clear the text boxes or answer "Yes" to the sale text question.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'What is the "Typeface" field, and why don\'t I see it?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'It lets your text use one of the fonts already set up for this website in Elementor, so it matches the rest of the site instead of a generic style. It only shows up if this site uses Elementor and already has fonts set up under Site Settings — if you don\'t see it, ask whoever manages the website, or just leave it on the default style below it.', 'rapm' ); ?></p>
			</details>

			<h2><?php esc_html_e( 'Where It Goes When Clicked — Questions and Answers', 'rapm' ); ?></h2>

			<details>
				<summary><?php esc_html_e( 'What should I pick if I\'m not sure?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Pick "A specific link" and paste in the full web address. It starts with https:// — the same kind of address you see at the top of your browser.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'How do I link to one specific product, category, or brand?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Pick that option from the dropdown, then start typing the name in the box that appears — for example, "leather sofa" or "Ashley Furniture." A list of matches will show up under the box. Click the right one. You never need to know an ID number.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'How do I link to search results for certain words?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Pick "Search results for some words" and type the words a visitor would type into the site\'s own search box, like "sectional" or "outdoor dining."', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'How do I link to a specific list of SKUs?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Pick "A hand-picked list of products." Search by product name or SKU and click each one to add it — they\'ll show first, in the order you add them. If you want more products to fill in the rest of the page automatically, choose search words, a category, or a brand under "Then fill in the rest of the page with." If you don\'t want anything else added, leave that set to "Nothing else."', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'I have a long list of SKUs already in a spreadsheet — do I have to search for each one?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'No. Under "Specific products," there\'s an option to upload a spreadsheet (.csv or Excel .xlsx) instead — it needs one column titled "SKU." Every SKU that matches a real product gets added automatically; any that don\'t match anything are listed separately so you can double-check them, rather than being silently skipped.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'I typed a name but nothing shows up in the list. What do I do?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Try typing fewer words, or check your spelling — the search looks for an exact match on the name as it appears on the website. If you still can\'t find it after a few tries, ask whoever manages the website to check.', 'rapm' ); ?></p>
			</details>

			<h2><?php esc_html_e( 'Shop the Look — Questions and Answers', 'rapm' ); ?></h2>

			<details>
				<summary><?php esc_html_e( 'What is Shop the Look?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'A big room photo across the page, with a bar of tabs under it. Each tab is one "look": a room photo, the name on its tab, and a few words with a button. Shoppers click a tab to see that room. The tabs also change by themselves every 8 seconds, until a shopper clicks one.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'What should the tab name say?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'The word shoppers click. It can be a room, like "Living Room," or a collection, like "Stanton 338." Keep it short (24 letters at most) so all the tabs fit.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'How do I change the order of the tabs?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'On the Promotions page, drag the looks in their section, or use "Move earlier" and "Move later." The first look is the first tab. The gray line above the pictures shows the tabs on the website, in order.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'Why is there only one picture, not a desktop and a phone picture?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Computers and phones use the same room photo. Computers show a wide strip of it, and phones show a squarer part. Use "Which part to keep" to choose the part of the room that stays in view. The small Computer and Phone boxes next to it show what each one will show.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'What size should the room photo be?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Any shape works. A photo 2000 pixels wide or more looks best across a big screen. Bigger photos are made smaller for you. A photo less than 800 pixels wide is turned down, because it would look blurry.', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'How do I take one tab off the website for a while?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Click the switch on that look. Its tab goes away and the other tabs close up. Click the switch again to bring it back. You can also give a look start and end dates, like a patio room for spring only.', 'rapm' ); ?></p>
			</details>

			<h2><?php esc_html_e( 'Scheduling — Questions and Answers', 'rapm' ); ?></h2>

			<details>
				<summary><?php esc_html_e( 'What happens if I leave the start and end dates blank?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Leaving the start date blank means it starts showing right away. Leaving the end date blank means it keeps showing until you come back and turn it off yourself.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'Can I trust the end date to actually turn it off on time?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Yes. It will turn off exactly when you set it to, automatically, with nothing more for you to do.', 'rapm' ); ?></p>
			</details>

			<h2><?php esc_html_e( 'Still Stuck?', 'rapm' ); ?></h2>
			<p><?php esc_html_e( 'If something still isn\'t working the way you expect, reach out to whoever manages the website for your company. Let them know what you were trying to do and what happened instead — that helps them fix it faster.', 'rapm' ); ?></p>
		</div>
		<?php
	}
}
