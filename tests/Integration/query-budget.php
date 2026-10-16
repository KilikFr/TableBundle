<?php

/*
 * Maximum number of SQL queries per scenario of QueryCountTest.
 *
 * Reference measured on 3.4.0 (identical on SQLite, MySQL and PostgreSQL). Lower a value when an optimization
 * reduces the number of queries, never raise it without a reason.
 */
return [
    // total COUNT (also the filtered one, without filter) + page (objects, then scalars from the same result)
    'refresh_legacy' => 2,
    'refresh_legacy_group_by' => 2,
    // total COUNT + filtered COUNT + page (objects, then scalars from the same result)
    'refresh_legacy_filtered_sorted' => 3,
    // total COUNT (also the filtered one, without filter) + page (scalars) + entities
    'refresh_repository' => 3,
    'refresh_callback' => 3,
    // total COUNT (also the filtered one, without filter) + page (scalars)
    'refresh_without_entities' => 2,
    'refresh_identifier_count' => 4,
    // refresh_legacy_group_by + totals
    'refresh_with_totals' => 3,
    'handle_request' => 3,
    // rows (scalars)
    'export_csv' => 1,
    // rows (scalars) + totals
    'export_csv_with_totals' => 2,
    'selected_rows_repository' => 1,
];
