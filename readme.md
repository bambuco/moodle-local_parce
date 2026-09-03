# Local Parce

`local_parce` is a Moodle 5.1 local plugin that provides a context-aware AI question-and-answer widget. It uses `aiprovider_bbco` as the required broker for configured Moodle AI providers.

## Requirements

- Moodle 5.1 (`2025100600`) only.
- PHP 8.2 or newer, following the Moodle 5.1 platform requirement.
- `aiprovider_bbco` version `2026080800` or newer.

## Data architecture

Parce deliberately keeps active and durable data separate:

- The active conversation and every AI prompt use only the Moodle `MODE_SESSION` cache.
- The database is used only for persistent history, guest audit records and AI action traces.
- AI traces correlate each turn and logical call, recording every ordered provider fallback with stable outcomes and monotonic duration.
- Persistent history is never read back into the active conversation or an AI prompt.
- A chat belongs to a canonical Moodle course context, or to the system context outside courses.
- The persistent `conversationkey` is a cryptographically random thread identifier. PHP session identifiers are used only to isolate internal cache keys.

The active thread rotates before a question would reach either its configured limit or the hard limits of 40 complete turns and 16,000 estimated tokens. Prompts include at most eight complete recent turns and 8,000 estimated tokens. Retrieved Search, Calendar, Grades or Progress data has an independent 8,000-token budget; the complete provider payload is limited to 18,000 estimated tokens.

Retrieved content, events, grade and completion data are untrusted data. Parce sends its request-specific instruction separately so BBCO can replace the effective provider's generic `generate_text` system instruction. Questions, prior turns and retrieved data remain isolated with explicit text delimiters in the user prompt.

## History, guests and privacy

- Authenticated users can read their own durable history after losing course enrolment.
- Reading another user's history requires `local/parce:viewallchats` in the requested chat context and emits a Moodle event.
- Guests can use the widget only when explicitly enabled. Their successful turns and AI traces are persisted under an auditable `conversationkey`, but guests cannot query history.
- The Moodle Privacy API describes, exports and deletes conversation and AI-action data. Privacy deletion also invalidates active cache snapshots in other sessions.

No automatic retention or purge policy is implemented. Conversation history and AI traces are provisionally retained until Privacy API deletion. They are intentionally excluded from course backup/restore pending an institutional lifecycle decision.

## Installation and deployment order

This development pair is installed in this order:

1. Install and configure `public/ai/provider/bbco`.
2. Install `public/local/parce`.
3. Configure at least one real Moodle AI provider behind BBCO.
4. Configure and enable Parce in Site administration.
5. Purge Moodle caches and run the component test suites.

For the current development workspace, replacement of unsupported older builds is performed by uninstalling and reinstalling from the fresh schema. Do not deploy Parce before its matching BBCO version.

## Configuration

- Enable or disable the widget globally.
- Set its title.
- Explicitly allow or deny guests.
- Configure instructions for intent planning and answer generation.
- Allow or deny open answers when retrieved content is unavailable.
- Set active limits from 1 to 40 complete turns and from 1 to 16,000 estimated tokens. Invalid values are rejected.
- Bound the history browser to 1–100 contexts, conversations per context and search results.

There is no cache TTL setting. The active conversation lifecycle is defined by the PHP session and the turn/token rollover limits.

## Intents and scope

Except for the widget's exact static help command, Parce first asks the configured AI provider to classify each question. The planner returns one of the supported intents and the parameters used to retrieve or produce the response. Intents that return a direct response stop after planning; intents that need an answer based on retrieved data make a second AI call.

- `greeting`: returns the cordial greeting supplied by the planner, or the plugin's default greeting when none is supplied. It does not search Moodle or make a second AI call.
- `help`: explains what can be asked. Its response is selected for the current system, course or activity-module context. The static help command reaches this intent without an AI planning call; an AI-classified help request stops after planning.
- `resource`: handles explicit requests to find, show or access courses, activities and other Moodle resources. In course contexts the planner receives a compact catalogue of module short names used in that course, with a boolean indicating each component's `FEATURE_GRADE_HAS_GRADE` support. Its required `resourcetype` parameter accepts `core_course`, `*` for every catalogued module type, or selected short names such as `assign` and `lti`. Searches with distinctive terms use Moodle Search; requests without them list matching visible activities directly. It returns up to five links without asking AI to interpret their content.
- `content`: answers explanatory questions from Moodle Search content. It searches with the planner's subject terms, rejects insufficiently relevant matches, ranks the remaining results and sends at most five to a second AI call. Link-only results are returned directly instead. When nothing is found, the configured open-answer policy either permits an answer without retrieved Moodle content or returns a not-found response.
- `dates`: answers questions about upcoming calendar events and deadlines. It reads visible events from now through the next 90 days, filters their names and descriptions with the planner's terms and sends at most ten matches to a second AI call. It is not a general calendar browser and does not retrieve past events or events beyond that window.
- `grades`: answers questions about the current user's own visible grades and grading feedback. In a course it reads that course's user grade report; at site level it checks the user's active enrolled courses. It respects Moodle grade-report capabilities, course `showgrades`, hidden items and activity availability, returns at most 50 matching items and makes a second AI call. It never uses an open answer when no grade data is available. Example: **“¿Qué calificación obtuve en el Quiz de seguridad?”**
- `progress`: answers questions about the current user's own course and activity completion. It reports separate course-completion and visible-activity percentages, can filter incomplete, completed, passed or failed activities, checks the current course or active enrolled courses at site level and sends at most 50 records to a second AI call. Hidden, group-excluded and untracked activities are excluded. Example: **“¿Qué actividades tengo pendientes en este curso?”**
- `base`: internal fallback that returns the generic unsupported-request response. It performs no retrieval and no second AI call. The runtime accepts it for compatibility, although the default planning prompt does not offer it as a classification choice.

Search, calendar, grade and progress retrieval always use the target user and Moodle's access controls. In a normal course context, results are limited to that course. At site level, Search can span content the user may access across courses, while Calendar covers the user's enrolled courses plus the site course and Grades and Progress cover active enrolled courses. Calendar retrieval also applies user and group filters and Moodle's event-visibility callbacks. Consequently, intents do not grant access to content, events, grades or completion data that the user could not otherwise see.

## Security notes

- Access and capabilities are checked in both the requested context and its canonical chat context.
- Questions are limited to 4,000 Unicode characters.
- Markdown is formatted and cleaned on the server before it is returned.
- Provider details are hidden unless Moodle uses `DEBUG_DEVELOPER`.
- Parce adds neither a custom rate limiter nor a custom session lock; it relies on Moodle's AI limiter and normal writable-session request locking.

## Development verification

Run from the Moodle root:

```bash
vendor/bin/phpunit --testsuite aiprovider_bbco_testsuite
vendor/bin/phpunit --testsuite local_parce_testsuite
phpcs --standard=moodle public/ai/provider/bbco
phpcs --standard=moodle public/local/parce
```

See `docs/TECHNICAL-SPEC.md` for contracts and verification details.

## License

GNU GPL v3 or later.

## Author

David Herney @ BambuCo (2026).
