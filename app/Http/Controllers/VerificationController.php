<?php

namespace App\Http\Controllers;

use App\Models\DataMigration;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function getStatus(DataMigration $migration)
    {
        return $migration;
    }
}
