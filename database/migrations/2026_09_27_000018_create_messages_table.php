<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The outbox: every WhatsApp message and SMS the shop sends, with its delivery state.
 * Messages are written here first and sent by a queued job, so a slow network never
 * holds up the counter and every attempt stays on record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20);                  // whatsapp | sms
            $table->string('purpose', 30);                  // receipt | marketing | other
            $table->string('recipient', 20);                // international digits, e.g. 255755123456
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template', 100)->nullable();    // provider template name, when one is required
            $table->json('parameters')->nullable();         // template values, in order
            $table->text('body');                           // readable copy of what the customer sees
            $table->string('status', 20)->default('queued'); // queued | sent | delivered | read | failed
            $table->string('provider', 30);
            $table->string('provider_message_id', 191)->nullable()->index();
            $table->string('dedupe_key', 191)->nullable()->unique();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['sale_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
