<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen the column so application-level ciphertext fits.
     * Existing plaintext values are left unchanged and stay readable
     * through the User national_id cast until that row is saved again.
     */
    public function up(): void
    {
        if ($this->nationalIdIsIndexed()) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(['national_id']);
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->text('national_id')->nullable()->change();
        });
    }

    /**
     * Restores varchar(30) only after ciphertext has been decrypted.
     * Fails closed when a value would be truncated.
     */
    public function down(): void
    {
        foreach (DB::table('users')->whereNotNull('national_id')->get(['id', 'national_id']) as $row) {
            $plain = (string) $row->national_id;

            try {
                $plain = Crypt::decryptString($plain);
            } catch (DecryptException) {
                // Already plaintext.
            }

            if (strlen($plain) > 30) {
                throw new RuntimeException('Cannot shrink users.national_id while a value is longer than 30 characters.');
            }

            DB::table('users')->where('id', $row->id)->update(['national_id' => $plain]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('national_id', 30)->nullable()->change();
            $table->index('national_id');
        });
    }

    private function nationalIdIsIndexed(): bool
    {
        foreach (Schema::getIndexes('users') as $index) {
            if (($index['columns'] ?? []) === ['national_id'] && ! ($index['primary'] ?? false)) {
                return true;
            }
        }

        return false;
    }
};
