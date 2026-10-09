# Architecture and security baseline

This document records the reviewed baseline for BrandPulse 0.2.8 against the
local GLPI 11.0.8 and 11.0.11 sources and TiniSys development reference 1.0.
It is intended for maintainers and release reviews. Loading native classes and
checking their signatures does not replace testing on an installed GLPI instance.

## Route model

| Route | Audience | Protection | Purpose |
| --- | --- | --- | --- |
| `front/config.php` | GLPI administrators | authenticated session, `config` update right and GLPI CSRF token | plugin configuration and uploads |
| `ajax/counters.php` | authenticated users | active GLPI session and profile Pulse right | counters resolved in the current user, profile, entity and rights context |
| `ajax/asset-management-link.php` | authenticated users | active GLPI session, asset view permission, allowlisted item type | validated management IP resolved from the asset's network ports |
| `ajax/branding.php` | public/login and authenticated pages | read-only JSON, no session data | non-sensitive branding payload |
| `ajax/icon.php`, `ajax/icons.php` | public/login and authenticated pages | fixed icon root, normalized paths and response hardening | bundled icon files and index |
| `front/asset.php` | public/login and authenticated pages | dedicated GLPI data directory, basename and real-path containment | uploaded branding images |
| `front/branding.css.php` | public/login and authenticated pages | generated read-only CSS with escaped URL values | pre-paint GLPI branding variables |

The direct-equipment action uses GLPI's standard `pre_item_form` hook for the
supported asset classes. The hook renders a dedicated placeholder inside
`#mainformtable`; it does not depend on fields or markup contributed by the Tags
plugin or any other optional module.

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
recursion state, profile rights, language and counter definition fingerprint.
Native item-type read permission is checked before executing a search. Management
links use `CommonDBTM::can($id, READ)` to check both type rights and entity scope.

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
- SVG validation also rejects animation, stylesheet processing instructions,
  relative external references and escaped CSS resource references.
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
release archive. The build uses an explicit file manifest and validates required
files, archive integrity and forbidden paths before reporting success. It produces
`dist/brandpulse/` and `dist/brandpulse-VERSION.zip`.

Counter polling starts independently of header visibility, retries after a failed
request and defers requests while the tab is hidden. Page entry, native GLPI AJAX
saves, successful AJAX writes inside GLPI, focus, visibility restoration and
browser history restoration request fresh results through `fresh=1`. This bypasses
only the 15-second counter cache; the service still checks the active profile,
user, entity scope and native item rights, and replaces the cache with the result.
Requests never overlap; a save during an active request queues one fresh request.
A 20-second timeout keeps stalled requests from stopping polling. Repeated event
triggers are coalesced, and ordinary periodic refreshes retain the short cache.

Branding storage keys include the plugin base URL to isolate different
GLPI installations sharing an origin. Storage errors cannot block startup.

After editing a PO catalogue, run `composer compile-translations` and commit the
generated MO file with its source catalogue.

## Per-counter ticket views

`TicketView` normalizes optional presentation settings stored with each counter.
Legacy counters keep native preferences by default and need no database migration.
The configuration modal reads columns from `Search::getCleanedOptions` and marks
`Search::addDefaultToView` columns as fixed. Applying the modal updates a draft;
the existing CSRF-protected configuration form persists it on Save.

`CounterService` applies `forcetoview`, `sort` and `order` only to Ticket navigation
links, validating fields again against the clicking user's native rights. Counter
queries remain unchanged. Native GLPI search handles rendering, fixed columns and
pagination. `reset=reset` clears prior session search state; native display
preferences are never written. Non-Ticket links ignore ticket presentation settings.
A zero sentinel preserves the request for fixed columns only when the optional
column list is empty; GLPI removes that nonexistent field during native validation.

## Composition and centralized profile permissions

Composition controls operate on the existing catalogue form rows; there is no
second persisted list. On submit, names are renumbered while stable catalogue
positions, counter keys and view JSON remain attached to their rows. Profile mode
continues to save assignment order independently of catalogue order. Previews use
sample counts and never query ticket data. Native table controls remain available.

`ProfileAccess` reads the native Pulse right and uses `Profile::can(id, UPDATE)`
plus the native profile UPDATE right before any write. It validates all submitted
targets before starting a transaction, invokes native Profile updates with rights
form inputs, and reloads the current profile after commit. Native hooks update
history and rights invalidation. Other rights are not supplied or altered.

The CSRF-protected profile-permissions form is separate from the composition form;
its explicit action cannot fall through into catalogue saving. Failed native writes
roll back the transaction. Draft guards warn before profile changes, page exits or
saving the other form. No schema migration is required.
