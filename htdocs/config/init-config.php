<?php

/**
 * Setup script TinyQueries
 *
 * This script is invoked during setup of the server by the script .extensions/tinyqueries/extensions.py 
 *
 * @author wouter@tinyqueries.com
 */

/**
 * Checks if the config file is up to date, e.g. has DB-credentials. If not, adds the credentials from the VCAP_SERVICES env var
 * Additionally sets the TinyQueries api_key and projectLabel and sends the url of this app to the tinyqueries server
 * to enable publishing of queries to the app.
 *
 */
function setup()
{
	// Read config file
	$configFile = dirname(__FILE__) . '/config.xml';

	$config = @file_get_contents( $configFile );
	
	if (!$config)
		throw new Exception('Cannot read config file');
		
	// Check if config file is already initialized
	if (strpos($config, '{driver}') === false)
		return;

	// Get credentials from Bluemix env var and app var
	$services 		= getEnvJson("VCAP_SERVICES");
	$application 	= getEnvJson("VCAP_APPLICATION");
	
	$specs 	= getDBspecs($services);
	$tqcred = getTQspecs($services);

	$config = str_replace('{driver}', 	$specs['driver'], 	$config);	
	$config = str_replace('{host}', 	$specs['hostname'], $config);	
	$config = str_replace('{name}', 	$specs['name'], 	$config);	
	$config = str_replace('{user}', 	$specs['username'], $config);	
	$config = str_replace('{password}', $specs['password'], $config);	
	
	$config = str_replace('{api_key}', 		$tqcred['api_key'], 		$config);	
	$config = str_replace('{projectLabel}', $tqcred['projectLabel'], 	$config);	
	
	$r = @file_put_contents( $configFile, $config );
	
	if (!$r)
		throw new \Exception("Cannot write configfile $configFile");
		
	$errorPublishURL = ' - you need to set publish-URL in TinyQueries manually';
		
	// Add publish_url which is needed for the TQ IDE to know where to publish the queries	
	if (!array_key_exists('uris', $application))
		throw new \Exception('Application URI not found' . $errorPublishURL);
		
	$protocol = (!array_key_exists('HTTPS', $_SERVER) || !$_SERVER['HTTPS']) ? 'http://' : 'https://';
	$specs['activeBinding']['publish_url'] 	= $protocol . $application['uris'][0] . '/api/';	
	$specs['activeBinding']['label']		= $tqcred['bindingLabel'];
		
	// Send the publish_url and DB credentials to TQ
	$ch = curl_init();

	if (!$ch) 
		throw new \Exception( 'Cannot initialize curl' . $errorPublishURL );
		
	curl_setopt($ch, CURLOPT_HEADER, true); 		// Return the headers
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);	// Return the actual reponse as string
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($specs));
	curl_setopt($ch, CURLOPT_URL, 'https://compiler1.tinyqueries.com/api/clients/projects/' . $tqcred['projectLabel'] . '/?api_key=' . $tqcred['api_key']);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // nodig omdat er anders een ssl-error is; waarschijnlijk moet er een intermediate certificaat aan curl worden gevoed.
	curl_setopt($ch, CURLOPT_HTTPHEADER,array('Expect:')); // To disable status 100 response 
	
	// Execute the API call
	$raw_data = curl_exec($ch); 
	
	if ($raw_data === false) 
		throw new \Exception('Did not receive a response from tinyqueries.com' . $errorPublishURL );
	
	// Split the headers from the actual response
	$response = explode("\r\n\r\n", $raw_data, 2);
		
	// Find the HTTP status code
	$matches = array();
	if (preg_match('/^HTTP.* ([0-9]+) /', $response[0], $matches)) 
		$status = intval($matches[1]);

	if ($status != 200)
		throw new \Exception('Received status code ' . $status . ': ' . $response[1] . $errorPublishURL);

	curl_close($ch);
	
	// Create sample DB
	$sql = @file_get_contents( dirname(__FILE__) . '/../../sample-db/classicmodels.v3.0.sql' );
	
	if (!$sql)
		throw new Exception('Cannot read sample DB file');
		
	$dsn = $specs['driver'] . ":dbname=" . $specs['name'] . ";host=" . $specs['hostname'];
	$pdo = new PDO($dsn, $specs['username'], $specs['password']);
		
	// throw exception for each error
	$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	
	$sth = $pdo->prepare($sql);
	
	$r = $sth->execute();

	if (!$r) 
	{
		$error = $sth->errorInfo();
		if ($error && is_array($error) && count($error)>=3)
			throw new \Exception($error[1] . " - " . $error[2]);
		throw new \Exception('unknown error during execution of query');
	}	
}

/**
 * Fetch DB credentials from env var
 *
 */
function getDBspecs(&$services)
{
	foreach ($services as $id => $service)
	{
		switch ($id)
		{
			case 'cleardb': 
				$specs = $service[0]['credentials'];
				$specs['driver'] = 'mysql';
				return $specs;
		}
	}
	
	throw new \Exception("Cannot find an (appropriate) SQL database service in VCAP_SERVICES");
}

/**
 * Fetch TinyQueries credentials from env var
 *
 */
function getTQspecs(&$services)
{
	if (!array_key_exists('tinyqueries', $services))
		throw new \Exception("Cannot find an TinyQueries credentials in VCAP_SERVICES");
		
	return $services['tinyqueries'][0]['credentials'];
}

/**
 * Get env var content and parse as json
 *
 */
function getEnvJson($varname)
{
	$value = getenv($varname);
	
	if (!$value)
		throw new \Exception("No $varname found");
	
	return json_decode($value, true);
}

/**
 * Run the setup
 *
 */
try
{
	setup();
}
catch (Exception $e)
{
	echo "Error during setup TinyQueries: " . $e->getMessage() . "\n";
	exit(1);
}

echo "TinyQueries setup complete\n";
exit(0);

