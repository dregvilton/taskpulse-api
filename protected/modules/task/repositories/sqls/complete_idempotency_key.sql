UPDATE {{%idempotency_keys}}
SET task_id = :taskId, response_body = :responseBody
WHERE idempotency_key = :idempotencyKey
  AND user_id = :userId
