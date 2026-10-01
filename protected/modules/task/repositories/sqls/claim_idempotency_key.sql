INSERT INTO {{%idempotency_keys}} (user_id, idempotency_key, request_hash)
VALUES (:userId, :idempotencyKey, :requestHash)
ON CONFLICT (user_id, idempotency_key) DO NOTHING
RETURNING idempotency_key
