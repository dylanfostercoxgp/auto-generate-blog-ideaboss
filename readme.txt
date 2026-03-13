=== Auto Generate Blog by ideaBoss ===
Contributors:       ideaboss
Tags:               blog, ai, automation, claude, content, writing, import
Requires at least:  5.6
Tested up to:       6.5
Requires PHP:       7.4
Stable tag:         1.0.5
License:            GPLv2 or later
License URI:        https://www.gnu.org/licenses/gpl-2.0.html

Automate WordPress blog post creation using Claude AI — generate from a prompt or import and format articles from any URL.

== Description ==

**Auto Generate Blog by ideaBoss** adds a "Generate New Post" panel directly inside the WordPress Classic Editor. With two modes — AI Generate and URL Import — you can go from idea to formatted draft in seconds.

**AI Generate Mode**
Type a prompt describing what you want to write. Claude writes a complete, well-formatted blog post including headings, paragraphs, and lists — and auto-fills the title, Yoast SEO keyphrase, and meta description.

**URL Import Mode**
Paste an article URL. The plugin fetches the article, and Claude:
- Writes a fresh 4–5 sentence *italic* intro/summary in its own words (no plagiarism)
- Formats the full article body with proper HTML
- Auto-truncates if the article is too long and adds a "Click here to continue reading" link
- Appends an attribution footer: *Original article published on [source domain]*
- Auto-fills the title, Yoast SEO keyphrase, and meta description

**Other features**
- Auto-checks your theme's "hide featured image" option on generated posts
- Saves posts as drafts by default (configurable)
- Works with Yoast SEO (focus keyphrase + meta description)
- Supports Claude Sonnet 4.6, Opus 4.6, and Haiku 4.5

== Installation ==

1. Upload the `auto-generate-blog-ideaboss` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Settings → Auto Generate Blog** and enter your Claude API key
   (Get one at https://console.anthropic.com)
4. Open or create a new post — the **Generate New Post** panel will appear below the editor

== Frequently Asked Questions ==

= Where do I get a Claude API key? =
Sign up at https://console.anthropic.com and create an API key under your account settings.

= The "hide featured image" checkbox isn't being checked automatically =
Different themes use different meta keys for this feature. Go to **Settings → Auto Generate Blog** and update the "Hide Featured Image Meta Key" field to match your theme's key. Common alternatives include `_hidden_featured_image` or `_genesis_hide_image`.

= Some news sites aren't loading =
Sites that require login, use heavy JavaScript rendering (SPAs), or actively block automated access cannot be fetched by the plugin. In those cases, copy and paste the article text into a Google Doc, then copy from there, or use the AI Generate mode with a manual prompt.

= Is the imported article content plagiarized? =
No. The plugin always instructs Claude to write an original intro/summary in its own words. The article body is reformatted and properly attributed to the source.

== Changelog ==

= 1.0.5 =
* Fixed: cURL error 61 — removed the Accept-Encoding: br header that caused Brotli-compressed responses to fail (libcurl only supports gzip/deflate without extra PHP extensions)

= 1.0.4 =
* Fixed: URL import now uses a tag-delimited response format (<agb_content>) that completely prevents JSON parsing failures caused by HTML attribute quotes inside article content
* Fixed: Article fetcher evaluates ALL content selectors to pick the highest word-count node (removes early exit that could grab the wrong element)
* Fixed: More realistic browser headers on URL fetch requests (Accept, Accept-Encoding, Referer, Cache-Control) to reduce bot-detection rejections
* Fixed: Added `aside` tag to noise removal list; added 12 additional CSS-class selectors for better coverage across themes and CMSs
* Fixed: Better HTTP error messages for 403 / 429 responses
* Added: GitHub-based auto-updater — sites running the plugin receive update notifications directly in WP Admin → Plugins
* Added: Settings → Default Website Name (pre-fills the Website field in AI Generate)
* Added: Settings → Default Post Author (assign all generated posts to a specific author)
* Added: Settings → Default Category (pre-selects a category in the Generate panel)
* Added: Settings → Auto-Tag Generated Posts (add a custom tag automatically)
* Added: Settings → Max Tokens (separate controls for AI Generate and URL Import)
* Added: Settings → Max Words Sent to Claude (control how much of a fetched article goes to the API)
* Added: Settings → Attribution Footer Template (customisable with {article_url}, {domain_url}, {domain} placeholders)
* Added: Settings → Fetch Timeout (configurable per site)
* Added: Settings → Fetch User Agent (override the browser string used for URL fetching)
* Added: Settings → SEO Plugin selector (Yoast / Rank Math / AIOSEO / Auto / None)
* Added: Settings → Auto-Hide Featured Image toggle
* Added: Settings → Debug Mode (log API responses to debug.log)
* Added: Settings page organised into labelled sections for easier navigation

= 1.0.2 =
* Added: AI Generate mode now uses structured Website / Topic / Overview fields with a fixed professional prompt template

= 1.0.1 =
* Fixed: URL import now extracts content from far more sites (less aggressive noise removal, JSON-LD support)
* Fixed: Yoast SEO keyphrase and meta description now saved reliably via PHP on post save (bypasses JS/React UI)
* Fixed: Hide featured image now saves multiple common meta keys to work across themes
* Added: Category dropdown in the Generate panel — select your category before generating
* Improved: More descriptive error messages on URL fetch failures

= 1.0.0 =
* Initial release
* AI Generate mode: write posts from a prompt using Claude API
* URL Import mode: fetch, format, and attribute articles from any URL
* Yoast SEO integration: auto-fill focus keyphrase and meta description
* Auto hide featured image on generated posts
* Settings page: API key, model selection, draft/publish toggle, word limit

== Upgrade Notice ==

= 1.0.0 =
Initial release.
