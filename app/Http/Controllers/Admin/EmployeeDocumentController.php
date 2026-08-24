<?php

namespace App\Http\Controllers\Admin;

use App\Models\DocumentType;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Prologue\Alerts\Facades\Alert;

class EmployeeDocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents)
    {
    }

    public function index(Request $request)
    {
        $query = EmployeeDocument::with(['user', 'documentType', 'uploader'])->latest('id');

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($typeId = $request->input('document_type_id')) {
            $query->where('document_type_id', $typeId);
        }

        if ($request->boolean('expiring')) {
            $query->expiringWithin(30);
        }

        if ($request->boolean('expired')) {
            $query->expired();
        }

        return view('admin.document.index', [
            'documents' => $query->paginate(25)->withQueryString(),
            'users'     => User::employed()->orderBy('name')->get(),
            'types'     => DocumentType::orderBy('name')->get(),
            'filters'   => $request->only(['user_id', 'document_type_id', 'expiring', 'expired']),
        ]);
    }

    public function create()
    {
        return view('admin.document.create', [
            'users' => User::employed()->orderBy('name')->get(),
            'types' => DocumentType::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'          => 'required|exists:users,id',
            'document_type_id' => 'required|exists:document_types,id',
            'file'             => 'required|file|max:20480',
            'document_number'  => 'nullable|string|max:100',
            'issued_date'      => 'nullable|date',
            'expiry_date'      => 'nullable|date|after_or_equal:issued_date',
            'notes'            => 'nullable|string|max:1000',
        ], [
            'user_id.required'          => 'Karyawan wajib dipilih.',
            'document_type_id.required' => 'Jenis dokumen wajib dipilih.',
            'file.required'             => 'Berkas wajib diunggah.',
            'expiry_date.after_or_equal' => 'Tanggal kedaluwarsa tidak boleh sebelum tanggal terbit.',
        ]);

        try {
            $this->documents->store(
                User::findOrFail($data['user_id']),
                DocumentType::findOrFail($data['document_type_id']),
                $request->file('file'),
                backpack_user(),
                [
                    'document_number' => $data['document_number'] ?? null,
                    'issued_date'     => $data['issued_date'] ?? null,
                    'expiry_date'     => $data['expiry_date'] ?? null,
                    'notes'           => $data['notes'] ?? null,
                ]
            );
        } catch (\DomainException | \RuntimeException $e) {
            Alert::error($e->getMessage())->flash();

            return back()->withInput();
        }

        Alert::success('Dokumen berhasil diunggah.')->flash();

        return redirect(backpack_url('employee-document'));
    }

    /**
     * Documents live on a private disk, so downloads are streamed through the
     * app after an access check rather than served as static files.
     */
    public function download(int $id)
    {
        $document = EmployeeDocument::findOrFail($id);

        $this->authoriseAccess($document);

        if (! Storage::disk(DocumentService::DISK)->exists($document->file_path)) {
            abort(404, 'Berkas tidak ditemukan.');
        }

        return Storage::disk(DocumentService::DISK)->download(
            $document->file_path,
            $document->file_name
        );
    }

    public function destroy(int $id)
    {
        $document = EmployeeDocument::findOrFail($id);

        $this->documents->delete($document);

        Alert::success('Dokumen dihapus.')->flash();

        return back();
    }

    public function completeness()
    {
        return view('admin.document.completeness', [
            'rows' => $this->documents->completenessReport(),
        ]);
    }

    /**
     * HR sees everything; anyone else only their own documents.
     */
    private function authoriseAccess(EmployeeDocument $document): void
    {
        $user = backpack_user();

        $allowed = $user->hasAnyRole(['super_admin', 'hr_admin'])
            || (int) $document->user_id === (int) $user->id;

        abort_unless($allowed, 403, 'Anda tidak berhak mengakses dokumen ini.');
    }
}
