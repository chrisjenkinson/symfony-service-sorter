# symfony-service-sorter

CLI for checking and fixing service ordering in Symfony `services:` YAML files.

## What it does

`symfony-service-sorter` helps keep Symfony service definitions in a consistent order.
It provides:

- `check` to report out-of-order services
- `fix` to rewrite a file into the expected order

## Installation

Install it as a development dependency:

```bash
composer require --dev chrisjenkinson/symfony-service-sorter
```

The binary is available at `vendor/bin/sort-services`.

## Usage

Run the binary with a subcommand and one or more target YAML files:

```bash
vendor/bin/sort-services <command> <path-to-yaml-file>...
```

### Check

```bash
vendor/bin/sort-services check config/services.yaml
```

`check` exits successfully when services are already in order and reports ordering problems otherwise.

You can also check more than one file in one invocation:

```bash
vendor/bin/sort-services check config/services.yaml config/services_test.yaml
```

### Fix

```bash
vendor/bin/sort-services fix config/services.yaml
```

`fix` rewrites the file in place.

You can also fix more than one file in one invocation:

```bash
vendor/bin/sort-services fix config/services.yaml config/services_test.yaml
```

To print the sorted output instead, use:

```bash
vendor/bin/sort-services fix config/services.yaml --stdout
```

`--stdout` only supports a single file.

## Compatibility

- PHP `^8.2`
- Symfony Console `^6.0 || ^7.0 || ^8.0`

## Development

Useful local verification commands:

```bash
vendor/bin/phpunit
vendor/bin/ecs check
vendor/bin/phpstan analyse
vendor/bin/infection --threads=max --min-msi=85 --min-covered-msi=85
```

## License

`GPL-3.0-or-later`
