<?php
/**
 * This module can be used to setup TinyQueries in a CloudFoundry environment
 *
 * usage:
 *
 * 		TinyQueries\SetupCloudFoundry::run();
 *
 * @author wouter@tinyqueries.com
 */
namespace TinyQueries;

class SetupCloudFoundry
{
	/**
	 * Runs the setup:
	 * Checks if the config file is up to date, e.g. has DB-credentials. If not, adds the credentials from the VCAP_SERVICES env var
	 * Additionally sets the TinyQueries api_key and projectLabel and sends the url of this app to the tinyqueries server
	 * to enable publishing of queries to the app.
	 *
	 * @return Returns an the database and tinyqueries credentials
	 */
	public static function run()
	{
		// Get credentials from CloudFoundry env var and app var
		$services 		= self::getEnvJson("VCAP_SERVICES");
		$application 	= self::getEnvJson("VCAP_APPLICATION");
		$dbcred 		= self::getDBcred($services);
		$tqcred 		= self::getTQcred($services);
		
		// Initializes config.xml
		self::initConfigFile($dbcred, $tqcred);
			
		// Send the publish_url to TQ
		self::sendPublishUrl($tqcred, $application, $dbcred['database']);
		
		return array( $dbcred, $tqcred );
	}
	
	/**
	 * Initializes config.xml
	 *
	 */
	public static function initConfigFile($dbcred, $tqcred)
	{
		// Read config file
		$configFile = dirname(__FILE__) . '/../../config/config.xml';

		$config = @file_get_contents( $configFile );
		
		if (!$config)
			throw new \Exception('Cannot read config file');
			
		// Check if config file is already initialized
		if (strpos($config, '{driver}') === false)
			return;

		// Fill in template vars
		$config = str_replace('{driver}', 	$dbcred['driver'], 		$config);	
		$config = str_replace('{host}', 	$dbcred['hostname'], 	$config);	
		$config = str_replace('{port}', 	$dbcred['port'], 		$config);	
		$config = str_replace('{name}', 	$dbcred['name'], 		$config);	
		$config = str_replace('{user}', 	$dbcred['username'], 	$config);	
		$config = str_replace('{password}', $dbcred['password'], 	$config);	
		
		$config = str_replace('{api_key}', 		$tqcred['api_key'], 		$config);	
		$config = str_replace('{projectLabel}', $tqcred['projectLabel'], 	$config);	
		
		$r = @file_put_contents( $configFile, $config );
		
		if (!$r)
			throw new \Exception("Cannot write configfile $configFile");
	}

	/**
	 * Uses curl to send the url of this app to the tinyqueries server
	 *
	 */
	public static function sendPublishUrl($tqcred, $application, $database)
	{
		$errorPublishURL = ' - you need to set publish-URL in TinyQueries manually';
			
		// Add publish_url which is needed for the TQ IDE to know where to publish the queries	
		if (!array_key_exists('uris', $application))
			throw new \Exception('Application URI not found' . $errorPublishURL);
		
		// This will be sent to tinyqueries
		$curlBody = array();	
			
		$protocol = (!array_key_exists('HTTPS', $_SERVER) || !$_SERVER['HTTPS']) ? 'http://' : 'https://';
		$curlBody['activeBinding']['publish_url']	= $protocol . $application['uris'][0] . '/admin/';	
		$curlBody['activeBinding']['label']			= $tqcred['bindingLabel'];
		$curlBody['database']						= $database;
			
		// Init curl
		$ch = curl_init();

		if (!$ch) 
			throw new \Exception( 'Cannot initialize curl' . $errorPublishURL );
			
		// Set options
		curl_setopt($ch, CURLOPT_HEADER, true); 		// Return the headers
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);	// Return the actual reponse as string
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($curlBody));
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
	}
	
	/**
	 * Converts an db URI to credentials
	 *
	 * example:
	 * postgres://[username]:[password]@[host]:[port]/[databasename]
	 */
	public static function dbUriToCredentials($uri)
	{
		$cred = array();
		
		list($uri,$params)							= explode('?', $uri);
		list($cred['database'], $uri) 				= explode('://', $uri);
		list($uri, $cred['name']) 					= explode('/', $uri);
		list($cred['username'],$uri,$cred['port']) 	= explode(':', $uri);
		list($cred['password'],$cred['hostname']) 	= explode('@', $uri);
		
		switch ($cred['database'])
		{
			// 'driver' is the name of the driver for PDO
			case 'postgres': 	$cred['driver'] = 'pgsql'; 	break;
			case 'mysql':		$cred['driver'] = 'mysql'; 	break;
			case 'db2':			$cred['driver'] = 'ibm';	break;
			default: throw new Exception('No driver known for database ' . $cred['database']);
		}
		
		return $cred;
	}
	
	/**
	 * Fetch DB credentials from env var
	 *
	 */
	public static function getDBcred(&$services)
	{
		foreach ($services as $id => $service)
		{
			switch ($id)
			{
				case 'cleardb': 
				case 'sqldb':
				case 'elephantsql':
					return self::dbUriToCredentials( $service[0]['credentials']['uri'] );
			}
		}
		
		throw new \Exception("Cannot find an (appropriate) SQL database service in VCAP_SERVICES");
	}

	/**
	 * Fetch TinyQueries credentials from env var
	 *
	 */
	public static function getTQcred(&$services)
	{
		if (!array_key_exists('tinyqueries', $services))
			throw new \Exception("Cannot find TinyQueries credentials in VCAP_SERVICES");
			
		return $services['tinyqueries'][0]['credentials'];
	}

	/**
	 * Get env var content and parse as json
	 *
	 */
	public static function getEnvJson($varname)
	{
		$value = getenv($varname);
		
		if (!$value)
			throw new \Exception("No $varname found");
		
		return json_decode($value, true);
	}
}