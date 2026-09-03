<?php
// This file is part of Moodle - http://moodle.org/

namespace local_parce\local;

/**
 * Signed, operation-bound cursors for history pagination.
 *
 * @package local_parce
 * @copyright 2026 David Herney @ BambuCo
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class history_cursor {
    /** Encode cursor state. */
    public static function encode(string $operation, array $scope, array $state): string {
        global $USER;
        $payload = json_encode([
            'v' => 1,
            'op' => $operation,
            'actor' => (int) $USER->id,
            'scope' => $scope,
            'state' => $state,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $payload, self::secret(), true);
        return self::base64url($payload) . '.' . self::base64url($signature);
    }

    /** Decode and validate cursor state. */
    public static function decode(string $cursor, string $operation, array $scope): array {
        global $USER;
        try {
            $parts = explode('.', $cursor);
            if (count($parts) !== 2) {
                throw new \Exception();
            }
            $payload = self::unbase64url($parts[0]);
            $signature = self::unbase64url($parts[1]);
            if (!hash_equals(hash_hmac('sha256', $payload, self::secret(), true), $signature)) {
                throw new \Exception();
            }
            $value = json_decode($payload, true, 8, JSON_THROW_ON_ERROR);
            if (($value['v'] ?? null) !== 1 || ($value['op'] ?? null) !== $operation ||
                    ($value['actor'] ?? null) !== (int) $USER->id || ($value['scope'] ?? null) !== $scope ||
                    !is_array($value['state'] ?? null)) {
                throw new \Exception();
            }
            return $value['state'];
        } catch (\Throwable $e) {
            throw new \invalid_parameter_exception('Invalid cursor.');
        }
    }

    /** Return a site-specific signing secret. */
    private static function secret(): string {
        global $CFG;
        return hash('sha256', ($CFG->passwordsaltmain ?? '') . '|' . ($CFG->siteidentifier ?? $CFG->wwwroot)
            . '|local_parce_history', true);
    }

    /** Base64-url encode. */
    private static function base64url(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** Base64-url decode. */
    private static function unbase64url(string $value): string {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \Exception();
        }
        return $decoded;
    }
}
