<?php
/**
 * Vocabulary Database class
 *
 * Handles all database operations for the vocabulary trainer
 *
 * PHP version 8.0
 */

class VocabularyDatabase {
	/**
	 * @var PDO Database connection
	 */
	private $db;

	/**
	 * Constructor
	 *
	 * @param PDO $db Database connection
	 */
	public function __construct($db) {
		$this->db = $db;
	}

	/**
	 * Get all vocabulary lists
	 *
	 * @return array Array of vocabulary lists
	 */
	public function getAllLists() {
		try {
			$query = "
                SELECT 
                    l.id, l.name, l.source_language, l.target_language, l.description,
                    COUNT(v.id) as vocabulary_count
                FROM vocabulary_lists l
                LEFT JOIN vocabulary v ON l.id = v.list_id
                GROUP BY l.id
                ORDER BY l.id = 1 DESC, l.name
            ";

			$stmt = $this->db->query($query);
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			error_log('Database error: ' . $e->getMessage());
			return [];
		}
	}

	/**
	 * Get a vocabulary list by ID
	 *
	 * @param int $id List ID
	 * @return array|bool List data or false if not found
	 */
	public function getListById($id) {
		try {
			$query = "
                SELECT 
                    l.id, l.name, l.source_language, l.target_language, l.description, 
                    COUNT(v.id) as vocabulary_count
                FROM vocabulary_lists l
                LEFT JOIN vocabulary v ON l.id = v.list_id
                WHERE l.id = :id
                GROUP BY l.id
            ";

			$stmt = $this->db->prepare($query);
			$stmt->bindParam(':id', $id, PDO::PARAM_INT);
			$stmt->execute();

			return $stmt->fetch(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			error_log('Database error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Create a new vocabulary list
	 *
	 * @param string $name List name
	 * @param string $description List description
	 * @return int|bool New list ID or false on failure
	 */
	public function createList($name, $description = '', $source_language = '', $target_language = '') {
		try {
			$query = "
               INSERT INTO vocabulary_lists (name, description, source_language, target_language)
               VALUES (:name, :description, :source_language, :target_language)
           ";

			$stmt = $this->db->prepare($query);
			$stmt->bindParam(':name', $name, PDO::PARAM_STR);
			$stmt->bindParam(':description', $description, PDO::PARAM_STR);
			$stmt->bindParam(':source_language', $source_language, PDO::PARAM_STR);
			$stmt->bindParam(':target_language', $target_language, PDO::PARAM_STR);
			$stmt->execute();

			return $this->db->lastInsertId();
		} catch (PDOException $e) {
			error_log('Database error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Update a vocabulary list
	 *
	 * @param int $id List ID
	 * @param string $name List name
	 * @param string $description List description
	 * @return bool Success or failure
	 */
	public function updateList($id, $name, $source_language = '', $target_language = '', $description = '') {
		try {
			$query = "
            UPDATE vocabulary_lists 
            SET name = :name, 
                description = :description,
                source_language = :source_language,
                target_language = :target_language
            WHERE id = :id
        ";

			$stmt = $this->db->prepare($query);
			$stmt->bindParam(':id', $id, PDO::PARAM_INT);
			$stmt->bindParam(':name', $name, PDO::PARAM_STR);
			$stmt->bindParam(':description', $description, PDO::PARAM_STR);
			$stmt->bindParam(':source_language', $source_language, PDO::PARAM_STR);
			$stmt->bindParam(':target_language', $target_language, PDO::PARAM_STR);

			return $stmt->execute();
		} catch (PDOException $e) {
			error_log('Database error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Delete a vocabulary list
	 *
	 * @param int $id List ID
	 * @return bool Success or failure
	 */
	public function deleteList($id) {
		try {
			// Start transaction
			$this->db->beginTransaction();

			// Move all vocabulary in this list to the default list
			$moveQuery = "
                UPDATE vocabulary
                SET list_id = 1
                WHERE list_id = :id
            ";

			$moveStmt = $this->db->prepare($moveQuery);
			$moveStmt->bindParam(':id', $id, PDO::PARAM_INT);
			$moveStmt->execute();

			// Delete the list
			$deleteQuery = "
                DELETE FROM vocabulary_lists
                WHERE id = :id
            ";

			$deleteStmt = $this->db->prepare($deleteQuery);
			$deleteStmt->bindParam(':id', $id, PDO::PARAM_INT);
			$deleteStmt->execute();

			// Commit transaction
			$this->db->commit();

			return true;
		} catch (PDOException $e) {
			// Rollback on error
			$this->db->rollBack();
			error_log('Database error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Get vocabulary filtered by list, importance, and search term
	 *
	 * @param int $listId List ID (0 for all lists)
	 * @param array $importance Array of importance levels
	 * @param string $searchTerm Search term
	 * @param string $sortBy Column to sort by
	 * @param string $sortOrder Sort order (ASC or DESC)
	 * @return array Array of vocabulary items
	 */
	public function getVocabularyByList($listId = 0, $importance = [], $searchTerm = '', $sortBy = 'date_added', $sortOrder = 'DESC') {
		try {
			$query = "
                SELECT 
                    v.id, 
                    v.word_source, 
                    v.word_target, 
                    v.example_sentence, 
                    v.importance, 
                    v.date_added,
                    v.list_id,
                    vl.name as list_name
                FROM vocabulary v
                LEFT JOIN vocabulary_lists vl ON v.list_id = vl.id
                WHERE 1=1
            ";

			$params = [];

			// Filter by list
			if ($listId > 0) {
				$query .= " AND v.list_id = :list_id";
				$params[':list_id'] = $listId;
			}

			// Filter by importance
			if (!empty($importance)) {
				$placeholders = [];
				for ($i = 0; $i < count($importance); $i++) {
					$param = ':importance' . $i;
					$placeholders[] = $param;
					$params[$param] = $importance[$i];
				}
				$query .= " AND v.importance IN (" . implode(', ', $placeholders) . ")";
			}

			// Filter by search term
			if (!empty($searchTerm)) {
				$query .= " AND (
                    v.word_source LIKE :search 
                    OR v.word_target LIKE :search 
                    OR v.example_sentence LIKE :search
                )";
				$params[':search'] = '%' . $searchTerm . '%';
			}

			// Add order by
			$allowedSortColumns = ['word_source', 'word_target', 'importance', 'date_added'];
			$allowedSortOrders = ['ASC', 'DESC'];

			if (!in_array($sortBy, $allowedSortColumns)) {
				$sortBy = 'date_added';
			}

			if (!in_array($sortOrder, $allowedSortOrders)) {
				$sortOrder = 'DESC';
			}

			$query .= " ORDER BY v." . $sortBy . " " . $sortOrder;

			$stmt = $this->db->prepare($query);

			foreach ($params as $param => $value) {
				$stmt->bindValue($param, $value);
			}

			$stmt->execute();
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			error_log('Database error: ' . $e->getMessage());
			return [];
		}
	}

	/**
	 * Get a vocabulary item by ID
	 *
	 * @param int $id Vocabulary ID
	 * @return array|bool Vocabulary data or false if not found
	 */
	public function getVocabularyById($id) {
		try {
			$query = "
                SELECT 
                    v.id, 
                    v.word_source, 
                    v.word_target, 
                    v.example_sentence, 
                    v.importance, 
                    v.date_added,
                    v.list_id,
                    vl.name as list_name
                FROM vocabulary v
                LEFT JOIN vocabulary_lists vl ON v.list_id = vl.id
                WHERE v.id = :id
            ";

			$stmt = $this->db->prepare($query);
			$stmt->bindParam(':id', $id, PDO::PARAM_INT);
			$stmt->execute();

			return $stmt->fetch(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			error_log('Database error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Add a new vocabulary item
	 *
	 * @param string $wordSource Source word
	 * @param string $wordTarget Target word
	 * @param string $exampleSentence Example sentence
	 * @param int $importance Importance (1-5)
	 * @param int $listId List ID
	 * @return int|bool New vocabulary ID or false on failure
	 */
	public function addVocabulary($wordSource, $wordTarget, $exampleSentence = '', $importance = 3, $listId = 1) {
		try {
			$query = "
                INSERT INTO vocabulary (word_source, word_target, example_sentence, importance, list_id)
                VALUES (:word_source, :word_target, :example_sentence, :importance, :list_id)
            ";

			$stmt = $this->db->prepare($query);
			$stmt->bindParam(':word_source', $wordSource, PDO::PARAM_STR);
			$stmt->bindParam(':word_target', $wordTarget, PDO::PARAM_STR);
			$stmt->bindParam(':example_sentence', $exampleSentence, PDO::PARAM_STR);
			$stmt->bindParam(':importance', $importance, PDO::PARAM_INT);
			$stmt->bindParam(':list_id', $listId, PDO::PARAM_INT);
			$stmt->execute();

			return $this->db->lastInsertId();
		} catch (PDOException $e) {
			error_log('Database error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Update a vocabulary item
	 *
	 * @param int $id Vocabulary ID
	 * @param string $wordSource Source word
	 * @param string $wordTarget Target word
	 * @param string $exampleSentence Example sentence
	 * @param int $importance Importance (1-5)
	 * @param int $listId List ID
	 * @return bool Success or failure
	 */
	public function updateVocabulary($id, $wordSource, $wordTarget, $exampleSentence = '', $importance = 3, $listId = 1) {
		try {
			$query = "
                UPDATE vocabulary
                SET 
                    word_source = :word_source, 
                    word_target = :word_target, 
                    example_sentence = :example_sentence, 
                    importance = :importance,
                    list_id = :list_id
                WHERE id = :id
            ";

			$stmt = $this->db->prepare($query);
			$stmt->bindParam(':id', $id, PDO::PARAM_INT);
			$stmt->bindParam(':word_source', $wordSource, PDO::PARAM_STR);
			$stmt->bindParam(':word_target', $wordTarget, PDO::PARAM_STR);
			$stmt->bindParam(':example_sentence', $exampleSentence, PDO::PARAM_STR);
			$stmt->bindParam(':importance', $importance, PDO::PARAM_INT);
			$stmt->bindParam(':list_id', $listId, PDO::PARAM_INT);

			return $stmt->execute();
		} catch (PDOException $e) {
			error_log('Database error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Check if a vocabulary entry with the same source and target words exists
	 *
	 * @param string $wordSource Source word
	 * @param string $wordTarget Target word
	 * @param int $excludeId ID to exclude from the check
	 * @return bool True if exists, false otherwise
	 */
	public function vocabExistsExcept($wordSource, $wordTarget, $excludeId = 0) {
		try {
			$query = "
                SELECT COUNT(*) FROM vocabulary
                WHERE word_source = :word_source 
                AND word_target = :word_target
                AND id != :exclude_id
            ";

			$stmt = $this->db->prepare($query);
			$stmt->bindParam(':word_source', $wordSource, PDO::PARAM_STR);
			$stmt->bindParam(':word_target', $wordTarget, PDO::PARAM_STR);
			$stmt->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
			$stmt->execute();

			return ($stmt->fetchColumn() > 0);
		} catch (PDOException $e) {
			error_log('Database error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Get vocabulary items for quiz based on filters, importance and success rate
	 *
	 * @param string $direction Quiz direction (source_to_target or target_to_source)
	 * @param array $importance Array of importance levels
	 * @param string $searchTerm Search term
	 * @param int $vocabId Specific vocabulary ID (0 for weighted selection)
	 * @param int $listId List ID (0 for all lists)
	 * @param int $recentLimit Limit to only recent vocabulary (0 for all)
	 * @param int $batchSize Number of vocabulary items to return (default 5)
	 * @return array Array of vocabulary items or empty array if none found
	 */
	public function getQuizVocabulary($direction, $importance = [], $searchTerm = '', $vocabId = 0, $listId = 0, $recentLimit = 0, $batchSize = 5) {
		try {
			// If a specific vocabulary ID is requested, just return that one
			if ($vocabId > 0) {
				$query = "
                SELECT 
                    v.id, 
                    v.word_source, 
                    v.word_target, 
                    v.example_sentence, 
                    v.importance,
                    v.date_added,
                    vl.name as list_name
                FROM vocabulary v
                LEFT JOIN vocabulary_lists vl ON v.list_id = vl.id
                WHERE v.id = :vocab_id
            ";

				$stmt = $this->db->prepare($query);
				$stmt->bindParam(':vocab_id', $vocabId, PDO::PARAM_INT);
				$stmt->execute();

				$result = $stmt->fetch(PDO::FETCH_ASSOC);
				return $result ? [$result] : [];
			}

			// For weighted selection, we need to get all matching vocabulary with their quiz statistics
			$query = "
            SELECT 
                v.id, 
                v.word_source, 
                v.word_target, 
                v.example_sentence, 
                v.importance,
                v.date_added,
                vl.name as list_name,
                COUNT(qa.id) as attempt_count,
                SUM(CASE WHEN qa.is_correct = 0 THEN 1 ELSE 0 END) as wrong_count,
                CASE WHEN COUNT(qa.id) > 0 
                    THEN CAST(SUM(CASE WHEN qa.is_correct = 0 THEN 1 ELSE 0 END) AS FLOAT) / COUNT(qa.id) * 100 
                    ELSE 0 
                END as failure_rate
            FROM vocabulary v
            LEFT JOIN vocabulary_lists vl ON v.list_id = vl.id
            LEFT JOIN quiz_attempts qa ON v.id = qa.vocabulary_id AND qa.direction = :direction
            WHERE 1=1
        ";


			$params = [':direction' => $direction];

			// Filter by list
			if ($listId > 0) {
				$query .= " AND v.list_id = :list_id";
				$params[':list_id'] = $listId;
			}

			// Filter by importance
			if (!empty($importance)) {
				$placeholders = [];
				for ($i = 0; $i < count($importance); $i++) {
					$param = ':importance' . $i;
					$placeholders[] = $param;
					$params[$param] = $importance[$i];
				}
				$query .= " AND v.importance IN (" . implode(', ', $placeholders) . ")";
			}

			// Filter by search term
			if (!empty($searchTerm)) {
				$query .= " AND (
                v.word_source LIKE :search 
                OR v.word_target LIKE :search 
                OR v.example_sentence LIKE :search
            )";
				$params[':search'] = '%' . $searchTerm . '%';
			}

			// Group by vocabulary ID to aggregate quiz statistics
			$query .= " GROUP BY v.id";

			// Limit to recent vocabulary if specified
			if ($recentLimit > 0) {
				$query .= " ORDER BY v.date_added DESC LIMIT :recent_limit";
				$params[':recent_limit'] = $recentLimit;
			}
			else {
				$query .= " ORDER BY attempt_count, failure_rate desc";
			}


			$query .= " LIMIT 20";

			xlog($query);

			$stmt = $this->db->prepare($query);

			foreach ($params as $param => $value) {
				if ($param === ':recent_limit') {
					$stmt->bindValue($param, $value, PDO::PARAM_INT);
				} else {
					$stmt->bindValue($param, $value);
				}
			}

			$stmt->execute();
			$vocabulary = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// If no vocabulary found, return empty array
			if (count($vocabulary) === 0) {
				return [];
			}

			// Calculate weights for each vocabulary item based on importance and failure rate
			$totalWeight = 0;
			$weights = [];

			xlog($vocabulary);
			foreach ($vocabulary as $key => $vocab) {
				// Base weight from importance (1-5)
				$importanceWeight = $vocab['importance'];

				// Additional weight from failure rate (0-100%)
				$failureRateWeight = $vocab['failure_rate'] / 20; // Scale to 0-5 range

				// Combined weight (higher = more likely to be selected)
				$weights[$key] = $importanceWeight + $failureRateWeight;
				$totalWeight += $weights[$key];
			}

			xlog($weights);

			// If we need to pick fewer items than available, use weighted random selection
			if (count($vocabulary) > $batchSize) {
				$selected = [];
				$selectedIndices = [];

				// Select batchSize items with weighted probability
				for ($i = 0; $i < $batchSize; $i++) {
					if (count($selectedIndices) >= count($vocabulary)) {
						break; // Break if we've selected all available items
					}

					// Get a random number between 0 and totalWeight
					$rand = mt_rand(0, intval($totalWeight * 1000)) / 1000;
					xlog($rand);
					$sum = 0;

					foreach ($weights as $key => $weight) {
						// Skip already selected items
						if (in_array($key, $selectedIndices)) {
							continue;
						}

						$sum += $weight;
						if ($sum >= $rand) {
							$selected[] = $vocabulary[$key];
							$selectedIndices[] = $key;
							$totalWeight -= $weight; // Remove this weight from the total
							break;
						}
					}

					// If we couldn't select an item (which shouldn't happen), just pick any remaining one
					if (count($selected) <= $i && count($selectedIndices) < count($vocabulary)) {
						foreach ($vocabulary as $key => $vocab) {
							if (!in_array($key, $selectedIndices)) {
								$selected[] = $vocab;
								$selectedIndices[] = $key;
								break;
							}
						}
					}
				}

				return $selected;
			}

			// If we have fewer or equal items than batchSize, return all
			return $vocabulary;
		} catch (PDOException $e) {
			error_log('Database error in getQuizVocabulary: ' . $e->getMessage());
			return [];
		}
	}
	/**
	 * Save quiz attempt result
	 *
	 * @param int $vocabId Vocabulary ID
	 * @param string $direction Quiz direction (source_to_target or target_to_source)
	 * @param int $isCorrect 1 if correct, 0 if incorrect
	 * @return bool Success or failure
	 */
	public function saveQuizAttempt($vocabId, $direction, $isCorrect) {
		try {
			$query = "
                INSERT INTO quiz_attempts (vocabulary_id, direction, is_correct)
                VALUES (:vocab_id, :direction, :is_correct)
            ";

			$stmt = $this->db->prepare($query);
			$stmt->bindParam(':vocab_id', $vocabId, PDO::PARAM_INT);
			$stmt->bindParam(':direction', $direction, PDO::PARAM_STR);
			$stmt->bindParam(':is_correct', $isCorrect, PDO::PARAM_INT);

			return $stmt->execute();
		} catch (PDOException $e) {
			error_log('Database error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Get quiz statistics for a vocabulary item
	 *
	 * @param int $vocabId Vocabulary ID
	 * @return array Statistics
	 */
	public function getVocabularyQuizStats($vocabId) {
		try {
			// Total attempts
			$query = "
                SELECT 
                    COUNT(*) as attempt_count,
                    SUM(is_correct) as correct_count,
                    CASE WHEN COUNT(*) > 0 THEN ROUND(SUM(is_correct) * 100.0 / COUNT(*)) ELSE 0 END as total_success_rate,
                    COUNT(CASE WHEN direction = 'source_to_target' THEN 1 END) as source_to_target_count,
                    SUM(CASE WHEN direction = 'source_to_target' THEN is_correct ELSE 0 END) as source_to_target_correct,
                    CASE 
                        WHEN COUNT(CASE WHEN direction = 'source_to_target' THEN 1 END) > 0 
                        THEN ROUND(SUM(CASE WHEN direction = 'source_to_target' THEN is_correct ELSE 0 END) * 100.0 / 
                            COUNT(CASE WHEN direction = 'source_to_target' THEN 1 END))
                        ELSE 0
                    END as source_to_target_rate,
                    COUNT(CASE WHEN direction = 'target_to_source' THEN 1 END) as target_to_source_count,
                    SUM(CASE WHEN direction = 'target_to_source' THEN is_correct ELSE 0 END) as target_to_source_correct,
                    CASE 
                        WHEN COUNT(CASE WHEN direction = 'target_to_source' THEN 1 END) > 0 
                        THEN ROUND(SUM(CASE WHEN direction = 'target_to_source' THEN is_correct ELSE 0 END) * 100.0 / 
                            COUNT(CASE WHEN direction = 'target_to_source' THEN 1 END))
                        ELSE 0
                    END as target_to_source_rate
                FROM quiz_attempts
                WHERE vocabulary_id = :vocab_id
            ";

			$stmt = $this->db->prepare($query);
			$stmt->bindParam(':vocab_id', $vocabId, PDO::PARAM_INT);
			$stmt->execute();

			$stats = $stmt->fetch(PDO::FETCH_ASSOC);

			if (!$stats) {
				return [
					'attempt_count' => 0,
					'correct_count' => 0,
					'total_success_rate' => 0,
					'source_to_target_count' => 0,
					'source_to_target_correct' => 0,
					'source_to_target_rate' => 0,
					'target_to_source_count' => 0,
					'target_to_source_correct' => 0,
					'target_to_source_rate' => 0
				];
			}

			return $stats;
		} catch (PDOException $e) {
			error_log('Database error: ' . $e->getMessage());
			return [
				'attempt_count' => 0,
				'correct_count' => 0,
				'total_success_rate' => 0,
				'source_to_target_count' => 0,
				'source_to_target_correct' => 0,
				'source_to_target_rate' => 0,
				'target_to_source_count' => 0,
				'target_to_source_correct' => 0,
				'target_to_source_rate' => 0
			];
		}
	}

	/**
	 * Get quiz statistics for a set of vocabulary items
	 *
	 * @param string $direction Quiz direction (source_to_target or target_to_source)
	 * @param array $importance Array of importance levels
	 * @param string $searchTerm Search term
	 * @param int $listId List ID (0 for all lists)
	 * @return array Statistics
	 */
	public function getQuizStats($direction, $importance = [], $searchTerm = '', $listId = 0, $recentLimit = 0) {
		try {
			// Build query to get vocabulary IDs based on filters
			$vocabQuery = "
                SELECT v.id
                FROM vocabulary v
                WHERE 1=1
            ";

			$params = [];

			// Filter by list
			if ($listId > 0) {
				$vocabQuery .= " AND v.list_id = :list_id";
				$params[':list_id'] = $listId;
			}

			// Filter by importance
			if (!empty($importance)) {
				$placeholders = [];
				for ($i = 0; $i < count($importance); $i++) {
					$param = ':importance' . $i;
					$placeholders[] = $param;
					$params[$param] = $importance[$i];
				}
				$vocabQuery .= " AND v.importance IN (" . implode(', ', $placeholders) . ")";
			}

			// Filter by search term
			if (!empty($searchTerm)) {
				$vocabQuery .= " AND (
                    v.word_source LIKE :search 
                    OR v.word_target LIKE :search 
                    OR v.example_sentence LIKE :search
                )";
				$params[':search'] = '%' . $searchTerm . '%';
			}

			// Add recent limit if specified
			if ($recentLimit > 0) {
				$vocabQuery .= " ORDER BY v.date_added DESC LIMIT :recent_limit";
				$params[':recent_limit'] = $recentLimit;
			}
			xlog($vocabQuery);

			// Prepare statement with all filter parameters
			$vocabStmt = $this->db->prepare($vocabQuery);

			foreach ($params as $param => $value) {
				$vocabStmt->bindValue($param, $value);
			}

			$vocabStmt->execute();
			$vocabularyIds = $vocabStmt->fetchAll(PDO::FETCH_COLUMN);

			// Count total vocabulary items matching the filters
			$totalCount = count($vocabularyIds);

			// If no vocabulary found, return empty stats
			if ($totalCount === 0) {
				return [
					'total_count' => 0,
					'attempt_count' => 0,
					'correct_count' => 0,
					'success_rate' => 0
				];
			}

			// Get quiz stats for the filtered vocabulary items
			$idPlaceholders = [];
			$statParams = [];

			for ($i = 0; $i < count($vocabularyIds); $i++) {
				$param = ':vocab_id' . $i;
				$idPlaceholders[] = $param;
				$statParams[$param] = $vocabularyIds[$i];
			}

			$statsQuery = "
                SELECT 
                    COUNT(*) as attempt_count,
                    SUM(is_correct) as correct_count
                FROM quiz_attempts
                WHERE vocabulary_id IN (" . implode(', ', $idPlaceholders) . ")
            ";

			// Add direction filter if specified
			if (!empty($direction) && $direction !== 'both') {
				$statsQuery .= " AND direction = :direction";
				$statParams[':direction'] = $direction;
			}

			$statsStmt = $this->db->prepare($statsQuery);

			foreach ($statParams as $param => $value) {
				$statsStmt->bindValue($param, $value);
			}

			$statsStmt->execute();
			$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

			// Calculate success rate
			$attemptCount = (int)$stats['attempt_count'];
			$correctCount = (int)$stats['correct_count'];
			$successRate = ($attemptCount > 0) ? round(($correctCount / $attemptCount) * 100, 1) : 0;

			return [
				'total_count' => $totalCount,
				'attempt_count' => $attemptCount,
				'correct_count' => $correctCount,
				'success_rate' => $successRate
			];
		} catch (PDOException $e) {
			error_log('Database error: ' . $e->getMessage());
			return [
				'total_count' => 0,
				'attempt_count' => 0,
				'correct_count' => 0,
				'success_rate' => 0
			];
		}
	}

	/**
	 * Get recently added vocabulary items
	 *
	 * @param int $limit Maximum number of items to return
	 * @return array Array of recently added vocabulary items
	 */
	public function getRecentVocabulary($limit = 5) {
		try {
			$query = "
                SELECT 
                    v.id, 
                    v.word_source, 
                    v.word_target, 
                    v.example_sentence, 
                    v.importance, 
                    v.date_added,
                    vl.name as list_name
                FROM vocabulary v
                LEFT JOIN vocabulary_lists vl ON v.list_id = vl.id
                ORDER BY v.date_added DESC
                LIMIT :limit
            ";

			$stmt = $this->db->prepare($query);
			$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
			$stmt->execute();

			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			error_log('Database error: ' . $e->getMessage());
			return [];
		}
	}

	public function getRecentlyPracticed($limit = 5) {
		$stmt = $this->db->query('
		SELECT v.*, MAX(qa.attempted_at) as last_practiced, SUM(qa.is_correct) as correct_count, COUNT(qa.id) as total_attempts
		FROM vocabulary v
		JOIN quiz_attempts qa ON v.id = qa.vocabulary_id
		GROUP BY v.id
		ORDER BY last_practiced DESC
		LIMIT ' . $limit . '
		');
		$recentlyPracticed = $stmt->fetchAll(PDO::FETCH_ASSOC);

		return $recentlyPracticed;
	}
}