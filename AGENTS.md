# Newspack Ads: Agent Instructions

This file covers what is specific to `newspack-ads`. Shared conventions (Docker commands, `n` script, coding standards, git rules, etc.) are in the root `newspack-workspace/AGENTS.md`.

## Overview

`newspack-ads` is a WordPress plugin for ad placement management. It provides a provider-based architecture (Google Ad Manager, Broadstreet), a placement system for controlling where ads render, header bidding via Prebid.js, ad suppression, and WordPress Customizer integration for visual placement configuration.

Key insight: the admin wizard UI (Advertising settings page) lives in `newspack-plugin` (via `Advertising_Wizard` and `Newspack_Ads_Configuration_Manager`), not in this repo. This plugin provides the backend APIs, Gutenberg blocks, and frontend rendering logic that the wizard consumes.

## Linting Commands

```bash
npm run lint             # JS + SCSS only (see gotchas)
npm run lint:js          # JavaScript linting
npm run lint:scss        # SCSS linting
npm run lint:php         # PHP linting (PHPCS)
npm run fix:js           # Auto-fix JS issues
npm run fix:php          # Auto-fix PHP issues (PHPCBF)
```

## Common Gotchas

- **`npm run lint` runs JS + SCSS only.** PHP linting requires a separate `npm run lint:php`.
- **No PSR-4/classmap autoloading for plugin classes.** Manual `include_once` in `Core::includes()`. After adding a new PHP file, you must add an `include_once` line there.
- **The Advertising wizard UI (settings page) lives in `newspack-plugin`, not here.** This repo provides the REST API and rendering logic that the wizard consumes. Do not look for settings UI code in this repo.
- **Settings are migrating from `Newspack_Ads_Configuration_Manager` (in newspack-plugin) to a REST API using `Newspack_Ads\Settings`.** New settings should use the `Settings` class in this repo.
- **Header bidding (Prebid.js) is gated behind the `NEWSPACK_ADS_EXPERIMENTAL_BIDDERS` constant.** It must be `define( 'NEWSPACK_ADS_EXPERIMENTAL_BIDDERS', true );` in `wp-config.php` for bidding features to appear.
- **The GAM provider requires `googleads-php-lib` (SOAP library).** PHP's SOAP extension must be enabled (it is in the Docker dev environment).
- **The Broadstreet provider is a thin wrapper around the separate [Broadstreet WP plugin](https://wordpress.org/plugins/broadstreet/).** It must be installed and activated for the provider to work.
- **All provider and integration checks are defensive** -- use `class_exists`/`function_exists`. The plugin must work standalone without any other Newspack plugin.

## PHP Backend

### Core Entities

**Providers** -- the ad server abstraction:
- Interface: `includes/providers/interface-provider.php` defines `get_provider_id()`, `get_provider_name()`, `is_active()`, `get_units()`, `get_ad_code()`, `render_code()`.
- Abstract class: `includes/providers/class-provider.php` implements the interface with sensible defaults.
- Registration: `Providers::register_provider($provider)` in `includes/class-providers.php`.
- Implementations: GAM (`includes/providers/gam/class-gam-provider.php`) and Broadstreet (`includes/providers/broadstreet/class-broadstreet-provider.php`).
- Broadstreet is a great reference for a simple provider implementation.

**Placements** -- where ads render:
- Registration: `Placements::register_placement($key, $config)` in `includes/class-placements.php`.
- Default placements: `global_above_header`, `global_below_header`, `global_above_footer`, `sticky`.
- Config schema: `name`, `description`, `default_enabled`, `default_ad_unit`, `show_ui`, `hook_name`, `hooks` (array), `supports` (array, e.g. `stick_to_top`).
- Hook-based rendering: call `do_action('hook_name')` in a template, the placement renders the configured ad.
- The Ad Unit block creates dynamic placements via the same API.
- SCAIP integration replaces widget areas with registered placements.

**Settings** -- centralized settings management:
- Class: `includes/class-settings.php`.
- API namespace: `newspack-ads/v1` (constant `Settings::API_NAMESPACE`).
- Option prefix: `_newspack_ads_` (constant `Settings::OPTION_NAME_PREFIX`).
- REST endpoints: `GET/POST /settings`.
- Permission: `manage_options` capability, filterable via `newspack_ads_can_current_user_manage_settings`.

**Bidding** -- header bidding via Prebid.js:
- Class: `includes/class-bidding.php`.
- Gated by `NEWSPACK_ADS_EXPERIMENTAL_BIDDERS` constant.
- Registration: `Newspack_Ads\register_bidder($id, $config)` (in `includes/class-bidding.php`, bottom of file).
- Config schema: `name`, `ad_sizes`, `active_key`, `settings`.
- Built-in bidders: Medianet, OpenX, PubMatic, Sovrn (in `includes/bidders/`).
- Price granularity: low, medium, auto, dense (dense is default).
- Each bidder must have a compatible Prebid.js adapter module imported in `src/prebid/index.js`.

### GAM Provider (Deep Dive)

The GAM provider is the most complex part of the plugin.

**API Layer:**
- Built on `googleads-php-lib` (SOAP library).
- Main client: `includes/providers/gam/api/class-api.php`.
- Entity-specific classes: `class-ad-units.php`, `class-line-items.php`, `class-orders.php`, `class-creatives.php`, `class-advertisers.php`, `class-targeting-keys.php`.
- Authentication: service account credentials or OAuth proxied by Newspack Manager (`includes/providers/gam/class-gam-model.php`).

**Targeting Key-Values:**
- Auto-created keys when GAM connects: `id`, `slug`, `category`, `post_type`, `template`, `site`.
- Values populated at render time from current page context.
- Extensible via `newspack_ads_ad_targeting` filter.

**Responsive Size Mapping:**
- 600px viewport threshold separates mobile from desktop ad sizes.
- Sizes within 30% of each other are grouped together.
- "Bounds containers" detect parent elements (e.g., `.wp-block-column`) that restrict allowed sizes.
- Filterable via `newspack_ads_gam_size_map`.

**Supporting Classes:**
- `class-gam-model.php`: data model, size mapping logic, targeting.
- `class-gam-scripts.php`: renders Google Publisher Tag (GPT) scripts, bounds containers.
- `class-gam-lazy-load.php`: lazy loading of ad slots.
- `class-gam-ad-block-recovery.php`: ad block detection and recovery.

### REST API

Namespace: `newspack-ads/v1`. All endpoints require `manage_options` unless noted.

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/settings` | GET | Get all plugin settings |
| `/settings` | POST | Update settings (section + values) |
| `/placements` | GET | Get all registered placements |
| `/placements/{key}` | POST | Update placement configuration |
| `/placements/{key}` | DELETE | Disable a placement |
| `/providers` | GET | Get active providers and their units |
| `/suppression` | GET/POST | Get/update ad suppression config |
| `/bidding/gam/orders` | GET | List header bidding GAM orders |
| `/bidding/gam/order` | GET | Get specific GAM order details |

### Integrations

| Class | File | Purpose |
|-------|------|---------|
| `SCAIP` | `integrations/class-scaip.php` | Replaces SCAIP widget areas with registered placements |
| `Bidding_GAM` | `integrations/class-bidding-gam.php` | Automates GAM setup for header bidding (creates advertiser, keys, creatives, orders, line items, LICAs) |
| `Complianz` | `integrations/class-complianz.php` | Cookie consent integration for GDPR compliance |
| `Ad_Refresh_Control` | `integrations/class-ad-refresh-control.php` | Controls ad refresh frequency |
| `Side_Rail_Placements` | `integrations/class-side-rail-placements.php` | Sticky sidebar ad placements |

## Frontend (JS/SCSS)

### Webpack Entry Points

Defined in `webpack.config.js` using `newspack-scripts/config/getWebpackConfig`:

| Entry | Source | Purpose |
|-------|--------|---------|
| `editor` | All `src/blocks/*/editor.js` | Combined block editor bundle |
| `frontend` | `src/frontend/index.js` | Frontend scripts (side rail, utilities) |
| `suppress-ads` | `src/suppress-ads/index.js` | Ad suppression logic |
| `customizer-preview` | `src/customizer/preview.js` | Customizer live preview |
| `customizer-control` | `src/customizer/control.js` | Customizer controls |
| `header-bidding-gam` | `src/wizard-settings/header-bidding-gam/index.js` | GAM header bidding config |
| `prebid` | `src/prebid/index.js` | Prebid.js with bidder adapters |
| `media-kit-frontend` | `src/media-kit/index.js` | Media kit page scripts |
| `<block>/view` | Each `src/blocks/*/view.js` | Per-block frontend scripts |

Note: Prebid.js has a custom Babel configuration in `webpack.config.js` because the library's source requires specific transforms.

### Blocks

Three custom blocks:

| Block | Directory | Purpose |
|-------|-----------|---------|
| `newspack-ads/ad-unit` | `src/blocks/ad-unit/` | Place individual ad units in content. Creates dynamic placements. |
| `newspack-ads/tabs` | `src/blocks/tabs/` | Tab container for organizing ad content |
| `newspack-ads/tabs-item` | `src/blocks/tabs-item/` | Individual tab within tabs block |

Block registration follows static class pattern in `includes/blocks/`:
- `class-ad-unit-block.php` -- registers block, enqueues editor assets, render callback.
- `class-tabs-block.php`
- `class-tabs-item-block.php`

### Customizer

Visual placement configuration via WordPress Customizer:
- `includes/customizer/class-customizer.php` -- registers sections and controls.
- `src/customizer/control.js` -- placement selection controls.
- `src/customizer/preview.js` -- live preview of ad placement changes.

## Testing

```bash
n test-php                    # Run all PHPUnit tests (from within repo directory)
n test-php --filter test_name # Run specific test
npm run lint                  # Run all linters
```

- Config: `phpunit.xml` with single `main` suite.
- Bootstrap: `tests/bootstrap.php` (defines `IS_TEST_ENV` constant).
- Test files: `test-model.php`, `test-bidding.php`, `test-providers.php`, `test-settings.php`, `test-gam-api.php`.
- Mock provider: `tests/class-newspack-ads-test-provider.php`.
- No PHPUnit groups currently defined.
- No JavaScript tests.

## Hooks & Extension Points

The plugin exposes many hooks. Rather than listing them all here (they change over time), use grep to find current hooks:

```bash
grep -r 'apply_filters\|do_action' includes/ --include='*.php'
```

Key prefixes:
- `newspack_ads_*` -- general plugin hooks.
- `newspack_ads_placement_*` -- placement-specific hooks.
- `newspack_ads_gam_*` -- GAM provider hooks.

**Important filters:**

| Filter | Purpose |
|--------|---------|
| `newspack_ads_should_show_ads` | Global ad suppression |
| `newspack_ads_placements` | Modify registered placements |
| `newspack_ads_placement_data` | Modify placement data before render |
| `newspack_ads_ad_targeting` | Extend GAM targeting key-values |
| `newspack_ads_gam_size_map` | Customize responsive size mapping |
| `newspack_ads_settings_list` | Extend settings |
| `newspack_ads_bidders` | Modify registered bidders |
| `newspack_ads_prebid_ad_units` | Modify Prebid.js ad unit config |
| `newspack_ads_can_current_user_manage_settings` | Permission override |

**Important actions:**

| Action | Purpose |
|--------|---------|
| `newspack_ads_before_placement_ad` / `newspack_ads_after_placement_ad` | Before/after a placement renders |
| `newspack_ads_before_update_setting` / `newspack_ads_after_update_setting` | Before/after a setting is updated |
| `newspack_ads_setup_gam` | GAM provider setup complete |

## Recipes

### Add a new provider

1. Create `includes/providers/<name>/class-<name>-provider.php`.
2. Extend `Newspack_Ads\Providers\Provider`, implement `get_ad_code()`.
3. Set `$this->provider_id` and `$this->provider_name` in the constructor.
4. `include_once` the file in `includes/class-core.php`.
5. Register: the provider auto-registers when `Providers::init()` runs if it is instantiated via `include_once`.
6. See `class-broadstreet-provider.php` for a minimal example.

### Add a new placement

1. Call `Placements::register_placement($key, $config)` (typically in a class `init()` method hooked to `init`).
2. Config must include: `name`, `hook_name` (or `hooks` array).
3. In the theme/template, call `do_action('hook_name')` where the ad should render.
4. The placement automatically appears in the UI if `show_ui` is true (default).

### Add a new bidder

1. Create `includes/bidders/class-<name>.php`.
2. Call `Newspack_Ads\register_bidder($id, $config)` with `name`, `ad_sizes`, `active_key`, `settings`.
3. Import the corresponding Prebid.js adapter module in `src/prebid/index.js`.
4. `include_once` the file in `includes/class-core.php`.
5. Rebuild: `n build` (Prebid.js bundle changes).

### Modify GAM targeting

Use the `newspack_ads_ad_targeting` filter:

```php
add_filter( 'newspack_ads_ad_targeting', function( $targeting ) {
    $targeting['my_key'] = 'my_value';
    return $targeting;
} );
```

The key must exist in GAM (auto-created on connection or manually via the API).
