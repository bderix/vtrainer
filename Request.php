<?php

/**
 * User: bderix
 * Date: 17.03.2025
 */
class Request {

	public function getRecentLimit() {
		$recentLimit = isset($_GET['recent_limit']) ? intval($_GET['recent_limit']) : (isset($_SESSION['quiz_recent_limit']) ? $_SESSION['quiz_recent_limit'] : 0);
		// if ($recentLimit < 0) $recentLimit = 0;
		// if ($recentLimit > 50) $recentLimit = 50;
		return $recentLimit;
	}

}