# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

KilikTableBundle (`kilik/table`) is a Symfony Bundle providing AJAX-powered datagrid tables for Doctrine entities. It supports pagination, filtering (12+ filter types), column sorting, CSV export, mass actions, and API-backed data sources.

- **PHP 7.4+ / 8.0+**, Symfony 4–7, Doctrine ORM 2.5+/3.2+, Twig
- PSR-4 autoload namespace: `Kilik\TableBundle\` → `src/`

## Commands

All test/build commands run in Docker (`kilik/php:8.3-dev` image):

```bash
# Install dependencies
./prepare-tests.sh

# Run full test suite (PHPUnit via simple-phpunit)
./run-tests.sh

# Run a single test file
docker run -it --rm -u ${UID} -v $(pwd):/app -v $(pwd)/.composer:/.composer -w /app kilik/php:8.3-dev vendor/bin/simple-phpunit tests/Components/TableTest.php

# Run composer commands
./scripts/composer.sh <command>

# Code style fixing (PHP CS Fixer with @Symfony rules)
docker run -it --rm -u ${UID} -v $(pwd):/app -w /app kilik/php:8.3-dev vendor/bin/php-cs-fixer fix
```

## Architecture

### Two table modes

- **Doctrine tables** (`Table` + `TableService`): Build tables from Doctrine QueryBuilder. The service (`kilik_table`) handles filtering by modifying the QueryBuilder with WHERE clauses, ordering, and pagination.
- **API tables** (`ApiTable` + `TableApiService`): Build tables from external API data sources implementing `ApiInterface`/`ResultInterface`.

Both share a base: `AbstractTable` → `TableInterface`, `AbstractTableService` → `TableServiceInterface`.

### Core flow

1. Controller creates a `Table`, adds `Column` objects (each with optional `Filter`)
2. `TableService::handleRequest()` processes AJAX requests — applies filters/sorts to QueryBuilder, returns `JsonResponse` with rows + pagination
3. Frontend JS (`KilikTable.js`) drives the AJAX lifecycle, sends filter state, receives rendered HTML rows

### Key classes

| Class | Role |
|---|---|
| `Components\Table` | Doctrine table definition (columns, filters, query config) |
| `Components\Column` | Column definition with display, sort, and filter config |
| `Components\Filter` | Base filter with operators (LIKE, EQUAL, GREATER, LESS, NOT, etc.) |
| `Components\MassAction` | Bulk actions on selected rows |
| `Services\TableService` | Main service — form building, request handling, CSV export |

### Services registered

- `kilik_table` → `TableService` (Doctrine)
- `kilik_table_api` → `TableApiService` (API)

### Frontend assets

- `src/Resources/public/js/KilikTable.js` — jQuery-based AJAX table controller
- `src/Resources/public/css/KilikTable.css` — Table styles
- `src/Resources/views/` — Twig templates (layout, pagination, filters, themes)

## Code Style

PHP CS Fixer with `@Symfony` + `@Symfony:risky` rules, `ordered_imports`, `ordered_class_elements`. Config in `.php-cs-fixer.dist.php`.
