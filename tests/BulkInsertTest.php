<?php
declare(strict_types=1);

namespace N1215\EloquentBulkSave;

use Carbon\Carbon;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Testing\Fakes\EventFake;
use PHPUnit\Framework\TestCase;

class BulkInsertTest extends TestCase
{
    /**
     * @var Manager
     */
    private $manager;

    /**
     * @var EventFake
     */
    private $eventDispatcher;

    /**
     * @var string
     */
    private static $now = '2018-07-02 19:00:00';

    public function setUp()
    {
        parent::setUp();
        Carbon::setTestNow(self::$now);

        // set up database manager
        $this->manager = new Manager();
        $this->manager->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'options' => [
                \PDO::ATTR_PERSISTENT => true,
            ],
        ]);

        // ensure bulk_inseratables table exists
        $pdo = $this->manager->getConnection()->getPdo();
        $pdo->exec('drop table if exists bulk_insertables');
        $createTableStmt = 'create table bulk_insertables ('
            . 'id int unsigned primary key, '
            . 'column1 varchar(255) null, '
            . 'column2 varchar(255) null, '
            . 'column3 varchar(255) null, '
            . 'created_at timestamp null, '
            . 'updated_at timestamp null'
            . ')';

        $pdo->exec($createTableStmt);
    }

    public function testBulkInsertCanPersistMultipleRecords(): void
    {
        $this->manager->setAsGlobal();
        $this->manager->bootEloquent();

        $models = Collection::make([
            new BulkInsertable(['id' => 1, 'column1' => 'col1_1', 'column2' => 'col2_1']),
            new BulkInsertable(['id' => 2, 'column1' => 'col1_2', 'column3' => 'col3_2']),
        ]);


        BulkInsertable::bulkInsert($models);


        $persistedModels = BulkInsertable::all();
        $this->assertEquals([
            [
                'id' => 1,
                'column1' => 'col1_1',
                'column2' => 'col2_1',
                'column3' => null,
                'created_at' => self::$now,
                'updated_at' => self::$now,
            ],
            [
                'id' => 2,
                'column1' => 'col1_2',
                'column2' => null,
                'column3' => 'col3_2',
                'created_at' => self::$now,
                'updated_at' => self::$now
            ],
        ], $persistedModels->toArray());
    }

    public function testBulkInsertFiresModelEvents(): void
    {
        /** @var Dispatcher $eventDispatcherMock */
        $eventDispatcherMock = $this->createMock(Dispatcher::class);
        $this->eventDispatcher = new EventFake($eventDispatcherMock);
        $this->manager->setEventDispatcher($this->eventDispatcher);
        $this->manager->setAsGlobal();
        $this->manager->bootEloquent();

        $models = Collection::make([
            new BulkInsertable(['id' => 1, 'column1' => 'col1_1', 'column2' => 'col2_1']),
            new BulkInsertable(['id' => 2, 'column1' => 'col1_2', 'column3' => 'col3_2']),
        ]);


        BulkInsertable::bulkInsert($models);


        foreach ($models as $model) {
            $this->eventDispatcher->assertDispatched('eloquent.saving: ' . BulkInsertable::class, function ($event, $payload, $halt) use ($model) {
                return $payload === $model && $halt === true;
            });
            $this->eventDispatcher->assertDispatched('eloquent.creating: ' . BulkInsertable::class, function ($event, $payload, $halt) use ($model) {
                return $payload === $model && $halt === true;
            });
            $this->eventDispatcher->assertDispatched('eloquent.created: ' . BulkInsertable::class, function ($event, $payload, $halt) use ($model) {
                return $payload === $model && $halt === false;
            });
            $this->eventDispatcher->assertDispatched('eloquent.saved: ' . BulkInsertable::class, function ($event, $payload, $halt) use ($model) {
                return $payload === $model && $halt === false;
            });
        }
    }

    public function tearDown()
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}


class BulkInsertable extends Model
{
    use BulkInsert;

    protected $fillable = [
        'id',
        'column1',
        'column2',
        'column3',
    ];
}
