<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_parce\local;

/**
 * Signed, operation-bound cursors for history pagination.
 *
 * @package local_parce
 * @copyright 2026 David Herney @ BambuCo
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class history_cursor {
    /**
     * Encode cursor state.
     *
     * @param string $operation The operation name.
     * @param array $scope The scope of the cursor.
     * @param array $state The state to be encoded.
     * @return string The encoded cursor string.
     */
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

    /**
     * Decode and validate cursor state.
     *
     * @param string $cursor The encoded cursor string.
     * @param string $operation The expected operation.
     * @param array $scope The expected scope.
     * @return array The decoded state.
     * @throws \invalid_parameter_exception If the cursor is invalid.
     */
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
            if (
                ($value['v'] ?? null) !== 1 ||
                ($value['op'] ?? null) !== $operation ||
                ($value['actor'] ?? null) !== (int) $USER->id ||
                ($value['scope'] ?? null) !== $scope ||
                !is_array($value['state'] ?? null)
            ) {
                throw new \Exception();
            }
            return $value['state'];
        } catch (\Throwable $e) {
            throw new \invalid_parameter_exception('Invalid cursor.');
        }
    }

    /**
     * Return a site-specific signing secret.
     *
     * @return string The site-specific signing secret.
     */
    private static function secret(): string {
        global $CFG;
        return hash('sha256', ($CFG->passwordsaltmain ?? '') . '|' . ($CFG->siteidentifier ?? $CFG->wwwroot)
            . '|local_parce_history', true);
    }

    /**
     * Base64-url encode.
     *
     * @param string $value The value to be encoded.
     * @return string The base64-url encoded value.
     */
    private static function base64url(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * Base64-url decode.
     *
     * @param string $value The base64-url encoded value.
     * @return string The decoded value.
     * @throws \Exception If the value cannot be decoded.
     */
    private static function unbase64url(string $value): string {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \Exception();
        }
        return $decoded;
    }
}
