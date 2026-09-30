SELECT id, task_id, event_type, payload
FROM task_events
WHERE published_at IS NULL
ORDER BY id
LIMIT :limit
FOR UPDATE SKIP LOCKED
