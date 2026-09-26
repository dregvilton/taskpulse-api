INSERT INTO {{%idempotency_keys}} (idempotency_key, request_hash)
VALUES (:idempotencyKey, :requestHash)
ON CONFLICT (idempotency_key) DO NOTHING
RETURNING idempotency_key
