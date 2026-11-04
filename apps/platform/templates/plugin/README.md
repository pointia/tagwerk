# acme/example

A [Tagwerk](https://github.com/pointia/tagwerk) plugin.

## Install

In the Tagwerk checkout (`apps/platform`):

```bash
composer require acme/example
```

The package lives in `plugins/`, which Composer reads as a path repository.
Tagwerk loads it through `extra.tagwerk.provider`; a plugin that fails is
left out and shown under Operations → Plugins.

## Licence

Choose one and put it in `LICENSE` and `composer.json` (`license`). This
plugin builds only on `tagwerk/plugin-api` (MIT), so the choice is yours.

## Tests

```bash
composer install && vendor/bin/phpunit
```

The tests use `Tagwerk\PluginApi\Testing\PluginAssertions` and run without
a Tagwerk checkout, so the package can live in its own repository;
`.github/workflows/tests.yml` runs them there on every push. `AGENTS.md`
holds the rules for anyone, person or coding assistant, changing it.
