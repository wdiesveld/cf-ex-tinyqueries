<?php

$publishUrl = 'https://' . $_SERVER["SERVER_NAME"] . '/api/';

?>
<html>
<body>
<h1>Welcome to the TinyQueries Sample App</h1>
<p>Please follow these intructions to complete the setup:</p>
<ul>
	<li>Go to <a href="https://compiler1.tinyqueries.com/ide" target="_blank">TinyQueries</a></li>
	<li>In the config section add this URL: <code><?php echo $publishUrl; ?></code> at publish settings</li>
	<li>Click on the Setup button</li>
</ul>
</body>
</html>