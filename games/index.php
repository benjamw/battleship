<?php

$dir = opendir('.');
$files = array( );

while (false !== ($file = readdir($dir))) {
	if (preg_match("/\\.pgn/",$file)) { // if the file is a pgn file...
		// sort the files by game id
		$key = (preg_match('/game_(\\d++)/i', $file, $match)) ? $match[1] : 0;

		// collect the complete filename
		$files[$key] = $file;
	}
}

closedir($dir);

// sort the array by key
ksort($files);

?><!DOCTYPE html>
<html lang="en-us">
<head>
	<title>PGN files for download</title>
	<style type="text/css">
		body    {font-family:sans-serif;}
		a       {color:black;}
		a:hover {text-decoration:none;}
	</style>
</head>
<body>
	<h1>Index of PGN files to download</h1>
	<a href="../">Return to Games List</a>
	<hr />
	<div>
	<?php

	if ( ! $files) {
		echo "There are currently no completed games to download.\n";
	}
	else {
		foreach ($files as $file) {
//			echo "<a href=\"../watchgame.php?file=./pgn/{$file}\">Watch Game</a> &mdash; OR ";
			echo "Download <a href=\"{$file}\">{$file}</a><br />\n";
		}
	}

	?>

	</div>
</body>
</html>