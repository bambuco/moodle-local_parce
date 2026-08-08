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

/**
 * Simulated gateway fixture for question handler tests.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_parce\tests\local;

/**
 * AI boundary returning queued responses without a network call.
 */
class test_ai_gateway extends \local_parce\local\ai_gateway {
    /** @var array Queued responses or exceptions. */
    private array $responses;

    /** @var \core_ai\provider|null Simulated provider. */
    private ?\core_ai\provider $provider;

    /** @var int Number of generation calls. */
    private int $generatecalls = 0;

    /** @var int Optional controlled delay in microseconds. */
    public int $delay = 0;

    /**
     * Create the simulated gateway.
     *
     * @param array $responses Queued responses or exceptions
     * @param \core_ai\provider|null $provider Simulated provider
     */
    public function __construct(array $responses, ?\core_ai\provider $provider) {
        $this->responses = $responses;
        $this->provider = $provider;
    }

    /**
     * Return the simulated provider.
     *
     * @return \core_ai\provider
     */
    public function resolve_provider(): ?\core_ai\provider {
        return $this->provider;
    }

    /**
     * Return the number of provider calls made.
     *
     * @return int
     */
    public function get_generate_calls(): int {
        return $this->generatecalls;
    }

    /**
     * Return the next queued response.
     *
     * @param \core_ai\provider $provider Simulated provider
     * @param object $action AI action
     * @return array
     */
    public function generate(\core_ai\provider $provider, object $action): array {
        $this->generatecalls++;
        $started = hrtime(true);
        if ($this->delay > 0) {
            usleep($this->delay);
        }
        $response = array_shift($this->responses);
        if ($response instanceof \Throwable) {
            throw $response;
        }
        if (is_array($response)) {
            $response += [
                'provider' => 'test_provider#1',
                'timecreated' => time(),
                'durationms' => max(1, (int) ceil((hrtime(true) - $started) / 1_000_000)),
            ];
            return $response;
        }
        return [
            'response' => $response,
            'provider' => 'test_provider#1',
            'timecreated' => time(),
            'durationms' => max(1, (int) ceil((hrtime(true) - $started) / 1_000_000)),
            'attempts' => [[
                'attemptordinal' => 1,
                'providercomponent' => 'test_provider',
                'providerinstanceid' => 1,
                'providername' => 'fake',
                'success' => $response->get_success(),
                'errorcode' => $response->get_success() ? null : $response->get_errorcode(),
            ]],
        ];
    }
}
