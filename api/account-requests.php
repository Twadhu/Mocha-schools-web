<?php
/**
 * Modern API Endpoint for Handling Account Requests
 *
 * This script provides a structured, secure, and maintainable way to manage
 * student and teacher registration requests.
 *
 * Endpoints:
 * - GET /api/account-requests: Lists pending requests with filters.
 * - POST /api/account-requests: Submits a new registration request.
 * - POST /api/account-requests/{id}/decision: Approves or rejects a request.
 */

// --- Dependencies (assuming these files exist and define the necessary functions) ---
require_once __DIR__ . '/config.php';       // For db() connection
require_once __DIR__ . '/auth_helpers.php';  // For auth_require_token(), generate_temp_password()
require_once __DIR__ . '/mail.php';          // For send_app_mail()

// --- Application Configuration ---
// Define the base URL for login links sent in emails.
define('APP_LOGIN_BASE_URL', '/front-mocha-schools-website/');

/**
 * A helper function to send consistent JSON responses.
 * (This should ideally be in a shared helper file).
 */
if (!function_exists('json_response')) {
    function json_response(array $payload, int $http_code = 200): void {
        http_response_code($http_code);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *'); // For development, restrict in production
        echo json_encode($payload);
        exit;
    }
}

/**
 * Fallback secure temporary password generator.
 * Uses random_int for cryptographically secure randomness and avoids ambiguous characters.
 */
if (!function_exists('generate_temp_password')) {
    function generate_temp_password(int $length = 12): string {
        $length = max(8, min(64, $length));
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@$!%*?&';
        $alphabetLength = strlen($alphabet);
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $index = random_int(0, $alphabetLength - 1);
            $password .= $alphabet[$index];
        }
        return $password;
    }
}

/**
 * Handles all logic related to account requests.
 */
class AccountRequestHandler
{
    private PDO $pdo;
    private string $method;
    private ?int $requestId;
    private ?string $action;
    private array $input;

    public function __construct()
    {
        $this->pdo = db();
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->input = (array) json_decode(file_get_contents('php://input'), true);

        // Simple routing based on URL segments
        $this->parseRequestPath();
    }

    /**
     * Parses the URL to determine the requested resource ID and action.
     */
    private function parseRequestPath(): void
    {
        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        $segments = explode('/', $path);
        
        // Find 'api' segment to handle flexible base paths
        $apiIndex = array_search('api', $segments, true);
        if ($apiIndex === false) {
             return; // Let the main router handle it
        }

        // The resource ID is the segment after 'account-requests'
        $resourceIndex = $apiIndex + 1;
        $this->requestId = isset($segments[$resourceIndex + 1]) && ctype_digit($segments[$resourceIndex + 1])
            ? (int)$segments[$resourceIndex + 1]
            : null;
        
        // The action is the segment after the ID (e.g., 'decision')
        $this->action = $segments[$resourceIndex + 2] ?? null;

        // Support for query parameter actions (?action=decision&id=123)
        if ($this->method === 'POST') {
            if (!$this->requestId && isset($_GET['id'])) $this->requestId = (int)$_GET['id'];
            if (!$this->action && isset($_GET['action'])) $this->action = $_GET['action'];
        }
    }

    /**
     * Main entry point to handle the incoming request.
     */
    public function handleRequest(): void
    {
        // Handle CORS preflight requests
        if ($this->method === 'OPTIONS') {
            $this->handleOptionsRequest();
        }

        switch ($this->method) {
            case 'GET':
                $this->listRequests();
                break;
            case 'POST':
                if ($this->requestId && $this->action === 'decision') {
                    $this->processDecision();
                } else {
                    $this->createRequest();
                }
                break;
            default:
                json_response(['ok' => false, 'message' => 'Method not allowed'], 405);
        }
    }

    /**
     * Lists account requests with optional filters.
     * GET /api/account-requests
     */
    private function listRequests(): void
    {
        $params = [];
        $whereClauses = [];

        // If Authorization token provided and role is manager, force filter by their school
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $userSchoolId = null; $isManager = false;
        // Only attempt auth if Authorization header looks like a valid bearer token
        if (preg_match('/^Bearer\s+[A-Fa-f0-9]{64}$/', $authHeader)) {
            try {
                $user = auth_require_token($this->pdo, []); // allow any role here; we'll check below
                if (!empty($user['school_id'])) { $userSchoolId = (int)$user['school_id']; }
                if (!empty($user['role']) && $user['role'] === 'manager') { $isManager = true; }
            } catch (Throwable $e) {
                // ignore auth errors and continue unauthenticated listing (public view)
            }
        }

        if (!empty($_GET['school_id'])) {
            $whereClauses[] = 'school_id = ?';
            $params[] = (int)$_GET['school_id'];
        }
        // Enforce manager school scoping if authenticated
        if ($isManager && $userSchoolId !== null) {
            $whereClauses[] = 'school_id = ?';
            $params[] = $userSchoolId;
        }
        if (!empty($_GET['status'])) {
            $whereClauses[] = 'status = ?';
            $params[] = $_GET['status'];
        }
        if (!empty($_GET['role']) && in_array($_GET['role'], ['student', 'teacher'], true)) {
            $whereClauses[] = 'role = ?';
            $params[] = $_GET['role'];
        }

    $sql = 'SELECT * FROM account_requests';
        if (!empty($whereClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 200';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            json_response(['ok' => true, 'requests' => $stmt->fetchAll()]);
        } catch (Throwable $e) {
            // In a real app, log the error: error_log($e->getMessage());
            json_response(['ok' => false, 'message' => 'Database query failed.'], 500);
        }
    }

    /**
     * Creates a new account request.
     * POST /api/account-requests
     */
    private function createRequest(): void
    {
        $required = ['role', 'school_id', 'first_name', 'last_name', 'email'];
        foreach ($required as $field) {
            if (empty($this->input[$field])) {
                json_response(['ok' => false, 'message' => "Missing required field: {$field}"], 422);
            }
        }

        if (!in_array($this->input['role'], ['student', 'teacher'], true)) {
            json_response(['ok' => false, 'message' => 'Invalid role specified.'], 422);
        }
        if (!filter_var($this->input['email'], FILTER_VALIDATE_EMAIL)) {
            json_response(['ok' => false, 'message' => 'Invalid email format.'], 422);
        }

        try {
            $sql = 'INSERT INTO account_requests (school_id, role, first_name, last_name, email, gender, grade_level) VALUES (?, ?, ?, ?, ?, ?, ?)';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                (int)$this->input['school_id'],
                $this->input['role'],
                trim($this->input['first_name']),
                trim($this->input['last_name']),
                trim($this->input['email']),
                $this->input['gender'] ?? null,
                $this->input['grade_level'] ?? null
            ]);
            json_response(['ok' => true, 'id' => $this->pdo->lastInsertId()], 201);
        } catch (PDOException $e) {
            // Handle unique constraint violation (duplicate email/request)
            if ((int)$e->getCode() === 23000) {
                json_response(['ok' => false, 'message' => 'A request with this email has already been submitted.'], 409);
            }
            json_response(['ok' => false, 'message' => 'Database operation failed.'], 500);
        }
    }

    /**
     * Processes a decision (approve/reject) for a request.
     * POST /api/account-requests/{id}/decision
     */
    private function processDecision(): void
    {
        $user = auth_require_token($this->pdo, ['manager']); // Managers only

        $decision = $this->input['decision'] ?? '';
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            json_response(['ok' => false, 'message' => 'Invalid decision value.'], 422);
        }

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare('SELECT * FROM account_requests WHERE id = ? FOR UPDATE');
            $stmt->execute([$this->requestId]);
            $request = $stmt->fetch();

            if (!$request || $request['status'] !== 'pending') {
                $this->pdo->rollBack();
                json_response(['ok' => false, 'message' => 'Request not found or already processed.'], 404);
            }
            
            $stmt = $this->pdo->prepare('UPDATE account_requests SET status = ?, decided_at = NOW() WHERE id = ?');
            $stmt->execute([$decision, $this->requestId]);

            $tempPassword = null;
            $createdUserId = null;

            if ($decision === 'approved') {
                [$createdUserId, $tempPassword] = $this->createUserAccount($request);
            }
            
            $this->pdo->commit();

            $this->sendDecisionEmail($request, $decision, $tempPassword);

            json_response([
                'ok' => true,
                'id' => $this->requestId,
                'status' => $decision,
                'user_id' => $createdUserId,
                'temp_password' => $tempPassword // For admin to give to user directly
            ]);

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            json_response(['ok' => false, 'message' => 'An internal server error occurred.'], 500);
        }
    }

    /**
     * Creates a new user in the system if one doesn't already exist.
     * @return array [?int userId, ?string tempPassword]
     */
    private function createUserAccount(array $request): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE school_id = ? AND role = ? AND email = ? LIMIT 1');
        $stmt->execute([$request['school_id'], $request['role'], $request['email']]);
        
        if ($existingUser = $stmt->fetch()) {
            return [(int)$existingUser['id'], null]; // User already exists, no new password
        }

        $tempPassword = generate_temp_password();
        $hash = password_hash($tempPassword, PASSWORD_BCRYPT);

        $sql = 'INSERT INTO users (school_id, role, first_name, last_name, email, password_hash, gender, grade_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $request['school_id'], $request['role'], $request['first_name'],
            $request['last_name'], $request['email'], $hash,
            $request['gender'], $request['grade_level']
        ]);

        return [(int)$this->pdo->lastInsertId(), $tempPassword];
    }
    
    /**
     * Sends an email notification to the user about the decision.
     */
    private function sendDecisionEmail(array $request, string $decision, ?string $tempPassword): void
    {
        try {
            $fullName = htmlspecialchars($request['first_name'] . ' ' . $request['last_name']);

            if ($decision === 'approved') {
                $roleLabel = $request['role'] === 'student' ? 'طالب' : 'معلم';
                $loginPage = $request['role'] === 'student' ? 'student-login.html' : 'teacher-login.html';
                $loginLink = APP_LOGIN_BASE_URL . $loginPage;

                $subject = 'تمت الموافقة على حسابك - مدارس المخا';
                $body = "<p>مرحبا {$fullName}،</p>"
                      . "<p>تمت الموافقة على حسابك كـ <strong>{$roleLabel}</strong>.</p>"
                      . "<p>بيانات الدخول المؤقتة:</p>"
                      . "<ul><li>البريد: {$request['email']}</li><li>كلمة المرور المؤقتة: <strong>{$tempPassword}</strong></li></ul>"
                      . '<p>يرجى تغيير كلمة المرور بعد أول تسجيل دخول.</p>'
                      . '<p>رابط الدخول: <a href="' . htmlspecialchars($loginLink) . '">دخول المنصة</a></p>';
            } else { // Rejected
                $subject = 'بخصوص طلب حسابك - مدارس المخا';
                $body = "<p>مرحباً {$fullName}،</p>"
                      . '<p>نأسف لإعلامك بأنه تم رفض طلب إنشاء الحساب الخاص بك حالياً. يمكنك التواصل مع الإدارة لمزيد من المعلومات.</p>';
            }
            
            $body .= '<p>تحياتنا،<br>فريق مدارس المخا</p>';

            send_app_mail($request['email'], $subject, $body);
        } catch (Throwable $e) {
            // Silently ignore mail errors to not fail the API request.
            // In production, this should be logged. error_log("Mail sending failed: " . $e->getMessage());
        }
    }
    
    /**
     * Handles OPTIONS preflight request for CORS.
     */
    private function handleOptionsRequest(): void
    {
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        http_response_code(204);
        exit;
    }
}


// --- Script Execution ---
$handler = new AccountRequestHandler();
$handler->handleRequest();

