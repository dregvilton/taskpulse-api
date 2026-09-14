SELECT
    id,
    author_id AS "authorId",
    title,
    description,
    completed,
    created_at AS "createdAt",
    updated_at AS "updatedAt",
    completed_at AS "completedAt"
FROM {{%tasks}}
WHERE deleted_at IS NULL
  AND (CAST(:authorId AS BIGINT) IS NULL OR author_id = CAST(:authorId AS BIGINT))
  AND (CAST(:completed AS BOOLEAN) IS NULL OR completed = CAST(:completed AS BOOLEAN))
  AND (CAST(:createdFrom AS TIMESTAMP) IS NULL OR created_at >= CAST(:createdFrom AS TIMESTAMP))
  AND (CAST(:createdTo AS TIMESTAMP) IS NULL OR created_at <= CAST(:createdTo AS TIMESTAMP))
  AND (CAST(:completedFrom AS TIMESTAMP) IS NULL OR completed_at >= CAST(:completedFrom AS TIMESTAMP))
  AND (CAST(:completedTo AS TIMESTAMP) IS NULL OR completed_at <= CAST(:completedTo AS TIMESTAMP))
