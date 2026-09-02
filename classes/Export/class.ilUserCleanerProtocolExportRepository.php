<?php
declare(strict_types=1);

use ILIAS\Filesystem\Stream\Streams;
use ILIAS\ResourceStorage\Identification\ResourceIdentification;
use ILIAS\ResourceStorage\Services as ResourceStorageServices;

require_once __DIR__ . '/class.ilUserCleanerProtocolExportStakeholder.php';

final class ilUserCleanerProtocolExportRepository extends ilUserCleanerDatabaseRepository
{
    private const TABLE = 'ucc_protocol_export';

    private ResourceStorageServices $storage;
    private ilUserCleanerProtocolExportStakeholder $stakeholder;

    public function __construct()
    {
        global $DIC;
        parent::__construct();
        $this->storage = $DIC->resourceStorage();
        $this->stakeholder = new ilUserCleanerProtocolExportStakeholder();
    }

    public function store(string $content, string $filename, array $filters): ResourceIdentification
    {
        $resource_id = $this->storage->manage()->stream(
            Streams::ofString($content),
            $this->stakeholder,
            $filename
        );
        try {
            $this->database->insert(self::TABLE, [
                'export_id' => ['integer', $this->database->nextId(self::TABLE)],
                'resource_id' => ['text', $resource_id->serialize()],
                'filename' => ['text', mb_substr($filename, 0, 255)],
                'created_at' => ['timestamp', date('Y-m-d H:i:s')],
                'filters' => ['clob', json_encode($filters, JSON_THROW_ON_ERROR)],
            ]);
        } catch (Throwable $exception) {
            $this->storage->manage()->remove($resource_id, $this->stakeholder);
            throw $exception;
        }

        return $resource_id;
    }

    public function download(ResourceIdentification $resource_id): void
    {
        $this->storage->consume()->download($resource_id)->run();
    }

    public function deleteOlderThan(DateTimeImmutable $cutoff): void
    {
        $rows = $this->fetchAll(
            'SELECT export_id, resource_id FROM ' . self::TABLE . ' WHERE created_at < %s',
            ['timestamp'],
            [$cutoff->format('Y-m-d H:i:s')]
        );
        foreach ($rows as $row) {
            $resource_id = $this->storage->manage()->find((string) $row['resource_id']);
            if ($resource_id !== null) {
                $this->storage->manage()->remove($resource_id, $this->stakeholder);
            }
            $this->database->manipulateF(
                'DELETE FROM ' . self::TABLE . ' WHERE export_id = %s',
                ['integer'],
                [(int) $row['export_id']]
            );
        }
    }

    public function deleteAll(): void
    {
        if (!$this->database->tableExists(self::TABLE)) {
            return;
        }

        foreach ($this->fetchAll('SELECT resource_id FROM ' . self::TABLE) as $row) {
            $resource_id = $this->storage->manage()->find((string) $row['resource_id']);
            if ($resource_id !== null) {
                $this->storage->manage()->remove($resource_id, $this->stakeholder);
            }
        }

        $this->database->manipulate('DELETE FROM ' . self::TABLE);
    }
}
