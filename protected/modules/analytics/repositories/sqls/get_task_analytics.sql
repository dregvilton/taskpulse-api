SELECT
    COUNT(*)::integer AS "totalCreated",
    COUNT(*) FILTER (WHERE completed)::integer AS "totalCompleted",
    COALESCE(
        ROUND(100.0 * COUNT(*) FILTER (WHERE completed) / NULLIF(COUNT(*), 0), 2),
        0
    )::double precision AS "completionPercent",
    ROUND(
        AVG(EXTRACT(EPOCH FROM completed_at - created_at)) FILTER (WHERE completed),
        2
    )::double precision AS "avgCompletionTimeSeconds"
FROM {{%tasks}}
WHERE deleted_at IS NULL
  AND (CAST(:authorId AS BIGINT) IS NULL OR author_id = CAST(:authorId AS BIGINT))
  AND (CAST(:createdFrom AS TIMESTAMP) IS NULL OR created_at >= CAST(:createdFrom AS TIMESTAMP))
  AND (CAST(:createdTo AS TIMESTAMP) IS NULL OR created_at <= CAST(:createdTo AS TIMESTAMP))
