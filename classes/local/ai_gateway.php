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
 * Testable boundary around the configured AI provider.
 *
 * @package local_parce
 * @copyright 2026 David Herney @ BambuCo
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_gateway {
    /**
     * Resolve the configured BBCO provider.
     *
     * @return \core_ai\provider|null
     */
    public function resolve_provider(): ?\core_ai\provider {
        $manager = \core\di::get(\core_ai\manager::class);
        $records = $manager->get_provider_records(['provider' => 'aiprovider_bbco\\provider', 'enabled' => 1]);
        $record = reset($records);
        if (!is_object($record)) {
            return null;
        }
        $provider = new \aiprovider_bbco\provider(
            true,
            id: $record->id,
            name: $record->name,
            config: $record->config,
            actionconfig: $record->actionconfig
        );
        return $provider->is_provider_configured() ? $provider : null;
    }

    /**
     * Generate text and expose the effective provider instance when known.
     *
     * @param \core_ai\provider $provider Broker provider
     * @param object $action AI action
     * @return array Response, provider identity and start timestamp
     */
    public function generate(\core_ai\provider $provider, object $action): array {
        $processor = new \aiprovider_bbco\process_generate_text($provider, $action);
        $started = time();
        $monotonicstart = hrtime(true);
        $response = $processor->process();
        $durationms = max(1, (int) ceil((hrtime(true) - $monotonicstart) / 1_000_000));
        $identity = $processor->get_effective_provider();
        $effectiveprovider = $identity === null
            ? null
            : $identity['component'] . '#' . $identity['id'];
        return [
            'response' => $response,
            'provider' => $effectiveprovider,
            'timecreated' => $started,
            'timecompleted' => time(),
            'durationms' => $durationms,
            'attempts' => $processor->get_attempts(),
        ];
    }
}
