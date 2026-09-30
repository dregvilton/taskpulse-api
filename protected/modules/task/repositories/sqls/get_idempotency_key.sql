SELECT request_hash, task_id, response_body
FROM {{%idempotency_keys}}
WHERE idempotency_key = :idempotencyKey
  AND user_id = :userId
