<?php

/**
 * User: bderix
 * Date: 17.03.2025
 */
class Request {

	const params = array(
		'importance' => '[123]'
	);

	public function isPostRequest() {
		return $_SERVER['REQUEST_METHOD'] === 'POST';
	}

	public function redirect($target) {
		header("Location: {$target}.php");
	}

	public function delSessionValue($name) {
		if (isset($_SESSION[$name])) unset($_SESSION[$name]);
	}

	public function get($name, $default = null) {
		if (!isset($_GET[$name])) return $default;
		if (!$this->checkParam($name, $_GET[$name])) return $default;
		return $this->requestValue($name);
	}

	public function post($name, $default = null) {
		if (!isset($_POST[$name])) return $default;
		else return $this->requestValue($name);
	}

	public function checkParam($name, $value) {
		if (!isset(self::params[$name])) return false;
		if (is_array($value)) {
			foreach ($value as $item) {
				$ok = $this->checkParam($name, $item);
				if (!$ok) return false;
			}
			return true;
		}
		else return preg_match("/^" . self::params[$name] . "$/", $value);
	}

	private function requestValue($name) {
		// Parameter mit Id am Ende sind per Definition Integer
		if (is_array($_REQUEST[$name])) return $_REQUEST[$name];
		else if (strtolower(substr($name, 0, -2)) == 'id') return intval($_REQUEST[$name]);
		else return trim($_REQUEST[$name]);
	}

	public function getAction() {
		return $this->requestValue('action');
	}

	public function getRecentLimit() {
		$recentLimit = isset($_GET['recent_limit']) ? intval($_GET['recent_limit']) : (isset($_SESSION['quiz_recent_limit']) ? $_SESSION['quiz_recent_limit'] : 0);
		// if ($recentLimit < 0) $recentLimit = 0;
		// if ($recentLimit > 50) $recentLimit = 50;
		return $recentLimit;
	}

	public function getListId($input = null) {
	if (empty($input)) $input = $_GET;
// Get list ID from GET, session, or default to 1 (standard list)
		if (isset($input['list_id'])) {
			$listId = intval($input['list_id']);
			if ($listId > 0) {
				$_SESSION['list_id'] = $listId;
				return $listId;
			}
		} else if (isset($_SESSION['list_id'])) {
			return $_SESSION['list_id'];
		}
		return false;
	}


}