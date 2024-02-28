<?php

namespace wsydney76\extras\migrations;

use craft\db\Migration;
use wsydney76\extras\records\TransferHistoryRecord;


class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTable(TransferHistoryRecord::tableName(), [
            'id' => $this->primaryKey(),
            'draftId' => $this->integer()->notNull(),
            'fromUserId' => $this->integer()->notNull(),
            'toUserId' => $this->integer()->notNull(),
            'fromUserName' => $this->string(255),
            'toUserName' => $this->string(255),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->string(255)
        ], 'ENGINE=InnoDB');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTableIfExists(TransferHistoryRecord::tableName());
        return true;
    }
}
