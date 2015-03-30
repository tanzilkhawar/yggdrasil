#!/usr/bin/php -q
<?php
/* SVN FILE: $Id: bake.php 3824 2006-11-03 23:37:01Z phpnut $ */
/**
 * Command-line code generation utility to automate programmer chores.
 *
 * Bake is CakePHP's code generation script, which can help you kickstart
 * application development by writing fully functional skeleton controllers,
 * models, and views. Going further, Bake can also write Unit Tests for you.
 *
 * PHP versions 4 and 5
 *
 * CakePHP :  Rapid Development Framework <http://www.cakephp.org/>
 * Copyright (c)	2005, Cake Software Foundation, Inc.
 *								1785 E. Sahara Avenue, Suite 490-204
 *								Las Vegas, Nevada 89104
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright		Copyright (c) 2005, Cake Software Foundation, Inc.
 * @link				http://www.cakefoundation.org/projects/info/cakephp CakePHP Project
 * @package			cake
 * @subpackage		cake.cake.scripts.bake
 * @since			CakePHP v 0.10.0.1232
 * @version			$Revision: 3824 $
 * @modifiedby		$LastChangedBy: phpnut $
 * @lastmodified	$Date: 2006-11-03 17:37:01 -0600 (Fri, 03 Nov 2006) $
 * @license			http://www.opensource.org/licenses/mit-license.php The MIT License
 */
	define ('DS', DIRECTORY_SEPARATOR);
	if (function_exists('ini_set')) {
		ini_set('display_errors', '1');
		ini_set('error_reporting', '7');
	}

	$app = null;
	$root = dirname(dirname(dirname(__FILE__)));
	$core = null;
	$here = $argv[0];
	$help = null;
	$project = null;

	for ($i = 1; $i < count($argv); $i += 2) {
		switch ($argv[$i]) {
			case '-a':
			case '-app':
				$app = $argv[$i + 1];
			break;
			case '-c':
			case '-core':
				$core = $argv[$i + 1];
			break;
			case '-r':
			case '-root':
				$root = $argv[$i + 1];
			break;
			case '-h':
			case '-help':
				$help = true;
			break;
			case '-p':
			case '-project':
				$project = true;
				$projectPath = $argv[$i + 1];
				$app = $argv[$i + 1];
			break;
		}
	}
	if(!$app) {
		$app = $argv[1];
	}
	if(!is_dir($app)) {
		$project = true;
		$projectPath = $app;

	}

	if($project) {
		$app = $projectPath;
	}

	$shortPath = str_replace($root, '', $app);
	$shortPath = str_replace('../', '', $shortPath);
	$shortPath = str_replace('//', '/', $shortPath);

	$pathArray = explode('/', $shortPath);
	$appDir = array_pop($pathArray);
	$rootDir = implode('/', $pathArray);
	$rootDir = str_replace('//', '', $rootDir);

	if(!$rootDir) {
		$rootDir = $root;
		$projectPath = $root.DS.$appDir;
	}

	define ('ROOT', $rootDir);
	define ('APP_DIR', $appDir);

	define ('DEBUG', 1);;
	define('CAKE_CORE_INCLUDE_PATH', $root);

	if(function_exists('ini_set')) {
		ini_set('include_path',ini_get('include_path').
													PATH_SEPARATOR.CAKE_CORE_INCLUDE_PATH.DS.
													PATH_SEPARATOR.ROOT.DS.APP_DIR.DS);
		define('APP_PATH', null);
		define('CORE_PATH', null);
	} else {
		define('APP_PATH', ROOT . DS . APP_DIR . DS);
		define('CORE_PATH', CAKE_CORE_INCLUDE_PATH . DS);
	}

	require_once (CORE_PATH.'cake'.DS.'basics.php');
	require_once (CORE_PATH.'cake'.DS.'config'.DS.'paths.php');
	require_once (CORE_PATH.'cake'.DS.'dispatcher.php');
	require_once (CORE_PATH.'cake'.DS.'scripts'.DS.'templates'.DS.'skel'.DS.'config'.DS.'core.php');
	uses ('inflector', 'model'.DS.'model');
	require_once (CORE_PATH.'cake'.DS.'app_model.php');
	require_once (CORE_PATH.'cake'.DS.'app_controller.php');
	uses ('neat_array', 'model'.DS.'connection_manager', 'controller'.DS.'controller', 'session',
			'configure', 'security', DS.'controller'.DS.'scaffold');

	$pattyCake = new Bake();
	if($help === true)
	{
		$pattyCake->help();
		exit();
	}
	if($project === true)
	{
		$pattyCake->project($projectPath);
		exit();
	}
	$pattyCake->main();
/**
 * Bake is a command-line code generation utility for automating programmer chores.
 *
 * @package		cake
 * @subpackage	cake.cake.scripts
 */
class Bake {

/**
 * Standard input stream.
 *
 * @var filehandle
 */
	var $stdin;
/**
 * Standard output stream.
 *
 * @var filehandle
 */
	var $stdout;
/**
 * Standard error stream.
 *
 * @var filehandle
 */
	var $stderr;
/**
 * Associated controller name.
 *
 * @var string
 */
	var $controllerName = null;
/**
 * If true, Bake will ask for permission to perform actions.
 *
 * @var boolean
 */
	var $interactive = false;

	var $__modelAlias = false;
/**
 * Private helper function for constructor
 * @access private
 */
	function __construct() {
		$this->stdin = fopen('php://stdin', 'r');
		$this->stdout = fopen('php://stdout', 'w');
		$this->stderr = fopen('php://stderr', 'w');
		$this->welcome();
	}
/**
 * Constructor.
 *
 * @return Bake
 */
	function Bake() {
		return $this->__construct();
	}
/**
 * Main-loop method.
 *
 */
	function main() {
		if(!file_exists(CONFIGS.'database.php')) {
			$this->stdout('');
			$this->stdout('');
			$this->stdout('Your database configuration was not found. Take a moment to create one:');
			$this->stdout('');
			$this->stdout('');
			$this->doDbConfig();
		}
		require_once (CONFIGS.'database.php');

		$this->stdout('[M]odel');
		$this->stdout('[C]ontroller');
		$this->stdout('[V]iew');
		$invalidSelection = true;

		while ($invalidSelection) {
			$classToBake = strtoupper($this->getInput('Please select a class to Bake:', array('M', 'V', 'C')));
			switch($classToBake) {
				case 'M':
					$invalidSelection = false;
					$this->doModel();
					break;
				case 'V':
					$invalidSelection = false;
					$this->doView();
					break;
				case 'C':
					$invalidSelection = false;
					$this->doController();
					break;
				default:
					$this->stdout('You have made an invalid selection. Please choose a type of class to Bake by entering M, V, or C.');
			}
		}
	}
/**
 * Database configuration setup.
 *
 */
	function doDbConfig() {
		$this->hr();
		$this->stdout('Database Configuration Bake:');
		$this->hr();

		$driver = '';

		while ($driver == '') {
			$driver = $this->getInput('What database driver would you like to use?', array('mysql','mysqli','mssql','sqlite','postgres', 'odbc'), 'mysql');
			if ($driver == '') {
				$this->stdout('The database driver supplied was empty. Please supply a database driver.');
			}
		}

		switch($driver) {
			case 'mysql':
			$connect = 'mysql_connect';
			break;
			case 'mysqli':
			$connect = 'mysqli_connect';
			break;
			case 'mssql':
			$connect = 'mssql_connect';
			break;
			case 'sqlite':
			$connect = 'sqlite_open';
			break;
			case 'postgres':
			$connect = 'pg_connect';
			break;
			case 'odbc':
			$connect = 'odbc_connect';
			break;
			default:
			$this->stdout('The connection parameter could not be set.');
			break;
		}

		$host = '';

		while ($host == '') {
			$host = $this->getInput('What is the hostname for the database server?', null, 'localhost');
			if ($host == '') {
				$this->stdout('The host name you supplied was empty. Please supply a hostname.');
			}
		}
		$login = '';

		while ($login == '') {
			$login = $this->getInput('What is the database username?', null, 'root');

			if ($login == '') {
				$this->stdout('The database username you supplied was empty. Please try again.');
			}
		}
		$password = '';
		$blankPassword = false;

		while ($password == '' && $blankPassword == false) {
			$password = $this->getInput('What is the database password?', null, 'password');
			if ($password == '') {
				$blank = $this->getInput('The password you supplied was empty. Use an empty password?', array('y', 'n'), 'n');
				if($blank == 'y')
				{
					$blankPassword = true;
				}
			}
		}
		$database = '';

		while ($database == '') {
			$database = $this->getInput('What is the name of the database you will be using?', null, 'cake');

			if ($database == '')  {
				$this->stdout('The database name you supplied was empty. Please try again.');
			}
		}

		$prefix = '';

		while ($prefix == '') {
			$prefix = $this->getInput('Enter a table prefix?', null, 'n');
		}
		if(low($prefix) == 'n') {
			$prefix = '';
		}

		$this->stdout('');
		$this->hr();
		$this->stdout('The following database configuration will be created:');
		$this->hr();
		$this->stdout("Driver:        $driver");
		$this->stdout("Connection:    $connect");
		$this->stdout("Host:          $host");
		$this->stdout("User:          $login");
		$this->stdout("Pass:          " . str_repeat('*', strlen($password)));
		$this->stdout("Database:      $database");
		$this->stdout("Table prefix:  $prefix");
		$this->hr();
		$looksGood = $this->getInput('Look okay?', array('y', 'n'), 'y');

		if (low($looksGood) == 'y' || low($looksGood) == 'yes') {
			$this->bakeDbConfig($driver, $connect, $host, $login, $password, $database, $prefix);
		} else {
			$this->stdout('Bake Aborted.');
		}
	}
/**
 * Action to create a Model.
 *
 */
	function doModel()
	{
		$this->hr();
		$this->stdout('Model Bake:');
		$this->hr();
		$this->interactive = true;

		$useTable = null;
		$primaryKey = 'id';
		$validate = array();
		$associations = array();
		/*$usingDefault = $this->getInput('Will your model be using a database connection setting other than the default?');
		if (low($usingDefault) == 'y' || low($usingDefault) == 'yes')
		{
			$useDbConfig = $this->getInput('Please provide the name of the connection you wish to use.');
		}*/
		$useDbConfig = 'default';
		$this->__doList($useDbConfig);


		$enteredModel = '';

		while ($enteredModel == '') {
			$enteredModel = $this->getInput('Enter a number from the list above, or type in the name of another model.');

			if ($enteredModel == '' || intval($enteredModel) > count($this->__modelNames)) {
				$this->stdout('Error:');
				$this->stdout("The model name you supplied was empty, or the number \nyou selected was not an option. Please try again.");
				$enteredModel = '';
			}
		}

		if (intval($enteredModel) > 0 && intval($enteredModel) <= count($this->__modelNames)) {
			$currentModelName = $this->__modelNames[intval($enteredModel) - 1];
		} else {
			$currentModelName = $enteredModel;
		}

		$useTable = Inflector::tableize($currentModelName);
		if(array_search($useTable, $this->__tables) === false) {
			$this->stdout("\nGiven your model named '$currentModelName', Cake would expect a database table named '" . $useTable . "'.");
			$tableIsGood = $this->getInput('Is this correct?', array('y','n'), 'y');
		}

		if (low($tableIsGood) == 'n' || low($tableIsGood) == 'no') {
			$useTable = $this->getInput('What is the name of the table (enter "null" to use NO table)?');
		}

		$wannaDoValidation = $this->getInput('Would you like to supply validation criteria for the fields in your model?', array('y','n'), 'y');

		$tempModel = new Model(false, $useTable);
		$db =& ConnectionManager::getDataSource($useDbConfig);
		$modelFields = $db->describe($tempModel);
		if(!isset($modelFields[0]['name']) && $modelFields[0]['name'] != 'id') {
			$primaryKey = $this->getInput('What is the primaryKey', null, 'id');
		}
		$validate = array();

		if (array_search($useTable, $this->__tables) !== false && (low($wannaDoValidation) == 'y' || low($wannaDoValidation) == 'yes')) {
			foreach($modelFields as $field) {
				$this->stdout('');
				$prompt .= 'Name: ' . $field['name'] . "\n";
				$prompt .= 'Type: ' . $field['type'] . "\n";
				$prompt .= '---------------------------------------------------------------'."\n";
				$prompt .= 'Please select one of the following validation options:'."\n";
				$prompt .= '---------------------------------------------------------------'."\n";
				$prompt .= "1- VALID_NOT_EMPTY\n";
				$prompt .= "2- VALID_EMAIL\n";
				$prompt .= "3- VALID_NUMBER\n";
				$prompt .= "4- VALID_YEAR\n";
				$prompt .= "5- Do not do any validation on this field.\n\n";
				$prompt .= "... or enter in a valid regex validation string.\n\n";

				if($field['name'] == 'id' || $field['name'] == 'created' || $field['name'] == 'modified') {
					$validation = $this->getInput($prompt, null, '5');
				} else {
					$validation = $this->getInput($prompt, null, '1');
				}

				switch ($validation) {
					case '1':
						$validate[$field['name']] = 'VALID_NOT_EMPTY';
						break;
					case '2':
						$validate[$field['name']] = 'VALID_EMAIL';
						break;
					case '3':
						$validate[$field['name']] = 'VALID_NUMBER';
						break;
					case '4':
						$validate[$field['name']] = 'VALID_YEAR';
						break;
					case '5':
						break;
					default:
						$validate[$field['name']] = $validation;
					break;
				}
			}
		}

		$wannaDoAssoc = $this->getInput('Would you like to define model associations (hasMany, hasOne, belongsTo, etc.)?', array('y','n'), 'y');

		if((low($wannaDoAssoc) == 'y' || low($wannaDoAssoc) == 'yes')) {
			$this->stdout('One moment while I try to detect any associations...');
			$possibleKeys = array();
			//Look for belongsTo
			$i = 0;
			foreach($modelFields as $field) {
				$offset = strpos($field['name'], '_id');
				if($offset !== false) {
					$tmpModelName = $this->__modelNameFromKey($field['name']);
					$associations['belongsTo'][$i]['alias'] = $tmpModelName;
					$associations['belongsTo'][$i]['className'] = $tmpModelName;
					$associations['belongsTo'][$i]['foreignKey'] = $field['name'];
					$i++;
				}
			}
			//Look for hasOne and hasMany and hasAndBelongsToMany
			$i = 0;
			$j = 0;
			foreach($this->__tables as $otherTable) {
				$tempOtherModel = & new Model(false, $otherTable);
				$modelFieldsTemp = $db->describe($tempOtherModel);
				foreach($modelFieldsTemp as $field) {
					if($field['type'] == 'integer' || $field['type'] == 'string') {
						$possibleKeys[$otherTable][] = $field['name'];
					}
					if($field['name'] == $this->__modelKey($currentModelName)) {
						$tmpModelName = $this->__modelName($otherTable);
						$associations['hasOne'][$j]['alias'] = $tmpModelName;
						$associations['hasOne'][$j]['className'] = $tmpModelName;
						$associations['hasOne'][$j]['foreignKey'] = $field['name'];

						$associations['hasMany'][$j]['alias'] = $tmpModelName;
						$associations['hasMany'][$j]['className'] = $tmpModelName;
						$associations['hasMany'][$j]['foreignKey'] = $field['name'];
						$j++;
					}
				}
				$offset = strpos($otherTable, $useTable . '_');
				if($offset !== false) {
					$offset = strlen($useTable . '_');
					$tmpModelName = $this->__modelName(substr($otherTable, $offset));
					$associations['hasAndBelongsToMany'][$i]['alias'] = $tmpModelName;
					$associations['hasAndBelongsToMany'][$i]['className'] = $tmpModelName;
					$associations['hasAndBelongsToMany'][$i]['foreignKey'] = $this->__modelKey($currentModelName);
					$associations['hasAndBelongsToMany'][$i]['associationForeignKey'] = $this->__modelKey($tmpModelName);
					$associations['hasAndBelongsToMany'][$i]['joinTable'] = $otherTable;
					$i++;
				}
				$offset = strpos($otherTable, '_' . $useTable);
				if ($offset !== false) {
					$tmpModelName = $this->__modelName(substr($otherTable, 0, $offset));
					$associations['hasAndBelongsToMany'][$i]['alias'] = $tmpModelName;
					$associations['hasAndBelongsToMany'][$i]['className'] = $tmpModelName;
					$associations['hasAndBelongsToMany'][$i]['foreignKey'] = $this->__modelKey($currentModelName);
					$associations['hasAndBelongsToMany'][$i]['associationForeignKey'] = $this->__modelKey($tmpModelName);
					$associations['hasAndBelongsToMany'][$i]['joinTable'] = $otherTable;
					$i++;
				}
			}
			$this->stdout('Done.');
			$this->hr();
			//if none found...
			if(empty($associations)) {
				$this->stdout('None found.');
			} else {
				$this->stdout('Please confirm the following associations:');
				$this->hr();
				if(!empty($associations['belongsTo'])) {
					$count = count($associations['belongsTo']);
					for($i = 0; $i < $count; $i++) {
						if($currentModelName == $associations['belongsTo'][$i]['alias']) {
							$response = $this->getInput("{$currentModelName} belongsTo {$associations['belongsTo'][$i]['alias']}\nThis looks like a self join. Do you want to specify an alternate association alias?", array('y','n'), 'y');
							if('y' == low($response) || 'yes' == low($response)) {
								$associations['belongsTo'][$i]['alias'] = $this->getInput("So what is the alias?", null, $associations['belongsTo'][$i]['alias']);
							}
							if($currentModelName != $associations['belongsTo'][$i]['alias']) {
								$response = $this->getInput("$currentModelName belongsTo {$associations['belongsTo'][$i]['alias']}?", array('y','n'), 'y');
							} else {
								$response = 'n';
							}
						} else {
							$response = $this->getInput("$currentModelName belongsTo {$associations['belongsTo'][$i]['alias']}?", array('y','n'), 'y');
						}
						if('n' == low($response) || 'no' == low($response)) {
							unset($associations['belongsTo'][$i]);
						}
					}
					$associations['belongsTo'] = array_merge($associations['belongsTo']);
				}

				if(!empty($associations['hasOne'])) {
					$count = count($associations['hasOne']);
					for($i = 0; $i < $count; $i++) {
						if($currentModelName == $associations['hasOne'][$i]['alias']) {
							$response = $this->getInput("{$currentModelName} hasOne {$associations['hasOne'][$i]['alias']}\nThis looks like a self join. Do you want to specify an alternate association alias?", array('y','n'), 'y');
							if('y' == low($response) || 'yes' == low($response)) {
								$associations['hasOne'][$i]['alias'] = $this->getInput("So what is the alias?", null, $associations['hasOne'][$i]['alias']);
							}
							if($currentModelName != $associations['hasOne'][$i]['alias']) {
								$response = $this->getInput("$currentModelName hasOne {$associations['hasOne'][$i]['alias']}?", array('y','n'), 'y');
							} else {
								$response = 'n';
							}
						} else {
							$response = $this->getInput("$currentModelName hasOne {$associations['hasOne'][$i]['alias']}?", array('y','n'), 'n');
						}
						if('n' == low($response) || 'no' == low($response)) {
							unset($associations['hasOne'][$i]);
						}
					}
					$associations['hasOne'] = array_merge($associations['hasOne']);
				}

				if(!empty($associations['hasMany'])) {
					$count = count($associations['hasMany']);
					for($i = 0; $i < $count; $i++) {
						if($currentModelName == $associations['hasMany'][$i]['alias']) {
							$response = $this->getInput("{$currentModelName} hasMany {$associations['hasMany'][$i]['alias']}\nThis looks like a self join. Do you want to specify an alternate association alias?", array('y','n'), 'y');
							if('y' == low($response) || 'yes' == low($response)) {
								$associations['hasMany'][$i]['alias'] = $this->getInput("So what is the alias?", null, $associations['hasMany'][$i]['alias']);
							}
							if($currentModelName != $associations['hasMany'][$i]['alias']) {
								$response = $this->getInput("$currentModelName hasMany {$associations['hasMany'][$i]['alias']}?", array('y','n'), 'y');
							} else {
								$response = 'n';
							}
						} else {
							$response = $this->getInput("$currentModelName hasMany {$associations['hasMany'][$i]['alias']}?", array('y','n'), 'y');
						}
						if('n' == low($response) || 'no' == low($response)) {
							unset($associations['hasMany'][$i]);
						}
					}
					$associations['hasMany'] = array_merge($associations['hasMany']);
				}

				if(!empty($associations['hasAndBelongsToMany'])) {
					$count = count($associations['hasAndBelongsToMany']);
					for($i = 0; $i < $count; $i++) {
						if($currentModelName == $associations['hasAndBelongsToMany'][$i]['alias']) {
							$response = $this->getInput("{$currentModelName} hasAndBelongsToMany {$associations['hasAndBelongsToMany'][$i]['alias']}\nThis looks like a self join. Do you want to specify an alternate association alias?", array('y','n'), 'y');
							if('y' == low($response) || 'yes' == low($response)) {
								$associations['hasAndBelongsToMany'][$i]['alias'] = $this->getInput("So what is the alias?", null, $associations['hasAndBelongsToMany'][$i]['alias']);
							}
							if($currentModelName != $associations['hasAndBelongsToMany'][$i]['alias']) {
								$response = $this->getInput("$currentModelName hasAndBelongsToMany {$associations['hasAndBelongsToMany'][$i]['alias']}?", array('y','n'), 'y');
							} else {
								$response = 'n';
							}
						} else {
							$response = $this->getInput("$currentModelName hasAndBelongsToMany {$associations['hasAndBelongsToMany'][$i]['alias']}?", array('y','n'), 'y');
						}
						if('n' == low($response) || 'no' == low($response)) {
							unset($associations['hasAndBelongsToMany'][$i]);
						}
					}
					$associations['hasAndBelongsToMany'] = array_merge($associations['hasAndBelongsToMany']);
				}
			}
			$wannaDoMoreAssoc = $this->getInput('Would you like to define some additional model associations?', array('y','n'), 'n');

			while((low($wannaDoMoreAssoc) == 'y' || low($wannaDoMoreAssoc) == 'yes')) {
				$assocs = array(1=>'belongsTo', 2=>'hasOne', 3=>'hasMany', 4=>'hasAndBelongsToMany');
				$bad = true;
				while($bad) {
					$this->stdout('What is the association type?');
					$prompt = "1- belongsTo\n";
					$prompt .= "2- hasOne\n";
					$prompt .= "3- hasMany\n";
					$prompt .= "4- hasAndBelongsToMany\n";
					$assocType = intval($this->getInput($prompt, null, null));

					if(intval($assocType) < 1 || intval($assocType) > 4) {
						$this->stdout('The selection you entered was invalid. Please enter a number between 1 and 4.');
					} else {
						$bad = false;
					}
				}
				$this->stdout('For the following options be very careful to match your setup exactly. Any spelling mistakes will cause errors.');
				$this->hr();
				$associationName = $this->getInput('What is the name of this association?');
				$className = $this->getInput('What className will '.$associationName.' use?', null, $associationName );
				$suggestedForeignKey = null;
				if($assocType == '1') {
					$showKeys = $possibleKeys[$useTable];
					$suggestedForeignKey = $this->__modelKey($associationName);
				} else {
					$otherTable = Inflector::tableize($className);
					if(in_array($otherTable, $this->__tables)) {
						if($assocType < '4') {
							$showKeys = $possibleKeys[$otherTable];
						} else {
							$showKeys = null;
						}
					} else {
						$otherTable = $this->getInput('What is the table for this class?');
						$showKeys = $possibleKeys[$otherTable];
					}
					$suggestedForeignKey = $this->__modelKey($currentModelName);
				}
				if(!empty($showKeys)) {
					$this->stdout('A helpful List of possible keys');
					for ($i = 0; $i < count($showKeys); $i++) {
						$this->stdout($i + 1 . ". " . $showKeys[$i]);
					}
					$foreignKey = $this->getInput('What is the foreignKey? Choose a number.');
					if (intval($foreignKey) > 0 && intval($foreignKey) <= $i ) {
						$foreignKey = $showKeys[intval($foreignKey) - 1];
					}
				}
				if(!isset($foreignKey)) {
					$foreignKey = $this->getInput('What is the foreignKey? Specify your own.', null, $suggestedForeignKey);
				}
				if($assocType == '4') {
					$associationForeignKey = $this->getInput('What is the associationForeignKey?', null, $this->__modelKey($currentModelName));
					$joinTable = $this->getInput('What is the joinTable?');
				}
				$associations[$assocs[$assocType]] = array_values($associations[$assocs[$assocType]]);
				$count = count($associations[$assocs[$assocType]]);
				$i = ($count > 0) ? $count : 0;
				$associations[$assocs[$assocType]][$i]['alias'] = $associationName;
				$associations[$assocs[$assocType]][$i]['className'] = $className;
				$associations[$assocs[$assocType]][$i]['foreignKey'] = $foreignKey;
				if($assocType == '4') {
					$associations[$assocs[$assocType]][$i]['associationForeignKey'] = $associationForeignKey;
					$associations[$assocs[$assocType]][$i]['joinTable'] = $joinTable;
				}
				$wannaDoMoreAssoc = $this->getInput('Define another association?', array('y','n'), 'y');
			}
		}
		$this->stdout('');
		$this->hr();
		$this->stdout('The following model will be created:');
		$this->hr();
		$this->stdout("Model Name:    $currentModelName");
		$this->stdout("DB Connection: " . ($usingDefault ? 'default' : $useDbConfig));
		$this->stdout("Model Table:   " . $useTable);
		$this->stdout("Validation:    " . print_r($validate, true));

		if(!empty($associations)) {
			$this->stdout("Associations:");

			if(count($associations['belongsTo'])) {
				for($i = 0; $i < count($associations['belongsTo']); $i++) {
					$this->stdout("            $currentModelName belongsTo {$associations['belongsTo'][$i]['alias']}");
				}
			}

			if(count($associations['hasOne'])) {
				for($i = 0; $i < count($associations['hasOne']); $i++) {
					$this->stdout("            $currentModelName hasOne	{$associations['hasOne'][$i]['alias']}");
				}
			}

			if(count($associations['hasMany'])) {
				for($i = 0; $i < count($associations['hasMany']); $i++) {
					$this->stdout("            $currentModelName hasMany   {$associations['hasMany'][$i]['alias']}");
				}
			}

			if(count($associations['hasAndBelongsToMany'])) {
				for($i = 0; $i < count($associations['hasAndBelongsToMany']); $i++) {
					$this->stdout("            $currentModelName hasAndBelongsToMany {$associations['hasAndBelongsToMany'][$i]['alias']}");
				}
			}
		}
		$this->hr();
		$looksGood = $this->getInput('Look okay?', array('y','n'), 'y');

		if (low($looksGood) == 'y' || low($looksGood) == 'yes') {
			if ($useTable == Inflector::tableize($currentModelName)) {
				// set it to null...
				// putting $useTable in the model
				// is unnecessary.
				$useTable = null;
			}
			$this->bakeModel($currentModelName, $useDbConfig, $useTable, $primaryKey, $validate, $associations);

			if ($this->doUnitTest()) {
				$this->bakeUnitTest('model', $currentModelName);
			}
		} else {
			$this->stdout('Bake Aborted.');
		}
	}
/**
 * Action to create a View.
 *
 */
	function doView() {
		$this->hr();
		$this->stdout('View Bake:');
		$this->hr();
		$uses = array();
		$wannaUseSession = 'y';
		$wannaDoScaffold = 'y';


		$useDbConfig = 'default';
		$this->__doList($useDbConfig, 'Controllers');

		$enteredController = '';

		while ($enteredController == '') {
			$enteredController = $this->getInput('Enter a number from the list above, or type in the name of another controller.');

			if ($enteredController == '' || intval($enteredController) > count($this->__controllerNames)) {
				$this->stdout('Error:');
				$this->stdout("The Controller name you supplied was empty, or the number \nyou selected was not an option. Please try again.");
				$enteredController = '';
			}
		}

		if (intval($enteredController) > 0 && intval($enteredController) <= count($this->__controllerNames) ) {
			$controllerName = $this->__controllerNames[intval($enteredController) - 1];
		} else {
			$controllerName = $enteredController;
		}
		$controllerPath = $this->__controllerPath($controllerName);

		$doItInteractive = $this->getInput("Would you like bake to build your views interactively?\nWarning: Choosing no will overwrite {$controllerClassName} views if it exist.", array('y','n'), 'y');

		if (low($doItInteractive) == 'y' || low($doItInteractive) == 'yes') {
			$this->interactive = true;
			$wannaDoScaffold = $this->getInput("Would you like to create some scaffolded views (index, view, form) for this controller?\nNOTE: Before doing so, you'll need to create your controller and model classes (including associated models).", array('y','n'), 'y');
		}

		$admin = null;
		$admin_url = null;
		if (low($wannaDoScaffold) == 'y' || low($wannaDoScaffold) == 'yes') {
			$wannaDoAdmin = $this->getInput("Would you like to create the views for admin routing?", array('y','n'), 'n');
		}

		if ((low($wannaDoAdmin) == 'y' || low($wannaDoAdmin) == 'yes')) {
			require(CONFIGS.'core.php');
			if(defined('CAKE_ADMIN')) {
				$admin = CAKE_ADMIN . '_';
				$admin_url = '/'.CAKE_ADMIN;
			} else {
				$adminRoute = '';
				$this->stdout('You need to enable CAKE_ADMIN in /app/config/core.php to use admin routing.');
				$this->stdout('What would you like the admin route to be?');
				$this->stdout('Example: www.example.com/admin/controller');
				while ($adminRoute == '') {
					$adminRoute = $this->getInput("What would you like the admin route to be?", null, 'admin');
				}
				if($this->__addAdminRoute($adminRoute) !== true){
					$this->stdout('Unable to write to /app/config/core.php.');
					$this->stdout('You need to enable CAKE_ADMIN in /app/config/core.php to use admin routing.');
					exit();
				} else {
					$admin = $adminRoute . '_';
					$admin_url = '/'.$adminRoute;
				}
			}
		}
		if (low($wannaDoScaffold) == 'y' || low($wannaDoScaffold) == 'yes') {
			$file = CONTROLLERS . $controllerPath . '_controller.php';

			if(!file_exists($file)) {
				$shortPath = str_replace(ROOT, null, $file);
				$shortPath = str_replace('../', '', $shortPath);
				$shortPath = str_replace('//', '/', $shortPath);
				$this->stdout('');
				$this->stdout("The file '$shortPath' could not be found.\nIn order to scaffold, you'll need to first create the controller. ");
				$this->stdout('');
				die();
			} else {
				loadController($controllerName);
				loadModels();
				if($admin) {
					$this->__bakeViews($controllerName, $controllerPath, $admin, $admin_url);
				}
				$this->__bakeViews($controllerName, $controllerPath, null, null);

				$this->hr();
				$this->stdout('');
				$this->stdout('View Scaffolding Complete.'."\n");
			}
		} else {
			$actionName = '';

			while ($actionName == '') {
				$actionName = $this->getInput('Action Name? (use camelCased function name)');

				if ($actionName == '') {
					$this->stdout('The action name you supplied was empty. Please try again.');
				}
			}
			$this->stdout('');
			$this->hr();
			$this->stdout('The following view will be created:');
			$this->hr();
			$this->stdout("Controller Name: $controllerName");
			$this->stdout("Action Name:     $actionName");
			$this->stdout("Path:            app/views/" . $controllerPath . DS . Inflector::underscore($actionName) . '.thtml');
			$this->hr();
			$looksGood = $this->getInput('Look okay?', array('y','n'), 'y');

			if (low($looksGood) == 'y' || low($looksGood) == 'yes') {
				$this->bakeView($controllerName, $actionName);
			} else {
				$this->stdout('Bake Aborted.');
			}
		}
	}

	function __bakeViews($controllerName, $controllerPath, $admin= null, $admin_url = null) {
		$controllerClassName = $controllerName.'Controller';
		$controllerObj = & new $controllerClassName();

		if(!in_array('Html', $controllerObj->helpers)) {
			$controllerObj->helpers[] = 'Html';
		}
		if(!in_array('Form', $controllerObj->helpers)) {
			$controllerObj->helpers[] = 'Form';
		}

		$controllerObj->constructClasses();
		$currentModelName = $controllerObj->modelClass;
		$this->__modelClass = $currentModelName;
		$modelKey = Inflector::underscore($currentModelName);
		$modelObj =& ClassRegistry::getObject($modelKey);
		$singularName = $this->__singularName($currentModelName);
		$pluralName = $this->__pluralName($currentModelName);
		$singularHumanName = $this->__singularHumanName($currentModelName);
		$pluralHumanName = $this->__pluralHumanName($controllerName);

		$fieldNames = $controllerObj->generateFieldNames(null, false);

		//-------------------------[INDEX]-------------------------//
		$indexView = null;
		if(!empty($modelObj->alias)) {
			foreach ($modelObj->alias as $key => $value) {
				$alias[] = $key;
			}
		}
		$indexView .= "<ul class=\"actions\">\n";
		$indexView .= "  <li><?php echo \$html->link('New {$singularHumanName}', \$link->getLink('{$admin_url}/{$controllerPath}/form/0')); ?></li>\n";
		$indexView .= "</ul>\n";
		$indexView .= "\n";
		$indexView .= "<fieldset>\n";
		$indexView .= "  <legend>{$pluralHumanName} List</legend>\n";
		$indexView .= "\n";
    $indexView .= "  <?php \$pagination->setPaging(\$paging); ?>\n";
    $indexView .= "  <?php echo \$this->renderElement('search'); ?>\n";
		$indexView .= "\n";
		$indexView .= "  <table cellpadding=\"0\" cellspacing=\"0\" class=\"data\">\n";
		$indexView .= "\n";
		$indexView .= "    <tr>\n";
		foreach($fieldNames as $field => $value) {
			if(isset($value['foreignKey'])) {
			  $otherModelName = $this->__modelName($value['model']);
				$otherModelKey = Inflector::underscore($otherModelName);
				$otherModelObj =& ClassRegistry::getObject($otherModelKey);
				$otherControllerName = $this->__controllerName($otherModelName);
				$otherControllerPath = $this->__controllerPath($otherControllerName);
				if(is_object($otherModelObj)) {
					$displayField = $otherModelObj->getDisplayField();
    			$indexView .= "      <th><?php echo \$pagination->sortBy('{$displayField}','{$value['model']}','{$value['model']}'); ?></th>\n";
				} else {
    			$indexView .= "      <th><?php echo \$pagination->sortBy('{$field}','{$value['prompt']}'); ?></th>\n";
				}
			} else {
        $value['prompt'] = $value['prompt']=='Id' ? 'ID' : $value['prompt'];
  			$indexView .= "      <th><?php echo \$pagination->sortBy('{$field}','{$value['prompt']}'); ?></th>\n";
			}
		}
		$indexView .= "      <th>Actions</th>\n";
		$indexView .= "    </tr>\n";
		$indexView .= "\n";
		$indexView .= "    <?php if (\${$pluralName}): ?>\n";
		$indexView .= "    <?php \$i=0; foreach (\${$pluralName} as \${$singularName}): \$style = (\$i++ % 2) ? 'even' : 'odd'; ?>\n";
		$indexView .= "\n";
		$indexView .= "    <tr class=\"<?php echo \$style; ?>\">\n";
		$count = 0;
		foreach($fieldNames as $field => $value) {
			if(isset($value['foreignKey'])) {
			  $otherModelName = $this->__modelName($value['model']);
				$otherModelKey = Inflector::underscore($otherModelName);
				$otherModelObj =& ClassRegistry::getObject($otherModelKey);
				$otherControllerName = $this->__controllerName($otherModelName);
				$otherControllerPath = $this->__controllerPath($otherControllerName);
				if(is_object($otherModelObj)) {
					$displayField = $otherModelObj->getDisplayField();
					$indexView .= "      <td><?php echo \$html->link(\$".$singularName."['{$alias[$count]}']['{$displayField}'], '{$admin_url}/" . $otherControllerPath . "/view/' .\$".$singularName."['{$alias[$count]}']['{$otherModelObj->primaryKey}'])?>&nbsp;</td>\n";
				} else {
					$indexView .= "      <td><?php echo \$".$singularName."['{$modelObj->name}']['{$field}']; ?>&nbsp;</td>\n";
				}
				$count++;
			} else {
				$indexView .= "      <td><?php echo \$".$singularName."['{$modelObj->name}']['{$field}']; ?>&nbsp;</td>\n";
			}
		}
		$indexView .= "      <td nowrap>\n";
		$indexView .= "        <?php echo \$html->link(\$html->Image('action/view.png', array('alt'=>'View','title'=>'View')), \$link->getLink('{$admin_url}/{$controllerPath}/view/' . \$".$singularName."['{$modelObj->name}']['{$modelObj->primaryKey}']), null, null, false);?>\n";
		$indexView .= "        <?php echo \$html->link(\$html->Image('action/edit.png', array('alt'=>'Edit','title'=>'Edit')), \$link->getLink('{$admin_url}/{$controllerPath}/form/' . \$".$singularName."['{$modelObj->name}']['{$modelObj->primaryKey}']), null, null, false);?>\n";
		$indexView .= "        <?php echo \$html->link(\$html->Image('action/delete.png', array('alt'=>'Delete','title'=>'Delete')), \$link->getLink('{$admin_url}/{$controllerPath}/delete/' . \$".$singularName."['{$modelObj->name}']['{$modelObj->primaryKey}']), null, 'Are you sure you want to delete {$singularHumanName} ID ' . \$".$singularName."['{$modelObj->name}']['{$modelObj->primaryKey}'] . '?', false);?>\n";
		$indexView .= "      </td>\n";
		$indexView .= "    </tr>\n";
		$indexView .= "\n";
		$indexView .= "    <?php endforeach; ?>\n";
		$indexView .= "    <?php endif; ?>\n";
		$indexView .= "\n";
		$indexView .= "  </table>\n";
		$indexView .= "\n";
		$indexView .= "  <?php echo \$this->renderElement('pagination'); ?>\n";
		$indexView .= "\n";
		$indexView .= "</fieldset>";

		//-------------------------[VIEW]-------------------------//
		$viewView = null;

		$viewView .= "<ul class=\"actions\">\n";
		$viewView .= "  <li><?php echo \$html->link('Edit {$singularHumanName}',   \$link->getLink('{$admin_url}/{$controllerPath}/form/' . \$".$singularName."['{$modelObj->name}']['{$modelObj->primaryKey}'])); ?></li>\n";
		$viewView .= "  <li><?php echo \$html->link('Delete {$singularHumanName}', \$link->getLink('{$admin_url}/{$controllerPath}/delete/' . \$".$singularName."['{$modelObj->name}']['{$modelObj->primaryKey}']), null, 'Are you sure you want to delete {$singularHumanName} ID ' . \$".$singularName."['{$modelObj->name}']['{$modelObj->primaryKey}'] . '?'); ?></li>\n";
		$viewView .= "  <li><?php echo \$html->link('List {$pluralHumanName}',   \$link->getLink('{$admin_url}/{$controllerPath}/index')); ?></li>\n";
		$viewView .= "  <li><?php echo \$html->link('New {$singularHumanName}',	\$link->getLink('{$admin_url}/{$controllerPath}/form/0')); ?></li>\n";
		foreach( $fieldNames as $field => $value ) {
			if( isset( $value['foreignKey'] ) ) {
				$otherModelName = $this->__modelName($value['model']);
				if($otherModelName != $currentModelName) {
					$otherControllerName = $this->__controllerName($otherModelName);
					$otherControllerPath = $this->__controllerPath($otherControllerName);
					$otherSingularHumanName = $this->__singularHumanName($value['controller']);
					$otherPluralHumanName = $this->__pluralHumanName($value['controller']);
      		$viewView .= "  <li><?php echo \$html->link('New {$otherSingularHumanName}', \$link->getLink('{$admin_url}/{$otherControllerPath}/form/0/{$singularName}_id-' . \$".$singularName."['{$modelObj->name}']['{$modelObj->primaryKey}'])); ?></li>\n";
        }
      }
    }
		$viewView .= "</ul>\n";
		$viewView .= "\n";
		$viewView .= "<fieldset>\n";
		$viewView .= "  <legend>{$singularHumanName} View</legend>\n";
		$viewView .= "\n";
		$viewView .= "  <dl>\n";
		$viewView .= "\n";
		$count = 0;
		foreach($fieldNames as $field => $value) {
		  $value['prompt'] = $value['prompt']=='Id' ? 'ID' : $value['prompt'];
			$viewView .= "    <dt>" . $value['prompt'] . "</dt>\n";
			if(isset($value['foreignKey'])) {
			  $otherModelName = $value['model'];
				$otherModelKey = Inflector::underscore($otherModelName);
				$otherModelObj =& ClassRegistry::getObject($otherModelKey);
				$otherControllerName = $this->__controllerName($otherModelName);
				$otherControllerPath = $this->__controllerPath($otherControllerName);
				$displayField = $otherModelObj->getDisplayField();
				$viewView .= "    <dd><?php echo \$html->link(\$".$singularName."['{$alias[$count]}']['{$displayField}'], '{$admin_url}/" . $otherControllerPath . "/view/' .\$".$singularName."['{$alias[$count]}']['{$otherModelObj->primaryKey}'])?>&nbsp;</dd>\n";
				$count++;
			} else {
				$viewView .= "    <dd><?php echo \$".$singularName."['{$modelObj->name}']['{$field}']?>&nbsp;</dd>\n";
			}
  		$viewView .= "\n";
		}
		$viewView .= "  </dl>\n";
		$viewView .= "\n";


    foreach ($modelObj->hasOne as $associationName => $relation) {
      $new = true;

      $otherModelName = $this->__modelName($relation['className']);
      $otherControllerName = $this->__controllerName($otherModelName);
      $otherControllerPath = $this->__controllerPath($otherModelName);
      $otherSingularName = $this->__singularName($associationName);
      $otherPluralHumanName = $this->__pluralHumanName($associationName);
      $otherSingularHumanName = $this->__singularHumanName($associationName);

      $viewView .= "  <?php if(!empty(\$".$singularName."['{$associationName}'])): ?>\n";
      $viewView .= "  <fieldset>\n";
      $viewView .= "    <legend>Related {$otherPluralHumanName}</legend>\n";
      $viewView .= "\n";
      $viewView .= "    <dl>\n";
      $viewView .= "      <?php foreach(\$".$singularName."['{$associationName}'] as \$field => \$value): ?>\n";
      $viewView .= "      <dt><?php echo \$field ?></dt>\n";
      $viewView .= "      <dd>&nbsp;<?php echo \$value ?></dd>\n";
      $viewView .= "      <?php endforeach; ?>\n";
      $viewView .= "      <dt>Actions</dt>\n";
      $viewView .= "      <dd>\n";
      $viewView .= "        <?php echo \$html->link(\$html->Image('action/view.png', array('alt'=>'View','title'=>'View')), \$link->getLink('{$admin_url}/{$otherControllerPath}/view/' . \$".$singularName."['{$associationName}']['" . $modelObj->{$otherModelName}->primaryKey . "']), null, null, false);?>\n";
      $viewView .= "        <?php echo \$html->link(\$html->Image('action/edit.png', array('alt'=>'Edit','title'=>'Edit')), \$link->getLink('{$admin_url}/{$otherControllerPath}/form/' . \$".$singularName."['{$associationName}']['" . $modelObj->{$otherModelName}->primaryKey . "']), null, null, false);?>\n";
      $viewView .= "        <?php echo \$html->link(\$html->Image('action/delete.png', array('alt'=>'Delete','title'=>'Delete')), \$link->getLink('{$admin_url}/{$otherControllerPath}/delete/' . \$".$singularName."['{$associationName}']['" . $modelObj->{$otherModelName}->primaryKey . "']), null, 'Are you sure you want to delete {$otherSingularHumanName} ID ' . \$".$singularName."['{$associationName}']['" . $modelObj->{$otherModelName}->primaryKey . "'] . '?', false);?>\n";
      $viewView .= "      </dd>\n";
      $viewView .= "    </dl>\n";
      $viewView .= "\n";
      $viewView .= "  </fieldset>\n";
      $viewView .= "  <?php endif; ?>\n";
    }
    $relations = array_merge($modelObj->hasMany, $modelObj->hasAndBelongsToMany);

    foreach($relations as $associationName => $relation) {
      $otherModelName = $this->__modelName($relation['className']);
      $otherControllerName = $this->__controllerName($otherModelName);
      $otherControllerPath = $this->__controllerPath($otherModelName);
      $otherSingularName = $this->__singularName($associationName);
      $otherPluralHumanName = $this->__pluralHumanName($associationName);
      $otherSingularHumanName = $this->__singularHumanName($associationName);
      $otherModelKey = Inflector::underscore($otherModelName);
      $otherModelObj =& ClassRegistry::getObject($otherModelKey);

      $viewView .= "  <?php if(!empty(\$".$singularName."['{$associationName}'])):?>\n";
      $viewView .= "  <fieldset>\n";
      $viewView .= "    <legend>Related {$otherPluralHumanName}</legend>\n";
      $viewView .= "\n";
      $viewView .= "    <table cellpadding=\"0\" cellspacing=\"0\" class=\"data\">\n";
      $viewView .= "      <tr>\n";
      $viewView .= "        <?php foreach(\$".$singularName."['{$associationName}']['0'] as \$column => \$value): ?>\n";
      $viewView .= "        <th><?php echo \$column?></th>\n";
      $viewView .= "        <?php endforeach; ?>\n";
      $viewView .= "        <th>Actions</th>\n";
      $viewView .= "      </tr>\n";
      $viewView .= "      <?php \$i=0; foreach(\$".$singularName."['{$associationName}'] as \$".$otherSingularName."): \$style = (\$i++ % 2) ? 'even' : 'odd'; ?>\n";
      $viewView .= "      <tr class=\"<?php echo \$style; ?>\">\n";
      $viewView .= "        <?php foreach(\$".$otherSingularName." as \$column => \$value):?>\n";
      $viewView .= "        <td><?php echo \$value;?></td>\n";
      $viewView .= "        <?php endforeach;?>\n";
      $viewView .= "        <td nowrap>\n";
      $viewView .= "          <?php echo \$html->link(\$html->Image('action/view.png', array('alt'=>'View','title'=>'View')), \$link->getLink('{$admin_url}/{$otherControllerPath}/view/' . \$".$otherSingularName."['{$otherModelObj->primaryKey}']), null, null, false);?>\n";
      $viewView .= "          <?php echo \$html->link(\$html->Image('action/edit.png', array('alt'=>'Edit','title'=>'Edit')), \$link->getLink('{$admin_url}/{$otherControllerPath}/form/' . \$".$otherSingularName."['{$otherModelObj->primaryKey}']), null, null, false);?>\n";
      $viewView .= "          <?php echo \$html->link(\$html->Image('action/delete.png', array('alt'=>'Delete','title'=>'Delete')), \$link->getLink('{$admin_url}/{$otherControllerPath}/delete/' . \$".$otherSingularName."['{$otherModelObj->primaryKey}']), null, 'Are you sure you want to delete {$otherSingularHumanName} ID ' . \$".$otherSingularName."['{$otherModelObj->primaryKey}'] . '?', false);?>\n";
      $viewView .= "        </td>\n";
      $viewView .= "      </tr>\n";
      $viewView .= "      <?php endforeach; ?>\n";
      $viewView .= "    </table>\n";
      $viewView .= "\n";
      $viewView .= "  </fieldset>\n";
      $viewView .= "  <?php endif; ?>\n\n";
    }

		$viewView .= "</fieldset>";


    //-------------------------[FORM]-------------------------//
    $formView = null;

    $formView .= "<ul class=\"actions\">\n";
    $formView .= "  <?php if (\$html->tagValue('{$modelObj->name}/{$modelObj->primaryKey}')): ?>\n";
    $formView .= "  <li><?php echo \$html->link('View {$singularHumanName}', \$link->getLink('{$admin_url}/{$controllerPath}/view/' . \$html->tagValue('{$modelObj->name}/{$modelObj->primaryKey}'))); ?></li>\n";
    $formView .= "  <li><?php echo \$html->link('Delete {$singularHumanName}', \$link->getLink('{$admin_url}/{$controllerPath}/delete/' . \$html->tagValue('{$modelObj->name}/{$modelObj->primaryKey}')), null, 'Are you sure you want to delete {$singularHumanName} ID ' . $html->tagValue('{$modelObj->name}/{$modelObj->primaryKey}') . '?'); ?></li>\n";
    $formView .= "  <?php endif; ?>\n";
    $formView .= "  <li><?php echo \$html->link('List {$pluralHumanName}', \$link->getLink('{$admin_url}/{$controllerPath}/index')); ?></li>\n";
    foreach ($modelObj->belongsTo as $associationName => $relation) {
      $otherModelName = $this->__modelName($relation['className']);
      if($otherModelName != $currentModelName) {
        $otherControllerName = $this->__controllerName($otherModelName);
        $otherControllerPath = $this->__controllerPath($otherModelName);
        $otherSingularName = $this->__singularName($associationName);
        $otherPluralName = $this->__pluralHumanName($associationName);
        $formView .= "  <li><?php echo \$html->link('List {$otherPluralName}', \$link->getLink('{$admin_url}/{$otherControllerPath}/index'));?></li>\n";
        $formView .= "  <li><?php echo \$html->link('Add {$otherSingularName}', \$link->getLink('{$admin_url}/{$otherControllerPath}/form/0'));?></li>\n";
      }
    }
    $formView .= "</ul>\n";
    $formView .= "\n";
    $formView .= "<fieldset>\n";
    $formView .= "  <legend>{$singularHumanName} Form</legend>\n";
    $formView .= "\n";
    $formView .= "  <?php echo \$html->formTag(\$link->getLink('{$admin_url}/{$controllerPath}/form/'.\$html->tagValue('{$modelObj->name}/{$modelObj->primaryKey}')),'post'); ?>\n";
    $formView .= "    <?php echo \$html->hidden('{$modelObj->name}/{$modelObj->primaryKey}')?>\n";
    $formView .= "\n";
    $formView .= "    <div class=\"outline\">\n";
    $formView .= "\n";
    $formView .= $this->generateFields($controllerObj->generateFieldNames(null, true));
    $formView .= "    </div>\n";
    $formView .= "\n";
    $formView .= $this->generateSubmitDiv('Save');
    $formView .= "  </form>\n";
    $formView .= "\n";
    $formView .= "</fieldset>";

    //------------------------------------------------------------------------------------//

    if(!file_exists(VIEWS.$controllerPath)) {
      mkdir(VIEWS.$controllerPath);
    }
    $filename = VIEWS . $controllerPath . DS .  $admin . 'index.thtml';
    $this->__createFile($filename, $indexView);
    $filename = VIEWS . $controllerPath . DS . $admin . 'view.thtml';
    $this->__createFile($filename, $viewView);
    $filename = VIEWS . $controllerPath . DS . $admin . 'form.thtml';
    $this->__createFile($filename, $formView);
  }
/**
 * Action to create a Controller.
 *
 */
	function doController() {
		$this->hr();
		$this->stdout('Controller Bake:');
		$this->hr();
		$uses = array();
		$helpers = array();
		$components = array();
		$wannaUseSession = 'y';
		$wannaDoScaffolding = 'y';

		$useDbConfig = 'default';
		$this->__doList($useDbConfig, 'Controllers');

		$enteredController = '';

		while ($enteredController == '') {
			$enteredController = $this->getInput('Enter a number from the list above, or type in the name of another controller.');

			if ($enteredController == '' || intval($enteredController) > count($this->__controllerNames)) {
				$this->stdout('Error:');
				$this->stdout("The Controller name you supplied was empty, or the number \nyou selected was not an option. Please try again.");
				$enteredController = '';
			}
		}

		if (intval($enteredController) > 0 && intval($enteredController) <= count($this->__controllerNames) ) {
			$controllerName = $this->__controllerNames[intval($enteredController) - 1];
		} else {
			$controllerName = $enteredController;
		}
		$controllerPath = $this->__controllerPath($controllerName);

		$doItInteractive = $this->getInput("Would you like bake to build your controller interactively?\nWarning: Choosing no will overwrite {$controllerClassName} controller if it exist.", array('y','n'), 'y');

		if (low($doItInteractive) == 'y' || low($doItInteractive) == 'yes') {
			$this->interactive = true;
			$wannaDoUses = $this->getInput("Would you like this controller to use other models besides '" . $this->__modelName($controllerName) .  "'?", array('y','n'), 'n');

			if (low($wannaDoUses) == 'y' || low($wannaDoUses) == 'yes') {
				$usesList = $this->getInput("Please provide a comma separated list of the classnames of other models you'd like to use.\nExample: 'Author, Article, Book'");
				$usesListTrimmed = str_replace(' ', '', $usesList);
				$uses = explode(',', $usesListTrimmed);
			}
			$wannaDoHelpers = $this->getInput("Would you like this controller to use other helpers besides HtmlHelper and FormHelper?", array('y','n'), 'n');

			if (low($wannaDoHelpers) == 'y' || low($wannaDoHelpers) == 'yes') {
				$helpersList = $this->getInput("Please provide a comma separated list of the other helper names you'd like to use.\nExample: 'Ajax, Javascript, Time'");
				$helpersListTrimmed = str_replace(' ', '', $helpersList);
				$helpers = explode(',', $helpersListTrimmed);
			}
			$wannaDoComponents = $this->getInput("Would you like this controller to use any components?", array('y','n'), 'n');

			if (low($wannaDoComponents) == 'y' || low($wannaDoComponents) == 'yes') {
				$componentsList = $this->getInput("Please provide a comma separated list of the component names you'd like to use.\nExample: 'Acl, MyNiftyHelper'");
				$componentsListTrimmed = str_replace(' ', '', $componentsList);
				$components = explode(',', $componentsListTrimmed);
			}

			$wannaUseSession = $this->getInput("Would you like to use Sessions?", array('y','n'), 'y');

			$wannaDoScaffolding = $this->getInput("Would you like to include some basic class methods (index(), save(), edit())?", array('y','n'), 'y');

		}

		if (low($wannaDoScaffolding) == 'y' || low($wannaDoScaffolding) == 'yes') {
			$wannaDoAdmin = $this->getInput("Would you like to create the methods for admin routing?", array('y','n'), 'n');
		} else {
			$wannaUseScaffold = $this->getInput("Would you like to use scaaffolding?", array('y','n'), 'y');
		}

		$admin = null;
		$admin_url = null;
		if ((low($wannaDoAdmin) == 'y' || low($wannaDoAdmin) == 'yes')) {
			require(CONFIGS.'core.php');
			if(defined('CAKE_ADMIN')) {
				$admin = CAKE_ADMIN.'_';
				$admin_url = '/'.CAKE_ADMIN;
			} else {
				$adminRoute = '';
				$this->stdout('You need to enable CAKE_ADMIN in /app/config/core.php to use admin routing.');
				$this->stdout('What would you like the admin route to be?');
				$this->stdout('Example: www.example.com/admin/controller');
				while ($adminRoute == '') {
					$adminRoute = $this->getInput("What would you like the admin route to be?", null, 'admin');
				}
				if($this->__addAdminRoute($adminRoute) !== true){
					$this->stdout('Unable to write to /app/config/core.php.');
					$this->stdout('You need to enable CAKE_ADMIN in /app/config/core.php to use admin routing.');
					exit();
				} else {
					$admin = $adminRoute . '_';
					$admin_url = '/'.$adminRoute;
				}
			}
		}

		if (low($wannaDoScaffolding) == 'y' || low($wannaDoScaffolding) == 'yes') {
			loadModels();
			$actions = $this->__bakeActions($controllerName, null, null, $wannaUseSession);
			if($admin) {
				$actions .= $this->__bakeActions($controllerName, $admin, $admin_url, $wannaUseSession);
			}
		}

		if($this->interactive === true) {
			$this->stdout('');
			$this->hr();
			$this->stdout('The following controller will be created:');
			$this->hr();
			$this->stdout("Controller Name:	$controllerName");

			if(count($uses)) {
				$this->stdout("Uses:            ", false);

				foreach($uses as $use) {
					if ($use != $uses[count($uses) - 1]) {
						$this->stdout(ucfirst($use) . ", ", false);
					} else {
						$this->stdout(ucfirst($use));
					}
				}
			}

			if(count($helpers)) {
				$this->stdout("Helpers:			", false);

				foreach($helpers as $help) {
					if ($help != $helpers[count($helpers) - 1]) {
						$this->stdout(ucfirst($help) . ", ", false);
					} else {
						$this->stdout(ucfirst($help));
					}
				}
			}

			if(count($components)) {
				$this->stdout("Components:            ", false);

				foreach($components as $comp) {
					if ($comp != $components[count($components) - 1]) {
						$this->stdout(ucfirst($comp) . ", ", false);
					} else {
						$this->stdout(ucfirst($comp));
					}
				}
			}
			$this->hr();
			$looksGood = $this->getInput('Look okay?', array('y','n'), 'y');

			if (low($looksGood) == 'y' || low($looksGood) == 'yes') {
				$this->bakeController($controllerName, $uses, $helpers, $components, $actions, $wannaUseScaffold);

				if ($this->doUnitTest()) {
					$this->bakeUnitTest('controller', $controllerName);
				}
			} else {
				$this->stdout('Bake Aborted.');
			}
		} else {
			$this->bakeController($controllerName, $uses, $helpers, $components, $actions, $wannaUseScaffold);
			if ($this->doUnitTest()) {
				$this->bakeUnitTest('controller', $controllerName);
			}
			exit();
		}
	}

  function __bakeActions($controllerName, $admin = null, $admin_url = null, $wannaUseSession = 'y') {
    $currentModelName = $this->__modelName($controllerName);
    $modelObj =& new $currentModelName();
    $controllerPath = $this->__controllerPath($currentModelName);
    $pluralName = $this->__pluralName($currentModelName);
    $singularName = $this->__singularName($currentModelName);
    $singularHumanName = $this->__singularHumanName($currentModelName);
    $pluralHumanName = $this->__pluralHumanName($controllerName);
    if(!class_exists($currentModelName)) {
      $this->stdout('You must have a model for this class to build scaffold methods. Please try again.');
      exit;
    }
    $actions .= "\n";
    /* INDEX ACTION */
    $actions .= "  function {$admin}index()\n";
    $actions .= "  {\n";
    $actions .= "    \$conditions = \$this->Search->init(array('{$currentModelName}.name'=>'Name'),\$this->namedArgs);\n";
    $actions .= "    list(\$order,\$limit,\$page) = \$this->Pagination->init(\$conditions,\$this->namedArgs);\n";
    $actions .= "    \$this->{$currentModelName}->recursive = 0;\n";
    $actions .= "    \$this->set('{$pluralName}', \$this->{$currentModelName}->findAll(\$conditions,null,\$order,\$limit,\$page));\n";
    $actions .= "  }\n";
    $actions .= "\n";
    /* VIEW ACTION */
    $actions .= "  function {$admin}view(\$id = null)\n";
    $actions .= "  {\n";
    $actions .= "    if (!\$id)\n";
    $actions .= "    {\n";
    if (low($wannaUseSession) == 'y' || low($wannaUseSession) == 'yes') {
    $actions .= "      \$this->Session->setFlash('Invalid {$singularHumanName} ID.');\n";
    $actions .= "      \$this->redirect('{$admin_url}/{$controllerPath}/index');\n";
    } else {
    $actions .= "      \$this->flash('Invalid {$singularHumanName} ID.', '{$admin_url}/{$controllerPath}/index');\n";
    }
    $actions .= "    }\n";
    $actions .= "    \$this->set('".$singularName."', \$this->{$currentModelName}->read(null, \$id));\n";
    $actions .= "  }\n";
    $actions .= "\n";
    /* FORM ACTION */
    $actions .= "  function {$admin}form(\$id = null)\n";
    $actions .= "  {\n";
    $actions .= "    if (empty(\$this->data))\n";
    $actions .= "    {\n";
    $actions .= "      if (\$id)\n";
    $actions .= "      {\n";
    $actions .= "        \$this->data = \$this->{$currentModelName}->read(null, \$id);\n";
    $actions .= "      }\n";
    $actions .= "    }\n";
    $actions .= "    else\n";
    $actions .= "    {\n";
    $actions .= "      \$this->cleanUpFields();\n";
    $actions .= "      \$this->{$currentModelName}->begin();\n";
    $actions .= "      if (\$this->{$currentModelName}->save(\$this->data))\n";
    $actions .= "      {\n";
    $actions .= "        \$this->{$currentModelName}->commit();\n";
    if (low($wannaUseSession) == 'y' || low($wannaUseSession) == 'yes') {
    $actions .= "        \$this->Session->setFlash('The ".$this->__singularHumanName($currentModelName)." has been saved.');\n";
    $actions .= "        \$this->redirect('{$admin_url}/{$controllerPath}/view/'.\$this->{$currentModelName}->id);\n";
    } else {
    $actions .= "        \$this->flash('The {$currentModelName} has been saved.', '{$admin_url}/{$controllerPath}/view/'.\$this->{$currentModelName}->id);\n";
    }
    $actions .= "      }\n";
    $actions .= "      else\n";
    $actions .= "      {\n";
    $actions .= "        \$this->{$currentModelName}->rollback();\n";
    if (low($wannaUseSession) == 'y' || low($wannaUseSession) == 'yes') {
    $actions .= "        \$this->Session->setFlash('Please correct the errors below.');\n";
    }
    $actions .= "      }\n";
    $actions .= "    }\n";
    foreach($modelObj->hasAndBelongsToMany as $associationName => $relation) {
      if(!empty($associationName)) {
        $otherModelName = $this->__modelName($associationName);
        $otherSingularName = $this->__singularName($associationName);
        $otherPluralName = $this->__pluralName($associationName);
        $selectedOtherPluralName = 'selected' . ucfirst($otherPluralName);
        $actions .= "    \$this->set('{$otherPluralName}', \$this->{$currentModelName}->{$otherModelName}->generateList());\n";
        $actions .= "    if (empty(\$this->data['{$associationName}']['{$associationName}'])) { \$this->data['{$associationName}']['{$associationName}'] = null; }\n";
        $actions .= "    \$this->set('{$selectedOtherPluralName}', \$this->data['{$associationName}']['{$associationName}']);\n";
      }
    }
    foreach($modelObj->belongsTo as $associationName => $relation) {
      if (!empty($associationName)) {
        $otherModelName = $this->__modelName($associationName);
        $otherSingularName = $this->__singularName($associationName);
        $otherPluralName = $this->__pluralName($associationName);
        $actions .= "    \$this->set('{$otherPluralName}', \$this->{$currentModelName}->{$otherModelName}->generateList());\n";
      }
    }
    $actions .= "  }\n";
    $actions .= "\n";
    /* DELETE ACTION */
    $actions .= "  function {$admin}delete(\$id = null)\n";
    $actions .= "  {\n";
    $actions .= "    if (!\$id)\n";
    $actions .= "    {\n";
    if (low($wannaUseSession) == 'y' || low($wannaUseSession) == 'yes') {
    $actions .= "      \$this->Session->setFlash('Invalid {$singularHumanName} ID.');\n";
    $actions .= "      \$this->redirect('{$admin_url}/{$controllerPath}/index');\n";
    } else {
    $actions .= "      \$this->flash('Invalid {$singularHumanName} ID.', '{$admin_url}/{$controllerPath}/index');\n";
    }
    $actions .= "    }\n";
    $actions .= "    try\n";
    $actions .= "    {\n";
    $actions .= "      \$this->{$currentModelName}->begin();\n";
    $actions .= "      if (\$this->{$currentModelName}->del(\$id))\n";
    $actions .= "      {\n";
    $actions .= "        \$this->{$currentModelName}->commit();\n";
    if (low($wannaUseSession) == 'y' || low($wannaUseSession) == 'yes') {
      $actions .= "        \$this->Session->setFlash('".$this->__singularHumanName($currentModelName)." ID '.\$id.' has been deleted.');\n";
      $actions .= "        \$this->redirect('{$admin_url}/{$controllerPath}/index');\n";
    } else {
      $actions .= "        \$this->flash('{$currentModelName} ID '.\$id.' has been deleted.', '{$admin_url}/{$controllerPath}/index');\n";
    }
    $actions .= "      }\n";
    $actions .= "      else\n";
    $actions .= "      {\n";
    $actions .= "        \$this->{$currentModelName}->rollback();\n";
    if (low($wannaUseSession) == 'y' || low($wannaUseSession) == 'yes') {
      $actions .= "        \$this->Session->setFlash('".$this->__singularHumanName($currentModelName)." ID '.\$id.' could not be deleted.');\n";
      $actions .= "        \$this->redirect('{$admin_url}/{$controllerPath}/index');\n";
    } else {
      $actions .= "        \$this->flash('{$currentModelName} ID '.\$id.' could not be deleted.', '{$admin_url}/{$controllerPath}/index');\n";
    }
    $actions .= "      }\n";
    $actions .= "    }\n";
    $actions .= "    catch (Exception \$e)\n";
    $actions .= "    {\n";
    $actions .= "      \$this->{$currentModelName}->rollback();\n";
    if (low($wannaUseSession) == 'y' || low($wannaUseSession) == 'yes') {
      $actions .= "      \$this->Session->setFlash('".$this->__singularHumanName($currentModelName)." ID '.\$id.' could not be deleted because:<br>'.\$e->getMessage());\n";
      $actions .= "      \$this->redirect('{$admin_url}/{$controllerPath}/index');\n";
    } else {
      $actions .= "      \$this->flash('{$currentModelName} ID '.\$id.' could not be deleted because:<br>'.\$e->getMessage(), '{$admin_url}/{$controllerPath}/index');\n";
    }
    $actions .= "    }\n";
    $actions .= "  }\n";
    $actions .= "\n";
    return $actions;
  }
/**
 * Action to create a Unit Test.
 *
 * @return Success
 */
  function doUnitTest() {
    if (is_dir('vendors'.DS.'simpletest') || is_dir(APP_PATH.'vendors'.DS.'simpletest')) {
      return true;
    }
    $unitTest = $this->getInput('Cake test suite not installed.  Do you want to bake unit test files anyway?', array('y','n'), 'n');
    $result = low($unitTest) == 'y' || low($unitTest) == 'yes';

    if ($result) {
      $this->stdout("\nYou can download the Cake test suite from http://cakeforge.org/frs/?group_id=62", true);
    }
    return $result;
  }
/**
 * Creates a database configuration file for Bake.
 *
 * @param string $host
 * @param string $login
 * @param string $password
 * @param string $database
 */
  function bakeDbConfig( $driver, $connect, $host, $login, $password, $database, $prefix) {
    $out = "<?php\n";
    $out .= "class DATABASE_CONFIG {\n\n";
    $out .= "  var \$default = array(\n";
    $out .= "    'driver' => '{$driver}',\n";
    $out .= "    'connect' => '{$connect}',\n";
    $out .= "    'host' => '{$host}',\n";
    $out .= "    'login' => '{$login}',\n";
    $out .= "    'password' => '{$password}',\n";
    $out .= "    'database' => '{$database}', \n";
    $out .= "    'prefix' => '{$prefix}' \n";
    $out .= "  );\n";
    $out .= "}\n";
    $out .= "?>";
    $filename = CONFIGS.'database.php';
    $this->__createFile($filename, $out);
  }
/**
 * Assembles and writes a Model file.
 *
 * @param string $name
 * @param object $useDbConfig
 * @param string $useTable
 * @param string $primaryKey
 * @param array $validate
 * @param array $associations
 */
  function bakeModel($name, $useDbConfig = 'default', $useTable = null, $primaryKey = 'id', $validate=array(), $associations=array()) {
    $out = "<?php\n";
    $out .= "class {$name} extends AppModel {\n\n";
    $out .= "  var \$name = '{$name}';\n";

    if ($useDbConfig != 'default') {
      $out .= "  var \$useDbConfig = '$useDbConfig';\n";
    }

    if ($useTable != null) {
      $out .= "  var \$useTable = '$useTable';\n";
    }

    if ($primaryKey != 'id') {
      $out .= "  var \$primaryKey = '$primaryKey';\n";
    }

    $out .= "\n";

    if (count($validate)) {
      $out .= "  var \$validate = array(\n";
      $keys = array_keys($validate);
      for($i = 0; $i < count($validate); $i++) {
        $out .= "    '" . $keys[$i] . "' => " . $validate[$keys[$i]] . ",\n";
      }
      $out .= "  );\n";
    }
    $out .= "\n";

    if(!empty($associations)) {
      if(!empty($associations['belongsTo'])) {
        $out .= "  var \$belongsTo = array(\n\n";

        for($i = 0; $i < count($associations['belongsTo']); $i++) {
          $out .= "    '{$associations['belongsTo'][$i]['alias']}' => array(\n";
          $out .= "      'className' => '{$associations['belongsTo'][$i]['className']}',\n";
          $out .= "      'foreignKey' => '{$associations['belongsTo'][$i]['foreignKey']}',\n";
          $out .= "      'conditions' => '',\n";
          $out .= "      'fields' => '',\n";
          $out .= "      'order' => '',\n";
          $out .= "      'counterCache' => ''\n";
          $out .= "    ),\n\n";
        }
        $out .= "  );\n\n";
      }

      if(!empty($associations['hasOne'])) {
        $out .= "  var \$hasOne = array(\n\n";

        for($i = 0; $i < count($associations['hasOne']); $i++) {
          $out .= "    '{$associations['hasOne'][$i]['alias']}' => array(\n";
          $out .= "      'className' => '{$associations['hasOne'][$i]['className']}',\n";
          $out .= "      'foreignKey' => '{$associations['hasOne'][$i]['foreignKey']}',\n";
          $out .= "      'conditions' => '',\n";
          $out .= "      'fields' => '',\n";
          $out .= "      'order' => '',\n";
          $out .= "      'dependent' => ''\n";
          $out .= "    ),\n\n";
        }
        $out .= "  );\n\n";
      }

      if(!empty($associations['hasMany'])) {
        $out .= "  var \$hasMany = array(\n\n";

        for($i = 0; $i < count($associations['hasMany']); $i++) {
          $out .= "    '{$associations['hasMany'][$i]['alias']}' => array(\n";
          $out .= "      'className' => '{$associations['hasMany'][$i]['className']}',\n";
          $out .= "      'foreignKey' => '{$associations['hasMany'][$i]['foreignKey']}',\n";
          $out .= "      'conditions' => '',\n";
          $out .= "      'fields' => '',\n";
          $out .= "      'order' => '',\n";
          $out .= "      'limit' => '',\n";
          $out .= "      'offset' => '',\n";
          $out .= "      'dependent' => '',\n";
          $out .= "      'exclusive' => '',\n";
          $out .= "      'finderQuery' => '',\n";
          $out .= "      'counterQuery' => ''\n";
          $out .= "    ),\n\n";
        }
        $out .= "  );\n\n";
      }

      if(!empty($associations['hasAndBelongsToMany'])) {
        $out .= "  var \$hasAndBelongsToMany = array(\n\n";

        for($i = 0; $i < count($associations['hasAndBelongsToMany']); $i++) {
          $out .= "    '{$associations['hasAndBelongsToMany'][$i]['alias']}' => array(\n";
          $out .= "      'className' => '{$associations['hasAndBelongsToMany'][$i]['className']}',\n";
          $out .= "      'joinTable' => '{$associations['hasAndBelongsToMany'][$i]['joinTable']}',\n";
          $out .= "      'foreignKey' => '{$associations['hasAndBelongsToMany'][$i]['foreignKey']}',\n";
          $out .= "      'associationForeignKey' => '{$associations['hasAndBelongsToMany'][$i]['associationForeignKey']}',\n";
          $out .= "      'conditions' => '',\n";
          $out .= "      'fields' => '',\n";
          $out .= "      'order' => '',\n";
          $out .= "      'limit' => '',\n";
          $out .= "      'offset' => '',\n";
          $out .= "      'uniq' => '',\n";
          $out .= "      'finderQuery' => '',\n";
          $out .= "      'deleteQuery' => '',\n";
          $out .= "      'insertQuery' => ''\n";
          $out .= "    ),\n\n";
        }
        $out .= "  );\n\n";
      }
    }
    $out .= "}\n";
    $out .= "?>";
    $filename = MODELS.Inflector::underscore($name) . '.php';
		$this->__createFile($filename, $out);
	}
/**
 * Assembles and writes a View file.
 *
 * @param string $controllerName
 * @param string $actionName
 * @param string $content
 */
	function bakeView($controllerName, $actionName, $content = '') {
		$out = "<h2>$actionName</h2>\n";
		$out .= $content;
		if(!file_exists(VIEWS.$this->__controllerPath($controllerName))) {
			mkdir(VIEWS.$this->__controllerPath($controllerName));
		}
		$filename = VIEWS . $this->__controllerPath($controllerName) . DS . Inflector::underscore($actionName) . '.thtml';
		$this->__createFile($filename, $out);
	}
/**
 * Assembles and writes a Controller file.
 *
 * @param string $controllerName
 * @param array $uses
 * @param array $helpers
 * @param array $components
 * @param string $actions
 */
  function bakeController($controllerName, $uses, $helpers, $components, $actions = '', $wannaUseScaffold = 'y') {
    $out = "<?php\n";
    $out .= "class $controllerName" . "Controller extends AppController {\n\n";
    if(low($wannaUseScaffold) == 'y' || low($wannaUseScaffold) == 'yes') {
    $out .= "  var \$scaffold;\n";
    }
    $out .= "  var \$name = '$controllerName';\n";

    if (count($uses)) {
      $out .= "  var \$uses = array('" . $this->__modelName($controllerName) . "', ";

      foreach($uses as $use) {
        if ($use != $uses[count($uses) - 1]) {
          $out .= "'" . $this->__modelName($use) . "', ";
        } else {
          $out .= "'" . $this->__modelName($use) . "'";
        }
      }
      $out .= ");\n";
    }

    if (count($helpers)) {
      $out .= "  var \$helpers = array(";
      foreach($helpers as $help) {
        if ($help != $helpers[count($helpers) - 1]) {
          $out .= "'" . Inflector::camelize($help) . "'";
        } else {
          $out .= ", '" . Inflector::camelize($help) . "'";
        }
      }
      $out .= ");\n";
    }

    if (count($components)) {
      $out .= "  var \$components = array(";

      foreach($components as $comp) {
        if ($comp != $components[count($components) - 1]) {
          $out .= "'" . Inflector::camelize($comp) . "', ";
        } else {
          $out .= "'" . Inflector::camelize($comp) . "'";
        }
      }
      $out .= ");\n";
    }

    $out .= $actions;
    $out .= "}\n";
    $out .= "?>";
    $filename = CONTROLLERS . $this->__controllerPath($controllerName) . '_controller.php';
    $this->__createFile($filename, $out);
  }
/**
 * Assembles and writes a unit test file.
 *
 * @param string $type One of "model", and "controller".
 * @param string $className
 */
	function bakeUnitTest($type, $className) {
		$out = '<?php '."\n\n";
		$error = false;
		switch ($type) {
			case 'model':
				$out .= "class {$className}TestCase extends UnitTestCase {\n";
				$out .= "  var \$object = null;\n\n";
				$out .= "  function setUp()\n  {\n    \$this->object = new {$className}();\n";
				$out .= "  }\n\n  function tearDown()\n  {\n    unset(\$this->object);\n  }\n";
				$out .= "\n  /*\n  function testMe()\n  {\n";
				$out .= "    \$result = \$this->object->doSomething();\n";
				$out .= "    \$expected = 1;\n";
				$out .= "    \$this->assertEquals(\$result, \$expected);\n  }\n  */\n}";
				$path = MODEL_TESTS;
				$filename = $this->__singularName($className).'.test.php';
			break;
			case 'controller':
				$out .= "class {$className}ControllerTestCase extends UnitTestCase {\n";
				$out .= "  var \$object = null;\n\n";
				$out .= "  function setUp()\n  {\n    \$this->object = new {$className}();\n";
				$out .= "  }\n\n  function tearDown()\n  {\n    unset(\$this->object);\n  }\n";
				$out .= "\n  /*\n  function testMe()\n  {\n";
				$out .= "    \$result = \$this->object->doSomething();\n";
				$out .= "    \$expected = 1;\n";
				$out .= "    \$this->assertEquals(\$result, \$expected);\n  }\n  */\n}";
				$path = CONTROLLER_TESTS;
				$filename = $this->__pluralName($className.'Controller').'.test.php';
			break;
			default:
				$error = true;
			break;
		}
		$out .= "\n?>";

		if (!$error) {
			$this->stdout("Baking unit test for $className...");
			$path = explode(DS, $path);
			foreach($path as $i => $val) {
				if ($val == '' || $val == '../') {
					unset($path[$i]);
				}
			}
			$path = implode(DS, $path);
			$unixPath = DS;
			if (strpos(PHP_OS, 'WIN') === 0){
				$unixPath = null;
			}
			if (!is_dir($unixPath.$path)) {
				$create = $this->getInput("Unit test directory does not exist.  Create it?", array('y','n'), 'y');
				if (low($create) == 'y' || low($create) == 'yes') {
					$build = array();

					foreach(explode(DS, $path) as $i => $dir) {
						$build[] = $dir;
						if (!is_dir($unixPath.implode(DS, $build))) {
							mkdir($unixPath.implode(DS, $build));
						}
					}
				} else {
					return false;
				}
			}
			$this->__createFile($unixPath.$path.DS.$filename, $out);
		}
	}
/**
 * Prompts the user for input, and returns it.
 *
 * @param string $prompt Prompt text.
 * @param mixed $options Array or string of options.
 * @param string $default Default input value.
 * @return Either the default value, or the user-provided input.
 */
	function getInput($prompt, $options = null, $default = null) {
		if (!is_array($options)) {
			$print_options = '';
		} else {
			$print_options = '(' . implode('/', $options) . ')';
		}

		if($default == null) {
			$this->stdout('');
			$this->stdout($prompt . " $print_options \n" . '> ', false);
		} else {
			$this->stdout('');
			$this->stdout($prompt . " $print_options \n" . "[$default] > ", false);
		}
		$result = trim(fgets($this->stdin));

		if($default != null && empty($result)) {
			return $default;
		} else {
			return $result;
		}
	}
/**
 * Outputs to the stdout filehandle.
 *
 * @param string $string String to output.
 * @param boolean $newline If true, the outputs gets an added newline.
 */
	function stdout($string, $newline = true) {
		if ($newline) {
			fwrite($this->stdout, $string . "\n");
		} else {
			fwrite($this->stdout, $string);
		}
	}
/**
 * Outputs to the stderr filehandle.
 *
 * @param string $string Error text to output.
 */
	function stderr($string) {
		fwrite($this->stderr, $string);
	}
/**
 * Outputs a series of minus characters to the standard output, acts as a visual separator.
 *
 */
	function hr() {
		$this->stdout('---------------------------------------------------------------');
	}
/**
 * Creates a file at given path.
 *
 * @param string $path		Where to put the file.
 * @param string $contents Content to put in the file.
 * @return Success
 */
	function __createFile ($path, $contents) {
		$path = str_replace('//', '/', $path);
		echo "\nCreating file $path\n";
		if (is_file($path) && $this->interactive === true) {
			fwrite($this->stdout, __("File exists, overwrite?", true). " {$path} (y/n/q):");
			$key = trim(fgets($this->stdin));

			if ($key=='q') {
				fwrite($this->stdout, __("Quitting.", true) ."\n");
				exit;
			} elseif ($key == 'a') {
				$this->dont_ask = true;
			} elseif ($key == 'y') {
			} else {
				fwrite($this->stdout, __("Skip", true) ." {$path}\n");
				return false;
			}
		}

		if ($f = fopen($path, 'w')) {
			fwrite($f, $contents);
			fclose($f);
			fwrite($this->stdout, __("Wrote", true) ."{$path}\n");
			return true;
		} else {
			fwrite($this->stderr, __("Error! Could not write to", true)." {$path}.\n");
			return false;
		}
	}
/**
 * Takes an array of database fields, and generates an HTML form for a View.
 * This is an extraction from the Scaffold functionality.
 *
 * @param array $fields
 * @param boolean $readOnly
 * @return Generated HTML and PHP.
 */
	function generateFields( $fields, $readOnly = false ) {
		$strFormFields = '';
		foreach( $fields as $field ) {
			if(isset( $field['type'])) {
				if(!isset($field['required'])) {
					$field['required'] = false;
				}

				if(!isset( $field['errorMsg'])) {
					$field['errorMsg'] = null;
				}

				if(!isset( $field['htmlOptions'])) {
					$field['htmlOptions'] = array();
				}

				if( $readOnly ) {
					$field['htmlOptions']['READONLY'] = "readonly";
				}

				switch( $field['type'] ) {
					case "input" :
						if(!isset( $field['size'])) {
							$field['size'] = 60;
						}
						$field['htmlOptions']['maxlength'] = "\$field_lengths['".Inflector::camelize(substr($tagName,strpos($tagName,'/')+1))."']";
						$strFormFields .= $this->generateInputDiv( $field['tagName'], $field['prompt'], $field['required'], $field['errorMsg'], $field['size'], $field['htmlOptions'] );
					break;
					case "checkbox" :
						$strFormFields .= $this->generateCheckboxDiv( $field['tagName'], $field['prompt'], $field['required'], $field['errorMsg'], $field['htmlOptions'] );
					break;
					case "select";
					case "selectMultiple";
						if( "selectMultiple" == $field['type'] ) {
							$field['selectAttr']['multiple'] = 'multiple';
							$field['selectAttr']['class'] = 'selectMultiple';
						}
						if(!isset( $field['selected'])) {
							$field['selected'] = null;
						}
						if(!isset( $field['selectAttr'])) {
							$field['selectAttr'] = null;
						}
						if(!isset( $field['optionsAttr'])) {
							$field['optionsAttr'] = null;
						}
						if($readOnly) {
							$field['selectAttr']['DISABLED'] = true;
						}
						if(!isset( $field['options'])) {
							$field['options'] = null;
						}
						$this->__modelAlias = null;
						if(isset($field['foreignKey'])) {
							$modelKey = Inflector::underscore($this->__modelClass);
							$modelObj =& ClassRegistry::getObject($modelKey);
							foreach($modelObj->belongsTo as $associationName =>$value) {
								if($field['model'] == $value['className']) {
									$this->__modelAlias = $this->__modelName($associationName);
									break;
								}
							}
						}
						$strFormFields .= $this->generateSelectDiv( $field['tagName'], $field['prompt'], $field['options'], $field['selected'], $field['selectAttr'], $field['optionsAttr'], $field['required'], $field['errorMsg'] );
					break;
					case "area";
						if(!isset( $field['rows'])) {
							$field['rows'] = 10;
						}
						if(!isset( $field['cols'])) {
							$field['cols'] = 60;
						}
						$strFormFields .= $this->generateAreaDiv( $field['tagName'], $field['prompt'], $field['required'], $field['errorMsg'], $field['cols'], $field['rows'], $field['htmlOptions'] );
					break;
					case "fieldset";
						$strFieldsetFields = $this->generateFields( $field['fields'] );
						$strFieldSet = sprintf( '
						<fieldset><legend>%s</legend><div class="notes"><h4>%s</h4><p class="last">%s</p></div>%s</fieldset>',
						$field['legend'], $field['noteHeading'], $field['note'], $strFieldsetFields );
						$strFormFields .= $strFieldSet;
					break;
					case "hidden";
						//$strFormFields .= $this->Html->hiddenTag( $field['tagName']);
					break;
					case "date":
						if (!isset($field['selected'])) {
							$field['selected'] = null;
						}
						$strFormFields .= $this->generateDate($field['tagName'], $field['prompt'], $field['required'], $field['errorMsg'], null, $field['htmlOptions'], $field['selected']);
					break;
					case "datetime":
						if (!isset($field['selected'])) {
							$field['selected'] = null;
						}
						$strFormFields .= $this->generateDateTime($field['tagName'], $field['prompt'], $field['required'], $field['errorMsg'], null, $field['htmlOptions'], $field['selected']);
					break;
					case "time":
						if (!isset($field['selected'])) {
							$field['selected'] = null;
						}
						$strFormFields .= $this->generateTime($field['tagName'], $field['prompt'], $field['required'], $field['errorMsg'], null, $field['htmlOptions'], $field['selected']);
					break;
					default:
					break;
				}
			}
		}
		return $strFormFields;
	}
/**
 * Generates PHP code for a View file that makes a textarea.
 *
 * @param string $tagName
 * @param string $prompt
 * @param boolean $required
 * @param string $errorMsg
 * @param integer $cols
 * @param integer $rows
 * @param array $htmlOptions
 * @return Generated HTML and PHP.
 */
	function generateAreaDiv($tagName, $prompt, $required=false, $errorMsg=null, $cols=60, $rows=10,  $htmlOptions=null ) {
		$htmlAttributes = $htmlOptions;
		$htmlAttributes['cols'] = $cols;
		$htmlAttributes['rows'] = $rows;
		$divClass = "optional";
		if( $required ) {
			$divClass = "required";
		}
		$divTagInside = "\n";
		$divTagInside .= "        <?php echo \$error->messageFor('{$tagName}'); ?>\n";
	  $divTagInside .= "        <div class=\"info\"><?php echo \$tooltip->show('".Inflector::camelize(substr($tagName,strpos($tagName,'/')+1))."','<p><b>Description:</b> field_description.</p><p><b>Requirements:</b></p><ul><li>".ucwords($divClass)." field.</li></ul>'); ?></div>\n";
		$divTagInside .= "        <div class=\"label\"><?php echo \$form->labelTag('{$tagName}', '{$prompt}');?></div>\n";
		$divTagInside .= "        <div class=\"input\"><?php echo \$html->textarea('{$tagName}', " . $this->__attributesToArray($htmlAttributes) . ");?></div>\n";
		$divTagInside .= "        <div class=\"clear\"></div>\n";
		return $this->divTag( $divClass, $divTagInside );
	}
/**
 * Generates PHP code for a View file that makes a checkbox, wrapped in a DIV.
 *
 * @param string $tagName
 * @param string $prompt
 * @param boolean $required
 * @param string $errorMsg
 * @param array $htmlOptions
 * @return Generated HTML and PHP.
 */
	function generateCheckboxDiv($tagName, $prompt, $required=false, $errorMsg=null, $htmlOptions=null ) {
		$htmlOptions['class'] = "inputCheckbox";
		$divClass = "optional";
  	if($required) {
			$divClass = "required";
		}

		$divTagInside = "\n";
		$divTagInside .= "        <?php echo \$error->messageFor('{$tagName}'); ?>\n";
	  $divTagInside .= "        <div class=\"info\"><?php echo \$tooltip->show('".Inflector::camelize(substr($tagName,strpos($tagName,'/')+1))."','<p><b>Description:</b> field_description.</p><p><b>Requirements:</b></p><ul><li>".ucwords($divClass)." field.</li></ul>'); ?></div>\n";
		$divTagInside .= "        <div class=\"label\"><?php echo \$html->checkbox('{$tagName}', null, " . $this->__attributesToArray($htmlAttributes) . ");?></div>\n";
		$divTagInside .= "        <div class=\"input\"><?php echo \$form->labelTag('{$tagName}', '{$prompt}');?></div>\n";
		$divTagInside .= "        <div class=\"clear\"></div>\n";
		return $this->divTag( $divClass, $divTagInside );
	}
/**
 * Generates PHP code for a View file that makes a date-picker, wrapped in a DIV.
 *
 * @param string $tagName
 * @param string $prompt
 * @param boolean $required
 * @param string $errorMsg
 * @param integer $size
 * @param array $htmlOptions
 * @param string $selected
 * @return Generated HTML and PHP.
 */
	function generateDate($tagName, $prompt, $required=false, $errorMsg=null, $size=20, $htmlOptions=null, $selected=null ) {
		$divClass = "optional";
		if($required) {
			$divClass = "required";
		}

		$divTagInside = "\n";
		$divTagInside .= "        <?php echo \$error->messageFor('{$tagName}'); ?>\n";
	  $divTagInside .= "        <div class=\"info\"><?php echo \$tooltip->show('".Inflector::camelize(substr($tagName,strpos($tagName,'/')+1))."','<p><b>Description:</b> field_description.</p><p><b>Requirements:</b></p><ul><li>".ucwords($divClass)." field.</li></ul>'); ?></div>\n";
		$divTagInside .= "        <div class=\"label\"><?php echo \$form->labelTag('{$tagName}', '{$prompt}');?></div>\n";
		$divTagInside .= "        <div class=\"input\"><?php echo \$html->dateTimeOptionTag('{$tagName}', 'MDY' , 'NONE', \$html->tagValue('{$tagName}'), " . $this->__attributesToArray($htmlOptions) . ");?></div>\n";
		$divTagInside .= "        <div class=\"clear\"></div>\n";
		return $this->divTag( $divClass, $divTagInside );
	}
/**
 * Generates PHP code for a View file that makes a time-picker, wrapped in a DIV.
 *
 * @param string $tagName
 * @param string $prompt
 * @param boolean $required
 * @param string $errorMsg
 * @param integer $size
 * @param array $htmlOptions
 * @param string $selected
 * @return Generated HTML and PHP.
 */
	function generateTime($tagName, $prompt, $required = false, $errorMsg = null, $size = 20, $htmlOptions = null, $selected = null) {
		$divClass = "optional";
		if($required) {
			$divClass = "required";
		}

		$divTagInside = "\n";
		$divTagInside .= "        <?php echo \$error->messageFor('{$tagName}'); ?>\n";
	  $divTagInside .= "        <div class=\"info\"><?php echo \$tooltip->show('".Inflector::camelize(substr($tagName,strpos($tagName,'/')+1))."','<p><b>Description:</b> field_description.</p><p><b>Requirements:</b></p><ul><li>".ucwords($divClass)." field.</li></ul>'); ?></div>\n";
		$divTagInside .= "        <div class=\"label\"><?php echo \$form->labelTag('{$tagName}', '{$prompt}');?></div>\n";
		$divTagInside .= "        <div class=\"input\"><?php echo \$html->dateTimeOptionTag('{$tagName}', 'NONE', '24', \$html->tagValue('{$tagName}'), " . $this->__attributesToArray($htmlOptions) . ");?></div>\n";
		$divTagInside .= "        <div class=\"clear\"></div>\n";
		return $this->divTag($divClass, $divTagInside);
	}
/**
 * EGenerates PHP code for a View file that makes a datetime-picker, wrapped in a DIV.
 *
 * @param string $tagName
 * @param string $prompt
 * @param boolean $required
 * @param string $errorMsg
 * @param integer $size
 * @param array $htmlOptions
 * @param string $selected
 * @return Generated HTML and PHP.
 */
	function generateDateTime($tagName, $prompt, $required=false, $errorMsg=null, $size=20, $htmlOptions=null, $selected = null ) {
		$divClass = "optional";
		if($required) {
			$divClass = "required";
		}

		$divTagInside = "\n";
		$divTagInside .= "        <?php echo \$error->messageFor('{$tagName}'); ?>\n";
	  $divTagInside .= "        <div class=\"info\"><?php echo \$tooltip->show('".Inflector::camelize(substr($tagName,strpos($tagName,'/')+1))."','<p><b>Description:</b> field_description.</p><p><b>Requirements:</b></p><ul><li>".ucwords($divClass)." field.</li></ul>'); ?></div>\n";
		$divTagInside .= "        <div class=\"label\"><?php echo \$form->labelTag('{$tagName}', '{$prompt}');?></div>\n";
		$divTagInside .= "        <div class=\"input\"><?php echo \$html->dateTimeOptionTag('{$tagName}', 'MDY' , '12', \$html->tagValue('{$tagName}'), " . $this->__attributesToArray($htmlOptions) . ");?></div>\n";
		$divTagInside .= "        <div class=\"clear\"></div>\n";
		return $this->divTag( $divClass, $divTagInside );
	}
/**
 * Generates PHP code for a View file that makes an INPUT field, wrapped in a DIV.
 *
 * @param string $tagName
 * @param string $prompt
 * @param boolean $required
 * @param string $errorMsg
 * @param integer $size
 * @param array $htmlOptions
 * @return Generated HTML and PHP.
 */
	function generateInputDiv($tagName, $prompt, $required=false, $errorMsg=null, $size=20, $htmlOptions=null ) {
		$htmlAttributes = $htmlOptions;
		$htmlAttributes['size'] = $size;
		$divClass = "optional";
		if($required) {
			$divClass = "required";
		}

		$divTagInside = "\n";
		$divTagInside .= "        <?php echo \$error->messageFor('{$tagName}'); ?>\n";
	  $divTagInside .= "        <div class=\"info\"><?php echo \$tooltip->show('".Inflector::camelize(substr($tagName,strpos($tagName,'/')+1))."','<p><b>Description:</b> field_description.</p><p><b>Requirements:</b></p><ul><li>".ucwords($divClass)." field.</li></ul>'); ?></div>\n";
    $divTagInside .= "        <div class=\"label\"><?php echo \$form->labelTag('{$tagName}', '{$prompt}');?></div>\n";
		$divTagInside .= "        <div class=\"input\"><?php echo \$html->input('{$tagName}', " . $this->__attributesToArray($htmlAttributes) . ");?></div>\n";
		$divTagInside .= "        <div class=\"clear\"></div>\n";
		return $this->divTag( $divClass, $divTagInside );
	}

/**
 * Generates PHP code for a View file that makes a SELECT box, wrapped in a DIV.
 *
 * @param string $tagName
 * @param string $prompt
 * @param array $options
 * @param string $selected
 * @param array $selectAttr
 * @param array $optionAttr
 * @param boolean $required
 * @param string $errorMsg
 * @return Generated HTML and PHP.
 */
	function generateSelectDiv($tagName, $prompt, $options, $selected=null, $selectAttr=null, $optionAttr=null, $required=false,  $errorMsg=null) {
		if($this->__modelAlias) {
			$pluralName = $this->__pluralName($this->__modelAlias);
		} else {
			$tagArray = explode('/', $tagName);
			$pluralName = $this->__pluralName($this->__modelNameFromKey($tagArray[1]));
		}
		$divClass = "optional";
		if($required) {
			$divClass = "required";
		}

		$divTagInside = "\n";
		$divTagInside .= "        <?php echo \$error->messageFor('{$tagName}'); ?>\n";
	  $divTagInside .= "        <div class=\"info\"><?php echo \$tooltip->show('".Inflector::camelize(substr($tagName,strpos($tagName,'/')+1))."','<p><b>Description:</b> field_description.</p><p><b>Requirements:</b></p><ul><li>".ucwords($divClass)." field.</li></ul>'); ?></div>\n";
		$divTagInside .= "        <div class=\"label\"><?php echo \$form->labelTag('{$tagName}', '{$prompt}');?></div>\n";

		if($selectAttr['multiple'] != 'multiple') {
			$divTagInside .= "        <div class=\"input\"><?php echo \$html->selectTag('{$tagName}', " . "\${$pluralName}, \$html->tagValue('{$tagName}'), " . $this->__attributesToArray($selectAttr) . ");?></div>\n";
		} else {
			$selectedPluralName = 'selected' . ucfirst($pluralName);
			$divTagInside .= "        <div class=\"input\"><?php echo \$html->selectTag('{$tagName}', \${$pluralName}, \${$selectedPluralName}, array('multiple' => 'multiple', 'class' => 'selectMultiple'));?></div>\n";
		}
		$divTagInside .= "        <div class=\"clear\"></div>\n";
		return $this->divTag( $divClass, $divTagInside );
	}
/**
 * Generates PHP code for a View file that makes a submit button, wrapped in a DIV.
 *
 * @param string $displayText
 * @param array $htmlOptions
 * @return Generated HTML.
 */
	function generateSubmitDiv($displayText, $htmlOptions = null) {
		$str = "\n        <?php echo \$html->submit('{$displayText}');?>\n";
		$divTagInside = sprintf( "%s", $str );
		return $this->divTag( 'submit', $divTagInside );
	}
/**
 * Returns the text wrapped in an HTML DIV, followed by a newline.
 *
 * @param string $class
 * @param string $text
 * @return Generated HTML.
 */
	function divTag($class, $text) {
		return sprintf('      <div class="%s">%s      </div>', $class, $text ) . "\n\n";
	}
/**
 * Parses the HTML attributes array, which is a common data structure in View files.
 * Returns PHP code for initializing this array in a View file.
 *
 * @param array $htmlAttributes
 * @return Generated PHP code.
 */
	function __attributesToArray($htmlAttributes) {
		if (is_array($htmlAttributes)) {
			$keys = array_keys($htmlAttributes);
			$vals = array_values($htmlAttributes);
			$out = "array(";

			for($i = 0; $i < count($htmlAttributes); $i++) {
				//don't put vars in quotes
				if(substr($vals[$i], 0, 1) != '$') {
					$out .= "'{$keys[$i]}' => '{$vals[$i]}', ";
				} else {
					$out .= "'{$keys[$i]}' => {$vals[$i]}, ";
				}
			}
			//Chop off last comma
			if(substr($out, -2, 1) == ',') {
				$out = substr($out, 0, strlen($out) - 2);
			}
			$out .= ")";
			return $out;
		} else {
			return 'array()';
		}
	}
/**
 * Outputs usage text on the standard output.
 *
 */
	function help() {
		$this->stdout('CakePHP Bake:');
		$this->hr();
		$this->stdout('The Bake script generates controllers, views and models for your application.');
		$this->stdout('If run with no command line arguments, Bake guides the user through the class');
		$this->stdout('creation process. You can customize the generation process by telling Bake');
		$this->stdout('where different parts of your application are using command line arguments.');
		$this->stdout('');
		$this->hr('');
		$this->stdout('usage: php bake.php [command] [path...]');
		$this->stdout('');
		$this->stdout('commands:');
		$this->stdout('   -app [path...] Absolute path to Cake\'s app Folder.');
		$this->stdout('   -core [path...] Absolute path to Cake\'s cake Folder.');
		$this->stdout('   -help Shows this help message.');
		$this->stdout('   -project [path...]  Generates a new app folder in the path supplied.');
		$this->stdout('   -root [path...] Absolute path to Cake\'s \app\webroot Folder.');
		$this->stdout('');
	}
/**
 * Checks that given project path does not already exist, and
 * finds the app directory in it. Then it calls __buildDirLayout() with that information.
 *
 * @param string $projectPath
 */
	function project($projectPath = null) {
		if($projectPath != '') {
			while ($this->__checkPath($projectPath) === true) {
				$projectPath = $this->getInput('Directory exists please choose another name:');
			}
		} else {
			while ($projectPath == '') {
				$projectPath = $this->getInput("What is the full path for this app including the app directory name?\nExample: ".ROOT.DS."myapp", null, ROOT.DS.'myapp');

				if ($projectPath == '') {
					$this->stdout('The directory path you supplied was empty. Please try again.');
				}
			}
		}
		while ($this->__checkPath($projectPath) === true || $projectPath == '') {
				$projectPath = $this->getInput('Directory path exists please choose another:');
			while ($projectPath == '') {
				$projectPath = $this->getInput('The directory path you supplied was empty. Please try again.');
			}
		}
		$parentPath = explode(DS, $projectPath);
		$count = count($parentPath);
		$appName = $parentPath[$count - 1];
		if($appName == '') {
			$appName = $parentPath[$count - 2];
		}
		$this->__buildDirLayout($projectPath, $appName);
		exit();
	}
/**
 * Returns true if given path is a directory.
 *
 * @param string $projectPath
 * @return True if given path is a directory.
 */
	function __checkPath($projectPath) {
		if(is_dir($projectPath)) {
			return true;
		} else {
			return false;
		}
	}
/**
 * Looks for a skeleton template of a Cake application,
 * and if not found asks the user for a path. When there is a path
 * this method will make a deep copy of the skeleton to the project directory.
 * A default home page will be added, and the tmp file storage will be chmod'ed to 0777.
 *
 * @param string $projectPath
 * @param string $appName
 */
	function __buildDirLayout($projectPath, $appName) {
		$skel = '';
		if($this->__checkPath(CAKE_CORE_INCLUDE_PATH.DS.'cake'.DS.'scripts'.DS.'templates'.DS.'skel') === true) {
			$skel = CAKE_CORE_INCLUDE_PATH.DS.'cake'.DS.'scripts'.DS.'templates'.DS.'skel';
		} else {

			while ($skel == '') {
				$skel = $this->getInput("What is the full path for the cake install app directory?\nExample: ", null, ROOT.'myapp'.DS);

				if ($skel == '') {
					$this->stdout('The directory path you supplied was empty. Please try again.');
				} else {
					while ($this->__checkPath($skel) === false) {
						$skel = $this->getInput('Directory path does not exist please choose another:');
					}
				}
			}
		}
		$this->stdout('');
		$this->hr();
		$this->stdout("Skel Directory: $skel");
		$this->stdout("Will be copied to:");
		$this->stdout("New App Directory: $projectPath");
		$this->hr();
		$looksGood = $this->getInput('Look okay?', array('y', 'n', 'q'), 'y');

		if (low($looksGood) == 'y' || low($looksGood) == 'yes') {
			$verboseOuptut = $this->getInput('Do you want verbose output?', array('y', 'n'), 'n');
			$verbose = false;

			if (low($verboseOuptut) == 'y' || low($verboseOuptut) == 'yes') {
				$verbose = true;
			}
			$this->__copydirr($skel, $projectPath, 0755, $verbose);
			$this->hr();
			$this->stdout('Created: '.$projectPath);
			$this->hr();
			$this->stdout('Creating welcome page');
			$this->hr();
			$this->__defaultHome($projectPath, $appName);
			$this->stdout('Welcome page created');
			if(chmodr($projectPath.DS.'tmp', 0777) === false) {
				$this->stdout('Could not set permissions on '. $projectPath.DS.'tmp'.DS.'*');
				$this->stdout('You must manually check that these directories can be wrote to by the server');
			}
			return;
		} elseif (low($looksGood) == 'q' || low($looksGood) == 'quit') {
			$this->stdout('Bake Aborted.');
		} else {
			$this->project();
		}
	}
/**
 * Recursive directory copy.
 *
 * @param string $fromDir
 * @param string $toDir
 * @param octal $chmod
 * @param boolean	 $verbose
 * @return Success.
 */
	function __copydirr($fromDir, $toDir, $chmod = 0755, $verbose = false) {
		$errors=array();
		$messages=array();

		if (!is_dir($toDir)) {
			uses('folder');
			$folder = new Folder();
			$folder->mkdirr($toDir, 0755);
		}

		if (!is_writable($toDir)) {
			$errors[]='target '.$toDir.' is not writable';
		}

		if (!is_dir($fromDir)) {
			$errors[]='source '.$fromDir.' is not a directory';
		}

		if (!empty($errors)) {
			if ($verbose) {
				foreach($errors as $err) {
					$this->stdout('Error: '.$err);
				}
			}
			return false;
		}
		$exceptions=array('.','..','.svn');
		$handle = opendir($fromDir);

		while (false!==($item = readdir($handle))) {
			if (!in_array($item,$exceptions)) {
				$from = str_replace('//','/',$fromDir.'/'.$item);
				$to = str_replace('//','/',$toDir.'/'.$item);
				if (is_file($from)) {
					if (@copy($from, $to)) {
						chmod($to, $chmod);
						touch($to, filemtime($from));
						$messages[]='File copied from '.$from.' to '.$to;
					} else {
						$errors[]='cannot copy file from '.$from.' to '.$to;
					}
				}

				if (is_dir($from)) {
					if (@mkdir($to)) {
						chmod($to,$chmod);
						$messages[]='Directory created: '.$to;
					} else {
						$errors[]='cannot create directory '.$to;
					}
					$this->__copydirr($from,$to,$chmod,$verbose);
				}
			}
		}
		closedir($handle);

		if ($verbose) {
			foreach($errors as $err) {
				$this->stdout('Error: '.$err);
			}
			foreach($messages as $msg) {
				$this->stdout($msg);
			}
		}
		return true;
	}

	function __addAdminRoute($name){
		$file = file_get_contents(CONFIGS.'core.php');
		if (preg_match('%([/\\t\\x20]*define\\(\'CAKE_ADMIN\',[\\t\\x20\'a-z]*\\);)%', $file, $match)) {
			$result = str_replace($match[0], 'define(\'CAKE_ADMIN\', \''.$name.'\');', $file);

			if(file_put_contents(CONFIGS.'core.php', $result)){
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
/**
 * Outputs an ASCII art banner to standard output.
 *
 */
	function welcome()
	{
		$this->stdout('');
		$this->stdout(' ___  __  _  _  ___  __  _  _  __      __   __  _  _  ___ ');
		$this->stdout('|    |__| |_/  |__  |__] |__| |__]    |__] |__| |_/  |__ ');
		$this->stdout('|___ |  | | \_ |___ |    |  | |       |__] |  | | \_ |___ ');
		$this->hr();
		$this->stdout('');
	}
/**
 * Writes a file with a default home page to the project.
 *
 * @param string $dir
 * @param string $app
 */
	function __defaultHome($dir, $app) {
		$path = $dir.DS.'views'.DS.'pages'.DS;
		include(CAKE_CORE_INCLUDE_PATH.DS.'cake'.DS.'scripts'.DS.'templates'.DS.'views'.DS.'home.thtml');
		$this->__createFile($path.'home.thtml', $output);
	}
/**
 * creates the proper pluralize controller for the url
 *
 * @param string $name
 * @return string $name
 */
	function __controllerPath($name) {
		return low(Inflector::tableize($name));
	}
/**
 * creates the proper pluralize controller class name.
 *
 * @param string $name
 * @return string $name
 */
	function __controllerName($name) {
		return Inflector::pluralize(Inflector::camelize($name));
	}
/**
 * creates the proper singular model name.
 *
 * @param string $name
 * @return string $name
 */
	function __modelName($name) {
		return Inflector::camelize(Inflector::singularize($name));
	}
/**
 * creates the proper singular model key for associations.
 *
 * @param string $name
 * @return string $name
 */
	function __modelKey($name) {
		return Inflector::underscore(Inflector::singularize($name)).'_id';
	}
/**
 * creates the proper model name from a foreign key.
 *
 * @param string $key
 * @return string $name
 */
	function __modelNameFromKey($key) {
		$name = str_replace('_id', '',$key);
		return $this->__modelName($name);
	}
/**
 * creates the singular name for use in views.
 *
 * @param string $name
 * @return string $name
 */
	function __singularName($name) {
		return Inflector::variable(Inflector::singularize($name));
	}
/**
 * creates the plural name for views.
 *
 * @param string $name
 * @return string $name
 */
	function __pluralName($name) {
		return Inflector::variable(Inflector::pluralize($name));
	}
/**
 * creates the singular human name used in views
 *
 * @param string $name
 * @return string $name
 */
	function __singularHumanName($name) {
		return Inflector::humanize(Inflector::underscore(Inflector::singularize($name)));
	}
/**
 * creates the plural humna name used in views
 *
 * @param string $name
 * @return string $name
 */
	function __pluralHumanName($name) {
		return Inflector::humanize(Inflector::underscore(Inflector::pluralize($name)));
	}
/**
 * outputs the a list of possible models or controllers from database
 *
 * @param string $useDbConfig
 * @param string $type = Models or Controllers
 * @return output
 */
	function __doList($useDbConfig = 'default', $type = 'Models') {
		$db =& ConnectionManager::getDataSource($useDbConfig);
		$usePrefix = empty($db->config['prefix']) ? '' : $db->config['prefix'];
		if ($usePrefix) {
			$tables = array();
			foreach ($db->listSources() as $table) {
				if (!strncmp($table, $usePrefix, strlen($usePrefix))) {
					$tables[] = substr($table, strlen($usePrefix));
				}
			}
		} else {
			$tables = $db->listSources();
		}
		$this->__tables = $tables;
		$this->stdout('Possible '.$type.' based on your current database:');
		$this->__controllerNames = array();
		$this->__modelNames = array();
		$count = count($tables);
		for ($i = 0; $i < $count; $i++) {
			if(low($type) == 'controllers') {
				$this->__controllerNames[] = $this->__controllerName($tables[$i]);
				$this->stdout($i + 1 . ". " . $this->__controllerNames[$i]);
			} else {
				$this->__modelNames[] = $this->__modelName($tables[$i]);
				$this->stdout($i + 1 . ". " . $this->__modelNames[$i]);
			}
		}
	}

}
?>