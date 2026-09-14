# Contributing

Contributions are welcome through focused issues and pull requests.

## Before opening a pull request

1. Target the `main` branch and keep the change limited to one subject.
2. Do not commit secrets, generated release archives or private GLPI data.
3. Preserve compatibility with the GLPI and PHP versions documented in the
   README.
4. Run the available quality checks and describe any manual validation.
5. Update `CHANGELOG.md` for user-visible changes.

Run the same checks as the continuous-integration workflow before submitting:

```bash
composer install
composer validate --no-check-publish
composer test
find . -path ./vendor -prune -o -path ./dist -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
node --check public/js/brandpulse.js
php -r 'exit(simplexml_load_file("brandpulse.xml") === false ? 1 : 0);'
```

By contributing, you agree that your contribution is licensed under
GPL-3.0-or-later, the licence of this project.
