# Pulse Icons

This directory contains the local SVG icon pack used by GLPI BrandPulse Pulse counters.

The current pack is a Solar Icon Set outline pack copied into category folders. The generated index.json indexes every SVG recursively and exposes compact entries:

- `p`: stable path relative to public/icons/pulse/
- `l`: display label
- `c`: folder/category name
- `s`: search terms used by the configuration modal

Configuration values use the stable syntax:

```text
pulse:Category/Icon.svg
```

Example:

```text
pulse:Notifications/Bell.svg
pulse:List/Checklist Minimalistic.svg
pulse:Search/Magnifer.svg
```

BrandPulse stores Pulse icons as local `pulse:...` values so saves remain deterministic across plugin updates.

Regenerate the index after adding or removing SVG files:

```bash
node scripts/generate-icon-index.js
```
