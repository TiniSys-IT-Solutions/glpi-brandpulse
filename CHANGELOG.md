# Changelog

All notable public changes to GLPI BrandPulse are documented here.

Detailed pre-public iterations remain available in
[docs/DEVELOPMENT_HISTORY.md](docs/DEVELOPMENT_HISTORY.md).

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
