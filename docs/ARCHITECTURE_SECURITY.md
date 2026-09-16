# Architecture and security baseline

This document records the reviewed baseline for BrandPulse 0.2.3 on GLPI
11.0.8. It is intended for maintainers and release reviews.

## Route model

| Route | Audience | Protection | Purpose |
| --- | --- | --- | --- |
| `front/config.php` | GLPI administrators | authenticated session, `config` update right and GLPI CSRF token | plugin configuration and uploads |
| `ajax/counters.php` | authenticated users | active GLPI session and profile Pulse right | counters resolved in the current user, profile, entity and rights context |
| `ajax/branding.php` | public/login and authenticated pages | read-only JSON, no session data | non-sensitive branding payload |
| `ajax/icon.php`, `ajax/icons.php` | public/login and authenticated pages | fixed icon root, normalized paths and response hardening | bundled icon files and index |
| `front/asset.php` | public/login and authenticated pages | dedicated GLPI data directory, basename and real-path containment | uploaded branding images |
| `front/branding.css.php` | public/login and authenticated pages | generated read-only CSS with escaped URL values | pre-paint GLPI branding variables |

The public routes are required on the anonymous login page. They expose only
administrator-configured visual assets and never user, ticket or session data.

## Pulse authorization and data flow

The GLPI profile right `plugin_brandpulse_view_general_pulse` is the master
switch in both Global Pulse and Pulse-by-profile modes. Profile assignments do
not grant that right. In profile mode, the configuration page warns when the
selected profile lacks it.

Counter queries use GLPI's search engine so native item rights, active entities
and recursive scope remain authoritative. Saved-search counters accept only
public searches that GLPI reports as visible in the current administrative
context. Counter cache keys include the user, active profile, active entities,
recursion state and counter definition fingerprint.

## Stored data and lifecycle

- Scalar configuration and the counter catalogue use GLPI's configuration API
  under the `plugin:brandpulse` context.
- Per-profile ordering and visibility use
  `glpi_plugin_brandpulse_profilecounters`, with a unique profile/counter key.
- Install and migration are idempotent. Unknown profile rows are cleaned during
  migration; uninstall removes the plugin table, configuration values and
  profile rights.
- Uploaded brand images are stored in GLPI's data area rather than in the plugin
  directory so upgrades do not erase active branding.

## Input and output controls

- Configuration writes require GLPI CSRF validation.
- Image uploads are size-, extension-, MIME- and content-checked. SVG uploads
  reject scripts, event handlers, external references and active CSS constructs.
- Manually configured brand assets accept only HTTP(S), root-relative or
  relative paths; active and malformed URI schemes are discarded.
- Dynamic HTML is escaped before output. The limited login-alert formatter first
  escapes input, then enables only headings, lists, emphasis, code and HTTP(S)
  links with `noopener`.
- File-serving routes constrain paths to their intended roots and send explicit
  content types, nosniff, content-security and cache headers as appropriate.

## Release verification

Each release must run Composer validation and audit, PHPUnit, PHP and JavaScript
syntax checks, XML validation, GitHub Actions validation, translation catalogue
loading, GLPI 11.0.8 class integration and archive-content checks. Development
state (`.local`, tests, CI files and build output) must remain outside the
release archive.

After editing a PO catalogue, run `composer compile-translations` and commit the
generated MO file with its source catalogue.
