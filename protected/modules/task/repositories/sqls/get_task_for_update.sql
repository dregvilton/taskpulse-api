SELECT *
FROM {{%tasks}}
WHERE id = :id
  AND author_id = :ownerId
  AND deleted_at IS NULL
FOR UPDATE
