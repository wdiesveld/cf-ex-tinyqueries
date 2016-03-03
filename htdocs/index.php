<?php

$publishUrl = 'https://' . $_SERVER["SERVER_NAME"] . '/api/';

?>
<html>
<body>
<h1>Welcome to the TinyQueries Sample App</h1>
<p>Please follow these intructions to complete the setup:</p>
<ul>
	<li>Go to <a href="https://compiler1.tinyqueries.com/ide" target="_blank">TinyQueries</a></li>
	<li>Ensure that in the config section this URL <code><?php echo $publishUrl; ?></code> is set at publish settings</li>
</ul>
</body>
</html>