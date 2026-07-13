<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Service\GameLicenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LicenseController extends Controller
{
    private $licenses;

    public function __construct(GameLicenseService $licenses)
    {
        $this->licenses = $licenses;
    }

    public function claim(Request $request)
    {
        if ($disabled = $this->disabledResponse()) return $disabled;
        if ($error = $this->validateInput($request, [
            'code' => 'required|string|max:64',
            'game_id' => 'required|string|max:64',
            'install_id' => 'required|string|min:8|max:128',
        ])) return $error;

        return $this->respond($this->licenses->claim(
            $request->input('code'),
            $request->input('game_id'),
            $request->input('install_id'),
            $request->ip() ?: '',
            $request->userAgent() ?: ''
        ));
    }

    public function verify(Request $request)
    {
        if ($disabled = $this->disabledResponse()) return $disabled;
        if ($error = $this->validateInput($request, [
            'device_token' => 'required|string|min:32|max:256',
            'game_id' => 'required|string|max:64',
            'install_id' => 'required|string|min:8|max:128',
        ])) return $error;

        return $this->respond($this->licenses->verify(
            $request->input('device_token'),
            $request->input('game_id'),
            $request->input('install_id'),
            $request->ip() ?: '',
            $request->userAgent() ?: ''
        ));
    }

    public function requestRecovery(Request $request)
    {
        if ($disabled = $this->disabledResponse()) return $disabled;
        if ($error = $this->validateInput($request, [
            'code' => 'required|string|max:64',
            'game_id' => 'required|string|max:64',
        ])) return $error;

        return $this->respond($this->licenses->requestRecovery(
            $request->input('code'),
            $request->input('game_id'),
            $request->ip() ?: '',
            $request->userAgent() ?: ''
        ));
    }

    public function confirmRecovery(Request $request)
    {
        if ($disabled = $this->disabledResponse()) return $disabled;
        if ($error = $this->validateInput($request, [
            'challenge_id' => 'required|string|size:36',
            'otp' => 'required|string|size:6',
            'game_id' => 'required|string|max:64',
            'install_id' => 'required|string|min:8|max:128',
        ])) return $error;

        return $this->respond($this->licenses->confirmRecovery(
            $request->input('challenge_id'),
            $request->input('otp'),
            $request->input('game_id'),
            $request->input('install_id'),
            $request->ip() ?: '',
            $request->userAgent() ?: ''
        ));
    }

    private function validateInput(Request $request, array $rules)
    {
        $validator = Validator::make($request->all(), $rules);
        if (!$validator->fails()) return null;

        return response()->json([
            'ok' => false,
            'code' => 'INVALID_REQUEST',
            'message' => '请求内容不完整或格式不正确。',
            'errors' => $validator->errors(),
        ], 422);
    }

    private function disabledResponse()
    {
        if (config('licenses.enabled')) return null;
        return response()->json([
            'ok' => false,
            'code' => 'SERVICE_DISABLED',
            'message' => '授权服务正在准备中，请稍后再试。',
        ], 503);
    }

    private function respond(array $payload)
    {
        $status = isset($payload['http_status']) ? (int) $payload['http_status'] : 200;
        unset($payload['http_status']);
        return response()->json($payload, $status);
    }
}
