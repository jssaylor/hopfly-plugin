# HopFly plugin

Content that has to survive a theme change: sponsors, events, the slideshow block, and the store-link/forms glue.
Written for WordPress 7.1, PHP 8.1. No build step (plain JavaScript). **Test after every WordPress update.**

## Sponsors
- **Sponsors** post type (not public: no sponsor URLs, direct hits 404). Logo = featured image. Website = *Sponsor link* panel.
- **Order** (Page attributes) sets the sort: title sponsors first.
- **Teams** category: club, racing, juniors, cross-and-brew, tri-and-brew, jianna. A sponsor can belong to several.
- The sponsor strip is a core Query Loop (see the theme's `hopfly/sponsor-strip` pattern). Set the team in the Query block's metadata
  `hopflyTeam` (default `club`). The plugin reads it in `render_block_context` and filters `query_loop_block_query_vars`.
- Each logo links out through the `hopfly/sponsor` block binding source (keys: `url`, `label`, `target`, `rel`).
- Sponsors without a logo show their name in the tile.

## Events
- **Events** post type; types: group-ride, charity-ride, fundraiser. One place for every event. Fields are in the editor sidebar:
  start/end date, start/end time (optional), all-day, date text (replaces the formatted date, e.g. "September 2027 · dates coming soon"),
  venue, address, map link (optional, else a Google Maps search), registration link and label, status (scheduled / canceled / postponed) and note.
- **Saturday ride:** tick *Repeats every Saturday* on one event. Set one start time (it changes with the season).
  *Date overrides* cancel, postpone or change the time of a single Saturday. The list shows the next Saturday; if it is canceled,
  the following Saturday appears right after it.
- No times are hardcoded anywhere. An event with no time shows no time.
- **Upcoming Events** block: options for count, type, grouped, photos. Past events are hidden. Undated events sort last.
- **Event Details** block: used by the single-event template (date, time, venue + map link, status, registration button).
- Later: month grid, .ics download, Event schema markup.

## Slideshow block (`hopfly/slideshow`)
Core Image blocks inside. Square crop, swipe (scroll-snap), arrows, keyboard arrows, position indicator, no autoplay.
Each image has a *Crop focus* control (focal point) so vertical photos keep heads in view. Give every image alt text.

## Store (off-site)
The store lives at **hopflycycling.org**. WooCommerce is not used on this site.
- **One address:** the `hopfly_store_url` option (default `https://hopflycycling.org`), edited under **Tools > HopFly Setup > Store URL**. PHP reads it with `HopFly\Plugin\store_url()` (plugin) or `HopFly\Theme\store_url()` (theme).
- **Where the address is used:** the *Shop* item in the desktop and mobile menus (opens in a new tab), the Shop card on the Home page (heading and both tile images), and the 301 redirects below. Buttons you add in the editor keep the address they were saved with, so update those by hand if the store moves.
- **Redirects (301 to the store):** `/shop/`, `/cart/`, `/checkout/`, `/my-account/`, `/product/...`, `/product-category/...`, `/refund_returns/`. See `includes/store.php`.
- **Crop focus:** each media-library photo has a *Crop focus* field (x,y in percent). The slideshow keeps that point in view when the photo is cropped to a square.

## Images
JPEG uploads are converted to WebP (quality 80); originals are capped at 2000px; full-width hero images declare `sizes="100vw"`.

## Gravity Forms
`HopFly\Plugin\ensure_forms()` creates *Email signup* (id 1) and *Contact* (id 2) if missing. The theme's patterns use those ids.
Create them on a fresh install so the ids match. The Contact form has a hidden *Team* field filled from the page (`team=...`).
Mailchimp: check the Gravity Forms license covers the add-on, or use webhooks/Zapier. Turn on double opt-in.

## Starter data
`wp eval 'HopFly\Plugin\seed_content();'` adds the teams, sponsor names per team (no logos or links) and the first five events.
It never overwrites anything.
