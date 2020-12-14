<?php

namespace App\Imports;

use App\Client;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ClientsImport implements ToModel, WithValidation, WithHeadingRow
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new Client([
            'first_name' => $row['Όνομα'],
            'last_name' => $row['Επώνυμο'],
            'phone' => $row['Τηλέφωνο'],
            'email' => $row['Email'],
        ]);
    }

    public function rules(): array
    {
        return [
            'Όνομα' => 'required|string',
            'Επώνυμο' => 'required|string',
            'Email' => 'nullable|email',
            'Τηλέφωνο' => 'required|unique:clients,phone'
        ];
    }
}
