# Eloquent Bulk Save

[![Latest Stable Version](https://poser.pugx.org/n1215/jugoya/v/stable)](https://packagist.org/packages/n1215/eloquent-bulk-save)

This library contains a trait that enables \Illuminate\Database\Eloquent\Model to bulk insert multiple records at one query.
Unlike in the case of directly using \Illuminate\Database\Query\Builder::insert(), this trait method fires each eloquent model events like eloquent.creating, eloquent.saving, ... and so on.


## Usage

```
// 1. change your eloquent model class to use the trait
class YouModel extends \Illuminate\Database\Eloquent\Model
{
    use \N1215\EloquentBulkSave\BulkInsert;
}

// 2. create model collection (you can use Illuminate\Database\Eloquent\Collection)
$models = \Illuminate\Support\Collection::make([
    new YourModel($attributes1),
    new YourModel($attributes2),
    ...
]);

// 3. use bulkInsert() static method
YourModel::bulkInsert($models);

```

## License
The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
