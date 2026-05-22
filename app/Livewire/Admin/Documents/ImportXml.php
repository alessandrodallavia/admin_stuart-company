<?php

namespace App\Livewire\Admin\Documents;

use App\Services\AdminDocumentXmlImportService;
use Livewire\Component;
use Livewire\WithFileUploads;

class ImportXml extends Component
{
    use WithFileUploads;

    public array $xmlFiles = [];

    public bool $markAsPaid = false;

    protected array $rules = [
        'xmlFiles' => ['required', 'array', 'min:1'],
        'xmlFiles.*' => ['file', 'mimes:xml', 'max:5120'],
        'markAsPaid' => ['boolean'],
    ];

    public function import(AdminDocumentXmlImportService $importer): void
    {
        $this->validate();

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($this->xmlFiles as $file) {
            try {
                $importer->import($file->getRealPath(), $file->getClientOriginalName(), $this->markAsPaid);
                $imported++;
            } catch (\Throwable $exception) {
                $skipped++;
                $errors[] = $file->getClientOriginalName().': '.$exception->getMessage();
            }
        }

        session()->flash('status', "Importate {$imported} fatture. Saltate {$skipped}.");
        session()->flash('import_errors', $errors);

        $this->reset('xmlFiles');
    }

    public function render()
    {
        return view('livewire.admin.documents.import-xml');
    }
}
