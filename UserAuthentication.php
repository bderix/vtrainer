<?php
/**
 * User Authentication and Management Class
 *
 * Provides functionality for user authentication, registration, and management
 * with support for different user roles (admin, premium, standard)
 *
 * PHP version 8.0
 */

class UserAuthentication {
	/**
	 * @var PDO Database connection
	 */
	private $db;

	/**
	 * @var array Current user data
	 */
	private $currentUser = null;

	/**
	 * @var array Error messages
	 */
	private $errors = [];

	/**
	 * @var array User roles cache
	 */
	private $roles = [];

	/**
	 * Constructor
	 *
	 * @param PDO $db Database connection
	 */
	public function __construct($db) {
		$this->db = $db;
		$this->loadUserRoles();
		$this->initSession();
	}

	/**
	 * Initialize the session and check for remember-me token
	 */
	private function initSession() {
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}

		// Check if user is already logged in via session
		if (isset($_SESSION['user_id'])) {
			$this->loadUserById($_SESSION['user_id']);
		}
		// If not, check for remember-me cookie
		elseif (isset($_COOKIE['remember_token']) && !empty($_COOKIE['remember_token'])) {
			$this->loginWithRememberToken($_COOKIE['remember_token']);
		}
	}

	/**
	 * Load all user roles into cache
	 */
	private function loadUserRoles() {
		try {
			$stmt = $this->db->query("SELECT id, name, description FROM user_roles");
			$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

			foreach ($roles as $role) {
				$this->roles[$role['id']] = $role;
			}
		} catch (PDOException $e) {
			$this->addError('database', 'Fehler beim Laden der Benutzerrollen: ' . $e->getMessage());
		}
	}

	/**
	 * Register a new user
	 *
	 * @param string $email User email
	 * @param string $username Username
	 * @param string $password Plain text password
	 * @param array $profile Optional profile data
	 * @param int $roleId User role ID (default: 3 for 'standard')
	 * @return int|bool User ID on success, false on failure
	 */
	public function registerUser($email, $username, $password, $profile = [], $roleId = 3) {
		$this->errors = [];

		// Validate input
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$this->addError('email', 'Ungültige E-Mail-Adresse');
		}

		if (empty($username) || strlen($username) < 3) {
			$this->addError('username', 'Der Benutzername muss mindestens 3 Zeichen lang sein');
		}

		if (empty($password) || strlen($password) < 8) {
			$this->addError('password', 'Das Passwort muss mindestens 8 Zeichen lang sein');
		}

		// Check if email already exists
		if ($this->emailExists($email)) {
			$this->addError('email', 'Diese E-Mail-Adresse wird bereits verwendet');
		}

		// Check if username already exists
		if ($this->usernameExists($username)) {
			$this->addError('username', 'Dieser Benutzername wird bereits verwendet');
		}

		// If there are validation errors, return false
		if (!empty($this->errors)) {
			return false;
		}

		// Hash password
		$passwordHash = password_hash($password, PASSWORD_DEFAULT);

		// Generate email verification token
		$verificationToken = bin2hex(random_bytes(32));

		try {
			// Begin transaction
			$this->db->beginTransaction();

			// Insert user
			$stmt = $this->db->prepare("
                INSERT INTO users (email, username, password_hash, role_id, verification_token)
                VALUES (:email, :username, :password_hash, :role_id, :verification_token)
            ");

			$stmt->bindParam(':email', $email, PDO::PARAM_STR);
			$stmt->bindParam(':username', $username, PDO::PARAM_STR);
			$stmt->bindParam(':password_hash', $passwordHash, PDO::PARAM_STR);
			$stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
			$stmt->bindParam(':verification_token', $verificationToken, PDO::PARAM_STR);

			$stmt->execute();

			// Get new user ID
			$userId = $this->db->lastInsertId();

			// Insert user profile
			$stmt = $this->db->prepare("
                INSERT INTO user_profiles (user_id, first_name, last_name, language, timezone)
                VALUES (:user_id, :first_name, :last_name, :language, :timezone)
            ");

			$firstName = $profile['first_name'] ?? null;
			$lastName = $profile['last_name'] ?? null;
			$language = $profile['language'] ?? 'de';
			$timezone = $profile['timezone'] ?? 'Europe/Berlin';

			$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
			$stmt->bindParam(':first_name', $firstName, PDO::PARAM_STR);
			$stmt->bindParam(':last_name', $lastName, PDO::PARAM_STR);
			$stmt->bindParam(':language', $language, PDO::PARAM_STR);
			$stmt->bindParam(':timezone', $timezone, PDO::PARAM_STR);

			$stmt->execute();

			// Log activity
			$this->logUserActivity($userId, 'registration', [
				'method' => 'email',
				'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
			]);

			// Commit transaction
			$this->db->commit();

			// TODO: Send verification email

			return $userId;

		} catch (PDOException $e) {
			// Rollback on error
			$this->db->rollBack();
			$this->addError('database', 'Datenbankfehler bei der Registrierung: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Attempt to log in a user
	 *
	 * @param string $email User email
	 * @param string $password Plain text password
	 * @param bool $remember Whether to remember the user
	 * @return bool True on success, false on failure
	 */
	public function login($email, $password, $remember = false) {
		$this->errors = [];

		try {
			// Get user by email
			$stmt = $this->db->prepare("
                SELECT id, email, username, password_hash, role_id, is_active, email_verified
                FROM users
                WHERE email = :email
            ");
			$stmt->bindParam(':email', $email, PDO::PARAM_STR);
			$stmt->execute();
			$user = $stmt->fetch(PDO::FETCH_ASSOC);

			// Check if user exists
			if (!$user) {
				$stmt = $this->db->prepare("
                SELECT id, email, username, password_hash, role_id, is_active, email_verified
                FROM users
                WHERE username = :username
            ");
				$stmt->bindParam(':username', $email, PDO::PARAM_STR);
				$stmt->execute();
				$user = $stmt->fetch(PDO::FETCH_ASSOC);
			}

			if (!$user) {
				$this->addError('credentials', 'Ungültige E-Mail-Adresse oder Passwort');
				return false;
			}

			// Check if user is active
			if (!$user['is_active']) {
				$this->addError('account', 'Dieses Konto wurde deaktiviert');
				return false;
			}

			// Verify password
			if (!password_verify($password, $user['password_hash'])) {
				$this->addError('credentials', 'Ungültige E-Mail-Adresse oder Passwort');

				// Log failed login attempt
				$this->logUserActivity($user['id'], 'login_failed', [
					'method' => 'password',
					'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
					'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
				]);

				return false;
			}

			// Update password hash if needed
			if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
				$newHash = password_hash($password, PASSWORD_DEFAULT);

				$updateStmt = $this->db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
				$updateStmt->bindParam(':hash', $newHash, PDO::PARAM_STR);
				$updateStmt->bindParam(':id', $user['id'], PDO::PARAM_INT);
				$updateStmt->execute();
			}

			// Update last login time
			$updateStmt = $this->db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id");
			$updateStmt->bindParam(':id', $user['id'], PDO::PARAM_INT);
			$updateStmt->execute();

			// Set session variables
			$_SESSION['user_id'] = $user['id'];
			$_SESSION['username'] = $user['username'];
			$_SESSION['role_id'] = $user['role_id'];
			$_SESSION['email_verified'] = $user['email_verified'];

			// Create remember token if requested
			if ($remember) {
				$this->createRememberToken($user['id']);
			}

			// Log successful login
			$this->logUserActivity($user['id'], 'login', [
				'method' => 'password',
				'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
			]);

			// Load full user data
			$this->loadUserById($user['id']);

			return true;

		} catch (PDOException $e) {
			$this->addError('database', 'Datenbankfehler beim Login: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Log in with a remember token
	 *
	 * @param string $token Remember token
	 * @return bool True on success, false on failure
	 */
	public function loginWithRememberToken($token) {
		try {
			// Get session by token
			$stmt = $this->db->prepare("
                SELECT us.user_id, us.expires_at, u.email, u.username, u.role_id, u.is_active, u.email_verified
                FROM user_sessions us
                JOIN users u ON us.user_id = u.id
                WHERE us.session_token = :token AND us.expires_at > CURRENT_TIMESTAMP
            ");

			$stmt->bindParam(':token', $token, PDO::PARAM_STR);
			$stmt->execute();

			$session = $stmt->fetch(PDO::FETCH_ASSOC);

			// Check if session exists and is valid
			if (!$session) {
				// Delete invalid cookie
				setcookie('remember_token', '', time() - 3600, '/', '', true, true);
				return false;
			}

			// Check if user is still active
			if (!$session['is_active']) {
				// Delete session and cookie
				$this->deleteRememberToken($token);
				return false;
			}

			// Set session variables
			$_SESSION['user_id'] = $session['user_id'];
			$_SESSION['username'] = $session['username'];
			$_SESSION['role_id'] = $session['role_id'];
			$_SESSION['email_verified'] = $session['email_verified'];

			// Refresh remember token
			$this->deleteRememberToken($token);
			$this->createRememberToken($session['user_id']);

			// Update last login time
			$updateStmt = $this->db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id");
			$updateStmt->bindParam(':id', $session['user_id'], PDO::PARAM_INT);
			$updateStmt->execute();

			// Log successful login
			$this->logUserActivity($session['user_id'], 'login', [
				'method' => 'remember_token',
				'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
			]);

			// Load full user data
			$this->loadUserById($session['user_id']);

			return true;

		} catch (PDOException $e) {
			$this->addError('database', 'Datenbankfehler beim Token-Login: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Create a remember token for a user
	 *
	 * @param int $userId User ID
	 * @return bool True on success, false on failure
	 */
	private function createRememberToken($userId) {
		try {
			// Generate a secure token
			$token = bin2hex(random_bytes(32));

			// Set expiration to 30 days from now
			$expires = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);

			// Insert session record
			$stmt = $this->db->prepare("
                INSERT INTO user_sessions (user_id, session_token, user_agent, ip_address, expires_at)
                VALUES (:user_id, :token, :user_agent, :ip_address, :expires_at)
            ");

			$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
			$ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

			$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
			$stmt->bindParam(':token', $token, PDO::PARAM_STR);
			$stmt->bindParam(':user_agent', $userAgent, PDO::PARAM_STR);
			$stmt->bindParam(':ip_address', $ipAddress, PDO::PARAM_STR);
			$stmt->bindParam(':expires_at', $expires, PDO::PARAM_STR);

			$stmt->execute();

			// Set cookie
			setcookie('remember_token', $token, time() + 60 * 60 * 24 * 30, '/', '', false, true);

			return true;

		} catch (PDOException $e) {
			$this->addError('database', 'Fehler beim Erstellen des Remember-Tokens: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Delete a remember token
	 *
	 * @param string $token Remember token
	 * @return bool True on success, false on failure
	 */
	private function deleteRememberToken($token) {
		try {
			$stmt = $this->db->prepare("DELETE FROM user_sessions WHERE session_token = :token");
			$stmt->bindParam(':token', $token, PDO::PARAM_STR);
			$stmt->execute();

			// Remove cookie
			setcookie('remember_token', '', time() - 3600, '/', '', false, true);

			return true;

		} catch (PDOException $e) {
			$this->addError('database', 'Fehler beim Löschen des Remember-Tokens: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Logout the current user
	 *
	 * @param bool $allSessions Whether to invalidate all sessions (default: false)
	 * @return bool True on success, false on failure
	 */
	public function logout($allSessions = false) {
		// Check if user is logged in
		if (!$this->isLoggedIn()) {
			return false;
		}

		$userId = $_SESSION['user_id'];

		try {
			// Delete remember token cookie
			if (isset($_COOKIE['remember_token'])) {
				$this->deleteRememberToken($_COOKIE['remember_token']);
			}

			// Delete all sessions if requested
			if ($allSessions) {
				$stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = :user_id");
				$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
				$stmt->execute();
			}

			// Log logout activity
			$this->logUserActivity($userId, 'logout', [
				'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
			]);

			// Destroy session
			session_unset();
			session_destroy();

			$this->currentUser = null;

			return true;

		} catch (PDOException $e) {
			$this->addError('database', 'Datenbankfehler beim Logout: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Check if a user is logged in
	 *
	 * @return bool True if user is logged in, false otherwise
	 */
	public function isLoggedIn() {
		return isset($_SESSION['user_id']);
	}

	/**
	 * Get the current user's data
	 *
	 * @return array|null User data or null if not logged in
	 */
	public function getCurrentUser() {
		return $this->currentUser;
	}

	/**
	 * Load user data by ID
	 *
	 * @param int $userId User ID
	 * @return bool True on success, false on failure
	 */
	public function loadUserById($userId) {
		try {
			// Get user data
			$stmt = $this->db->prepare("
                SELECT u.*, r.name as role_name, r.description as role_description,
                       p.first_name, p.last_name, p.language, p.timezone, p.avatar, p.bio
                FROM users u
                JOIN user_roles r ON u.role_id = r.id
                LEFT JOIN user_profiles p ON u.id = p.user_id
                WHERE u.id = :id
            ");

			$stmt->bindParam(':id', $userId, PDO::PARAM_INT);
			$stmt->execute();

			$user = $stmt->fetch(PDO::FETCH_ASSOC);

			if (!$user) {
				return false;
			}

			// Remove sensitive data
			unset($user['password_hash']);
			unset($user['verification_token']);
			unset($user['reset_token']);

			$this->currentUser = $user;

			return true;

		} catch (PDOException $e) {
			$this->addError('database', 'Fehler beim Laden der Benutzerdaten: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Update user profile
	 *
	 * @param int $userId User ID
	 * @param array $data Profile data to update
	 * @return bool True on success, false on failure
	 */
	public function updateProfile($userId, $data) {
		$this->errors = [];

		// Check if user exists
		if (!$this->userExists($userId)) {
			$this->addError('user', 'Benutzer nicht gefunden');
			return false;
		}

		try {
			// Begin transaction
			$this->db->beginTransaction();

			// Update username if provided
			if (isset($data['username']) && !empty($data['username'])) {
				// Check if username is already taken by another user
				$stmt = $this->db->prepare("SELECT id FROM users WHERE username = :username AND id != :id");
				$stmt->bindParam(':username', $data['username'], PDO::PARAM_STR);
				$stmt->bindParam(':id', $userId, PDO::PARAM_INT);
				$stmt->execute();

				if ($stmt->fetch()) {
					$this->addError('username', 'Dieser Benutzername wird bereits verwendet');
					$this->db->rollBack();
					return false;
				}

				// Update username
				$stmt = $this->db->prepare("UPDATE users SET username = :username, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
				$stmt->bindParam(':username', $data['username'], PDO::PARAM_STR);
				$stmt->bindParam(':id', $userId, PDO::PARAM_INT);
				$stmt->execute();
			}

			// Update profile data
			$updateFields = [];
			$params = [':user_id' => $userId];

			$profileFields = ['first_name', 'last_name', 'language', 'timezone', 'bio'];

			foreach ($profileFields as $field) {
				if (isset($data[$field])) {
					$updateFields[] = "$field = :$field";
					$params[":$field"] = $data[$field];
				}
			}

			if (!empty($updateFields)) {
				// Check if profile exists
				$stmt = $this->db->prepare("SELECT user_id FROM user_profiles WHERE user_id = :user_id");
				$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
				$stmt->execute();

				if ($stmt->fetch()) {
					// Update existing profile
					$sql = "UPDATE user_profiles SET " . implode(', ', $updateFields) .
						", updated_at = CURRENT_TIMESTAMP WHERE user_id = :user_id";

					$stmt = $this->db->prepare($sql);
					foreach ($params as $key => $value) {
						$stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
					}
					$stmt->execute();
				} else {
					// Create new profile
					$fields = array_keys($params);
					$placeholders = array_map(function($field) { return ":$field"; }, $fields);

					$sql = "INSERT INTO user_profiles (" . implode(', ', $fields) .
						") VALUES (" . implode(', ', $placeholders) . ")";

					$stmt = $this->db->prepare($sql);
					foreach ($params as $key => $value) {
						$stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
					}
					$stmt->execute();
				}
			}

			// Handle avatar upload if included
			if (isset($data['avatar']) && $data['avatar']['error'] === UPLOAD_ERR_OK) {
				$avatarPath = $this->processAvatarUpload($data['avatar'], $userId);

				if ($avatarPath) {
					$stmt = $this->db->prepare("
                        UPDATE user_profiles 
                        SET avatar = :avatar, updated_at = CURRENT_TIMESTAMP 
                        WHERE user_id = :user_id
                    ");
					$stmt->bindParam(':avatar', $avatarPath, PDO::PARAM_STR);
					$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
					$stmt->execute();
				}
			}

			// Log activity
			$this->logUserActivity($userId, 'profile_update', [
				'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
			]);

			// Commit transaction
			$this->db->commit();

			// Reload user data if it's the current user
			if ($this->isLoggedIn() && $_SESSION['user_id'] == $userId) {
				$this->loadUserById($userId);

				// Update session username if changed
				if (isset($data['username']) && !empty($data['username'])) {
					$_SESSION['username'] = $data['username'];
				}
			}

			return true;

		} catch (PDOException $e) {
			// Rollback on error
			$this->db->rollBack();
			$this->addError('database', 'Datenbankfehler beim Aktualisieren des Profils: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Process avatar upload
	 *
	 * @param array $file Upload file data
	 * @param int $userId User ID
	 * @return string|bool Path to the saved avatar or false on failure
	 */
	private function processAvatarUpload($file, $userId) {
		// Create avatars directory if it doesn't exist
		$uploadDir = __DIR__ . '/../uploads/avatars/';
		if (!is_dir($uploadDir)) {
			mkdir($uploadDir, 0755, true);
		}

		// Generate a unique filename
		$filename = 'avatar_' . $userId . '_' . time() . '.jpg';
		$uploadPath = $uploadDir . $filename;

		// Check file type
		$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
		if (!in_array($file['type'], $allowedTypes)) {
			$this->addError('avatar', 'Nur JPEG, PNG und GIF-Bilder sind erlaubt');
			return$this->addError('avatar', 'Nur JPEG, PNG und GIF-Bilder sind erlaubt');
			return false;
		}

		// Check file size (max 2MB)
		if ($file['size'] > 2 * 1024 * 1024) {
			$this->addError('avatar', 'Das Bild darf maximal 2MB groß sein');
			return false;
		}

		// Move uploaded file
		if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
			// Return the relative path to store in the database
			return 'uploads/avatars/' . $filename;
		} else {
			$this->addError('avatar', 'Fehler beim Hochladen des Bildes');
			return false;
		}
	}

	/**
	 * Change user password
	 *
	 * @param int $userId User ID
	 * @param string $currentPassword Current password
	 * @param string $newPassword New password
	 * @return bool True on success, false on failure
	 */
	public function changePassword($userId, $currentPassword, $newPassword) {
		$this->errors = [];

		// Validate new password
		if (empty($newPassword) || strlen($newPassword) < 8) {
			$this->addError('new_password', 'Das neue Passwort muss mindestens 8 Zeichen lang sein');
			return false;
		}

		try {
			// Get current password hash
			$stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = :id");
			$stmt->bindParam(':id', $userId, PDO::PARAM_INT);
			$stmt->execute();

			$user = $stmt->fetch(PDO::FETCH_ASSOC);

			if (!$user) {
				$this->addError('user', 'Benutzer nicht gefunden');
				return false;
			}

			// Verify current password
			if (!password_verify($currentPassword, $user['password_hash'])) {
				$this->addError('current_password', 'Das aktuelle Passwort ist falsch');
				return false;
			}

			// Hash new password
			$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

			// Update password
			$stmt = $this->db->prepare("
                UPDATE users 
                SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
			$stmt->bindParam(':password_hash', $newHash, PDO::PARAM_STR);
			$stmt->bindParam(':id', $userId, PDO::PARAM_INT);
			$stmt->execute();

			// Invalidate all sessions except the current one for security
			if (isset($_COOKIE['remember_token'])) {
				$currentToken = $_COOKIE['remember_token'];

				$stmt = $this->db->prepare("
                    DELETE FROM user_sessions 
                    WHERE user_id = :user_id AND session_token != :current_token
                ");
				$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
				$stmt->bindParam(':current_token', $currentToken, PDO::PARAM_STR);
				$stmt->execute();
			} else {
				// Delete all sessions if no current token
				$stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = :user_id");
				$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
				$stmt->execute();
			}

			// Log activity
			$this->logUserActivity($userId, 'password_change', [
				'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
			]);

			return true;

		} catch (PDOException $e) {
			$this->addError('database', 'Datenbankfehler beim Ändern des Passworts: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Request password reset
	 *
	 * @param string $email User email
	 * @return bool True on success, false on failure
	 */
	public function requestPasswordReset($email) {
		$this->errors = [];

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$this->addError('email', 'Ungültige E-Mail-Adresse');
			return false;
		}

		try {
			// Check if email exists
			$stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email");
			$stmt->bindParam(':email', $email, PDO::PARAM_STR);
			$stmt->execute();

			$user = $stmt->fetch(PDO::FETCH_ASSOC);

			if (!$user) {
				// Don't reveal if email exists for security reasons
				// but still return true to prevent user enumeration
				return true;
			}

			// Generate reset token
			$token = bin2hex(random_bytes(32));
			$expires = date('Y-m-d H:i:s', time() + 60 * 60); // 1 hour expiry

			// Update user with reset token
			$stmt = $this->db->prepare("
                UPDATE users 
                SET reset_token = :token, reset_token_expires_at = :expires, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
			$stmt->bindParam(':token', $token, PDO::PARAM_STR);
			$stmt->bindParam(':expires', $expires, PDO::PARAM_STR);
			$stmt->bindParam(':id', $user['id'], PDO::PARAM_INT);
			$stmt->execute();

			// Log activity
			$this->logUserActivity($user['id'], 'password_reset_request', [
				'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
			]);

			// TODO: Send password reset email with the token
			// This would typically use a mail library like PHPMailer

			return true;

		} catch (PDOException $e) {
			$this->addError('database', 'Datenbankfehler bei der Passwort-Reset-Anfrage: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Reset password using token
	 *
	 * @param string $token Reset token
	 * @param string $newPassword New password
	 * @return bool True on success, false on failure
	 */
	public function resetPassword($token, $newPassword) {
		$this->errors = [];

		// Validate new password
		if (empty($newPassword) || strlen($newPassword) < 8) {
			$this->addError('new_password', 'Das neue Passwort muss mindestens 8 Zeichen lang sein');
			return false;
		}

		try {
			// Find user by reset token
			$stmt = $this->db->prepare("
                SELECT id 
                FROM users 
                WHERE reset_token = :token AND reset_token_expires_at > CURRENT_TIMESTAMP
            ");
			$stmt->bindParam(':token', $token, PDO::PARAM_STR);
			$stmt->execute();

			$user = $stmt->fetch(PDO::FETCH_ASSOC);

			if (!$user) {
				$this->addError('token', 'Ungültiger oder abgelaufener Token');
				return false;
			}

			// Hash new password
			$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

			// Update password and clear token
			$stmt = $this->db->prepare("
                UPDATE users 
                SET password_hash = :password_hash, reset_token = NULL, reset_token_expires_at = NULL, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
			$stmt->bindParam(':password_hash', $newHash, PDO::PARAM_STR);
			$stmt->bindParam(':id', $user['id'], PDO::PARAM_INT);
			$stmt->execute();

			// Delete all sessions for security
			$stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = :user_id");
			$stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
			$stmt->execute();

			// Log activity
			$this->logUserActivity($user['id'], 'password_reset', [
				'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
			]);

			return true;

		} catch (PDOException $e) {
			$this->addError('database', 'Datenbankfehler beim Zurücksetzen des Passworts: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Verify user email
	 *
	 * @param string $token Verification token
	 * @return bool True on success, false on failure
	 */
	public function verifyEmail($token) {
		$this->errors = [];

		try {
			// Find user by verification token
			$stmt = $this->db->prepare("SELECT id FROM users WHERE verification_token = :token");
			$stmt->bindParam(':token', $token, PDO::PARAM_STR);
			$stmt->execute();

			$user = $stmt->fetch(PDO::FETCH_ASSOC);

			if (!$user) {
				$this->addError('token', 'Ungültiger Verifizierungstoken');
				return false;
			}

			// Update user to mark email as verified
			$stmt = $this->db->prepare("
                UPDATE users 
                SET email_verified = 1, verification_token = NULL, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
			$stmt->bindParam(':id', $user['id'], PDO::PARAM_INT);
			$stmt->execute();

			// Update session if user is logged in
			if ($this->isLoggedIn() && $_SESSION['user_id'] == $user['id']) {
				$_SESSION['email_verified'] = 1;

				// Reload user data
				$this->loadUserById($user['id']);
			}

			// Log activity
			$this->logUserActivity($user['id'], 'email_verified', [
				'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
			]);

			return true;

		} catch (PDOException $e) {
			$this->addError('database', 'Datenbankfehler bei der E-Mail-Verifizierung: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Change user role
	 *
	 * @param int $userId User ID
	 * @param int $roleId New role ID
	 * @return bool True on success, false on failure
	 */
	public function changeUserRole($userId, $roleId) {
		$this->errors = [];

		// Check if current user is admin
		if (!$this->isAdmin()) {
			$this->addError('permission', 'Keine Berechtigung zum Ändern von Benutzerrollen');
			return false;
		}

		// Check if role exists
		if (!isset($this->roles[$roleId])) {
			$this->addError('role', 'Ungültige Benutzerrolle');
			return false;
		}

		try {
			// Update user role
			$stmt = $this->db->prepare("
                UPDATE users 
                SET role_id = :role_id, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
			$stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
			$stmt->bindParam(':id', $userId, PDO::PARAM_INT);
			$stmt->execute();

			if ($stmt->rowCount() === 0) {
				$this->addError('user', 'Benutzer nicht gefunden');
				return false;
			}

			// Log activity
			$adminId = $_SESSION['user_id'] ?? null;
			$this->logUserActivity($adminId, 'role_change', [
				'target_user_id' => $userId,
				'new_role_id' => $roleId,
				'new_role_name' => $this->roles[$roleId]['name'],
				'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
			]);

			// Update session if it's the current user
			if ($this->isLoggedIn() && $_SESSION['user_id'] == $userId) {
				$_SESSION['role_id'] = $roleId;
				$this->loadUserById($userId);
			}

			return true;

		} catch (PDOException $e) {
			$this->addError('database', 'Datenbankfehler beim Ändern der Benutzerrolle: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Activate or deactivate a user
	 *
	 * @param int $userId User ID
	 * @param bool $active Whether to activate (true) or deactivate (false)
	 * @return bool True on success, false on failure
	 */
	public function setUserActive($userId, $active) {
		$this->errors = [];

		// Check if current user is admin
		if (!$this->isAdmin()) {
			$this->addError('permission', 'Keine Berechtigung zum Aktivieren/Deaktivieren von Benutzern');
			return false;
		}

		try {
			// Update user status
			$stmt = $this->db->prepare("
                UPDATE users 
                SET is_active = :is_active, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
			$isActive = $active ? 1 : 0;
			$stmt->bindParam(':is_active', $isActive, PDO::PARAM_INT);
			$stmt->bindParam(':id', $userId, PDO::PARAM_INT);
			$stmt->execute();

			if ($stmt->rowCount() === 0) {
				$this->addError('user', 'Benutzer nicht gefunden');
				return false;
			}

			// Log activity
			$adminId = $_SESSION['user_id'] ?? null;
			$action = $active ? 'user_activated' : 'user_deactivated';
			$this->logUserActivity($adminId, $action, [
				'target_user_id' => $userId,
				'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
			]);

			// If deactivating, invalidate all sessions
			if (!$active) {
				$stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = :user_id");
				$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
				$stmt->execute();

				// Log out the current user if they're being deactivated
				if ($this->isLoggedIn() && $_SESSION['user_id'] == $userId) {
					$this->logout();
				}
			}

			return true;

		} catch (PDOException $e) {
			$this->addError('database', 'Datenbankfehler beim Ändern des Benutzerstatus: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Delete a user account
	 *
	 * @param int $userId User ID
	 * @param string $password Admin password for confirmation
	 * @return bool True on success, false on failure
	 */
	public function deleteUser($userId, $password) {
		$this->errors = [];

		// Check if current user is admin
		if (!$this->isAdmin()) {
			$this->addError('permission', 'Keine Berechtigung zum Löschen von Benutzern');
			return false;
		}

		// Verify admin password
		$adminId = $_SESSION['user_id'];
		$stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = :id");
		$stmt->bindParam(':id', $adminId, PDO::PARAM_INT);
		$stmt->execute();
		$admin = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!password_verify($password, $admin['password_hash'])) {
			$this->addError('password', 'Falsches Passwort');
			return false;
		}

		try {
			// Begin transaction
			$this->db->beginTransaction();

			// Get user data for logging
			$stmt = $this->db->prepare("SELECT email, username FROM users WHERE id = :id");
			$stmt->bindParam(':id', $userId, PDO::PARAM_INT);
			$stmt->execute();
			$userData = $stmt->fetch(PDO::FETCH_ASSOC);

			if (!$userData) {
				$this->addError('user', 'Benutzer nicht gefunden');
				$this->db->rollBack();
				return false;
			}

			// Delete sessions
			$stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = :user_id");
			$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
			$stmt->execute();

			// Delete activity logs
			$stmt = $this->db->prepare("DELETE FROM user_activity_logs WHERE user_id = :user_id");
			$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
			$stmt->execute();

			// Delete profile
			$stmt = $this->db->prepare("DELETE FROM user_profiles WHERE user_id = :user_id");
			$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
			$stmt->execute();

			// Delete user-vocabulary list associations
			$stmt = $this->db->prepare("DELETE FROM user_vocabulary_lists WHERE user_id = :user_id");
			$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
			$stmt->execute();

			// Finally delete user
			$stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
			$stmt->bindParam(':id', $userId, PDO::PARAM_INT);
			$stmt->execute();

			// Log activity
			$this->logUserActivity($adminId, 'user_deleted', [
				'target_user_id' => $userId,
				'target_user_email' => $userData['email'],
				'target_username' => $userData['username'],
				'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
			]);

			// Commit transaction
			$this->db->commit();

			// If the user deleted themselves, log them out
			if ($adminId == $userId) {
				$this->logout();
			}

			return true;

		} catch (PDOException $e) {
			// Rollback on error
			$this->db->rollBack();
			$this->addError('database', 'Datenbankfehler beim Löschen des Benutzers: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Get all users (admin function)
	 *
	 * @param int $page Page number (default: 1)
	 * @param int $perPage Items per page (default: 20)
	 * @param string $sortBy Column to sort by (default: id)
	 * @param string $sortDir Sort direction (default: ASC)
	 * @param array $filters Optional filters
	 * @return array Array of users
	 */
	public function getAllUsers($page = 1, $perPage = 20, $sortBy = 'id', $sortDir = 'ASC', $filters = []) {
		$this->errors = [];

		// Check if current user is admin
		if (!$this->isAdmin()) {
			$this->addError('permission', 'Keine Berechtigung zum Auflisten von Benutzern');
			return [];
		}

		// Validate sort column
		$allowedSortColumns = ['id', 'email', 'username', 'role_id', 'is_active', 'last_login', 'created_at'];
		if (!in_array($sortBy, $allowedSortColumns)) {
			$sortBy = 'id';
		}

		// Validate sort direction
		$sortDir = strtoupper($sortDir);
		if ($sortDir != 'ASC' && $sortDir != 'DESC') {
			$sortDir = 'ASC';
		}

		// Calculate offset
		$offset = ($page - 1) * $perPage;

		try {
			// Build base query
			$query = "
                SELECT u.id, u.email, u.username, u.role_id, u.is_active, u.email_verified, 
                       u.last_login, u.created_at, r.name as role_name,
                       p.first_name, p.last_name
                FROM users u
                JOIN user_roles r ON u.role_id = r.id
                LEFT JOIN user_profiles p ON u.id = p.user_id
                WHERE 1=1
            ";

			$params = [];

			// Apply filters
			if (isset($filters['role_id']) && $filters['role_id']) {
				$query .= " AND u.role_id = :role_id";
				$params[':role_id'] = $filters['role_id'];
			}

			if (isset($filters['is_active']) && $filters['is_active'] !== '') {
				$query .= " AND u.is_active = :is_active";
				$params[':is_active'] = $filters['is_active'] ? 1 : 0;
			}

			if (isset($filters['email_verified']) && $filters['email_verified'] !== '') {
				$query .= " AND u.email_verified = :email_verified";
				$params[':email_verified'] = $filters['email_verified'] ? 1 : 0;
			}

			if (isset($filters['search']) && $filters['search']) {
				$query .= " AND (u.email LIKE :search OR u.username LIKE :search OR p.first_name LIKE :search OR p.last_name LIKE :search)";
				$params[':search'] = '%' . $filters['search'] . '%';
			}

			// Add sorting and pagination
			$query .= " ORDER BY u.$sortBy $sortDir LIMIT :limit OFFSET :offset";

			// Prepare and execute query
			$stmt = $this->db->prepare($query);

			// Bind parameters
			foreach ($params as $key => $value) {
				$stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
			}

			$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
			$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

			$stmt->execute();
			$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// Get total count for pagination
			$countQuery = "
                SELECT COUNT(*) as total 
                FROM users u
                LEFT JOIN user_profiles p ON u.id = p.user_id
                WHERE 1=1
            ";

			// Apply the same filters to count query
			if (isset($filters['role_id']) && $filters['role_id']) {
				$countQuery .= " AND u.role_id = :role_id";
			}

			if (isset($filters['is_active']) && $filters['is_active'] !== '') {
				$countQuery .= " AND u.is_active = :is_active";
			}

			if (isset($filters['email_verified']) && $filters['email_verified'] !== '') {
				$countQuery .= " AND u.email_verified = :email_verified";
			}

			if (isset($filters['search']) && $filters['search']) {
				$countQuery .= " AND (u.email LIKE :search OR u.username LIKE :search OR p.first_name LIKE :search OR p.last_name LIKE :search)";
			}

			$countStmt = $this->db->prepare($countQuery);

			// Bind parameters for count query
			foreach ($params as $key => $value) {
				$countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
			}

			$countStmt->execute();
			$totalCount = $countStmt->fetchColumn();

			return [
				'users' => $users,
				'total' => $totalCount,
				'page' => $page,
				'per_page' => $perPage,
				'last_page' => ceil($totalCount / $perPage)
			];

		} catch (PDOException $e) {
			$this->addError('database', 'Datenbankfehler beim Abrufen der Benutzer: ' . $e->getMessage());
			return [
				'users' => [],
				'total' => 0,
				'page' => $page,
				'per_page' => $perPage,
				'last_page' => 1
			];
		}
	}

	/**
	 * Get all available user roles
	 *
	 * @return array Array of roles
	 */
	public function getAllRoles() {
		return $this->roles;
	}

	/**
	 * Check if a user has a specific role
	 *
	 * @param string $roleName Role name to check
	 * @return bool True if user has the role, false otherwise
	 */
	public function hasRole($roleName) {
		if (!$this->isLoggedIn()) {
			return false;
		}

		$roleId = $_SESSION['role_id'];

		foreach ($this->roles as $role) {
			if ($role['id'] == $roleId && $role['name'] == $roleName) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if current user is an admin
	 *
	 * @return bool True if user is admin, false otherwise
	 */
	public function isAdmin() {
		return $this->hasRole('admin');
	}

	/**
	 * Check if current user is a premium user
	 *
	 * @return bool True if user is premium, false otherwise
	 */
	public function isPremium() {
		return $this->hasRole('admin') || $this->hasRole('premium');
	}

	/**
	 * Check if email exists
	 *
	 * @param string $email Email to check
	 * @return bool True if email exists, false otherwise
	 */
	private function emailExists($email) {
		$stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email");
		$stmt->bindParam(':email', $email, PDO::PARAM_STR);
		$stmt->execute();

		return $stmt->fetch() !== false;
	}

	/**
	 * Check if username exists
	 *
	 * @param string $username Username to check
	 * @return bool True if username exists, false otherwise
	 */
	private function usernameExists($username) {
		$stmt = $this->db->prepare("SELECT id FROM users WHERE username = :username");
		$stmt->bindParam(':username', $username, PDO::PARAM_STR);
		$stmt->execute();

		return $stmt->fetch() !== false;
	}

	/**
	 * Check if user exists
	 *
	 * @param int $userId User ID to check
	 * @return bool True if user exists, false otherwise
	 */
	private function userExists($userId) {
		$stmt = $this->db->prepare("SELECT id FROM users WHERE id = :id");
		$stmt->bindParam(':id', $userId, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetch() !== false;
	}

	/**
	 * Log user activity
	 *
	 * @param int $userId User ID
	 * @param string $activityType Activity type
	 * @param array $details Additional details
	 * @return bool True on success, false on failure
	 */
	private function logUserActivity($userId, $activityType, $details = []) {
		if (!$userId) {
			return false;
		}

		try {
			$stmt = $this->db->prepare("
                INSERT INTO user_activity_logs (user_id, activity_type, ip_address, user_agent, details)
                VALUES (:user_id, :activity_type, :ip_address, :user_agent, :details)
            ");

			$ipAddress = $details['ip'] ?? null;
			$userAgent = $details['user_agent'] ?? null;
			$detailsJson = json_encode($details);

			$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
			$stmt->bindParam(':activity_type', $activityType, PDO::PARAM_STR);
			$stmt->bindParam(':ip_address', $ipAddress, PDO::PARAM_STR);
			$stmt->bindParam(':user_agent', $userAgent, PDO::PARAM_STR);
			$stmt->bindParam(':details', $detailsJson, PDO::PARAM_STR);

			return $stmt->execute();

		} catch (PDOException $e) {
			// Just log to error_log and continue - don't let activity logging failure affect main operations
			error_log('Failed to log user activity: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Get user activity logs
	 *
	 * @param int $userId User ID
	 * @param int $limit Maximum number of logs to retrieve
	 * @return array Activity logs
	 */
	public function getUserActivityLogs($userId, $limit = 20) {
		// Check if current user is admin or the user themselves
		if (!$this->isAdmin() && (!$this->isLoggedIn() || $_SESSION['user_id'] != $userId)) {
			$this->addError('permission', 'Keine Berechtigung zum Anzeigen von Aktivitätsprotokollen');
			return [];
		}

		try {
			$stmt = $this->db->prepare("
                SELECT id, activity_type, ip_address, user_agent, details, created_at
                FROM user_activity_logs
                WHERE user_id = :user_id
                ORDER BY created_at DESC
                LIMIT :limit
            ");

			$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decode JSON details
            foreach ($logs as &$log) {
				if (isset($log['details'])) {
					$log['details'] = json_decode($log['details'], true);
				}
			}

            return $logs;

        } catch (PDOException $e) {
			$this->addError('database', 'Datenbankfehler beim Abrufen der Aktivitätsprotokolle: ' . $e->getMessage());
			return [];
		}
	}

	/**
	 * Add an error message
	 *
	 * @param string $key Error key
	 * @param string $message Error message
	 */
	private function addError($key, $message) {
		$this->errors[$key] = $message;
	}

	/**
	 * Get all error messages
	 *
	 * @return array Error messages
	 */
	public function getErrors() {
		return $this->errors;
	}

	/**
	 * Get first error message
	 *
	 * @return string|null First error message or null if no errors
	 */
	public function getFirstError() {
		return !empty($this->errors) ? reset($this->errors) : null;
	}

	/**
	 * Get error message for a specific key
	 *
	 * @param string $key Error key
	 * @return string|null Error message or null if key not found
	 */
	public function getError($key) {
		return isset($this->errors[$key]) ? $this->errors[$key] : null;
	}

	/**
	 * Check if there are any errors
	 *
	 * @return bool True if there are errors, false otherwise
	 */
	public function hasErrors() {
		return !empty($this->errors);
	}

	/**
	 * Create a new user role
	 *
	 * @param string $name Role name
	 * @param string $description Role description
	 * @return int|bool New role ID on success, false on failure
	 */
	public function createRole($name, $description = '') {
		$this->errors = [];

		// Check if current user is admin
		if (!$this->isAdmin()) {
			$this->addError('permission', 'Keine Berechtigung zum Erstellen von Benutzerrollen');
			return false;
		}

		// Validate input
		if (empty($name)) {
			$this->addError('name', 'Der Rollenname darf nicht leer sein');
			return false;
		}

		// Check if role name already exists
		foreach ($this->roles as $role) {
			if (strtolower($role['name']) === strtolower($name)) {
				$this->addError('name', 'Eine Rolle mit diesem Namen existiert bereits');
				return false;
			}
		}

		try {
			// Insert new role
			$stmt = $this->db->prepare("
                INSERT INTO user_roles (name, description)
                VALUES (:name, :description)
            ");

			$stmt->bindParam(':name', $name, PDO::PARAM_STR);
			$stmt->bindParam(':description', $description, PDO::PARAM_STR);
			$stmt->execute();

			$roleId = $this->db->lastInsertId();

			// Add to roles cache
			$this->roles[$roleId] = [
				'id' => $roleId,
				'name' => $name,
				'description' => $description
			];

			// Log activity
			$adminId = $_SESSION['user_id'] ?? null;
			$this->logUserActivity($adminId, 'role_created', [
				'role_id' => $roleId,
				'role_name' => $name,
				'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
			]);

			return $roleId;

		} catch (PDOException $e) {
			$this->addError('database', 'Datenbankfehler beim Erstellen der Rolle: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Update a user role
	 *
	 * @param int $roleId Role ID
	 * @param string $name New role name
	 * @param string $description New role description
	 * @return bool True on success, false on failure
	 */
	public function updateRole($roleId, $name, $description = '') {
		$this->errors = [];

		// Check if current user is admin
		if (!$this->isAdmin()) {
			$this->addError('permission', 'Keine Berechtigung zum Aktualisieren von Benutzerrollen');
			return false;
		}

		// Validate input
		if (empty($name)) {
			$this->addError('name', 'Der Rollenname darf nicht leer sein');
			return false;
		}

		// Check if role exists
		if (!isset($this->roles[$roleId])) {
			$this->addError('role', 'Rolle nicht gefunden');
			return false;
		}

		// Prevent modification of built-in roles
		if ($roleId <= 3) {
			$this->addError('role', 'Systemrollen können nicht geändert werden');
			return false;
		}

		// Check if new name conflicts with existing roles
		foreach ($this->roles as $role) {
			if ($role['id'] != $roleId && strtolower($role['name']) === strtolower($name)) {
				$this->addError('name', 'Eine Rolle mit diesem Namen existiert bereits');
				return false;
			}
		}

		try {
			// Update role
			$stmt = $this->db->prepare("
                UPDATE user_roles 
                SET name = :name, description = :description 
                WHERE id = :id
            ");

			$stmt->bindParam(':id', $roleId, PDO::PARAM_INT);
			$stmt->bindParam(':name', $name, PDO::PARAM_STR);
			$stmt->bindParam(':description', $description, PDO::PARAM_STR);
			$stmt->execute();

			// Update roles cache
			$this->roles[$roleId]['name'] = $name;
			$this->roles[$roleId]['description'] = $description;

			// Log activity
			$adminId = $_SESSION['user_id'] ?? null;
			$this->logUserActivity($adminId, 'role_updated', [
				'role_id' => $roleId,
				'role_name' => $name,
				'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
			]);

			return true;

		} catch (PDOException $e) {
			$this->addError('database', 'Datenbankfehler beim Aktualisieren der Rolle: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Delete a user role
	 *
	 * @param int $roleId Role ID
	 * @param int $newRoleId New role ID for users of the deleted role
	 * @return bool True on success, false on failure
	 */
	public function deleteRole($roleId, $newRoleId = 3) {
		$this->errors = [];

		// Check if current user is admin
		if (!$this->isAdmin()) {
			$this->addError('permission', 'Keine Berechtigung zum Löschen von Benutzerrollen');
			return false;
		}

		// Check if role exists
		if (!isset($this->roles[$roleId])) {
			$this->addError('role', 'Rolle nicht gefunden');
			return false;
		}

		// Prevent deletion of built-in roles
		if ($roleId <= 3) {
			$this->addError('role', 'Systemrollen können nicht gelöscht werden');
			return false;
		}

		// Check if new role exists
		if (!isset($this->roles[$newRoleId])) {
			$this->addError('new_role', 'Neue Rolle nicht gefunden');
			return false;
		}

		try {
			// Begin transaction
			$this->db->beginTransaction();

			// Store role info for logging
			$roleName = $this->roles[$roleId]['name'];

			// Update users with this role to the new role
			$stmt = $this->db->prepare("
                UPDATE users 
                SET role_id = :new_role_id 
                WHERE role_id = :role_id
            ");

			$stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
			$stmt->bindParam(':new_role_id', $newRoleId, PDO::PARAM_INT);
			$stmt->execute();

			$updatedUsers = $stmt->rowCount();

			// Delete the role
			$stmt = $this->db->prepare("DELETE FROM user_roles WHERE id = :id");
			$stmt->bindParam(':id', $roleId, PDO::PARAM_INT);
			$stmt->execute();

			// Remove from roles cache
			unset($this->roles[$roleId]);

			// Log activity
			$adminId = $_SESSION['user_id'] ?? null;
			$this->logUserActivity($adminId, 'role_deleted', [
				'role_id' => $roleId,
				'role_name' => $roleName,
				'new_role_id' => $newRoleId,
				'affected_users' => $updatedUsers,
				'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
			]);

			// Commit transaction
			$this->db->commit();

			return true;

		} catch (PDOException $e) {
			// Rollback on error
			$this->db->rollBack();
			$this->addError('database', 'Datenbankfehler beim Löschen der Rolle: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Assign a vocabulary list to a user
	 *
	 * @param int $listId List ID
	 * @param int $userId User ID
	 * @param bool $isOwner Whether the user is the owner of the list
	 * @param bool $canEdit Whether the user can edit the list
	 * @return bool True on success, false on failure
	 */
	public function assignListToUser($listId, $userId, $isOwner = false, $canEdit = false) {
		$this->errors = [];

		// Check permission - only admin or owner can share lists
		if (!$this->isAdmin() && (!$this->isListOwner($listId) || $userId != $_SESSION['user_id'])) {
			$this->addError('permission', 'Keine Berechtigung zum Zuweisen von Listen');
			return false;
		}

		try {
			// Check if user exists
			if (!$this->userExists($userId)) {
				$this->addError('user', 'Benutzer nicht gefunden');
				return false;
			}

			// Check if list exists
			$stmt = $this->db->prepare("SELECT id FROM vocabulary_lists WHERE id = :id");
			$stmt->bindParam(':id', $listId, PDO::PARAM_INT);
			$stmt->execute();

			if (!$stmt->fetch()) {
				$this->addError('list', 'Liste nicht gefunden');
				return false;
			}

			// Check if assignment already exists
			$stmt = $this->db->prepare("
                SELECT * FROM user_vocabulary_lists 
                WHERE user_id = :user_id AND list_id = :list_id
            ");

			$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
			$stmt->bindParam(':list_id', $listId, PDO::PARAM_INT);
			$stmt->execute();

			$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

			if ($assignment) {
				// Update existing assignment
				$stmt = $this->db->prepare("
                    UPDATE user_vocabulary_lists 
                    SET is_owner = :is_owner, can_edit = :can_edit 
                    WHERE user_id = :user_id AND list_id = :list_id
                ");
			} else {
				// Create new assignment
				$stmt = $this->db->prepare("
                    INSERT INTO user_vocabulary_lists (user_id, list_id, is_owner, can_edit)
                    VALUES (:user_id, :list_id, :is_owner, :can_edit)
                ");
			}

			$isOwnerInt = $isOwner ? 1 : 0;
			$canEditInt = $canEdit ? 1 : 0;

			$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
			$stmt->bindParam(':list_id', $listId, PDO::PARAM_INT);
			$stmt->bindParam(':is_owner', $isOwnerInt, PDO::PARAM_INT);
			$stmt->bindParam(':can_edit', $canEditInt, PDO::PARAM_INT);

			$stmt->execute();

			// Log activity
			$currentUserId = $_SESSION['user_id'] ?? null;
			$this->logUserActivity($currentUserId, 'list_assigned', [
				'list_id' => $listId,
				'target_user_id' => $userId,
				'is_owner' => $isOwner,
				'can_edit' => $canEdit,
				'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
			]);

			return true;

		} catch (PDOException $e) {
			$this->addError('database', 'Datenbankfehler beim Zuweisen der Liste: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Check if user is the owner of a list
	 *
	 * @param int $listId List ID
	 * @param int|null $userId User ID (defaults to current user)
	 * @return bool True if user is the owner, false otherwise
	 */
	public function isListOwner($listId, $userId = null) {
		// If no user ID provided, use current user
		if ($userId === null) {
			if (!$this->isLoggedIn()) {
				return false;
			}
			$userId = $_SESSION['user_id'];
		}

		try {
			$stmt = $this->db->prepare("
                SELECT is_owner 
                FROM user_vocabulary_lists 
                WHERE user_id = :user_id AND list_id = :list_id
            ");

			$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
			$stmt->bindParam(':list_id', $listId, PDO::PARAM_INT);
			$stmt->execute();

			$result = $stmt->fetch(PDO::FETCH_ASSOC);

			return $result && $result['is_owner'] == 1;

		} catch (PDOException $e) {
			$this->addError('database', 'Datenbankfehler: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Check if user can edit a list
	 *
	 * @param int $listId List ID
	 * @param int|null $userId User ID (defaults to current user)
	 * @return bool True if user can edit, false otherwise
	 */
	public function canEditList($listId, $userId = null) {
		// Admins can edit all lists
		if ($this->isAdmin()) {
			return true;
		}

		// If no user ID provided, use current user
		if ($userId === null) {
			if (!$this->isLoggedIn()) {
				return false;
			}
			$userId = $_SESSION['user_id'];
		}

		try {
			$stmt = $this->db->prepare("
                SELECT is_owner, can_edit 
                FROM user_vocabulary_lists 
                WHERE user_id = :user_id AND list_id = :list_id
            ");

			$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
			$stmt->bindParam(':list_id', $listId, PDO::PARAM_INT);
			$stmt->execute();

			$result = $stmt->fetch(PDO::FETCH_ASSOC);

			return $result && ($result['is_owner'] == 1 || $result['can_edit'] == 1);

		} catch (PDOException $e) {
			$this->addError('database', 'Datenbankfehler: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Get lists accessible by a user
	 *
	 * @param int|null $userId User ID (defaults to current user)
	 * @return array Array of lists
	 */
	public function getUserLists($userId = null) {
		// If no user ID provided, use current user
		if ($userId === null) {
			if (!$this->isLoggedIn()) {
				return [];
			}
			$userId = $_SESSION['user_id'];
		}

		// Check permission if requesting other user's lists
		if ($userId != $_SESSION['user_id'] && !$this->isAdmin()) {
			$this->addError('permission', 'Keine Berechtigung zum Anzeigen von Listen anderer Benutzer');
			return [];
		}

		try {
			$stmt = $this->db->prepare("
                SELECT 
                    l.*, 
                    uvl.is_owner, 
                    uvl.can_edit
                FROM vocabulary_lists l
                JOIN user_vocabulary_lists uvl ON l.id = uvl.list_id
                WHERE uvl.user_id = :user_id
                ORDER BY l.name
            ");

			$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
			$stmt->execute();

			return $stmt->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$this->addError('database', 'Datenbankfehler beim Abrufen der Listen: ' . $e->getMessage());
			return [];
		}
	}
}