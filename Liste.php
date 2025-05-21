<?php
/**
 * Class Liste
 *
 * Modelliert eine Vokabelliste mit allen notwendigen Feldern.
 * PHP version 8.0
 */
class Liste {
	public int $id;
	public string $name;
	public string $sourceLanguage;
	public string $targetLanguage;
	public string $description;
	public bool $ispublic;
	public int $userId;
	public int $vocabularyCount;

	public function __construct(
		int $id,
		string $name,
		string $sourceLanguage,
		string $targetLanguage,
		string $description,
		bool $ispublic,
		int $userId,
		int $vocabularyCount = 0
	) {
		$this->id = $id;
		$this->name = $name;
		$this->sourceLanguage = $sourceLanguage;
		$this->targetLanguage = $targetLanguage;
		$this->description = $description;
		$this->ispublic = $ispublic;
		$this->userId = $userId;
		$this->vocabularyCount = $vocabularyCount;
	}

	public function getId(): int { return $this->id; }
	public function getName(): string { return $this->name; }

	public function getSourceLanguage(): string { return $this->sourceLanguage ?? 'Grundsprache'; }
	public function getTargetLanguage(): string { return $this->targetLanguage ?? 'Zielsprache'; }

	public function getDescription(): string { return $this->description; }
	public function ispublic(): bool { return $this->ispublic; }
	public function getUserId(): int { return $this->userId; }
	public function getVocabularyCount(): int { return $this->vocabularyCount; }
}
