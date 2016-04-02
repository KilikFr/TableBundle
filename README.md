KilikTableBundle is a fast, modern, and easy-to-use way to manipulate paginated 
information, with filtering and ordering features, with ajax queries.

This bundle is a work in progress.

Links:
- [Live demo](http://tabledemo.kilik.fr/)
- [KilikTableDemoBundle](https://github.com/KilikFr/TableDemoBundle)

Working features:
- pagination
- basic filtering (like %...%)
- advanced filtering (<,>,<=,>=,=,!,!=)
- ordering by column (and reverse)
- basic table template extendable
- keep filters and orders in browser local storage (api REST)
- filtering on queries with group by
- show ordered column (normal and reverse)
- max items per page selector (customizable)
- delay on keyup events (to prevent multiple reloads)
- checkbox and select filter

Planned features:
- more translations
- customize visibles colums (hide/show checkboxes)
- clean/reset filters in local storage browser
- add advanced templates
- loading image waiting ajax response
- new column display types (now: only raw text value)
- column display callback
- multiple lists on one page (binding jquery functions on good scopes, perhaps it's already working ?)
- easy export of filtered rows
