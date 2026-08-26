<?php

use App\Models\Applicant;
use App\Models\Candidate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * M17-1 — Recruitment 2.0 foundation.
 *
 * - candidates: external applicant accounts (own auth guard).
 * - job_openings: + publish flag, slug, structured criteria + AI scoring_prompt.
 * - applicants (kept as the "application" record — one row per apply): + candidate_id,
 *   AI score columns, apply-once unique, retention columns.
 *
 * We EXTEND the existing M09 `applicants` table rather than renaming it to
 * `applications`, so the whole M09 suite (14 tests) keeps passing. Conceptually
 * an `applicant` row IS an application (candidate ↔ opening).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 150)->unique();
            $table->string('password');
            $table->string('phone', 30)->nullable();
            $table->string('headline', 160)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::table('job_openings', function (Blueprint $table) {
            $table->boolean('is_published')->default(false)->after('status');
            $table->timestamp('published_at')->nullable()->after('is_published');
            $table->string('slug', 180)->nullable()->unique()->after('code');
            $table->json('required_skills')->nullable()->after('description');
            $table->unsignedInteger('min_experience_years')->nullable()->after('required_skills');
            $table->string('education_min', 60)->nullable()->after('min_experience_years');
            // Free-form rubric HR writes; used by the LLM scorer (M17-4b).
            $table->text('scoring_prompt')->nullable()->after('education_min');
            $table->timestamp('vector_synced_at')->nullable()->after('scoring_prompt');
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->unsignedBigInteger('candidate_id')->nullable()->after('job_opening_id');
            // AI matching (M17-4/4b)
            $table->text('cv_text')->nullable()->after('cv_path');
            $table->decimal('vector_score', 5, 2)->nullable()->after('cv_text');
            $table->decimal('ai_score', 5, 2)->nullable()->after('vector_score');
            $table->json('ai_reasoning')->nullable()->after('ai_score');
            $table->string('ai_model', 80)->nullable()->after('ai_reasoning');
            $table->timestamp('ai_scored_at')->nullable()->after('ai_model');
            // Retention (M17-5)
            $table->timestamp('rejected_at')->nullable()->after('ai_scored_at');
            $table->timestamp('cv_purged_at')->nullable()->after('rejected_at');

            $table->foreign('candidate_id')->references('id')->on('candidates')->nullOnDelete();
        });

        // Backfill: turn each existing M09 applicant into a candidate + link.
        $this->backfillCandidates();

        // Apply-once: a candidate may apply to an opening only once.
        // (Only enforced for rows that HAVE a candidate; legacy null rows exempt.)
        Schema::table('applicants', function (Blueprint $table) {
            $table->unique(['candidate_id', 'job_opening_id'], 'uniq_candidate_opening');
        });
    }

    private function backfillCandidates(): void
    {
        $rows = DB::table('applicants')->whereNull('candidate_id')->get();

        foreach ($rows as $row) {
            $email = $row->email ?: 'pelamar' . $row->id . '.' . Str::random(6) . '@legacy.local';

            $candidateId = DB::table('candidates')->where('email', $email)->value('id');
            if (! $candidateId) {
                $candidateId = DB::table('candidates')->insertGetId([
                    'name'       => $row->name,
                    'email'      => $email,
                    'password'   => bcrypt(Str::random(24)),
                    'phone'      => $row->phone,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('applicants')->where('id', $row->id)->update(['candidate_id' => $candidateId]);
        }
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropUnique('uniq_candidate_opening');
            $table->dropForeign(['candidate_id']);
            $table->dropColumn([
                'candidate_id', 'cv_text', 'vector_score', 'ai_score', 'ai_reasoning',
                'ai_model', 'ai_scored_at', 'rejected_at', 'cv_purged_at',
            ]);
        });

        Schema::table('job_openings', function (Blueprint $table) {
            $table->dropColumn([
                'is_published', 'published_at', 'slug', 'required_skills',
                'min_experience_years', 'education_min', 'scoring_prompt', 'vector_synced_at',
            ]);
        });

        Schema::dropIfExists('candidates');
    }
};
