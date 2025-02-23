<?php

declare(strict_types=1);

namespace Imi\Model\Traits;

use Imi\Db\Interfaces\IDb;
use Imi\Db\Query\Field;
use Imi\Db\Query\Interfaces\IResult;
use Imi\Db\Query\Result\ChunkByOffsetResult;
use Imi\Db\Query\Result\ChunkResult;
use Imi\Db\Query\Result\CursorResult;
use Imi\Model\Meta;
use Imi\Model\Model;
use Imi\Model\ModelQueryResult;

trait TModelQuery
{
    /**
     * 是否设置序列化字段.
     */
    protected bool $isSetSerializedFields = false;

    /**
     * 关联查询预加载字段.
     */
    protected ?array $with = null;

    /**
     * 指定查询出的模型可序列化的字段.
     */
    protected ?array $withField = null;

    /**
     * 模型元数据.
     */
    protected ?Meta $meta = null;

    /**
     * @param class-string<Model>|null $modelClass
     */
    public function __construct(?IDb $db = null, ?string $modelClass = null, ?string $poolName = null, ?int $queryType = null, ?string $prefix = null)
    {
        if (null !== $modelClass)
        {
            $this->meta = $meta = $modelClass::__getMeta();
            if (null === $prefix && !$meta->isUsePrefix())
            {
                $prefix = '';
            }
        }
        parent::__construct($db, $modelClass, $poolName, $queryType, $prefix);
        $this->setResultClass(ModelQueryResult::class);
    }

    protected function initQuery(): void
    {
        parent::initQuery();
        if ($meta = $this->meta)
        {
            if (null !== ($tableName = $meta->getTableName()))
            {
                $this->table($tableName, null, $meta->getDatabaseName());
            }
        }
    }

    private function queryPreProcess(): void
    {
        if ($this->hasCustomFields())
        {
            $this->isSetSerializedFields = true;
        }
        else
        {
            /** @var \Imi\Model\Meta $meta */
            $meta = $this->modelClass::__getMeta();
            if ($sqlColumns = $meta->getSqlColumns())
            {
                $this->field($meta->getTableName() . '.*');
                $fields = $meta->getFields();
                foreach ($sqlColumns as $name => $sqlAnnotations)
                {
                    $sqlAnnotation = $sqlAnnotations[0];
                    $this->fieldRaw($sqlAnnotation->sql, $fields[$name]->name ?? $name);
                }
            }
            $this->isSetSerializedFields = false;
        }
    }

    /**
     * 查询记录.
     */
    public function select(): IResult
    {
        $this->queryPreProcess();

        return parent::select();
    }

    /**
     * {@inheritDoc}
     */
    public function cursor(): CursorResult
    {
        $this->queryPreProcess();

        return parent::cursor();
    }

    /**
     * {@inheritDoc}
     */
    public function chunkById(int $count, string $column, ?string $alias = null): ChunkResult
    {
        $this->queryPreProcess();

        return parent::chunkById($count, $column, $alias);
    }

    /**
     * {@inheritDoc}
     */
    public function chunkByOffset(int $count): ChunkByOffsetResult
    {
        $this->queryPreProcess();

        return parent::chunkByOffset($count);
    }

    /**
     * {@inheritDoc}
     */
    public function chunkEach(int $count, string $column, ?string $alias = null)
    {
        $this->queryPreProcess();

        return parent::chunkEach($count, $column, $alias);
    }

    private function hasCustomFields(): bool
    {
        $field = $this->option->field;
        if (!$field)
        {
            return false;
        }
        if (\count($field) > 1)
        {
            return true;
        }

        $k = array_key_first($field);
        $v = $field[$k] ?? null;

        if (is_numeric($k))
        {
            if ('*' === $v)
            {
                return false;
            }
            if ($v instanceof Field)
            {
                $field = $v;
            }
            else
            {
                $field = new Field();
                $field->setValue($v ?? '', $this);
            }
        }
        else
        {
            $field = new Field(null, null, $k, $v);
        }
        if ('*' !== $field->getField())
        {
            return true;
        }
        $table = $field->getTable();
        $tableObject = $this->option->table;
        if (null === $table || $table === $tableObject->getTable() || $table === $tableObject->getAlias())
        {
            return false;
        }

        return true;
    }

    /**
     * 执行SQL语句.
     */
    public function execute(string $sql): IResult
    {
        /** @var ModelQueryResult $result */
        $result = parent::execute($sql);
        if ($this->isSetSerializedFields)
        {
            $result->setIsSetSerializedFields(true);
        }
        if ($this->with)
        {
            $result->setWith($this->with);
        }
        if ($this->withField)
        {
            $result->setWithField($this->withField);
        }

        return $result;
    }

    /**
     * 关联查询预加载.
     *
     * @param string|array|null $field
     */
    public function with($field): self
    {
        $this->with = null === $field ? null : (array) $field;

        return $this;
    }

    /**
     * 指定查询出的模型可序列化的字段.
     *
     * @param string $fields
     *
     * @return static
     */
    public function withField(...$fields): self
    {
        $this->withField = $fields;

        return $this;
    }
}
