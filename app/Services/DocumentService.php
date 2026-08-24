<?php

namespace App\Services;

use App\Models\DocumentType;
use App\Models\EmployeeDocument;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Employee document storage, completeness checks and expiry alerts.
 *
 * Files go on the `local` disk rather than `public`: identity documents and
 * contracts must not be reachable by guessing a URL.
 */
class DocumentService
{
    public const DISK = 'local';
    private const DIRECTORY = 'employee-documents';

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /**
     * Store an uploaded document against a user.
     *
     * @throws \DomainException when the file violates the type's rules
     */
    public function store(
        User $user,
        DocumentType $type,
        UploadedFile $file,
        User $uploader,
        array $meta = [],
    ): EmployeeDocument {
        $this->assertFileIsAcceptable($type, $file);

        if ($type->has_expiry && empty($meta['expiry_date'])) {
            throw new \DomainException("Tanggal kedaluwarsa wajib diisi untuk dokumen {$type->name}.");
        }

        $path = $file->store(self::DIRECTORY . '/' . $user->id, self::DISK);

        if (! $path) {
            throw new \RuntimeException('Gagal menyimpan berkas dokumen.');
        }

        return EmployeeDocument::create([
            'user_id'         => $user->id,
            'document_type_id' => $type->id,
            'file_path'       => $path,
            'file_name'       => $file->getClientOriginalName(),
            'file_size'       => $file->getSize(),
            'document_number' => $meta['document_number'] ?? null,
            'issued_date'     => $meta['issued_date'] ?? null,
            'expiry_date'     => $meta['expiry_date'] ?? null,
            'notes'           => $meta['notes'] ?? null,
            'uploaded_by'     => $uploader->id,
        ]);
    }

    private function assertFileIsAcceptable(DocumentType $type, UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = $type->extensions();

        if ($allowed && ! in_array($extension, $allowed, true)) {
            throw new \DomainException(
                "Format .{$extension} tidak diizinkan untuk {$type->name}. "
                . 'Gunakan: ' . implode(', ', $allowed) . '.'
            );
        }

        $maxBytes = $type->maxFileSizeKb() * 1024;

        if ($file->getSize() > $maxBytes) {
            throw new \DomainException(
                "Ukuran berkas melebihi batas {$type->max_file_size_mb} MB untuk {$type->name}."
            );
        }
    }

    /**
     * Remove the row and its file together — an orphaned file on disk is a
     * privacy problem, not just clutter.
     */
    public function delete(EmployeeDocument $document): void
    {
        $path = $document->file_path;

        $document->delete();

        try {
            if ($path && Storage::disk(self::DISK)->exists($path)) {
                Storage::disk(self::DISK)->delete($path);
            }
        } catch (\Throwable $e) {
            Log::error('[Documents] failed to delete file', [
                'path'    => $path,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Which required document types a user is still missing.
     *
     * @return Collection<int, DocumentType>
     */
    public function missingRequiredFor(User $user): Collection
    {
        $held = EmployeeDocument::where('user_id', $user->id)
            ->pluck('document_type_id')
            ->unique()
            ->all();

        return DocumentType::required()
            ->whereNotIn('id', $held ?: [0])
            ->orderBy('name')
            ->get();
    }

    /**
     * Completeness overview across all employed staff.
     */
    public function completenessReport(): Collection
    {
        $requiredTypes = DocumentType::required()->orderBy('name')->get();
        $requiredCount = $requiredTypes->count();

        return User::employed()
            ->with('department')
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($requiredTypes, $requiredCount) {
                $missing = $this->missingRequiredFor($user);

                return [
                    'user'       => $user,
                    'department' => $user->department?->name ?? '—',
                    'held'       => $requiredCount - $missing->count(),
                    'required'   => $requiredCount,
                    'missing'    => $missing,
                    'complete'   => $missing->isEmpty(),
                ];
            });
    }

    /**
     * Warn HR (and the document owner) about documents nearing expiry.
     *
     * @return int number of notifications raised
     */
    public function notifyExpiring(int $daysAhead = 30): int
    {
        $documents = EmployeeDocument::with(['user', 'documentType'])
            ->expiringWithin($daysAhead)
            ->get();

        $sent = 0;

        foreach ($documents as $document) {
            if (! $document->user || ! $document->documentType) {
                continue;
            }

            $payload = [
                'document_id'   => $document->id,
                'document_type' => $document->documentType->name,
                'user_name'     => $document->user->name,
                'expiry_date'   => $document->expiry_date?->toDateString(),
            ];

            $this->notifications->notify($document->user, Notification::DOCUMENT_EXPIRING, $payload);
            $this->notifications->notifyRole('hr_admin', Notification::DOCUMENT_EXPIRING, $payload);

            $sent++;
        }

        return $sent;
    }
}
