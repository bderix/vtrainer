<?php

/**
 * Class UserListen
 *
 * Hält alle Vokabellisten eines bestimmten Benutzers.
 * PHP version 8.0
 */
class UserListen {
	private VocabularyDatabase $db;
	private int $userId;
	/** @var Liste[] */
	private array $lists;

	public function __construct(VocabularyDatabase $db, int $userId) {
		$this->db = $db;
		$this->userId = $userId;
	}

	public function getList($listId) {
		foreach ($this->lists as $list) {
			if ($list->id == $listId) return $list;
		}
		return false;
	}

	/** @return Liste[] */
	public function getLists(): array {
		if (empty($this->lists)) $this->lists = $this->db->getVocabularyListsByUser($this->userId);
		return $this->lists;
	}

	public function getDefaultList() {
		$this->getLists();
		return $this->lists[0];
	}

	public function isUserList($listId) {
		$this->getLists();
		foreach ($this->lists as $list) {
			if ($list->id == $listId) return true;
		}
		return false;
	}

	public function isUserVokabel($vokabelId) {
		$v = $this->db->getVokabel($this->userId, $vokabelId);
		return !empty($v);
	}

	public function getRecentlyAdded() {
		return $this->db->getRecentVocabulary($this->userId, 5);
	}
	public function getRecentlyPracticed() {
		return $this->db->getRecentlyPracticed($this->userId, 5);
	}


}