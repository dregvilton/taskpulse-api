SELECT *
FROM {{%tasks}}
WHERE id = :id
  AND deleted_at IS NULL
FOR UPDATE
