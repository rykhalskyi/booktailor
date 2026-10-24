---
created: 2026-10-24
type: research
status: implemented
summary: Langfuse and BYOK spike — OTLP tracing, prompt management, and encrypted per-user keys on Nextcloud 35.
---

# Spike — Langfuse and BYOK (T0)

Part of [T0 — Foundation](../tickets/phase-0-foundation.md). Decisions: D-22 (Langfuse
OTLP + v2 APIs) and D-23 (encrypted BYOK keys, masked on read). Related:
[Compose job contract](compose-job-contract.md), [OpenAPI draft](../../../openapi.draft.yaml).

## Environment

- Self-hosted Langfuse 4.63.0 at `http://langfuse.homelab.home.arpa`, LAN-only, no TLS.
- The instance runs in v4 **events_only** mode.
- Project credentials were read from the user's local opencode Langfuse config and
  copied into temporary files inside the dev container. The temporary files were removed
  after the spike; no keys are stored in this repository.
- Nextcloud 35.0.0.3, PHP 8.5.9. Container-to-Langfuse connectivity is confirmed.
- Prompt `booktailor-t0-spike` (text, version 1, label `production`).
- Traces: `b04ea470c6302e5cbac44ed1fde72c20` (host Python OTLP) and
  `85257816dfb8811849ec60d1ea333252` (Nextcloud PHP OTLP).

## Verified operations

| Operation | Result |
|---|---|
| Health | `GET /api/public/health` returns `{"status":"OK","version":"4.63.0"}`. |
| Prompt create | `POST /api/public/v2/prompts` creates version 1 with `production`/`latest`. |
| Prompt read | `GET /api/public/v2/prompts/booktailor-t0-spike?label=production` returns the text and version. |
| OTLP ingest | `POST /api/public/otel/v1/traces` returns 200 from host Python and Nextcloud PHP. |
| Auth | Basic auth with `publicKey:secretKey` works for prompts and OTLP. |
| Trace visibility | `GET /api/public/v2/observations?traceId=...&fields=core,io,prompt,model,usage` returns the generation with input, output, model, usage, and prompt link. |
| Prompt link in trace | `promptName=booktailor-t0-spike`, `promptVersion=1`. |
| Legacy read API | `/api/public/traces` returns 404 in v4 events_only mode. |
| Legacy write API | `/api/public/ingestion` is deprecated; use OTLP. |
| BYOK encrypt/decrypt | `ICrypto::encrypt()` → store → `decrypt()` round-trips; ciphertext does not contain the plaintext. |
| BYOK masking | The serialized value exposes only a prefix/suffix mask, never the full key. |
| BYOK server-side use | The decrypted key authenticated a Langfuse prompt request from PHP with HTTP 200. |
| Container → LAN | Nextcloud `IClientService` reaches Langfuse with `nextcloud.allow_local_address = true`. |

## Findings

1. **Use OTLP, not the legacy ingestion API.** With `x-langfuse-ingestion-version: 4`,
   `POST /api/public/otel/v1/traces` accepts OTLP/HTTP JSON. Without that header, direct
   OTel data can be delayed by up to 10 minutes. The legacy `/api/public/ingestion` path
   is deprecated and the legacy trace read API is unavailable in v4 events_only mode.
2. **Read traces through the v2 observations API.** `GET /api/public/v2/observations`
   returns summary fields by default. Add `fields=core,io,prompt,model,usage` to get
   input, output, model, prompt name/version, and usage. `/api/public/v2/traces/{id}`
   and `/api/public/traces/{id}` are not available.
3. **Attribute mapping is explicit.** A generation needs
   `langfuse.observation.type=generation`, and the useful keys are
   `langfuse.trace.name`, `langfuse.user.id`, `langfuse.trace.tags`,
   `langfuse.observation.model.name`, `langfuse.observation.input`,
   `langfuse.observation.output`, `langfuse.observation.usage_details`,
   `langfuse.observation.prompt.name`, and `langfuse.observation.prompt.version`.
   The prompt attributes produce a real prompt link on the observation.
4. **No PHP SDK is required.** Direct OTLP JSON through Nextcloud's `IClientService`
   works and keeps the dependency surface small. If the app later adopts the PHP
   OpenTelemetry SDK, point its OTLP exporter at the same endpoint with the same headers.
5. **Prompt management is a runtime lookup.** Fetch by name and label at call time,
   cache briefly, and fall back to the local predefined prompt if Langfuse is unavailable.
   A prompt version should be linked into the trace so the exact prompt is auditable.
6. **Langfuse is best-effort, not a hard dependency.** If tracing or prompt fetch fails,
   the AI call should still work with the local prompt and no trace; log a warning and
   include the trace id in the response when one exists.
7. **BYOK uses Nextcloud crypto, not a new secret store.** Encrypt with `ICrypto`,
   persist the ciphertext in a per-user protected record, decrypt only server-side at
   call time, and return only a mask (`sk-l••••…` style) from the API. The existing
   `byebyemoneylist-ns` app uses this pattern with a DB entity plus `apiKeyMasked`
   serialization; BookTailor can reuse it.
8. **Never put the key in a job argument or log.** Decrypt inside the request or the AI
   job, use it in the outbound Authorization header, and discard it. Job arguments are
   visible in `background-job:list`.
9. **App credentials and BYOK are different.** Langfuse project keys belong to the app;
   the user's LLM provider key is BYOK. In the spike, a Langfuse secret was used as a
   stand-in user secret only to exercise the encrypt/store/mask/use path.

## Reproduction

The OTLP payload used one root generation span:

```json
{
  "resourceSpans": [{
    "resource": {"attributes": [{"key": "service.name", "value": {"stringValue": "booktailor"}}]},
    "scopeSpans": [{
      "scope": {"name": "booktailor-t0"},
      "spans": [{
        "traceId": "<32 hex>",
        "spanId": "<16 hex>",
        "name": "booktailor-t0-spike",
        "kind": 1,
        "startTimeUnixNano": "<ns>",
        "endTimeUnixNano": "<ns>",
        "attributes": [
          {"key": "langfuse.trace.name", "value": {"stringValue": "booktailor-t0-spike"}},
          {"key": "langfuse.user.id", "value": {"stringValue": "user1"}},
          {"key": "langfuse.observation.type", "value": {"stringValue": "generation"}},
          {"key": "langfuse.observation.model.name", "value": {"stringValue": "spike-model"}},
          {"key": "langfuse.observation.input", "value": {"stringValue": "..."}},
          {"key": "langfuse.observation.output", "value": {"stringValue": "..."}},
          {"key": "langfuse.observation.prompt.name", "value": {"stringValue": "booktailor-t0-spike"}},
          {"key": "langfuse.observation.prompt.version", "value": {"intValue": "1"}}
        ]
      }]
    }]
  }]
}
```

Headers: `Authorization: Basic <base64(public:secret)>`,
`Content-Type: application/json`, `x-langfuse-ingestion-version: 4`.

BYOK verification:

```php
$cipher = $crypto->encrypt($plainKey);
$config->setUserValue($userId, 'booktailor', 'spike_byok_key', $cipher);
$plain = $crypto->decrypt($config->getUserValue($userId, 'booktailor', 'spike_byok_key'));
$auth = base64_encode($publicKey . ':' . $plain); // used only in the server-side request
```

The mask is derived from the plaintext only after decrypting, for display; it is never
stored. Delete the user value when the key is removed.

## Implications for the app

- Add a `LangfuseService` with `fetchPrompt(name, label)` and
  `traceGeneration(traceId, userId, promptName, promptVersion, input, output, usage)`.
- Make Langfuse calls best-effort and timeout-bounded; never block AI on tracing.
- Store Langfuse project credentials in app config/environment, not in the repo.
- Add a per-user AI settings record with an encrypted key and a read-only mask; never
  return the plaintext after entry.
- Send the trace id in the chat response so the UI can link to Langfuse when enabled.
- Redact prompts/outputs if the user disables content logging (T6).
- Do not use Langfuse for composition jobs; they are local and have no AI call.

## Fallback

If Langfuse is unreachable, use local predefined prompts and skip tracing. If prompt
lookup fails, still run the call with the last known/local prompt and no prompt link.
If the user has no BYOK key, disable AI or use an app-provided default (T6) rather than
calling a provider without credentials.

## Open checks (T6)

- Provider list, default model, and whether an app-level fallback key is allowed.
- PII redaction and content-logging consent.
- Prompt caching, label promotion, and rollback.
- Langfuse retention and score/evaluation APIs.
- Key rotation and audit events for BYOK changes.
- OTel PHP SDK versus direct OTLP JSON.
