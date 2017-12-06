<?php
$db_name = isset($argv[1]) ? $argv[1] : false;
if($db_name === false) {
    exit(1);
}
$error_message = 'An error occurred when attempting to set administrator permission. Please navigate to your new site and add permissions to your new content types here admin/people/permissions.';
$permissions = [];
$bundles = [];

try {
    $pdo = new \PDO("pgsql:host=postgres;port=5432;dbname={$db_name};user=tripal;password=secret");
} catch (\Exception $exception) {
    exit(1);
}

foreach ($pdo->query("SELECT name FROM tripal_bundle") as $row) {
    array_push($bundles, $row);
}

foreach ($bundles as $bundle) {
    array_push(
        $permissions,
        ' view '.$bundle[0],
        ' create '.$bundle[0],
        ' edit '.$bundle[0],
        ' delete '.$bundle[0]
    );
}

$string_permissions = implode(",", $permissions);
$args = ['administrator', $string_permissions];
$args = implode(' ', $args);
exit(system("drush --root=/var/www/html role-add-perm {$args}") ? 0 : 1);