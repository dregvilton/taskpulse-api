INSERT INTO processed_task_events (event_id)
VALUES (:eventId)
ON CONFLICT (event_id) DO NOTHING
