<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE messages ALTER COLUMN user_id DROP NOT NULL');
        DB::statement('ALTER TABLE messages DROP CONSTRAINT messages_user_id_foreign');
        DB::statement(
            'ALTER TABLE messages ADD CONSTRAINT messages_user_id_foreign
             FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL'
        );

        DB::statement('ALTER TABLE lore_entries DROP CONSTRAINT lore_entries_status_check');
        DB::statement(
            "ALTER TABLE lore_entries ADD CONSTRAINT lore_entries_status_check
             CHECK (status IN ('draft', 'approved', 'archived'))"
        );

        DB::statement('ALTER TABLE rule_documents DROP CONSTRAINT rule_documents_status_check');
        DB::statement(
            "ALTER TABLE rule_documents ADD CONSTRAINT rule_documents_status_check
             CHECK (status IN ('draft', 'approved', 'archived'))"
        );

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_lore_entry_delete()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'Lore entries cannot be deleted; archive them instead.';
END;
$$;

DROP TRIGGER IF EXISTS prevent_lore_entry_delete ON lore_entries;
CREATE TRIGGER prevent_lore_entry_delete
    BEFORE DELETE ON lore_entries
    FOR EACH ROW
    EXECUTE FUNCTION prevent_lore_entry_delete();

CREATE OR REPLACE FUNCTION prevent_rule_document_delete()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'Rule documents cannot be deleted; archive them instead.';
END;
$$;

DROP TRIGGER IF EXISTS prevent_rule_document_delete ON rule_documents;
CREATE TRIGGER prevent_rule_document_delete
    BEFORE DELETE ON rule_documents
    FOR EACH ROW
    EXECUTE FUNCTION prevent_rule_document_delete();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS prevent_lore_entry_delete ON lore_entries;
DROP FUNCTION IF EXISTS prevent_lore_entry_delete();
DROP TRIGGER IF EXISTS prevent_rule_document_delete ON rule_documents;
DROP FUNCTION IF EXISTS prevent_rule_document_delete();
SQL);

        DB::statement('ALTER TABLE lore_entries DROP CONSTRAINT lore_entries_status_check');
        DB::statement(
            "ALTER TABLE lore_entries ADD CONSTRAINT lore_entries_status_check
             CHECK (status IN ('draft', 'approved'))"
        );

        DB::statement('ALTER TABLE rule_documents DROP CONSTRAINT rule_documents_status_check');
        DB::statement(
            "ALTER TABLE rule_documents ADD CONSTRAINT rule_documents_status_check
             CHECK (status IN ('draft', 'approved'))"
        );

        DB::statement('ALTER TABLE messages DROP CONSTRAINT messages_user_id_foreign');
        DB::statement('UPDATE messages SET user_id = (SELECT id FROM users ORDER BY id LIMIT 1) WHERE user_id IS NULL');
        DB::statement('ALTER TABLE messages ALTER COLUMN user_id SET NOT NULL');
        DB::statement(
            'ALTER TABLE messages ADD CONSTRAINT messages_user_id_foreign
             FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE'
        );
    }
};
