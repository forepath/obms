<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->change();
        });

        Schema::table('support_ticket_history', function (Blueprint $table) {
            $table->enum('type', [
                'message',
                'priority',
                'assignment',
                'category',
                'escalation',
                'deescalation',
                'lock',
                'unlock',
                'hold',
                'unhold',
                'close',
                'reopen',
                'file',
                'status',
                'run',
            ])->change();
        });

        Schema::table('support_runs', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('support_runs', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable(false)->change();
        });

        Schema::table('support_ticket_history', function (Blueprint $table) {
            $table->enum('type', [
                'message',
                'priority',
                'assignment',
                'category',
                'escalation',
                'deescalation',
                'lock',
                'unlock',
                'hold',
                'unhold',
                'close',
                'reopen',
                'file',
            ])->change();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable(false)->change();
        });
    }
};
