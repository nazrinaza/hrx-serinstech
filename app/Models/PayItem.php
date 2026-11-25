<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class PayItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'type', 'epf_applicable', 'socso_applicable', 'eis_applicable',
        'pcb_applicable', 'hrdf_applicable',
    ];

    protected function casts(): array
    {
        return [
            'epf_applicable' => 'boolean',
            'socso_applicable' => 'boolean',
            'eis_applicable' => 'boolean',
            'pcb_applicable' => 'boolean',
            'hrdf_applicable' => 'boolean',
        ];
    }

    /** @return array{epf:bool,socso:bool,eis:bool,pcb:bool,hrdf:bool} */
    public function flags(): array
    {
        return [
            'epf' => $this->epf_applicable,
            'socso' => $this->socso_applicable,
            'eis' => $this->eis_applicable,
            'pcb' => $this->pcb_applicable,
            'hrdf' => $this->hrdf_applicable,
        ];
    }
}
