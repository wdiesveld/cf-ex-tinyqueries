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
	
	$specs = getDBspecs($services);

	$config = str_replace('{driver}', 	$specs['driver'], 	$config);	
	$config = str_replace('{host}', 	$specs['hostname'], $config);	
	$config = str_replace('{name}', 	$specs['name'], 	$config);	
	$config = str_replace('{user}', 	$specs['username'], $config);	
	$config = str_replace('{password}', $specs['password'], $config);	
	
	$r = @file_put_contents( $configFile, $config );
	
	if (!$r)
		throw new \Exception("Cannot write configfile $configFile");
		
	// Send the DB credentials to TQ
	/*
	$ch = curl_init();

	if (!$ch) 
		throw new \Exception( 'Cannot initialize curl' );
		
	curl_setopt($ch, CURLOPT_HEADER, true); 		// Return the headers
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);	// Return the actual reponse as string
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($specs));
	curl_setopt($ch, CURLOPT_URL, 'https://compiler1.tinyqueries.com/api/clients/projects/' . $projectLabel . '/?api_key=' . $TQapiKey);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // nodig omdat er anders een ssl-error is; waarschijnlijk moet er een intermediate certificaat aan curl worden gevoed.
	curl_setopt($ch, CURLOPT_HTTPHEADER,array('Expect:')); // To disable status 100 response 
	
	// Execute the API call
	$raw_data = curl_exec($ch); 
	
	if ($raw_data === false) 
		throw new \Exception('Did not receive a response from tinyqueries.com');
	
	// Split the headers from the actual response
	$response = explode("\r\n\r\n", $raw_data, 2);
		
	// Find the HTTP status code
	$matches = array();
	if (preg_match('/^HTTP.* ([0-9]+) /', $response[0], $matches)) 
		$status = intval($matches[1]);

	if ($status != 200)
		throw new \Exception('Received status code ' . $status . ': ' . $response[1]);

	curl_close($ch);
	*/		
	
	return array(
		'message' => 'TinyQueries setup complete'
	);
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
	
	throw new \Exception("Cannot find an (appropriate) SQL database service in the VCAP_SERVICES");
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

