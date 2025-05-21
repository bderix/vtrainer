<?php

class Vokabel
{
	public int $id;
	public string $wordSource;
	public string $wordTarget;
	public string $exampleSentence;
	public int $importance;
	public string $dateAdded;
	public int $listId;
	public string $listName;

	public function __construct(
		int $id,
		string $wordSource,
		string $wordTarget,
		string $exampleSentence,
		int $importance,
		string $dateAdded,
		int $listId,
		string $listName
	) {
		$this->id = $id;
		$this->wordSource = $wordSource;
		$this->wordTarget = $wordTarget;
		$this->exampleSentence = $exampleSentence;
		$this->importance = $importance;
		$this->dateAdded = $dateAdded;
		$this->listId = $listId;
		$this->listName = $listName;
	}
}
