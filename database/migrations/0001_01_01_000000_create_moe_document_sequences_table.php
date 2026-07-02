<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('moe-identifiers.document_number.sequence.table', 'moe_document_sequences');

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->string('key');       // kunci counter (mis. nama tabel/model)
            $table->string('period');    // label periode: 2026 / 2026-07 / all
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();

            // Satu baris counter unik per kombinasi key+period.
            $table->unique(['key', 'period']);
        });
    }

    public function down(): void
    {
        $table = config('moe-identifiers.document_number.sequence.table', 'moe_document_sequences');

        Schema::dropIfExists($table);
    }
};
