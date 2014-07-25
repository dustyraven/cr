<?php
define('DS', DIRECTORY_SEPARATOR);

// comment

$files = array('index.html');


foreach(array('css', 'fonts', 'js', 'i') as $f)
	$files = array_merge($files, glob($f . DS . '*'));

$lastmod = 0;

foreach($files as $f)
{
	$lm = filemtime($f);
	if($lm > $lastmod)
		$lastmod = $lm;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-type: text/cache-manifest', true);
echo "CACHE MANIFEST\n";
echo "# v{$lastmod}:2\n";
foreach($files as $f)
	echo "$f\n";

echo "\n";
echo "NETWORK:\n";
echo "*\n";

