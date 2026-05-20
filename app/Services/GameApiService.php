<?php

namespace App\Services;

use App\Exceptions\GameApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GameApiService
{
    protected string $baseUrl;

    protected string $apiKey;

    protected string $encKey;

    protected string $encMethod;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.game_api.url', ''), '/');
        $this->apiKey = config('services.game_api.key', '');
        $this->encKey = config('services.game_api.openssl_key', '');
        $this->encMethod = config('services.game_api.openssl_method', 'AES-128-CBC');
    }

    // -------------------------------------------------------------------------
    // Core infrastructure
    // -------------------------------------------------------------------------

    /**
     * Encrypts a plain identifier using AES-256-CBC + base64url, exactly as the
     * wallet API expects. This is done locally to avoid a round-trip to /encrypt.
     */
    public function encryptId(int|string $id): string
    {
        $ivLength = openssl_cipher_iv_length($this->encMethod);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt((string) $id, $this->encMethod, $this->encKey, 0, $iv);
        $combined = $iv.$encrypted;

        return rtrim(strtr(base64_encode($combined), '+/', '-_'), '=');
    }

    /**
     * Authenticated request to the wallet API.
     *
     * @throws GameApiException on 4xx/5xx responses
     */
    protected function makeRequest(
        string $method,
        string $path,
        array $body = [],
        array $query = []
    ): array {
        $url = $this->baseUrl.$path;

        $pending = Http::withHeaders([
            'X-API-KEY' => $this->apiKey,
            'Accept' => 'application/json',
        ])->timeout(8)->connectTimeout(5);

        try {
            $response = match (strtoupper($method)) {
                'GET' => $pending->get($url, $query ?: null),
                'POST' => $pending->post($url, $body),
                'PUT' => $pending->put($url, $body),
                'PATCH' => $pending->patch($url, $body),
                'DELETE' => $pending->delete($url, $body),
                default => throw new GameApiException("Unsupported HTTP method: {$method}"),
            };
        } catch (ConnectionException $e) {
            Log::error('GameAPI unreachable', ['path' => $path, 'error' => $e->getMessage()]);
            throw new GameApiException('Game API is unreachable. Please try again later.', 0, $e->getMessage());
        }

        // BUG B5: STK push (and possibly others) can return null/empty body on exception.
        $decoded = $response->json() ?? [];

        if ($response->failed()) {
            $apiMessage = $decoded['message'] ?? $decoded['error'] ?? $decoded['status'] ?? '';
            $statusCode = $response->status();

            Log::error('GameAPI request failed', [
                'method' => $method,
                'path' => $path,
                'status' => $statusCode,
                'api_message' => $apiMessage,
            ]);

            throw new GameApiException(
                "Game API error {$statusCode}: {$apiMessage}",
                $statusCode,
                $apiMessage,
            );
        }

        return $decoded;
    }

    // -------------------------------------------------------------------------
    // HIGH PRIORITY — TASK-001 to TASK-016
    // -------------------------------------------------------------------------

    // TASK-001: auth is handled in makeRequest via X-API-KEY header.

    /**
     * TASK-002: Encrypt ID via the API's own endpoint (fallback / one-off use only).
     * Prefer the local encryptId() method — it's faster and avoids a network round-trip.
     */
    public function encryptIdViaApi(string $plainId): string
    {
        return $this->makeRequest('POST', '/encrypt', ['identifier' => $plainId])['encrypted_id'] ?? '';
    }

    /**
     * TASK-003: Fetch a single customer by their API customer ID.
     * Endpoint: GET /api/v1/customers/{enc}
     */
    public function getCustomer(int|string $customerId): array
    {
        $enc = $this->encryptId((string) $customerId);

        return $this->makeRequest('GET', "/customers/{$enc}")['data'] ?? [];
    }

    /**
     * TASK-003: Fetch a customer by their plain account_no (no encryption needed —
     * the API's DecryptIdentifier middleware accepts plain account_no).
     * Endpoint: GET /api/v1/customers/{enc}
     */
    public function getCustomerByAccount(string $accountNo): array
    {
        return $this->makeRequest('GET', "/customers/{$accountNo}")['data'] ?? [];
    }

    /**
     * TASK-004: List all active customers.
     * Endpoint: GET /api/v1/customers
     *
     * BUG B1: Hard-coded filter `created_at >= 2026-03-01` on the API side.
     * Customers registered before that date will not appear here.
     * Use searchCustomers() for full-history lookups.
     */
    public function listCustomers(): array
    {
        return $this->makeRequest('GET', '/customers')['data'] ?? [];
    }

    /**
     * TASK-004: Search customers by name, account_no, phone, or email (LIKE search).
     * Endpoint: GET /api/v1/customers/search?q={query}
     */
    public function searchCustomers(string $query): array
    {
        return $this->makeRequest('GET', '/customers/search', [], ['q' => $query]);
    }

    /**
     * TASK-005: Create a new customer (auto-creates wallet with 250 KES balance).
     * Endpoint: POST /api/v1/customers
     *
     * @param  array{account_no: string, name: string, email: string, id_no?: string, phone_no?: string, referral_code?: string}  $data
     */
    public function createCustomer(array $data): array
    {
        return $this->makeRequest('POST', '/customers', $data);
    }

    /**
     * TASK-006: Fetch a wallet by its wallet ID.
     * Endpoint: GET /api/v1/wallets/{enc}
     */
    public function getWallet(int $walletId): array
    {
        $enc = $this->encryptId((string) $walletId);

        return $this->makeRequest('GET', "/wallets/{$enc}");
    }

    /**
     * TASK-006: List all wallets with customer info.
     * Endpoint: GET /api/v1/wallets
     */
    public function listWallets(): array
    {
        return $this->makeRequest('GET', '/wallets');
    }

    /**
     * TASK-007: Create a new game wallet for a game round.
     * Endpoint: POST /api/v1/game/wallets
     */
    public function createGameWallet(string $gameId, int $gameType = 1): array
    {
        return $this->makeRequest('POST', '/game/wallets', [
            'game_id' => $gameId,
            'game_type' => $gameType,
        ]);
    }

    /**
     * TASK-008: Place a bet — deducts from customer wallet and adds to game pot.
     * Endpoint: POST /api/v1/game/bets
     *
     * @throws GameApiException with "Insufficient balance" message on HTTP 400
     */
    public function placeBet(int $gameWalletId, int $customerId, float $amount): array
    {
        try {
            return $this->makeRequest('POST', '/game/bets', [
                'game_wallet_id' => $gameWalletId,
                'customer_id' => $customerId,
                'payment_type' => 'deposit',
                'amount' => $amount,
            ]);
        } catch (GameApiException $e) {
            if ($e->statusCode === 400) {
                throw new GameApiException('Insufficient balance', 400, 'Insufficient balance');
            }
            throw $e;
        }
    }

    /**
     * TASK-009: Pay out the game pot — 90% to winner, 10% to house. Closes the game wallet.
     * Endpoint: POST /api/v1/game/withdraw/{enc}
     *
     * Note: customer_id must be cast to string in the request body (API requirement).
     */
    public function payoutGame(int $gameWalletId, int $winnerId): array
    {
        $enc = $this->encryptId((string) $gameWalletId);

        return $this->makeRequest('POST', "/game/withdraw/{$enc}", [
            'customer_id' => (string) $winnerId,
        ]);
    }

    /**
     * TASK-010: Handle a player disconnect / drop event.
     * Endpoint: POST /api/v1/game/drop/{enc}
     *
     * Note: `game` param is int 1/0 (not bool) — converted here from the bool argument.
     */
    public function handlePlayerDrop(
        int $gameWalletId,
        array $players,
        array $active,
        array $dropped,
        bool $gameStarted = true
    ): array {
        $enc = $this->encryptId((string) $gameWalletId);

        return $this->makeRequest('POST', "/game/drop/{$enc}", [
            'players' => $players,
            'active' => $active,
            'dropped' => $dropped,
            'game' => $gameStarted ? 1 : 0,
        ]);
    }

    /**
     * TASK-011: Create a competition wallet entry (enroll a player in a tournament/jackpot).
     * Endpoint: POST /api/v1/competition/wallets
     *
     * Note: jp_rounds must be 13, 17, or 21 for jackpot payout tiers to work correctly.
     *
     * @param  array{competition_id: string, cmp_uid: string, game_type: int, customer_id: int, jp_rounds: int}  $data
     */
    public function createCompetitionWallet(array $data): array
    {
        return $this->makeRequest('POST', '/competition/wallets', $data);
    }

    /**
     * TASK-012: Record a player's competition entry (deducts from wallet, loads competition wallet).
     * Endpoint: POST /api/v1/competition/transactions
     * Tournament: 85% to comp wallet, 15% to house. Jackpot: 80% to comp wallet, 20% to house.
     */
    public function recordCompetitionEntry(int $competitionWalletId, int $customerId, float $amount): array
    {
        return $this->makeRequest('POST', '/competition/transactions', [
            'competition_wallet_id' => $competitionWalletId,
            'customer_id' => $customerId,
            'payment_type' => 'deposit',
            'amount' => $amount,
        ]);
    }

    /**
     * TASK-013: Process a competition match result (loser → winner balance transfer).
     * Endpoint: POST /api/v1/competition/payout
     */
    public function processCompetitionMatchResult(int $loserWalletId, int $winnerWalletId): array
    {
        return $this->makeRequest('POST', '/competition/payout', [
            'sender_competition_wallet_id' => $loserWalletId,
            'receiver_competition_wallet_id' => $winnerWalletId,
        ]);
    }

    /**
     * TASK-014: Pay out the competition winner's balance to their main wallet.
     * Endpoint: POST /api/v1/competition/withdraw/{enc}
     */
    public function withdrawCompetitionWinnings(int $competitionWalletId, int $customerId): array
    {
        $enc = $this->encryptId((string) $competitionWalletId);

        return $this->makeRequest('POST', "/competition/withdraw/{$enc}", [
            'customer_id' => (string) $customerId,
        ]);
    }

    /**
     * TASK-015: Trigger an M-Pesa STK Push to deposit funds.
     * Endpoint: POST /api/v1/deposits/{enc}  (rate limited: 10/min)
     *
     * BUG B5: This endpoint can return a null body on exception — handled in makeRequest()
     * by defaulting to []. The actual wallet credit happens asynchronously via C2B callback.
     */
    public function triggerStkPush(int $customerId, float $amount): array
    {
        $enc = $this->encryptId((string) $customerId);

        return $this->makeRequest('POST', "/deposits/{$enc}", [
            'amount' => (int) $amount,
        ]);
    }

    /**
     * TASK-016: Trigger an M-Pesa STK Push for an in-app purchase (load/gift/emoji).
     * Endpoint: POST /api/v1/load/{enc}  (rate limited: 10/min)
     */
    public function triggerStkLoad(
        int $customerId,
        float $amount,
        string $type,
        float $coinValue = 0,
        string $phoneNo = '',
        string $referralCode = ''
    ): array {
        $enc = $this->encryptId((string) $customerId);
        $body = ['amount' => $amount, 'type' => $type];

        if ($coinValue > 0) {
            $body['coin_value'] = $coinValue;
        }
        if ($phoneNo !== '') {
            $body['phone_no'] = $phoneNo;
        }
        if ($referralCode !== '') {
            $body['referral_code'] = $referralCode;
        }

        return $this->makeRequest('POST', "/load/{$enc}", $body);
    }

    // -------------------------------------------------------------------------
    // MEDIUM PRIORITY — TASK-017 to TASK-029
    // -------------------------------------------------------------------------

    /**
     * TASK-017: Fetch all four dashboard stat endpoints in parallel and merge into one array.
     * Endpoints: GET /stats/customers, /stats/income, /stats/played, /stats/purchases
     */
    public function getDashboardStats(): array
    {
        $headers = ['X-API-KEY' => $this->apiKey, 'Accept' => 'application/json'];
        $base = $this->baseUrl;

        $responses = Http::pool(fn (Pool $pool) => [
            $pool->as('customer')->withHeaders($headers)->timeout(8)->connectTimeout(5)->get("{$base}/stats/customers"),
            $pool->as('income')->withHeaders($headers)->timeout(8)->connectTimeout(5)->get("{$base}/stats/income"),
            $pool->as('played')->withHeaders($headers)->timeout(8)->connectTimeout(5)->get("{$base}/stats/played"),
            $pool->as('purchases')->withHeaders($headers)->timeout(8)->connectTimeout(5)->get("{$base}/stats/purchases"),
        ]);

        $safe = fn (string $key) => ! ($responses[$key] instanceof \Throwable) && ! $responses[$key]->failed()
            ? ($responses[$key]->json('data') ?? [])
            : [];

        return [
            'customer' => $safe('customer'),
            'income' => $safe('income'),
            'played' => $safe('played'),
            'purchases' => $safe('purchases'),
        ];
    }

    /**
     * TASK-018: Fetch player retention stats.
     * Endpoint: GET /api/v1/stats/retention
     */
    public function getRetentionStats(): array
    {
        return $this->makeRequest('GET', '/stats/retention')['data'] ?? [];
    }

    /**
     * TASK-019: Fetch leaderboards for a date range.
     * Endpoint: POST /api/v1/customers/leaderboard
     * Returns: { single_leaderboard: [...], competitions_leaderboard: [...] }
     */
    public function getLeaderboard(string $startDate, string $endDate): array
    {
        return $this->makeRequest('POST', '/customers/leaderboard', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * TASK-019: Fetch the combined weekly leaderboard (single + competition winnings).
     * Endpoint: GET /api/v1/customers/combined-leaderboard
     *
     * BUG B3: This endpoint always uses the current week regardless of any date params passed.
     * Date filtering is not supported here — use getLeaderboard() for date-ranged results.
     */
    public function getCombinedLeaderboard(): array
    {
        return $this->makeRequest('GET', '/customers/combined-leaderboard');
    }

    /**
     * TASK-020: Fetch all completed game results.
     * Endpoint: GET /api/v1/game/results
     * Returns: { status: "Success", data: { "5": { id, game_id, players, total_bet, ... } } }
     */
    public function getGameResults(): array
    {
        return $this->makeRequest('GET', '/game/results')['data'] ?? [];
    }

    /**
     * TASK-021: Fetch competition results for a game type.
     * Endpoint: GET /api/v1/competition/results/{enc}  (encrypt the gameType integer)
     *
     * @param  int  $gameType  1 = Tournament, 2 = Jackpot
     */
    public function getCompetitionResults(int $gameType): array
    {
        $enc = $this->encryptId((string) $gameType);

        return $this->makeRequest('GET', "/competition/results/{$enc}")['data'] ?? [];
    }

    /**
     * TASK-022: Fetch competition award winners for a game type.
     * Endpoint: GET /api/v1/competition/awards/{enc}  (encrypt the gameType integer)
     *
     * @param  int  $gameType  1 = Tournament, 2 = Jackpot
     */
    public function getCompetitionAwards(int $gameType): array
    {
        $enc = $this->encryptId((string) $gameType);

        return $this->makeRequest('GET', "/competition/awards/{$enc}")['data'] ?? [];
    }

    /**
     * TASK-023: Purchase coins for a customer (KES 10 = 1 coin, floor division).
     * Endpoint: POST /api/v1/coins/buy/{enc}
     */
    public function buyCoins(int $customerId, float $amount): array
    {
        $enc = $this->encryptId((string) $customerId);

        return $this->makeRequest('POST', "/coins/buy/{$enc}", ['amount' => $amount]);
    }

    /**
     * TASK-024: Exchange coins back to KES (credits customer's main wallet).
     * Endpoint: PUT /api/v1/coins/exchange/{enc}
     *
     * @param  int|null  $coins  Coins to exchange; omit to exchange all.
     */
    public function exchangeCoins(int $coinWalletId, ?int $coins = null): array
    {
        $enc = $this->encryptId((string) $coinWalletId);
        $body = $coins !== null ? ['coins' => $coins] : [];

        return $this->makeRequest('PUT', "/coins/exchange/{$enc}", $body);
    }

    /**
     * TASK-025: Fetch the last 10 wallet transactions for a customer.
     * Endpoint: POST /api/v1/customers/transactions/{enc}
     *
     * Maps payment_type from PHP class name to human-readable label:
     *   "App\Models\Deposit"  → "deposit"
     *   "App\Models\Withdraw" → "withdrawal"
     *
     * @param  string  $type  "deposit" | "withdraw" | "all"
     */
    public function getCustomerTransactions(int $customerId, string $type = 'all'): array
    {
        $enc = $this->encryptId((string) $customerId);
        $response = $this->makeRequest('POST', "/customers/transactions/{$enc}", [
            'payment_type' => $type,
        ]);

        $typeMap = [
            'App\\Models\\Deposit' => 'deposit',
            'App\\Models\\Withdraw' => 'withdrawal',
        ];

        $response['transactions'] = array_map(function (array $tx) use ($typeMap): array {
            $tx['payment_type'] = $typeMap[$tx['payment_type']] ?? $tx['payment_type'];

            return $tx;
        }, $response['transactions'] ?? []);

        return $response;
    }

    /**
     * TASK-026: Fetch all game sessions (single, tournament, jackpot) for a customer.
     * Endpoint: GET /api/v1/customers/played/{enc}
     */
    public function getCustomerGamesPlayed(int $customerId): array
    {
        $enc = $this->encryptId((string) $customerId);

        return $this->makeRequest('GET', "/customers/played/{$enc}");
    }

    /**
     * TASK-027: Fetch referral stats (customers and purchases for given referral codes).
     * Accepts comma-separated codes or an array.
     *
     * @param  string|array<int, string>  $referralCode
     */
    public function getReferralStats(string|array $referralCode): array
    {
        $codes = is_array($referralCode) ? implode(',', $referralCode) : $referralCode;

        return [
            'customers' => $this->makeRequest('POST', '/stats/customers/referrals', ['referral_code' => $codes]),
            'purchases' => $this->makeRequest('POST', '/stats/purchases/referrals', ['referral_code' => $codes]),
            'customer_list' => $this->makeRequest('POST', '/customers/referrals', ['referral_code' => $codes]),
            'purchase_list' => $this->makeRequest('POST', '/purchases/referrals', ['referral_code' => $codes]),
        ];
    }

    /**
     * TASK-027 (partial): Fetch customers by referral code(s).
     * Endpoint: POST /api/v1/customers/referrals
     *
     * @param  string|array<int, string>  $codes
     */
    public function getCustomersByReferral(string|array $codes): array
    {
        $referralCode = is_array($codes) ? implode(',', $codes) : $codes;

        return $this->makeRequest('POST', '/customers/referrals', ['referral_code' => $referralCode]);
    }

    /**
     * TASK-027 (partial): Fetch purchases by referral code(s).
     * Endpoint: POST /api/v1/purchases/referrals
     *
     * @param  string|array<int, string>  $codes
     */
    public function getPurchasesByReferral(string|array $codes): array
    {
        $referralCode = is_array($codes) ? implode(',', $codes) : $codes;

        return $this->makeRequest('POST', '/purchases/referrals', ['referral_code' => $referralCode]);
    }

    /**
     * TASK-028: Fetch game session counts for a specific player and date range.
     * Endpoint: POST /api/v1/stats/customers/played
     *
     * BUG B2: The API accepts start_date/end_date but ignores them — always returns today's counts.
     * This is a known API bug; track ticket for the API team to fix.
     */
    public function getPlayerGameStats(int $customerId, string $startDate, string $endDate): array
    {
        return $this->makeRequest('POST', '/stats/customers/played', [
            'customer_id' => $customerId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ])['data'] ?? [];
    }

    /**
     * TASK-029: Send an OTP code to a phone number.
     * Endpoint: POST /api/v1/customer/send-code
     *
     * BUG B4: SMS sending is stubbed on the API side ($smsSent = true).
     * No real SMS is ever delivered. Track ticket for real SMS provider integration.
     */
    public function sendOtpCode(string $phoneNo): array
    {
        return $this->makeRequest('POST', '/customer/send-code', ['phone_no' => $phoneNo]);
    }

    /**
     * TASK-029: Verify a customer's phone using the 6-digit OTP.
     * Endpoint: PATCH /api/v1/customers/{enc}/verify-phone
     */
    public function verifyPhone(int $customerId, string $code): array
    {
        $enc = $this->encryptId((string) $customerId);

        return $this->makeRequest('PATCH', "/customers/{$enc}/verify-phone", ['code' => $code]);
    }

    // -------------------------------------------------------------------------
    // LOW PRIORITY — TASK-030 to TASK-037
    // -------------------------------------------------------------------------

    /**
     * TASK-030: Mark a customer's email as verified.
     * Endpoint: GET /api/v1/customers/{enc}/verify-email
     */
    public function verifyEmail(int $customerId): array
    {
        $enc = $this->encryptId((string) $customerId);

        return $this->makeRequest('GET', "/customers/{$enc}/verify-email");
    }

    /**
     * TASK-031: List all deposits.
     * Endpoint: GET /api/v1/deposits
     */
    public function listDeposits(): array
    {
        return $this->makeRequest('GET', '/deposits');
    }

    /**
     * List all player withdrawals.
     * Endpoint: GET /api/v1/withdrawals
     *
     * BUG: No global withdrawal listing endpoint confirmed in the API spec.
     * If this returns 404, withdrawals must be fetched per-customer via getCustomerTransactions().
     */
    public function listWithdrawals(): array
    {
        return $this->makeRequest('GET', '/withdrawals');
    }

    /**
     * List all in-app purchases.
     * Endpoint: GET /api/v1/purchases
     *
     * BUG: No global purchases listing endpoint confirmed in the API spec.
     * If this returns 404, purchases must be fetched per-customer via getCustomerPurchases().
     */
    public function listPurchases(): array
    {
        return $this->makeRequest('GET', '/purchases');
    }

    /**
     * TASK-031: Get a single deposit by ID.
     * Endpoint: GET /api/v1/deposits/{enc}
     */
    public function getDeposit(int $depositId): array
    {
        $enc = $this->encryptId((string) $depositId);

        return $this->makeRequest('GET', "/deposits/{$enc}");
    }

    /**
     * TASK-032: Get single-game income analytics grouped by number of players.
     * Endpoint: POST /api/v1/game/income
     */
    public function getGameIncomeBreakdown(string $startDate, string $endDate): array
    {
        return $this->makeRequest('POST', '/game/income', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ])['data'] ?? [];
    }

    /**
     * TASK-033: Get competition income analytics grouped by jp_rounds bracket.
     * Endpoint: POST /api/v1/competition/income/{enc}  (encrypt the gameType integer)
     *
     * @param  int  $gameType  1 = Tournament, 2 = Jackpot
     */
    public function getCompetitionIncomeBreakdown(int $gameType, string $startDate, string $endDate): array
    {
        $enc = $this->encryptId((string) $gameType);

        return $this->makeRequest('POST', "/competition/income/{$enc}", [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ])['data'] ?? [];
    }

    /**
     * TASK-034: Get total amount deposited into the house wallet today.
     * Endpoint: GET /api/v1/wallets/today
     */
    public function getWalletToday(): float
    {
        return (float) ($this->makeRequest('GET', '/wallets/today')['amount'] ?? 0);
    }

    /**
     * TASK-035: Transfer funds from one wallet to another.
     * Endpoint: POST /api/v1/wallets/transfer/{enc}
     *
     * @param  int  $toWalletId  Defaults to 1 (house wallet).
     */
    public function transferWallet(int $fromWalletId, float $amount, int $toWalletId = 1): array
    {
        $enc = $this->encryptId((string) $fromWalletId);

        return $this->makeRequest('POST', "/wallets/transfer/{$enc}", [
            'amount' => $amount,
            'wallet_id' => $toWalletId,
        ]);
    }

    /**
     * TASK-036: Register M-Pesa C2B callback URLs with Safaricom.
     * Endpoint: GET /api/v1/c2b/register
     *
     * WARNING: Only call this once in production. Calling it multiple times
     * re-registers the callbacks and may disrupt in-flight M-Pesa transactions.
     */
    public function registerC2BCallbacks(): array
    {
        return $this->makeRequest('GET', '/c2b/register');
    }

    /**
     * TASK-037: Get the latest B2C balance record (withdrawal float monitoring).
     * Endpoint: GET /api/v1/b2c/balance
     */
    public function getB2CBalance(): array
    {
        return $this->makeRequest('GET', '/b2c/balance')['data'] ?? [];
    }

    /**
     * Get the B2C float as a plain numeric value (convenience wrapper for widgets).
     */
    public function getB2CBalanceAmount(): float
    {
        $data = $this->getB2CBalance();

        return (float) ($data['balance'] ?? $data['amount'] ?? 0);
    }

    // -------------------------------------------------------------------------
    // Wallet management helpers
    // -------------------------------------------------------------------------

    /**
     * Update a customer's wallet by directly setting an amount (adds to balance).
     * Endpoint: PUT /api/v1/customers/{enc}/wallet
     */
    public function updateCustomerWallet(int $customerId, float $amount): array
    {
        $enc = $this->encryptId((string) $customerId);

        return $this->makeRequest('PUT', "/customers/{$enc}/wallet", ['amount' => $amount]);
    }

    /**
     * Update a customer's fields.
     * Endpoint: PUT /api/v1/customers/{enc}
     */
    public function updateCustomer(int $customerId, array $data): array
    {
        $enc = $this->encryptId((string) $customerId);

        return $this->makeRequest('PUT', "/customers/{$enc}", $data);
    }

    /**
     * Get a game wallet by ID.
     * Endpoint: GET /api/v1/game/wallets/{enc}
     */
    public function getGameWallet(int $gameWalletId): array
    {
        $enc = $this->encryptId((string) $gameWalletId);

        return $this->makeRequest('GET', "/game/wallets/{$enc}");
    }

    /**
     * Update a game wallet (e.g. status change).
     * Endpoint: PUT /api/v1/game/wallets/{enc}
     */
    public function updateGameWallet(int $gameWalletId, array $data): array
    {
        $enc = $this->encryptId((string) $gameWalletId);

        return $this->makeRequest('PUT', "/game/wallets/{$enc}", $data);
    }

    /**
     * Get customer purchases.
     * Endpoint: GET /api/v1/customers/purchases/{enc}
     */
    public function getCustomerPurchases(int $customerId): array
    {
        $enc = $this->encryptId((string) $customerId);

        return $this->makeRequest('GET', "/customers/purchases/{$enc}")['data'] ?? [];
    }
}
