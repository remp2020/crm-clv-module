<?php

use Phinx\Migration\AbstractMigration;

class CustomerLifetimeValuesTable extends AbstractMigration
{
    public function change()
    {
        $this->table('customer_lifetime_values')
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('period_start', 'datetime', ['null' => false])
            ->addColumn('period_end', 'datetime', ['null' => false])

            ->addColumn('period_amount', 'decimal', ['scale' => 2, 'precision' => 10, 'null' => false])
            ->addColumn('percentile_0_amount', 'decimal', ['scale' => 2, 'precision' => 10, 'null' => false])
            ->addColumn('percentile_25_amount', 'decimal', ['scale' => 2, 'precision' => 10, 'null' => false])
            ->addColumn('percentile_50_amount', 'decimal', ['scale' => 2, 'precision' => 10, 'null' => false])
            ->addColumn('percentile_75_amount', 'decimal', ['scale' => 2, 'precision' => 10, 'null' => false])
            ->addColumn('percentile_100_amount', 'decimal', ['scale' => 2, 'precision' => 10, 'null' => false])

            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])

            ->addForeignKey('user_id', 'users')
            ->create();
    }
}
