# Changelog

All notable public changes to GLPI BrandPulse are documented here.

Detailed pre-public iterations remain available in
[docs/DEVELOPMENT_HISTORY.md](docs/DEVELOPMENT_HISTORY.md).

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
