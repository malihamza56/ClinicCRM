<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Setting;
use App\Services\SettingsService;

final class SettingsController extends Controller
{
    public function edit(): Response
    {
        return $this->view('settings/edit', ['title' => 'Clinic settings', 'settings' => Setting::all()]);
    }

    public function update(): Response
    {
        $v = SettingsService::validate($this->request->body());
        if ($v->fails()) {
            return $this->backWithErrors('/settings', $v->errors());
        }
        SettingsService::save($v->validated());

        return $this->redirectWith('/settings', 'success', 'Settings saved.');
    }
}
