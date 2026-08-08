# Local Parce technical specification

## Supported platform

| Component | Supported version |
| --- | --- |
| Moodle | 5.1 (`2025100600`) |
| PHP | 8.2+ as required by Moodle 5.1 |
| `aiprovider_bbco` | `2026080600` or newer |
| `local_parce` | `2026080600` |

Both plugins remain `MATURITY_BETA` after phases 4 and 5B. Production concurrency, load/latency, retention-policy and operational dashboard/alert validation are release gates before considering stable maturity.

## Ownership boundaries

```text
Browser widget
    | local_parce_answer / local_parce_get_active_conversation
    v
MODE_SESSION cache ---------------> AI prompt context
    | successful complete turn
    v
Conversation history DB ----------> history/Privacy API only
    |
    +-----------------------------> AI action traces
```

### Active cache

`local_parce` defines the `conversation` cache in `MODE_SESSION`. Its internal key contains user, canonical chat context and a hash of the PHP session identifier. The session identifier is an isolation mechanism only and is never included in the persistent thread identifier.

The cached value contains:

- a cryptographically random 64-character hexadecimal `conversationkey`;
- complete user/system entry pairs;
- creation and last-access timestamps;
- the durable global cache generation used for Privacy invalidation.

The cache is the only source for the visible active conversation and prior turns supplied to AI. There is no cache-to-database read-through and no TTL.

### Persistent storage

`local_parce_conversation_entries` stores successful complete turns for history and audit. `local_parce_ai_actions` stores provider-call traces and links them to the durable turn when one is produced. Neither table is used to reconstruct active state or prompts.

Each logical planning or answer call is opened before provider resolution and closed in a `finally` block. Attempts share a random request and call correlation ID; fallback attempts are separate ordered rows, while a call with no provider has ordinal zero. Closed traces expose stable outcomes and monotonic millisecond duration without being returned by web services.

`chatid` is always a canonical course or system context ID. Course-module pages resolve to their containing course context. Pages outside a course resolve to the system context.

## Conversation lifecycle and limits

| Limit | Value |
| --- | ---: |
| Question length | 4,000 Unicode characters |
| Active thread | configurable 1–40 complete turns; hard max 40 |
| Active estimated tokens | configurable 1–16,000; hard max 16,000 |
| Prompt history | 8 complete turns / 8,000 estimated tokens |
| Retrieved Search/Calendar payload | 8,000 estimated tokens |
| Entire provider payload | 18,000 estimated tokens |
| Persistent history page | 100 complete turns maximum |

Token estimation uses one token per three Unicode characters. A new active thread is created before the next question would reach a configured turn or token limit. The question that triggers rollover becomes the first turn of the new thread.

Invalid stored limit values are rejected with a coding exception instead of being silently clamped. Admin forms apply the same ranges.

## Provider payload and trust

Parce treats retrieved Search, Calendar, Grades and Progress values as untrusted data. `controller::build_ai_payload()` places instructions, the question, previous turns and retrieved content between distinct text markers and enforces the total budget. These markers are text-level separation; they do not assert that BBCO or an effective provider supports or preserves a native `system` role.

`ai_gateway` is the testable boundary around BBCO. BBCO discovers enabled and configured real provider instances, applies configured preference ordering and delegates a fresh cloned action to each eligible provider. Fallback is allowed only for recoverable 5xx responses. A 4xx response, including 429, and processor exceptions are terminal.

Parce does not add another rate limiter. BBCO applies its own Moodle provider limit and the effective provider applies its limit when called.

### Answer result contract

`local_parce_answer` retains `answer`, `newconversation` and `usagepercentage` and also returns:

- `status`: `success`, `error` or `rate_limited`;
- `successful`: boolean operation outcome;
- `retryable`: whether a later user-initiated retry may succeed;
- `errorcode`: stable machine-readable code, omitted on success;
- `retryafter`: seconds until retry, omitted unless Moodle or the provider supplied a value.

No retry delay is inferred. A rate-limited planning call cannot proceed to answer generation, and BBCO treats 429 as terminal without provider fallback. Failed turns are not added to active conversation entries or future prompts. Technical provider details are appended to the safe localised message only under `DEBUG_DEVELOPER`.

Moodle quotas are per provider call, user ID and configured Moodle rate-limit window. One question may consume a planning call and an answer call. They are not daily, per-question, per-IP, per-session or per-`conversationkey` quotas. All guests use Moodle's shared guest user ID and therefore share its quota, even though their active conversation caches are session-isolated.

## Widget UX contract

The floating trigger is a native button controlling a non-modal dialog. Open, closed, `hidden`, `aria-hidden` and `aria-expanded` state are updated together. Opening focuses the textarea; Escape, the close button and an outside click close the dialog and restore focus to its opener. There is no focus trap.

The active session conversation is fetched completely once. The active endpoint has no pagination contract and persistent history is never requested by the widget. Initial entries are rendered while the accessible log is not live; only subsequently added user and system messages are announced.

Conversation loading is serialised before sending. A failed load remains retryable and does not set `hasLoadedHistory`. A failed send retains one pending question node, replaces only its operation feedback during retry, and never adds the question twice. Rollover moves that existing node into the new visible conversation while announcements are disabled for the rearrangement.

Server sanitisation is the HTML trust boundary. Live and restored system responses use the same renderer and no client-side security regex. User-authored text is inserted as text rather than HTML.

Open state is not persisted: every page and browser tab starts closed, so no state can leak across tabs, logins or courses. The widget includes visible loading, empty, error and rate-limit states, focus-visible styling, mobile viewport sizing and reduced-motion behaviour.

## Authorization

`local_parce_answer` validates the page's requested context, resolves the canonical chat context and requires chat access in both. Non-guest access uses `local/parce:usechat`; guest access requires the explicit `enable_guests` setting.

`local_parce_get_active_conversation` returns only the current session cache. `local_parce_get_conversation` returns persistent history:

- authenticated users may read their own history without current enrolment;
- guests are always denied persistent history;
- another user's history requires `local/parce:viewallchats` in that chat context;
- each authorised foreign-history read emits `conversation_history_viewed`.

## Guest audit contract

Guests share Moodle's guest user ID, so they are not represented as separate identities. Session-isolated cache keys and random `conversationkey` values keep their active threads separate. Successful guest turns and AI actions are persisted for audit and linked by `conversationkey`; guests cannot call the persistent-history endpoint.

## Consistency and concurrency

Conversation entry insertion and AI-action linking use one delegated database transaction. The complete turn is published to active cache only after durable persistence succeeds and the Privacy cache generation remains current.

Parce AJAX services retain Moodle's writable session. Moodle's normal PHP session lock therefore serialises concurrent requests using the same cookie. Parce intentionally adds no second lock. This assumption must be verified by an HTTP concurrency probe whenever session handling or service declarations change.

## Privacy and lifecycle

The Privacy API declares both tables and the external BBCO disclosure. It supports context discovery, user export, deletion by context, approved user and user list. Deletion increments a durable cache generation so snapshots held by other PHP sessions become unreadable.

No scheduled retention, purge, anonymisation or TTL exists. Until an institutional policy is approved, records remain until Privacy API deletion. History and AI traces are treated as personal/audit data independent from course content and are excluded from course backup/restore.

## Deployment

Deploy the matching pair in this order:

1. BBCO `2026080600`.
2. Parce `2026080601`, whose dependency requires that BBCO version.
3. Configure a real provider behind BBCO.
4. Install from the fresh schema, configure and purge caches.

The `2026080601` Parce upgrade adds correlation, lifecycle, duration, outcome and effective-provider fields to
`local_parce_ai_actions`; fresh installations receive the same schema from `install.xml`.

Both plugins remain at `MATURITY_BETA` after phases 4 and 5B. Promotion requires production concurrency and load
evidence, an approved trace-retention policy, and operational alerting based on the new outcome/latency metrics.

## Verification contract

- Full PHPUnit suites for BBCO and Parce.
- Fresh-schema test using `check_database_schema()` and required indices.
- HTTP concurrency probe with two overlapping requests and one cookie.
- Moodle PHPCS for both plugin trees.
- PHP syntax checks for modified PHP.
- ESLint and AMD rebuild only when JavaScript source changes.
- `git diff --check` in both repositories.

Any skipped check must be reported explicitly; a prior successful run is not a substitute for a current run.
