<!-- file generated with AI assistance: Claude Code - 2026-10-08 22:19:20 UTC -->

# Changelog

## 0.9.0

Engine credentials are stored encrypted, using the secret handling of `dmstr/api-configuration-bundle` 0.5.

### Breaking changes

- **Requires `dmstr/api-configuration-bundle` `^0.5`.** The constraint `^0.2.0 || ^0.3.0 || ^0.4.0` is dropped, because the bundle now depends on `ConfigSecrets` from 0.5. Applications on api-configuration-bundle 0.4 or older stay on flowable-bundle 0.8.
- **`password` and `token` are `writeOnly`.** `schema.json` marks both keys with `"writeOnly": true`. As a result, api-configuration-bundle encrypts them at rest (`enc:v1:…`), returns them as `********` in API responses (also for administrators) and keeps the stored value when an update omits the key or sends the mask back. A usable `CREDENTIALS_ENCRYPTION_KEY` (`dmstr_api_platform_utils.credential_encryption.key`) is required as soon as a `flowable` configuration holds a password or token.
- **`FlowableClientLocator` takes a fourth constructor argument**, `ConfigSecrets $secrets`. It is wired in the bundle's `config/services.yaml`; this only matters if you instantiate the locator yourself.

### Existing clear-text records

Records created with 0.8 or older hold `password`/`token` in clear text. `ConfigSecrets::decrypt()` only touches values with the `enc:v1:` prefix and returns every other value unchanged, so these records keep working after the upgrade: the locator passes the clear-text value to the engine as before. They stay unencrypted in the database, though, until they are written again. Encrypt them in one of two ways:

- run `bin/console app:api-configuration:encrypt-secrets` from api-configuration-bundle (idempotent, `--dry-run` available), or
- re-save each `flowable` configuration through the API with the secret entered again. The Doctrine `onFlush` listener encrypts every secret of an inserted or updated record, so any write that Doctrine schedules as an update also encrypts a kept clear-text value. A save that sends only `********` and changes nothing else may not be scheduled as an update at all and then leaves the value in clear text; the console command is the reliable way.

Responses mask the clear-text values as well, because masking follows the schema and not the stored format.

### Changed

- **Credential seam:** `FlowableClientLocator::createClient()` decrypts the configuration with `ConfigSecrets::decrypt()` before it builds the `FlowableClient`. All consumers resolve their client through the locator: the API state providers and processors, every `flowable:*` command including `flowable:health`, and the external worker runner. The decrypted values exist only inside the client. A decryption failure (missing or changed key, corrupted value) is rethrown as `SecretEncryptionException` naming the configuration id. The bundle never falls back to sending the stored ciphertext to the engine. Over HTTP, this surfaces as a server error (500).

### Unchanged

- **Completing a task that does not exist (any more):** `POST /api/flowable/tasks/{id}/complete` still answers HTTP 404 when the engine answers 404, for example for an already completed task. `FlowableClient::completeTask()` does not catch the engine status, and `FlowableApiException::fromUpstreamStatus()` maps an upstream 404 to a 404 problem response (`Flowable resource not found.`). Callers that need idempotent completion have to treat this 404 themselves.

## 0.8.0 and earlier

See the git history and the GitHub releases.
