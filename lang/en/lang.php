<?php

$lang['error_types_config'] = 'Strata storage: Invalid %s configuration, falling back to <code>text</code>';

$lang['error_triples_nodriver'] = 'Strata storage: no complementary driver for PDO driver %s.';
$lang['error_triples_remove'] = 'Strata storage: Failed to remove triples: %s';
$lang['error_triples_fetch'] = 'Strata storage: Failed to fetch triples: %s';
$lang['error_triples_add'] = 'Strata storage: Failed to add triples: %s';
$lang['error_triples_query'] = 'Strata storage: Failed to execute query: %s';
$lang['error_triples_node'] = 'Strata storage: Unknown abstract query tree node type \'%s\'';

$lang['debug_sql'] = 'Debug SQL: <code>%s</code>';
$lang['debug_literals'] = 'Debug Literals: <pre>%s</pre>';

$lang['driver_failed_detail'] = 'Strata storage: Failed to open data source \'%s\': %s';
$lang['driver_failed'] = 'Strata storage: Failed to open data source.';
$lang['driver_setup_start'] = 'Strata storage: Setting up %s database.';
$lang['driver_setup_statement'] = 'Strata storage: Executing \'<code>%s</code>\'.';
$lang['driver_setup_failed'] = 'Failed to set up database';
$lang['driver_setup_succes'] = 'Strata storage: Database set up successful!';
$lang['driver_remove_failed'] = 'Failed to remove database';
$lang['driver_prepare_failed'] = 'Strata storage: Failed to prepare query \'<code>%s</code>\': %s';
$lang['driver_query_failed'] = 'Strata storage: %s (with \'<code>%s</code>\'): %s';
$lang['driver_query_failed_default'] = 'Query failed';

$lang['unnamed_group'] = 'unnamed group';
$lang['named_group'] = '\'<code>%s</code>\' group';

$lang['error_entry_block'] = 'I don\'t know what to do with the %s in the \'%s\' data entry';
$lang['error_entry_line'] = 'I don\'t understand data entry line \'<code>%s</code>\'';

$lang['error_pattern_garbage'] = 'I don\'t know what to do with the text after the object variable.';

$lang['error_query_bothfields'] = 'Query contains both <code>fields</code> group and normal selection';
$lang['error_query_fieldsgroups'] = 'I don\'t know how to handle a query containing multiple <code>fields</code> groups.';
$lang['error_query_fieldsblock'] = 'I don\'t know what to do with the %s in the <code>fields</code> group.';
$lang['error_query_noselect'] = 'I don\'t know which fields to select.';
$lang['error_query_unknownselect'] = 'Query selects unknown field \'<code>%s</code>\'.';

$lang['error_query_outofwhere'] = 'I don\'t know what to do with things outside of the <code>where</code> group.';
$lang['error_query_singlewhere'] = 'A query should contain at most a single <code>where</code> group.';

$lang['error_query_multisort'] = 'I don\'t know what to do with multiple <code>sort</code> groups.';
$lang['error_query_sortblock'] = 'I can\'t handle groups in a <code>sort</code> group.';
$lang['error_query_sortvar'] = '<code>sort</code> group uses out-of-scope variable \'<code>%s</code>\'.';
$lang['error_query_sortline'] = 'I can\'t handle line \'<code>%s</code>\' in the <code>sort</code> group.';
$lang['error_query_selectvar'] = 'selected variable \'<code>%s</code>\' is out-of-scope.';
$lang['error_query_group'] = 'Unexpected %s in query.';
$lang['error_query_unionblocks'] = 'Lines or named groups inside a <code>union</code> group. I can only handle unnamed groups inside a <code>union</code> group.';
$lang['error_query_unionreq'] = 'I need at least 2 unnamed groups inside a <code>union</code> group.';
$lang['error_query_pattern'] = 'Unknown triple pattern or filter pattern \'<code>%s</code>\'.';
$lang['error_query_fieldsline'] = 'Weird line \'<code>%s</code>\' in <code>fields</code> group.';
$lang['error_query_fieldsdoubletyped'] = 'Double type on field declaration of \'<code>%s</code>\'; using left type.';
$lang['error_syntax_braces'] = 'Unmatched braces in %s';

$lang['error_query_multigrouping'] = 'I don\'t know what to do with multiple <code>group</code> groups.';
$lang['error_query_groupblock'] = 'I can\'t handle groups in a <code>group</code> group.';
$lang['error_query_groupvar'] = '<code>group</code> group uses out-of-scope variable \'<code>%s</code>\'.';
$lang['error_query_groupline'] = 'I can\'t handle line \'<code>%s</code>\' in the <code>group</code> group.';
$lang['error_query_groupeverything'] = 'I can\'t group everything if other variables are mentioned.';

$lang['error_query_multiconsidering'] = 'I don\'t know what to do with multiple <code>consider</code> groups.';
$lang['error_query_considerblock'] = 'I can\'t handle considers in a <code>consider</code> group.';
$lang['error_query_considervar'] = '<code>consider</code> group uses out-of-scope variable \'<code>%s</code>\'.';
$lang['error_query_considerline'] = 'I can\'t handle line \'<code>%s</code>\' in the <code>consider</code> group.';

$lang['error_query_grouppattern'] = 'I can\'t handle a group without at least one triple pattern or union group.';

$lang['content_error_explanation'] = 'An error ocurred';
