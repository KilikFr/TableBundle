# Changelog

All notable changes are documented in this file. Changes of earlier versions are listed in the
[GitHub releases](https://github.com/KilikFr/TableBundle/releases).

## 3.5.0 (unreleased)

### Added

- Symfony 8 support (8.1 and later), with DoctrineBundle 3.
- Doctrine ORM 3.7 support: column sorts accept `\SortDirection` cases (PHP 8.6, or `symfony/polyfill-php86`) in
  addition to `"asc"` and `"desc"`, with every supported Doctrine ORM version, and no deprecation is triggered.
- jQuery 4 support in `KilikTable.js`; jQuery 3 is still supported.

### Changed

- Requirements: PHP 8.2+, Symfony 6.4, 7.4 or 8.1+, Doctrine ORM 2.17+ or 3.2+, DoctrineBundle 2.11+ or 3.1+,
  Twig 3.30+. Applications that cannot meet them can keep using 3.4.
- The Symfony components used by the bundle are now declared as dependencies (config, dependency-injection,
  framework-bundle, http-foundation, http-kernel, twig-bridge, twig-bundle and yaml); they were already required
  in practice.
- `KilikTable.js` uses `.on()` and `.trigger()` instead of the event shorthands deprecated since jQuery 3.3.

### Fixed

- Fatal error with symfony/http-foundation 8, which removed `Request::get()`. It could already happen with 3.4.0
  in Symfony 7.4 applications getting http-foundation 8.
- PHP 8.4 deprecation of the implicitly nullable parameter of `Column::setFilter()`.
- `KilikTable.js` used `$.parseJSON()`, removed in jQuery 4.
- The CSV export of tables loaded from an API (`ApiTable`) failed with an undefined method error.
- A "words" filter (`TYPE_LIKE_WORDS_AND`, `TYPE_LIKE_WORDS_OR`) given a value without any word, like `0`, raised a
  PHP warning, or added the condition of the previous filter again; it is now ignored.
