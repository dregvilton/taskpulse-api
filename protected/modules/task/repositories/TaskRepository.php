<?php

declare(strict_types=1);

namespace app\modules\task\repositories;

use app\extensions\DbConnectTrait;
use app\models\Task;
use app\modules\task\forms\TaskSearchForm;
use RuntimeException;
use yii\base\InvalidConfigException;
use yii\data\SqlDataProvider;
use yii\db\Exception;

/**
 * Репозиторий задач.
 */
final class TaskRepository
{
    use DbConnectTrait;

    /**
     * Получить задачу.
     *
     * @param int $id
     * @param int $ownerId
     * @return Task|null
     * @throws Exception
     */
    public function getById(int $id, int $ownerId): ?Task
    {
        return Task::find()
            ->where(['id' => $id, 'author_id' => $ownerId, 'deleted_at' => null])
            ->one();
    }

    /**
     * Получить и заблокировать задачу до завершения транзакции.
     *
     * @param int $id
     * @param int $ownerId
     * @return Task|null
     * @throws InvalidConfigException|Exception|RuntimeException
     */
    public function getByIdForUpdate(int $id, int $ownerId): ?Task
    {
        $row = $this->getDbConnection()
            ->createCommand(__DIR__ . '/sqls/get_task_for_update.sql')
            ->bindValue(':id', $id)
            ->bindValue(':ownerId', $ownerId)
            ->queryOne();

        if ($row === false) {
            return null;
        }

        $task = new Task();
        Task::populateRecord($task, $row);

        return $task;
    }

    /**
     * Получить список задач.
     *
     * @param TaskSearchForm $form
     * @param int|null $authorId
     * @return SqlDataProvider
     * @throws InvalidConfigException|RuntimeException
     */
    public function getList(TaskSearchForm $form, ?int $authorId = null): SqlDataProvider
    {
        return new SqlDataProvider([
            'db' => $this->getDbConnection(),
            'sql' => $this->getDbConnection()->getSql(__DIR__ . '/sqls/get_tasks.sql'),
            'params' => [
                ':authorId' => $authorId ?? $form->authorId,
                ':completed' => $form->completed,
                ':createdFrom' => $form->createdFrom,
                ':createdTo' => $form->createdTo,
                ':completedFrom' => $form->completedFrom,
                ':completedTo' => $form->completedTo,
            ],
            'key' => 'id',
            'pagination' => [
                'defaultPageSize' => 20,
                'pageSizeLimit' => [1, 100],
                'pageParam' => 'page',
                'pageSizeParam' => 'perPage',
            ],
            'sort' => [
                'sortParam' => 'sort',
                'defaultOrder' => [
                    'createdAt' => SORT_DESC,
                    'id' => SORT_DESC,
                ],
                'attributes' => [
                    'id',
                    'title',
                    'completed',
                    'createdAt' => [
                        'asc' => ['created_at' => SORT_ASC],
                        'desc' => ['created_at' => SORT_DESC],
                    ],
                    'completedAt' => [
                        'asc' => ['completed_at' => SORT_ASC],
                        'desc' => ['completed_at' => SORT_DESC],
                    ],
                ],
            ],
        ]);
    }
}
