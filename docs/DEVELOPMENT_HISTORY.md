# Development history

## 0.2.2 — adaptive global/profile Pulse configuration

- Added mutually exclusive Global Pulse and Pulse-by-profile operating modes.
- Kept the existing counter catalogue as the single configuration table and made
  its visibility, ordering, required state and profile selector context-aware.
- Added an explicit Global Pulse self-service option without bypassing the
  existing GLPI profile permission.
- Preserved stored profile assignments when switching temporarily to global mode.

## 0.2.0 — profile-targeted Pulse

- Added ordered counter assignments for each GLPI profile.
- Kept the existing global catalogue as the migration-safe source of counter
  definitions.
- Added a normalized profile-assignment table with active, position and mandatory
  state.
- Prepared a bounded hybrid resolver for future user-owned saved-search additions
  linked to the active profile.
- Strengthened saved-search visibility checks and avoided calculating counters
  that are not assigned to the active profile.
- Kept six counters as the default profile limit and added an administrator
  slider allowing a controlled increase up to ten.

This document preserves the detailed pre-public development history. These
entries describe internal iterations and do not represent the current public
release sequence.

# Changelog

## Unreleased

- Align project licensing, ownership metadata, security guidance and
  contribution documentation with TiniSys IT Solutions standards.
- Record the Solar Icon Set attribution and applicable third-party licence.

## 0.1.43

- Replace URL-based Pulse visibility with the native GLPI profile right "Display General Pulse".
- Grant the new right by default to central profiles and deny it by default to helpdesk profiles.
- Check the profile right before calculating counters and allow the authenticated endpoint in both GLPI interfaces.
- Remove browser-side counter hydration so counters from a previous account or profile can never flash in another session.

## 0.1.42

- Add a default-enabled option to hide Pulse counters in the GLPI service catalog.
- Rename the existing counter table to General Pulse in preparation for a dedicated service-catalog configuration.

## 0.1.41

- Present Brand image groups as lighter collapsible sections and keep anchored headings below the GLPI header.
- Align Pulse default-setting labels and place both controls consistently after their text.

## 0.1.40

- Cache contextual Pulse counter results for 15 seconds and stagger browser refreshes to reduce simultaneous GLPI searches.
- Reuse saved-search data within each request instead of loading it twice.
- Harden uploaded image validation with MIME checks and structured SVG inspection.
- Add restrictive response headers for uploaded and bundled SVG assets.
- Clarify GLPI 11 compatibility metadata and add automated PHP 8.2/8.4 quality checks.

## 0.1.39

- Make the login background cover the full anonymous page and increase the login logo size.
- Simplify the Login alert tab by removing the duplicate alert visibility switch and adding a lightweight markdown toolbar.
- Move Pulse refresh and compact-search options into a dedicated Default settings block above the Pulse counters table.

## 0.1.38

- Clear GLPI translation cache during BrandPulse install/update/uninstall so old corrupted locale catalogs cannot keep rendering mixed labels.
- Validate the shipped gettext MO catalogs with GLPI's Laminas gettext loader.

## 0.1.37

- Promote Login alert to a dedicated top-level configuration tab.
- Keep Brand asset saves from overwriting existing login alert settings.
- Rebuild gettext catalogs with correct MO offsets so translated descriptions no longer appear mixed.

## 0.1.36

- Move version, schema and asset checks into a dedicated Diagnostic tab and remove the introductory text from the configuration page.
- Split Login alert from Login page assets, with icon selection, markdown-like formatting and long-message expansion controls.
- Add BrandPulse to the GLPI Tools menu and polish the Brand/Pulse tab and Pulse toolbar layout.

## 0.1.35

- Cache bundled Pulse SVG icons with ETag, Last-Modified and immutable browser cache headers so icons are not refetched on every page navigation.
- Remove the obsolete Install BrandPulse panel now that branding is applied through the dynamic plugin CSS endpoint.
- Increase the Brand sidebar logo display area for expanded and collapsed menus.

## 0.1.34

- Load Brand logo variables through a dynamic plugin CSS endpoint registered in GLPI CSS hooks so logos are available before first page paint.
- Stop using JavaScript to set GLPI logo CSS variables, avoiding the native GLPI logo flash on page navigation.
- Reframe the Brand install panel as a cleanup note for old entity CSS snippets instead of a CSS block to paste.

## 0.1.33

- Keep generated GLPI entity CSS free of BrandPulse image endpoint URLs so native GLPI logos return cleanly when the plugin is inactive or unavailable.
- Cache imported Brand image files through their immutable `front/asset.php?file=...` URLs while keeping `field` endpoints dynamic.
- Apply Brand logo layout only while the active plugin JavaScript has marked the page as BrandPulse-branded.

## 0.1.32

- Stop applying login logos directly on GLPI `.glpi-logo` spans to avoid duplicated logo rendering on the login page.
- Avoid stacking the login background on both `body` and `.page-anonymous` in the JavaScript fallback CSS.

## 0.1.31

- Increase the generated GLPI entity CSS sidebar logo display area for expanded and collapsed menus.
- Expose BrandPulse sidebar logo sizing variables in the generated CSS block.

## 0.1.30

- Allow WebP uploads for Brand sidebar and login logos in addition to SVG and PNG.
- Update Brand image guidance and gettext catalogs to mention WebP logo support.

## 0.1.29

- Add a recognizable BrandPulse header comment to the generated entity CSS block.
- Add a copy button for the generated Brand entity CSS.

## 0.1.28

- Add a BrandPulse installation CSS block designed to be pasted once into GLPI entity interface customization.
- Serve Brand image slots through stable `front/asset.php?field=...` endpoints so changing images does not require editing entity CSS again.
- Stop applying sidebar logos directly on GLPI `.glpi-logo` spans to avoid expanded/collapsed logo overlap and stale reduced logos after sidebar toggles.

## 0.1.27

- Add a prominent Pulse activation switch matching the Brand activation panel.
- Update English and French gettext catalogs for the new Brand and Pulse settings labels.
- Rework the Brand settings page with internal section navigation, denser image fields and a collapsed diagnostic area.

## 0.1.26

- Make the Brand activation switch prominent at the top of the Brand settings page.
- Add GLPI 11 image size guidance for favicon, expanded logos, collapsed logos, login logos and login background.

## 0.1.25

- Apply Brand logos through GLPI 11 official CSS variables so header, sidebar, collapsed menu and login logos update reliably.
- Detect GLPI 11 dark theme metadata when selecting theme-aware Brand assets.
- Target GLPI 11 anonymous login layout for login logo, background and alert rendering.

## 0.1.24

- Persist the last Pulse header state in browser local storage so it survives GLPI page navigations more reliably.
- Register a GLPI 11 head-loaded compact-search stylesheet only when the Pulse compact search option is enabled.
- Align Pulse header/search integration with GLPI 11 `page_header`, `user_header` and `global_search_form` selectors.
- Rehydrate cached Pulse counters as soon as the header appears, including on delayed header DOM insertion.

## 0.1.23

- Rehydrate Pulse counters and compact search from the last successful browser payload when navigating between GLPI pages.
- Refresh existing Pulse counter badges in place when only counts, colours or links change.

## 0.1.22

- Fix compact global search so GLPI native search buttons do not create a second magnifier.
- Keep the compact search container constrained to avoid growing the GLPI header.

## 0.1.21

- Use the validated SVG endpoint for Pulse icon previews in the configuration table.
- Add Pulse row ordering controls and save counters in the visible table order.
- Replace pre-rendered empty Pulse rows with an on-demand add button.
- Regenerate the Pulse icon manifest from the full local SVG pack.
- Add a category filter next to the Pulse icon picker search field.

## 0.1.20

- Fix Pulse saves so each row preserves its selected SVG icon instead of falling back to the default icon.
- Validate stored Pulse icons against the bundled local SVG pack.
- Align release metadata with GLPI 11 plugin catalogue packaging for v0.1.20.

## 0.1.19

- Remove the custom SVG URL field from Pulse rows.
- Save only the selected Pulse icon value and serve header icons through the GLPI SVG endpoint.

## 0.1.18

- Serve individual Pulse SVG icons through a validated GLPI endpoint.
- Remove inherited CSS mask styling from icon picker image previews.

## 0.1.17

- Render Pulse icon picker previews as real SVG images instead of CSS masks.
- Improve icon picker pagination wording and missing-image fallback styling.

## 0.1.16

- Serve the Pulse icon manifest through a GLPI AJAX endpoint.
- Keep the static icon manifest as a browser-side fallback.

## 0.1.15

- Fix frontend plugin base URL detection when GLPI serves plugin assets without the public path segment.
- Restore Pulse counters and SVG icon picker endpoints after the v0.1.14 path regression.

## 0.1.14

- Fix gettext MO generation so accented French labels render correctly.
- Make Brand and Pulse frontend asset URLs resilient to GLPI plugin path variants.
- Load Brand assets on anonymous pages when the GLPI hook is available.
- Improve GLPI 11 logo replacement across image, background and navbar brand containers.
- Restore Pulse icon picker results when the manifest only exposes the compact icons index.

## 0.1.13

- Extend Brand configuration with theme-aware logos for light, dark and neutral variants.
- Add separate logo slots for expanded sidebar, collapsed sidebar and login views.
- Add per-asset diagnostics, stricter file type validation and clearer Brand sections.
- Keep legacy `menu_logo` and `login_logo` values as fallback during migration.

## 0.1.12

- Align Brand image imports with GLPI plugin file storage under `GLPI_PLUGIN_DOC_DIR/brandpulse/brand`.
- Serve imported Brand images through a plugin endpoint and show the storage path in the Brand tab.
- Use GLPI canonical `/plugins/brandpulse` URLs for plugin assets, including Pulse SVG icons.
- Improve Pulse icon contrast for light, dark and custom header themes.
- Refresh the Brand tab layout with grouped upload controls and image previews.

## 0.1.11

- Add URL-or-upload controls for Brand assets and fill the stored URL after image import.
- Store imported Brand images under `GLPI_PLUGIN_DOC_DIR/brandpulse/brand` with image type checks and FR/EN messages.

## 0.1.10

- Remove the duplicate legacy `Session::checkCSRF()` call from the configuration page and rely on GLPI 11 request CSRF handling.
- Send a resolved `icon_url` for each Pulse counter so header SVG masks work from both `plugins` and `marketplace` installs.

## 0.1.9

- Derive BrandPulse public URLs from the actual loaded script URL so marketplace and plugins installs both resolve SVG icons correctly.
- Simplify the Pulse icon picker to one flat 170-icon index with search and 24-icon pagination, without category chunks.
- Remove category JSON chunks from the bundled icon pack to avoid extra requests and stale category state.

## 0.1.8

- Curate the bundled Solar icon subset for Pulse counters around medical, IT and logistics use cases.
- Remove decorative categories and lookalike variants from the picker to reduce visual noise and loading work.
- Regenerate the lazy icon index with 170 focused SVG icons across 20 categories.

## 0.1.7

- Add a robust GLPI bootstrap for direct plugin front/ajax entrypoints without emitting PHP warnings.
- Prevent BrandPulse JavaScript from running Pulse calls outside real HTML header pages.
- Align GLPI 11 public asset URLs by removing `/public` from CSS, JavaScript and SVG web paths.
- Declare legacy script firewall strategies for the configuration page and AJAX endpoints.
- Split the Solar icon picker into a lightweight index and lazy category chunks instead of loading the whole icon database at popup opening.

## 0.1.6

- Keep Pulse save actions visible at the top of the tab and as a floating bottom action.
- Simplify Pulse rows to `Source` plus one contextual `Target` column.
- Reduce icon picker work with a slimmer manifest, 24 icons per page and debounced search rendering.

## 0.1.5

- Replace the inline icon grid with a compact modal picker with search and pagination.
- Generate a recursive manifest for the Solar outline SVG icon subset copied into `public/icons/pulse`.
- Keep the Pulse save button visible with a sticky action bar on long configuration screens.
- Store local icon choices as stable `pulse:Category/Icon.svg` values.

## 0.1.4

- Count saved-search Pulse counters through GLPI `Search` parameters instead of calling `SavedSearch::execute()` directly.
- Protect BrandPulse JSON endpoints from PHP warnings or incidental output corrupting AJAX responses.
- Render Pulse SVG icons as CSS masks so they adapt to light, dark and custom header colours.
- Add a visual icon picker, custom SVG URL/path support and a broader local SVG icon pack.

## 0.1.3

- Use GLPI native SavedSearch and Search mechanisms for Pulse counter criteria.
- Let Pulse counters target GLPI saved ticket searches, preserving native AND/OR criteria and metacriteria.
- Run standard presets through the GLPI search engine instead of custom SQL counters.
- Replace category/group-specific Pulse settings with a saved-search selector.

## 0.1.2

- Fix GLPI 11 marketplace front/ajax bootstrap by removing legacy `inc/includes.php` includes.
- Render Pulse counters in the top header navbar instead of the left sidebar menu.
- Replace raw JSON configuration with Brand and Pulse tabs.
- Add Brand settings for title, favicon, login logo, left menu logo, login background and login alert message.
- Add Pulse rows with icon, color, thresholds and selectable ticket category/group scope.
- Remove non-standard historical presets and legacy hard-coded exclusions.

## 0.1.1

- Restrict Pulse counters to the GLPI central interface only.
- Keep Pulse away from the helpdesk/self-service and catalogue portal.
- Add optional compact global search mode with a magnifier trigger.
- Add the local `pulse:search` SVG icon.
- Update GLPI catalog metadata for the `v0.1.1` release archive.

## 0.1.0

- Initial GLPI BrandPulse plugin skeleton.
- Add GLPI 11 plugin declaration and hooks.
- Add header counters endpoint and frontend renderer.
- Add historical counter presets from the previous local Modifications fork.
- Add configuration defaults for future branding support.
- Add GitHub tag-based release workflow and installable ZIP packaging.
- Add schema version tracking and idempotent plugin migration mechanism.
- Add GLPI catalog metadata file for Marketplace/update publication readiness.
- Add local SVG Pulse icon pack and default counter icon mapping.
- Add gettext FR/EN catalogs using the `brandpulse` translation domain.
