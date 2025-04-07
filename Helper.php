<?php

/**
 * User: bderix
 * Date: 13.03.2025
 */
class Helper {

	/**
	 * Helper function to get badge color based on importance
	 */
	public static function getImportanceBadgeColor($importance) {
		switch ($importance) {
			case 1:
				return 'danger';
			case 2:
				return 'primary';
			case 3:
				return 'success';
			default:
				return 'primary';
		}
	}

	/**
	 * Helper function to get progress bar color based on success rate
	 */
	public static function getProgressBarColor($rate) {
		if ($rate < 30) {
			return 'bg-danger';
		} elseif ($rate < 50) {
			return 'bg-warning';
		} elseif ($rate < 70) {
			return 'bg-info';
		} else {
			return 'bg-success';
		}
	}




}