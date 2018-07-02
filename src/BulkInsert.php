<?php
declare(strict_types=1);

namespace N1215\EloquentBulkSave;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Trait BulkInsert
 * @package N1215\EloquentBulkSave
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait BulkInsert
{
    /**
     * @param Collection|Model[] $models
     * @return bool
     */
    public static function bulkInsert(Collection $models): bool
    {
        if (!is_subclass_of(static::class, Model::class)) {
            throw new \LogicException(
                'class using BulkInsert trait should be a subclass of ' . Model::class
            );
        }

        // check the argument
        foreach ($models as $model) {
            if (! $model instanceof static) {
                throw new \LogicException(static::class . '::bulkInsert() cannot be used for ' . \get_class($model));
            }

            if ($model->exists) {
                throw new \LogicException(
                    'this eloquent model has already been persisted: class = ' . static::class . ', primary key =' . $model->getKey()
                );
            }
        }

        if ($models->isEmpty()) {
            return true;
        }

        // before save
        foreach ($models as $model) {
            if ($model->usesTimestamps()) {
                $time = $model->freshTimestamp();

                if (static::UPDATED_AT !== null && !$model->isDirty(static::UPDATED_AT)) {
                    $model->setUpdatedAt($time);
                }

                if (static::CREATED_AT !== null && !$model->isDirty(static::CREATED_AT)) {
                    $model->setCreatedAt($time);
                }
            }

            if ($model->fireModelEvent('saving') === false) {
                return false;
            }
        }

        // before insert
        foreach ($models as $model) {
            if ($model->fireModelEvent('creating') === false) {
                return false;
            }
        }

        // perform insert
        $attributesArray = static::convertModelsToArray($models);
        $saved = (new static)->newQueryWithoutScopes()->insert($attributesArray);
        if (!$saved) {
            return false;
        }

        // after insert
        foreach ($models as $model) {
            $model->exists = true;
            $model->wasRecentlyCreated = true;
            $model->fireModelEvent('created', false);
        }

        // after save
        $options = [];
        foreach ($models as $model) {
            $model->finishSave($options);
        }

        return true;
    }

    /**
     * create array of model attributes
     * @param Collection|Model[] $models
     * @return array
     */
    private static function convertModelsToArray(Collection $models): array
    {
        $attributesCollection = $models
            ->map(function (Model $model) {
                return $model->attributes;
            });

        $columns = $attributesCollection
            ->flatMap(function(array $attributesArray) {
                return array_keys($attributesArray);
            })
            ->unique()
            ->values();

        // fill non-existent columns
        return $attributesCollection
            ->map(function (array $attributes) use ($columns) {
                foreach ($columns as $column) {
                    if (!array_key_exists($column, $attributes)) {
                        $attributes[$column] = null;
                    }
                }
                return $attributes;
            })
            ->toArray();
    }
}
