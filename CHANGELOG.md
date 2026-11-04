# Changelog

All notable public changes to GLPI BrandPulse are documented here.

## [0.2.12] - 2026-11-03

### Added

- Display the BrandPulse logo in GLPI's native plugin management using a bundled
  PNG converted from the existing SVG artwork.
- Add a centered logo, title and badges to the GitHub README, following the
  presentation used by other TiniSys plugins.
- Include both PNG logo locations and the SVG source in release package checks.

## [0.2.11] - 2026-11-01

### Fixed

- Prevent public image URLs from containing embedded credentials, and omit
  disabled branding and unpublished login alerts from the public JSON response.
- Restrict uploaded asset serving to image filenames inside the branding folder.
- Remove stale counter assignments when catalogue entries or GLPI profiles are removed.
- Initialize default Pulse rights during native profile creation instead of
  changing rights when the profile tab is merely viewed.
- Strengthen Git exclusions for credentials and local development files; keep
  pre-public working history outside the public source tree.
- Remove unused icon-manifest output and the empty resource-directory build input.

## [0.2.10] - 2026-10-09

### Fixed

- Limit the composition profile dropdown to the profiles authorized in Default
  settings, updating its options immediately when the native selection changes.
- Preserve read-only authorized profiles, handle an empty profile selection and
  resolve a removed profile to an authorized one after saving and reloading.
- Keep the loaded composition context while editing permissions. Removing the
  current profile or selecting a newly authorized profile requires saving first,
  preventing accidental changes to another profile's counter assignments.
- Render profile selection counts and guidance using server-side translations.

## [0.2.9] - 2026-10-09

### Changed

- Reuse GLPI's native html5sortable bundle for Pulse composition, catalogue rows
  and ticket columns, including native drag feedback and visible drop placeholders.
- Show fixed ticket columns in GLPI's exact native order. Preserve keyboard arrows
  and prevent custom-column dragging while native display preferences are selected.
- Replace the ticket-view gear with GLPI's native `ti-table-row` icon and the
  stable label "Columns to display".
- Add a native multi-select profile field under Default settings in profile mode.
  Saving Pulse applies changes to native Pulse rights for editable profiles only;
  read-only profiles and their other rights remain untouched. Global mode keeps
  the separate native-permissions panel.

## [0.2.8] - 2026-10-09

### Added

- A separate Pulse composition panel with displayed/available counters, live
  header preview using sample values, selection limits and required-counter badges.
- Drag handles and accessible arrow controls to reorder counters, position labels,
  temporary highlighting and a live announcement for moved counters.
- A centralized profile permissions panel using the existing native Pulse right.
  Writes require native profile UPDATE permission and item-level checks, use native
  Profile updates, and keep permissions separate from composition saves.
- Show unsaved composition changes and protect drafts when switching profiles,
  leaving the page or saving the other panel.

### Fixed

- Update header previews immediately after label, color and icon edits.
- Keep each ticket view attached to its counter when changing display order.

## [0.2.7] - 2026-10-09

### Added

- Configure ticket columns, their order and ascending or descending sorting for
  each Pulse counter through a gear button in the counter catalogue.
- Reuse GLPI's native ticket search and rights-filtered column catalogue, keeping
  native fixed columns first and validating fields again for the clicking user.
- Preserve existing counters' native display preferences by default. Custom views
  only affect Pulse links and do not write personal GLPI display preferences.
- Reset prior search state when opening a Pulse link so its filters and view apply
  together, including links backed by saved searches.

## [0.2.6] - 2026-10-09

### Fixed

- Start Pulse polling on all authenticated pages, even when the header is not
  visible at startup, and immediately recompute counters when entering a page.
- Recompute counters after native GLPI AJAX saves, successful AJAX writes,
  returning to a tab and restoring a page from browser history. These refreshes
  bypass the short server cache while preserving all access checks.
- Coalesce simultaneous refresh triggers, queue a fresh request when a save
  completes during polling, and abort stalled requests after 20 seconds.
- Preserve the configured polling interval when a request fails or the page
  is hidden; automatic refresh continues without requiring F5.

## [0.2.5] - 2026-10-09

### Fixed

- Check the complete GLPI asset read permission before exposing a management IP.
- Preserve Pulse mode, counter limits and self-service settings during older
  configuration migrations; separate counter caches by language and profile rights.
- Resume counter refresh after network errors and avoid polling hidden tabs.
- Isolate cached branding between GLPI installations on the same origin and
  keep browser storage restrictions from preventing plugin startup.
- Avoid scanning the asset form after unrelated DOM changes.

### Security

- Reject SVG animation, external references and active CSS in uploaded logos,
  malformed branding URLs and unusable equivalent IPv6 addresses.
- Require native item-type read permission before running counter searches.
- Build from an explicit distribution manifest and verify the archive contents.
  The installable archive now follows the TiniSys `brandpulse-VERSION.zip` convention.

## [0.2.4] - 2026-09-28

### Added

- Add a direct management-interface action to the main form of computers,
  network equipment, printers, phones and PDUs. The preferred IP is resolved
  from the asset's GLPI network ports and opens over HTTPS, with an explicit
  HTTP fallback.
- Add a **Features** configuration tab to enable this shortcut, choose its
  preferred protocol and select the applicable asset types.
- Anchor the shortcut through GLPI's standard `pre_item_form` hook so its
  placement remains stable whether the optional Tags plugin is active or not.

### Security

- Resolve management links only for authenticated users who can view the asset,
  and accept only allowlisted asset types and validated IP addresses.

## [0.2.3] - 2026-09-16

### Changed

- Clarify in Pulse-by-profile mode when the selected GLPI profile does not have
  the required **Display General Pulse** permission, without changing profile
  rights implicitly.
- Remove unused PHP, CSS and legacy `pulse_interface` configuration remnants
  identified during the full consistency review.
- Revalidate routes, access control, CSRF protection, database lifecycle,
  GLPI 11.0.8 integration and release packaging.

### Security

- Restrict configured external brand asset references to HTTP(S), root-relative
  or relative paths and reject active or malformed URI schemes.

## [0.2.2] - 2026-09-15

### Added

- Add PHPUnit coverage for configuration normalization, asset input validation
  and per-session Pulse counter cache isolation.
- Add ordered Pulse counter assignments for each active GLPI profile.
- Prepare the resolver for a future hybrid layer of up to two personal saved
  searches per user and active profile.
- Add a Pulse setting to adjust the per-profile counter limit from six to ten,
  while retaining six as the default.

### Changed

- Replace the separate profile-assignment panel with one adaptive counter
  catalogue for mutually exclusive Global Pulse and Pulse-by-profile modes.
- Add an explicit option controlling Global Pulse in the self-service interface
  while preserving the existing profile right as the security master switch.
- Repair and validate the GitHub Actions quality workflow.
- Make development dependency installation reproducible with a committed
  Composer lock file while keeping development-only files out of releases.
- Restrict profile-level saved-search counters to public searches visible in the
  current GLPI rights and entity context.

## [0.1.3] - 2026-09-03

### Added

- Brand configuration for GLPI logos, favicon, browser title and login page.
- Login alert with icons and lightweight formatting.
- Pulse counters based on presets and saved searches.
- Profile-based Pulse visibility, diagnostic tools and bilingual interface.

### Changed

- Establish the first clean public release baseline.
- Align licensing, ownership, security and contribution documentation with
  TiniSys IT Solutions standards.

### Security

- Validate uploaded image type, MIME, size and SVG structure.
- Restrict profile-specific counters and prevent cross-session counter state.
- Document responsible vulnerability reporting and third-party icon licensing.
