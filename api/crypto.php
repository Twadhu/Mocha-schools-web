<?php
// Lightweight crypto helpers for access-code signing/verification

declare(strict_types=1);

require_once __DIR__.'/config.php';

function b64url_enc(string $bin): string { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }
function b64url_dec(string $str): string { return base64_decode(strtr($str, '-_', '+/')); }

/**
 * Load active HMAC keys for access-code, supports rotation via KID.
 * Sources:
 * - ENV: ACCESS_CODE_SECRET (required for production), ACCESS_CODE_KID (default '2025-09-k0')
 * - File (optional): api/_secrets/access_code_secret.txt
 *      Format options:
 *        kid=secret            (one per line)
 *        secret                (single line; kid from env or default)
 * Fallback (DEV ONLY): derived from DB creds. Do NOT use in production.
 */
function load_access_keys(): array {
    $keys = [];
    $kidEnv = getenv('ACCESS_CODE_KID') ?: '2025-09-k0';
    $secEnv = getenv('ACCESS_CODE_SECRET');
    if ($secEnv && is_string($secEnv) && $secEnv!=='') {
        $keys[$kidEnv] = $secEnv;
    }
    $file = __DIR__.'/_secrets/access_code_secret.txt';
    if (is_file($file) && is_readable($file)){
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach($lines as $ln){
            $ln = trim($ln);
            if ($ln==='') continue;
            if (strpos($ln,'=')!==false){
                [$k,$v] = explode('=', $ln, 2); $k=trim($k); $v=trim($v);
                if($k!=='' && $v!=='') $keys[$k]=$v;
            } else {
                // single secret
                $keys[$kidEnv] = $ln;
            }
        }
    }
    if (!$keys){
        // DEV fallback: hash DB creds; WARN: not for production
        $fallback = hash('sha256', (string)DB_PASS.'|schools-sites-dev|'.(string)DB_NAME, true);
        $keys[$kidEnv] = b64url_enc($fallback); // still opaque
    }
    return $keys;
}

function hmac_sign_b64url(string $payload, string $secret): string {
    return b64url_enc(hash_hmac('sha256', $payload, $secret, true));
}

function hmac_verify_b64url(string $payload, string $sigB64, string $secret): bool {
    $mac = hash_hmac('sha256', $payload, $secret, true);
    $sig = b64url_dec($sigB64);
    return hash_equals($mac, $sig);
}

/** Parse versioned payload string into associative array */
function parse_versioned_payload(string $payloadNoSig): array {
    $out = [];
    foreach (explode('|', $payloadNoSig) as $kv){
        $p = explode('=', $kv, 2);
        if(count($p)===2) $out[$p[0]] = $p[1];
    }
    return $out;
}
