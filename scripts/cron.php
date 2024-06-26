<?php
$rootPath = dirname(__DIR__);

$now = date('Y-m-d H:i:s');

exec("cd {$rootPath} && /usr/bin/git pull");

exec("php -q {$rootPath}/scripts/01_fetch_raw.php");
exec("php -q {$rootPath}/scripts/01_fetch_violation.php");
exec("php -q {$rootPath}/scripts/01_fetch_detail.php");
exec("php -q {$rootPath}/scripts/03_geocoding.php");

exec("cd {$rootPath} && /usr/bin/git add -A");

exec("cd {$rootPath} && /usr/bin/git commit --author 'auto commit <noreply@localhost>' -m 'auto update @ {$now}'");

exec("cd {$rootPath} && /usr/bin/git push origin master");