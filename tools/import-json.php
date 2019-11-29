#!/bin/env php
<?php
/**
 * This command line interface PHP script read a JSON file and import its data into the MySQL DB.
 *
 * The format of the JSON file is the one created by MySQL at export.
 *
 * Options -h or --help are supported to give some help.
 * The full path of the input JSON file must be given as the first argument of this script.
 *
 * 2019-11-14 written by pix@pixeye.net
 */

$config_file = "../src/config.json";

$json_config = file_get_contents($config_file, true);
$config = json_decode($json_config, true);
$db_config = $config['db'];
// printf("DB config: %s%s", var_export($db_config, true), PHP_EOL);

if (!isSet($argv) || !is_array($argv)) {
    fprintf(STDERR, "This is a CLI program!%s", PHP_EOL);
    exit(1);
}

$cmd = array_shift($argv);
$argc = count($argv);
// printf("argc = %d%s", $argc, PHP_EOL);
if ($argc!==1 || $argv[0]==='-h' || $argv[0]==='--help') {
    fprintf(STDERR, "Usage: %s <input_json_file_path>%s", basename($cmd), PHP_EOL);
    exit(2);
}

$input_file = array_shift($argv);
$json = file_get_contents($input_file, true);
$export = json_decode($json, true);
// We asume that JSON export is in MySQL format
$nb_blocks = count($export);
printf("%d block(s) found in export:%s", $nb_blocks, PHP_EOL);

$sql = '';
forEach($export as $i => $block) {
    printf("%2d/ Block type: %s%s", $i+1, $block['type'], PHP_EOL);
    if ($block['type']==='table' && isSet($block['data'])) {
        $nb_records = count($block['data']);
        $table = $block['name'];
        printf("%d records(s) found in data for table: '%s'.%s", $nb_records, $table, PHP_EOL);
        if ($nb_records<=0) {
            fprintf(STDERR, "No records to insert in DB!%s", PHP_EOL);
            exit(3);
        }

        $keys = array_keys($block['data'][0]);
        array_shift($keys); // drop ID (useless in my case)
        forEach($keys as $j => $key) {
            $keys[$j] = '`'.$key.'`';
        }
        $keys = implode(', ', $keys);
        $sql.= "INSERT INTO `$table`\n(".$keys.") VALUES";
        // printf("First SQL lines:\n %s%s", $sql, PHP_EOL);

        $values = array();
        forEach($block['data'] as $j => $record) {
            // printf("%2d/ record: %s%s", $j+1, var_export($record, true), PHP_EOL);

            $vals = array_values($record);
            array_shift($vals); // drop ID (useless in my case)

            forEach($vals as $k => $val) {
                if (!is_numeric($val)) $vals[$k] = '"'.$val.'"';
            }
            $vals = implode(', ', $vals);
            $values[] = "($vals)";
        }
        printf('%s', PHP_EOL);
        // printf("Last SQL line:\n (%s)%s", $vals, PHP_EOL);
        printf("First and last SQL lines:\n\n%s\n...\n(%s)%s%s", $sql, $vals, PHP_EOL, PHP_EOL);
        $sql.= "\n".implode(",\n", $values);
        printf("SQL now contains %d line feeds.%s", substr_count($sql, "\n"), PHP_EOL);
    }
}

$mysqli = new mysqli($db_config['host'], $db_config['user'], $db_config['pw'], $db_config['name']);

// Check connection:
if ($mysqli->connect_errno) {
    printf("Connection failed: %s%s", $mysqli->connect_error, PHP_EOL);
    exit(4);
}

if ($result = $mysqli->query($sql)) {
    printf("Result is about %d line(s).%s", $result->affected_rows, PHP_EOL);

    // Free result memory:
    $result->close();
} else {
    fprintf(STDERR, "DB error: %s%s", $mysqli->error, PHP_EOL);
}

// Close DB connection:
$mysqli->close();

// Normal exit:
exit(0);
