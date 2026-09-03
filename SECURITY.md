# Security Policy

## Supported versions

Security fixes are provided for the latest published release and the current
`main` branch.

## Reporting a vulnerability

Do not disclose suspected vulnerabilities or exposed secrets in a public
issue. Use GitHub's private vulnerability reporting feature for this
repository and include the affected version, impact, reproduction steps and
any suggested mitigation.

## Deployment guidance

- Install releases obtained from this repository and keep GLPI and PHP within
  their supported versions.
- Restrict plugin configuration to trusted GLPI administrators.
- Validate uploaded branding assets and avoid untrusted remote resources.
- Back up GLPI files and its database before installing or upgrading plugins.

GLPI is a separate project. Vulnerabilities in GLPI itself must be reported to
the GLPI maintainers through their security process.
