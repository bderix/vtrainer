<?php
/**
 * Configuration file for the Vocabulary Trainer
 *
 * PHP version 8.0
 */

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_PATH', __DIR__ . '/vocabulary.db');
define('APP_NAME', 'Vokabeltrainer');

include 'Helper.php';
include 'Request.php';

session_start();

$vtrequest = new Request();

// Function to get database connection
function getDbConnection() {
    $db = null;
    try {
        // Create/connect to the SQLite database
        $db = new PDO('sqlite:' . DB_PATH);

        // Set error mode to exceptions
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Enable foreign keys
        $db->exec('PRAGMA foreign_keys = ON;');
    } catch (PDOException $e) {
        // Handle database connection error
        die("Database connection failed: " . $e->getMessage());
    }

    return $db;
}

// Function to initialize database if it doesn't exist
function initializeDatabase() {
    if (!file_exists(DB_PATH)) {
        $db = getDbConnection();

        // Read SQL from file
        $sql = file_get_contents(__DIR__ . '/db-structure.sql');
		// print_r($sql);

        // Execute SQL statements
        try {
            $db->exec($sql);
            return true;
        } catch (PDOException $e) {
            die("Database initialization failed: " . $e->getMessage());
        }
    }
    return false;
}

function xlog($msg, $tracenr = 0, $log_on_live_system = false, $header = '') {
	static $first = true;
	static $divId = 1;
	//  return;
		$logfile = 'logfile.html';
		$logfile_bak = 'logfile.bak.html';
		$traceStr = '';
		$js = '';

		if ($first) {
			// error_log::xlog('get', $_SERVER);
			//          $status = http_response_code();
			$ok = copy($logfile, $logfile_bak);
			$handle = fopen($logfile, 'w+');
			if (!$handle) return;
			ftruncate($handle, 0);
			$first = false;
			$js = "<script>function toggleMsg(div) {
			  var x = document.getElementById(div);
			  if (x.style.display == 'none') x.style.display = 'block'
			  else x.style.display = 'none';}</script>";
		}

		$trace = debug_backtrace();
		//  array_shift($trace);
		if ($tracenr > 0) {
			for ($i = 0; $i < min(count($trace), $tracenr); $i++) {
				$traceStr .= basename($trace[$i]['file']) . ':' . $trace[$i]['line'] . '->';
			}
			$traceStr = substr($traceStr, 0, -2);
		} else {
			$file = basename($trace[0]['file']);
			$line = $trace[0]['line'];
			$func = '';
			if (isset($trace[1])) $func = $trace[1]['function'];
			$traceStr = "$func@$file: $line";
		}
		$date = date("Y-m-d h:i:s");
		if (!is_string($msg)) $msg = print_r($msg, 1);
		error_log("-- $date $traceStr: $msg\n ", 3, $logfile);
		//      $msg = nl2br($msg);
		$msg = nl2br(htmlentities($msg));
		$msg = str_replace("    ", '&nbsp;&nbsp;&nbsp;&nbsp;', $msg);
		$divId = rand(0, 10000);

		if ($header) $header = "<b>$header</b>";

		error_log("$js<hr><div onclick='toggleMsg(\"$divId\");'>{$header} $date <b>$traceStr:</b></div><div id='$divId' style='display: block'> $msg\n </div>", 3, $logfile);
}

// Initialize database if needed
$dbInitialized = initializeDatabase();
